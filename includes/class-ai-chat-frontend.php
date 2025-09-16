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
        // Mostrar solo en páginas relevantes de WooCommerce
        if (class_exists('WooCommerce') && (is_product() || is_shop() || is_product_category())) {
            include WC_AI_CHAT_PLUGIN_PATH . 'templates/chat-interface.php';
        }
    }
    
    public function handle_chat_message() {
        check_ajax_referer('wc_ai_chat_nonce', 'nonce');
        
        if (!isset($_POST['message']) || empty(trim($_POST['message']))) {
            wp_send_json_error(array('message' => 'Mensaje vacío'));
            return;
        }
        
        $user_message = sanitize_text_field($_POST['message']);
        
        // Respuesta temporal básica
        $response = array(
            'session_id' => 'temp-session-' . time(),
            'ai_response' => 'He recibido tu mensaje: "' . $user_message . '". Esta es una respuesta de prueba. Configura la API key de OpenAI en el panel de administración para respuestas reales.',
            'recommended_products' => array()
        );
        
        wp_send_json_success($response);
    }
}
