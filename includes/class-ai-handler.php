<?php
class AI_Handler {
    
    private $api_key;
    private $api_url;
    private $model;
    private $api_provider;
    
    public function init() {
        $this->api_key = get_option('wc_ai_chat_api_key', '');
        $this->api_provider = get_option('wc_ai_chat_api_provider', 'openai');
        $this->model = get_option('wc_ai_chat_model', 'gpt-3.5-turbo');
        
        // Configurar la URL según el proveedor
        if ($this->api_provider === 'deepseek') {
            $this->api_url = 'https://api.deepseek.com/v1/chat/completions';
            // Modelos de Deepseek
            $this->model = get_option('wc_ai_chat_model', 'deepseek-chat');
        } else {
            $this->api_url = 'https://api.openai.com/v1/chat/completions';
            // Modelos de OpenAI
            $this->model = get_option('wc_ai_chat_model', 'gpt-3.5-turbo');
        }
    }
    
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
        update_option('wc_ai_chat_api_key', $api_key);
    }
    
    public function set_api_provider($provider) {
        $this->api_provider = $provider;
        update_option('wc_ai_chat_api_provider', $provider);
        $this->init(); // Re-inicializar con la nueva configuración
    }
    
    public function set_model($model) {
        $this->model = $model;
        update_option('wc_ai_chat_model', $model);
    }
    
    public function get_recommendations($user_message, $matched_products = array()) {
        if (empty($this->api_key)) {
            return array(
                'error' => 'API key no configurada',
                'recommendations' => array()
            );
        }
        
        // Preparar contexto con información de productos
        $products_context = $this->prepare_products_context($matched_products);
        
        // Preparar mensaje del sistema
        $system_message = $this->prepare_system_message($products_context);
        
        $messages = array(
            array('role' => 'system', 'content' => $system_message),
            array('role' => 'user', 'content' => $user_message)
        );
        
        $response = $this->call_api($messages);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        $ai_response = $response['choices'][0]['message']['content'];
        $mentioned_products = $this->extract_mentioned_products($ai_response, $matched_products);
        
        return array(
            'response' => $ai_response,
            'products' => $mentioned_products,
            'full_response' => $response
        );
    }
    
    private function prepare_products_context($matched_products) {
        $products_context = '';
        $product_count = 0;
        
        foreach ($matched_products as $product_id => $data) {
            if ($product_count >= 5) break;
            
            $product = $data['analysis'];
            $actual_product = wc_get_product($product_id);
            
            $products_context .= "=== PRODUCTO: {$product['name']} ===\n";
            $products_context .= "DESCRIPCIÓN: {$product['short_description']}\n";
            
            if (!empty($product['symptoms'])) {
                $products_context .= "SÍNTOMAS: " . implode(', ', $product['symptoms']) . "\n";
            }
            
            if (!empty($product['ailments'])) {
                $products_context .= "PADECIMIENTOS: " . implode(', ', $product['ailments']) . "\n";
            }
            
            if (!empty($product['categories'])) {
                $products_context .= "CATEGORÍAS: " . implode(', ', $product['categories']) . "\n";
            }
            
            if ($actual_product && $actual_product->get_price()) {
                $products_context .= "PRECIO: " . wc_price($actual_product->get_price()) . "\n";
            }
            
            $products_context .= "ENLACE: " . get_permalink($product_id) . "\n\n";
            
            $product_count++;
        }
        
        return $products_context;
    }
    
    private function prepare_system_message($products_context) {
        return "Eres un asistente especializado en homeopatía y medicina natural. 
        Analiza los síntomas descritos por el usuario y recomienda productos homeopáticos apropiados.
        Basa tus recomendaciones EXCLUSIVAMENTE en la información de productos proporcionada.
        
        Información de productos disponibles:
        {$products_context}
        
        Responde de manera profesional, empática y precisa. 
        Si no encuentras productos específicos para los síntomas mencionados, 
        sugiere consultar con un especialista homeopático.
        
        Formato de respuesta: 
        1. Identifica los síntomas principales mencionados
        2. Recomienda productos específicos con breve explicación de por qué podrían ayudar
        3. Sé honesto sobre las limitaciones de los productos disponibles
        4. Incluye consejos generales de cuidado si es apropiado";
    }
    
    private function call_api($messages) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'stream' => false
        );
        
        // DeepSeek requiere un header ligeramente diferente en algunos casos
        if ($this->api_provider === 'deepseek') {
            $headers['Accept'] = 'application/json';
        }
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        );
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (isset($data['error'])) {
            return array('error' => $data['error']['message']);
        }
        
        if (!isset($data['choices'])) {
            return array('error' => 'Respuesta inesperada de la API: ' . $response_body);
        }
        
        return $data;
    }
    
    private function extract_mentioned_products($ai_response, $matched_products) {
        $mentioned_products = array();
        
        foreach ($matched_products as $product_id => $data) {
            $product_name = $data['analysis']['name'];
            
            // Verificar si el producto es mencionado en la respuesta de la IA
            if (stripos($ai_response, $product_name) !== false) {
                $product = wc_get_product($product_id);
                
                if ($product && $product->is_visible()) {
                    $mentioned_products[$product_id] = array(
                        'name' => $product_name,
                        'score' => $data['score'],
                        'analysis' => $data['analysis'],
                        'price' => $product->get_price_html(),
                        'url' => get_permalink($product_id),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')
                    );
                }
            }
        }
        
        return $mentioned_products;
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return false;
        }
        
        $test_message = array(
            array('role' => 'system', 'content' => 'Responde con "OK" si estás funcionando.'),
            array('role' => 'user', 'content' => 'Test de conexión - responde solo con "OK"')
        );
        
        $response = $this->call_api($test_message);
        
        return isset($response['choices']) && 
               isset($response['choices'][0]['message']['content']) &&
               trim($response['choices'][0]['message']['content']) === 'OK';
    }
    
    public function get_available_models() {
        if ($this->api_provider === 'deepseek') {
            return array(
                'deepseek-chat' => 'DeepSeek Chat (recomendado)',
                'deepseek-coder' => 'DeepSeek Coder'
            );
        } else {
            return array(
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                'gpt-4' => 'GPT-4',
                'gpt-4-turbo' => 'GPT-4 Turbo'
            );
        }
    }
}
