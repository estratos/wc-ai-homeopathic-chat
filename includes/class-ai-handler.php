<?php
class AI_Handler {
    
    private $api_key;
    private $api_url;
    private $api_provider;
    
    public function init() {
        $this->api_key = get_option('wc_ai_chat_api_key', '');
        $this->api_provider = get_option('wc_ai_chat_api_provider', 'openai');
        
        if ($this->api_provider === 'deepseek') {
            $this->api_url = 'https://api.deepseek.com/v1/chat/completions';
        } else {
            $this->api_url = 'https://api.openai.com/v1/chat/completions';
        }
    }
    
    public function debug_api_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => '❌ API key no configurada',
                'details' => array()
            );
        }
        
        $test_messages = array(
            array(
                'role' => 'system', 
                'content' => 'Responde únicamente con la palabra "OK"'
            ),
            array(
                'role' => 'user', 
                'content' => 'Test de conexión. Responde solo con OK.'
            )
        );
        
        $result = $this->call_api($test_messages, true);
        
        if (isset($result['error'])) {
            return array(
                'success' => false,
                'message' => '❌ Error en la conexión',
                'details' => $result
            );
        }
        
        return array(
            'success' => true,
            'message' => '✅ Conexión exitosa',
            'details' => $result
        );
    }
    
    public function get_recommendations($user_message, $matched_products = array()) {
        if (empty($this->api_key)) {
            return array(
                'error' => 'API key no configurada. Por favor configura tu API key en la administración del plugin.',
                'recommendations' => array()
            );
        }
        
        $products_context = $this->prepare_products_context($matched_products);
        
        $messages = array(
            array(
                'role' => 'system', 
                'content' => $this->prepare_system_message($products_context)
            ),
            array(
                'role' => 'user', 
                'content' => $user_message
            )
        );
        
        $response = $this->call_api($messages);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return array(
            'response' => $response['choices'][0]['message']['content'],
            'products' => $matched_products
        );
    }
    
    private function prepare_products_context($matched_products) {
        if (empty($matched_products)) {
            return 'No hay productos específicos disponibles para recomendar.';
        }
        
        $context = "Productos disponibles en la tienda:\n\n";
        $count = 0;
        
        foreach ($matched_products as $product_id => $data) {
            if ($count >= 5) break;
            
            $product = $data['analysis'];
            $wc_product = wc_get_product($product_id);
            
            $context .= "=== " . ($count + 1) . ". {$product['name']} ===\n";
            $context .= "Descripción: {$product['short_description']}\n";
            
            if (!empty($product['categories'])) {
                $context .= "Categorías: " . implode(', ', $product['categories']) . "\n";
            }
            
            if ($wc_product && $wc_product->get_price()) {
                $context .= "Precio: " . wc_price($wc_product->get_price()) . "\n";
            }
            
            $context .= "Enlace: " . get_permalink($product_id) . "\n\n";
            $count++;
        }
        
        return $context;
    }
    
    private function prepare_system_message($products_context) {
        return "Eres un asistente especializado en homeopatía y medicina natural. 
        Tu objetivo es ayudar a los usuarios a encontrar productos homeopáticos adecuados 
        para sus síntomas o necesidades de salud.
        
        {$products_context}
        
        Instrucciones importantes:
        1. Analiza los síntomas descritos por el usuario
        2. Recomienda SOLO productos de la lista anterior
        3. Sé empático y profesional
        4. Si no hay productos adecuados, sugiere consultar con un especialista
        5. Proporciona explicaciones breves y útiles
        6. Mantén un tono cálido y acogedor";
    }
    
    private function call_api($messages, $debug = false) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => ($this->api_provider === 'deepseek') ? 'deepseek-chat' : 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 800,
            'stream' => false
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 45,
            'sslverify' => false
        );
        
        if ($debug) {
            $debug_info = array(
                'url' => $this->api_url,
                'headers' => $headers,
                'body' => $body,
                'args' => $args
            );
        }
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            $error_result = array(
                'error' => 'Error de conexión: ' . $response->get_error_message(),
                'error_code' => $response->get_error_code()
            );
            
            if ($debug) {
                $error_result['debug'] = $debug_info;
                $error_result['response'] = $response;
            }
            
            return $error_result;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if ($response_code !== 200) {
            $error_result = array(
                'error' => 'Error en la API (' . $response_code . ')',
                'response_code' => $response_code,
                'response_body' => $response_body
            );
            
            if (isset($data['error']['message'])) {
                $error_result['api_error'] = $data['error']['message'];
            }
            
            if ($debug) {
                $error_result['debug'] = $debug_info;
            }
            
            return $error_result;
        }
        
        if (!isset($data['choices']) || empty($data['choices'])) {
            return array(
                'error' => 'Estructura de respuesta inesperada',
                'response_body' => $response_body
            );
        }
        
        $result = array(
            'success' => true,
            'response' => $data['choices'][0]['message']['content'],
            'full_response' => $data
        );
        
        if ($debug) {
            $result['debug'] = $debug_info;
        }
        
        return $result;
    }
    
    public function test_api_connection() {
        $result = $this->debug_api_connection();
        return $result['success'];
    }
}