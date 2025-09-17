<?php
class AI_Handler {
    
    private $api_key;
    private $api_url;
    
    public function init() {
        $this->api_key = get_option('wc_ai_chat_api_key', '');
        $provider = get_option('wc_ai_chat_api_provider', 'openai');
        
        if ($provider === 'deepseek') {
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
        
        $products_context = '';
        foreach ($matched_products as $product_id => $data) {
            $product = $data['analysis'];
            $products_context .= "Producto: {$product['name']}\n";
            $products_context .= "Descripción: {$product['short_description']}\n\n";
        }
        
        $messages = array(
            array(
                'role' => 'system', 
                'content' => "Eres un asistente especializado en homeopatía. Recomienda productos basados en los síntomas del usuario. Productos disponibles:\n{$products_context}"
            ),
            array('role' => 'user', 'content' => $user_message)
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
    
    private function call_api($messages) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 500
        );
        
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
        
        return $data;
    }
    
    public function test_api_connection() {
        if (empty($this->api_key)) {
            return false;
        }
        
        $test_message = array(
            array('role' => 'system', 'content' => 'Responde con "OK"'),
            array('role' => 'user', 'content' => 'Test de conexión')
        );
        
        $response = $this->call_api($test_message);
        return !isset($response['error']);
    }
}