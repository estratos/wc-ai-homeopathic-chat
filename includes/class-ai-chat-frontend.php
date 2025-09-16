<?php
class AI_Chat_Frontend {
    
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_chat_interface'));
        add_action('wp_ajax_wc_ai_chat_send_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_wc_ai_chat_send_message', array($this, 'handle_chat_message'));
    }
    
    public function enqueue_scripts() {
        if (!is_admin()) {
            wp_enqueue_style('wc-ai-chat-style', WC_AI_CHAT_PLUGIN_URL . 'assets/css/ai-chat.css', array(), WC_AI_CHAT_VERSION);
            wp_enqueue_script('wc-ai-chat-script', WC_AI_CHAT_PLUGIN_URL . 'assets/js/ai-chat.js', array('jquery'), WC_AI_CHAT_VERSION, true);
            
            wp_localize_script('wc-ai-chat-script', 'wc_ai_chat_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_ai_chat_nonce'),
                'loading_text' => __('Analizando tus síntomas...', 'wc-ai-homeopathic-chat'),
                'error_text' => __('Error al procesar tu mensaje. Intenta nuevamente.', 'wc-ai-homeopathic-chat')
            ));
        }
    }
    
    public function render_chat_interface() {
        if (is_product() || is_shop() || is_product_category()) {
            include WC_AI_CHAT_PLUGIN_PATH . 'templates/chat-interface.php';
        }
    }
    
    public function handle_chat_message() {
        check_ajax_referer('wc_ai_chat_nonce', 'nonce');
        
        if (!isset($_POST['message']) || empty($_POST['message'])) {
            wp_send_json_error(array('message' => 'Mensaje vacío'));
            return;
        }
        
        $user_message = sanitize_text_field($_POST['message']);
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        // Obtener instancias
        $product_analyzer = WC_AI_Homeopathic_Chat::get_instance()->get_product_analyzer();
        $ai_handler = WC_AI_Homeopathic_Chat::get_instance()->get_ai_handler();
        $chat_sessions = WC_AI_Homeopathic_Chat::get_instance()->get_chat_sessions();
        
        // Extraer síntomas del mensaje del usuario
        $symptoms = $this->extract_symptoms_from_message($user_message);
        
        // Buscar productos que coincidan con los síntomas
        $matched_products = $product_analyzer->find_products_by_symptoms($symptoms);
        
        // Obtener recomendaciones de la IA
        $recommendations = $ai_handler->get_recommendations($user_message, $matched_products);
        
        if (isset($recommendations['error'])) {
            wp_send_json_error(array('message' => $recommendations['error']));
            return;
        }
        
        // Guardar en sesión de chat
        $chat_session = array(
            'user_message' => $user_message,
            'ai_response' => $recommendations['response'],
            'matched_products' => $matched_products,
            'recommended_products' => $recommendations['products'],
            'timestamp' => current_time('mysql')
        );
        
        if ($session_id) {
            $chat_sessions->add_message_to_session($session_id, $chat_session);
        } else {
            $session_id = $chat_sessions->create_session($chat_session);
        }
        
        // Preparar respuesta
        $response = array(
            'session_id' => $session_id,
            'ai_response' => $recommendations['response'],
            'recommended_products' => $this->prepare_products_response($recommendations['products'])
        );
        
        wp_send_json_success($response);
    }
    
    private function extract_symptoms_from_message($message) {
        $message = strtolower($message);
        $symptoms = array();
        
        // Lista de síntomas comunes (puede extenderse)
        $common_symptoms = array(
            'dolor', 'fiebre', 'inflamación', 'tos', 'estornudos', 'picazón', 
            'ardor', 'mareo', 'náuseas', 'vómito', 'diarrea', 'estreñimiento',
            'insomnio', 'ansiedad', 'estrés', 'fatiga', 'debilidad', 'gripe',
            'resfriado', 'alergia', 'migraña', 'artritis', 'depresión', 'acné'
        );
        
        foreach ($common_symptoms as $symptom) {
            if (strpos($message, $symptom) !== false) {
                $symptoms[] = $symptom;
            }
        }
        
        return array_unique($symptoms);
    }
    
    private function prepare_products_response($products) {
        $prepared_products = array();
        
        foreach ($products as $product_id => $data) {
            $product = wc_get_product($product_id);
            
            if ($product && $product->is_visible()) {
                $prepared_products[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price_html(),
                    'url' => get_permalink($product_id),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                    'description' => $product->get_short_description()
                );
            }
        }
        
        return $prepared_products;
    }
}
