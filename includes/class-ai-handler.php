<?php
class AI_Handler {
    
    private $api_key;
    
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
        
        // Implementación básica temporal
        return array(
            'response' => 'Esta es una respuesta de prueba. Configura tu API key de OpenAI para respuestas reales.',
            'products' => array(),
            'full_response' => array()
        );
    }
    
    public function test_api_connection() {
        return !empty($this->api_key);
    }
}
