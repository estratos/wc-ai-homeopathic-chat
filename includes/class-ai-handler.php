<?php
class AI_Handler {
    
    private $api_key;
    private $api_url;
    private $api_provider;
    
    public function init() {
        $this->api_key = get_option('wc_ai_chat_api_key', '');
        $this->api_provider = get_option('wc_ai_chat_api_provider', 'openai');
        
        // Configurar URLs según el proveedor
        if ($this->api_provider === 'deepseek') {
            $this->api_url = 'https://api.deepseek.com/v1/chat/completions';
        } else {
            $this->api_url = 'https://api.openai.com/v1/chat/completions';
        }
    }
    
    public function get_recommendations($user_message, $matched_products = array()) {
        if (empty($this->api_key)) {
            return array(
                'error' => 'API key no configurada. Por favor configura tu API key en la administración del plugin.',
                'recommendations' => array()
            );
        }
        
        // Preparar contexto de productos
        $products_context = $this->prepare_products_context($matched_products);
        
        // Preparar mensajes para la API
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
            if ($count >= 5) break; // Limitar a 5 productos
            
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
    
    private function call_api($messages) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        // Configuración específica para DeepSeek
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 800,
            'stream' => false
        );
        
        // Si es OpenAI, cambiar el modelo
        if ($this->api_provider === 'openai') {
            $body['model'] = 'gpt-3.5-turbo';
        }
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 45,
            'sslverify' => false // Ayuda con problemas de SSL
        );
        
        $response = wp_remote_post($this->api_url, $args);
        
        // Debug: registrar la solicitud
        error_log('WC AI Chat - API Request to: ' . $this->api_url);
        error_log('WC AI Chat - API Headers: ' . print_r($headers, true));
        error_log('WC AI Chat - API Body: ' . json_encode($body));
        
        if (is_wp_error($response)) {
            $error_message = 'Error de conexión: ' . $response->get_error_message();
            error_log('WC AI Chat - ' . $error_message);
            return array('error' => $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('WC AI Chat - API Response Code: ' . $response_code);
        error_log('WC AI Chat - API Response Body: ' . $response_body);
        
        $data = json_decode($response_body, true);
        
        if ($response_code !== 200) {
            $error_message = 'Error en la API (' . $response_code . '): ';
            
            if (isset($data['error']['message'])) {
                $error_message .= $data['error']['message'];
            } else {
                $error_message .= 'Respuesta inesperada de la API';
            }
            
            error_log('WC AI Chat - ' . $error_message);
            return array('error' => $error_message);
        }
        
        if (!isset($data['choices']) || empty($data['choices'])) {
            $error_message = 'Estructura de respuesta inesperada de la API';
            error_log('WC AI Chat - ' . $error_message);
            return array('error' => $error_message);
        }
        
        return $data;
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            error_log('WC AI Chat - Test Connection: No API key');
            return false;
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
        
        $response = $this->call_api($test_messages);
        
        if (isset($response['error'])) {
            error_log('WC AI Chat - Test Connection Failed: ' . $response['error']);
            return false;
        }
        
        $response_text = trim($response['choices'][0]['message']['content']);
        $success = strtoupper($response_text) === 'OK';
        
        if (!$success) {
            error_log('WC AI Chat - Test Connection Failed: Unexpected response: ' . $response_text);
        }
        
        return $success;
    }
    
    public function get_available_models() {
        if ($this->api_provider === 'deepseek') {
            return array(
                'deepseek-chat' => 'DeepSeek Chat',
                'deepseek-coder' => 'DeepSeek Coder'
            );
        } else {
            return array(
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                'gpt-4' => 'GPT-4'
            );
        }
    }
}