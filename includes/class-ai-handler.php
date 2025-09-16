<?php
class AI_Handler {
    
    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $model = 'gpt-3.5-turbo';
    
    public function init() {
        $this->api_key = get_option('wc_ai_chat_api_key', '');
    }
    
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
        update_option('wc_ai_chat_api_key', $api_key);
    }
    
    public function get_recommendations($user_message, $matched_products = array()) {
        if (empty($this->api_key)) {
            return array(
                'error' => 'API key no configurada',
                'recommendations' => array()
            );
        }
        
        // Preparar contexto con información de productos
        $products_context = '';
        $product_count = 0;
        
        foreach ($matched_products as $product_id => $data) {
            if ($product_count >= 5) break; // Limitar a 5 productos para el contexto
            
            $product = $data['analysis'];
            $products_context .= "Producto: {$product['name']}\n";
            $products_context .= "Descripción: {$product['short_description']}\n";
            $products_context .= "Síntomas: " . implode(', ', $product['symptoms']) . "\n";
            $products_context .= "Padecimientos: " . implode(', ', $product['ailments']) . "\n";
            $products_context .= "Beneficios: " . implode(', ', $product['benefits']) . "\n\n";
            
            $product_count++;
        }
        
        // Preparar mensaje del sistema con instrucciones
        $system_message = "Eres un asistente especializado en homeopatía. 
        Analiza los síntomas descritos por el usuario y recomienda productos homeopáticos apropiados.
        Basa tus recomendaciones en la información de productos proporcionada.
        
        Información de productos disponibles:
        {$products_context}
        
        Responde de manera profesional y empática. Si no encuentras productos específicos, 
        sugiere consultar con un especialista homeopático.
        
        Formato de respuesta: 
        1. Identifica los síntomas principales
        2. Recomienda productos específicos con breve explicación
        3. Incluye consejos generales si es apropiado";
        
        $messages = array(
            array('role' => 'system', 'content' => $system_message),
            array('role' => 'user', 'content' => $user_message)
        );
        
        $response = $this->call_api($messages);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        // Extraer recomendaciones y productos mencionados
        $ai_response = $response['choices'][0]['message']['content'];
        $mentioned_products = $this->extract_mentioned_products($ai_response, $matched_products);
        
        return array(
            'response' => $ai_response,
            'products' => $mentioned_products,
            'full_response' => $response
        );
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
    
    private function extract_mentioned_products($ai_response, $matched_products) {
        $mentioned_products = array();
        
        foreach ($matched_products as $product_id => $data) {
            $product_name = $data['analysis']['name'];
            
            // Verificar si el producto es mencionado en la respuesta de la IA
            if (stripos($ai_response, $product_name) !== false) {
                $mentioned_products[$product_id] = array(
                    'name' => $product_name,
                    'score' => $data['score'],
                    'analysis' => $data['analysis']
                );
            }
        }
        
        return $mentioned_products;
    }
    
    public function test_api_connection() {
        $test_message = array(
            array('role' => 'system', 'content' => 'Responde con "OK" si estás funcionando.'),
            array('role' => 'user', 'content' => 'Test de conexión')
        );
        
        $response = $this->call_api($test_message);
        
        return !isset($response['error']);
    }
}
