<?php
class AI_Chat_Frontend {
    
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_chat_interface'));
        add_action('wp_ajax_wc_ai_chat_send_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_wc_ai_chat_send_message', array($this, 'handle_chat_message'));
    }
    
    public function enqueue_scripts() {
        if (is_admin()) {
            return;
        }
        
        if (!$this->should_load_chat()) {
            return;
        }
        
        wp_enqueue_style('wc-ai-chat-style', WC_AI_CHAT_PLUGIN_URL . 'assets/css/ai-chat.css', array(), WC_AI_CHAT_VERSION);
        wp_enqueue_script('wc-ai-chat-script', WC_AI_CHAT_PLUGIN_URL . 'assets/js/ai-chat.js', array('jquery'), WC_AI_CHAT_VERSION, true);
        
        wp_localize_script('wc-ai-chat-script', 'wc_ai_chat_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ai_chat_ajax'),
            'placeholder_image' => WC()->plugin_url() . '/assets/images/placeholder.png',
            'loading_text' => 'Pensando...',
            'error_text' => 'Error de conexión. Intenta nuevamente.'
        ));
    }
    
    private function should_load_chat() {
    if (!class_exists('WooCommerce')) {
        return false;
    }
    
    $enabled = get_option('wc_ai_chat_enabled', '1');
    if ($enabled !== '1') {
        return false;
    }
    
    // Mostrar en más páginas
    $show_chat = is_shop() || is_product_category() || is_product() || 
                 is_page() || is_front_page() || is_home();
    
    return apply_filters('wc_ai_chat_show_chat', $show_chat);
}

public function render_chat_interface() {
    if (!$this->should_load_chat()) {
        return;
    }
    ?>
    <div id="wc-ai-chat-container">
        <button id="wc-ai-chat-button" aria-label="Abrir chat de asistente homeopático">💬</button>
        <div id="wc-ai-chat-window">
            <div class="wc-ai-chat-header">
                <h3>Asistente Homeopático</h3>
                <button class="wc-ai-chat-close" aria-label="Cerrar chat">×</button>
            </div>
            <div class="wc-ai-chat-messages">
                <div class="wc-ai-message ai">
                    <div class="wc-ai-message-bubble">
                        ¡Hola! Soy tu asistente homeopático. ¿En qué puedo ayudarte hoy?
                    </div>
                </div>
            </div>
            <div class="wc-ai-chat-input">
                <form class="wc-ai-chat-input-form">
                    <input type="text" class="wc-ai-chat-input-field" 
                           placeholder="Describe tus síntomas o malestares..." 
                           required
                           aria-label="Escribe tu mensaje">
                    <button type="submit" class="wc-ai-chat-send-button">Enviar</button>
                </form>
            </div>
        </div>
    </div>
    <?php
}
    
    public function handle_chat_message() {
        // Limpiar cualquier output previo
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Establecer headers para JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $response = array();
        
        try {
            // Verificar nonce
            if (!isset($_POST['nonce'])) {
                throw new Exception('Nonce no proporcionado.');
            }
            
            $nonce = sanitize_text_field($_POST['nonce']);
            
            if (!wp_verify_nonce($nonce, 'wc_ai_chat_ajax')) {
                error_log('WC AI Chat - Nonce verification failed');
                throw new Exception('Error de seguridad. Por favor recarga la página.');
            }
            
            // Verificar mensaje
            if (!isset($_POST['message'])) {
                throw new Exception('Mensaje no proporcionado.');
            }
            
            $user_message = sanitize_text_field($_POST['message']);
            
            if (empty(trim($user_message))) {
                throw new Exception('El mensaje no puede estar vacío.');
            }
            
            $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
            
            // Procesar mensaje
            $product_analyzer = new Product_Analyzer();
            $matched_products = $product_analyzer->find_products_by_query($user_message);
            
            $ai_handler = new AI_Handler();
            $ai_response = $ai_handler->get_recommendations($user_message, $matched_products);
            
            if (isset($ai_response['error'])) {
                throw new Exception($ai_response['error']);
            }
            
            // Respuesta exitosa
            $response = array(
                'success' => true,
                'data' => array(
                    'response' => $ai_response['response'],
                    'products' => $this->prepare_products_response($matched_products),
                    'session_id' => $session_id
                )
            );
            
        } catch (Exception $e) {
            error_log('WC AI Chat Error: ' . $e->getMessage());
            
            $response = array(
                'success' => false,
                'data' => array(
                    'message' => $e->getMessage()
                )
            );
        }
        
        // Enviar respuesta JSON
        echo json_encode($response);
        wp_die();
    }
    
    private function prepare_products_response($products) {
        $result = array();
        
        if (!is_array($products) || empty($products)) {
            return $result;
        }
        
        foreach ($products as $product_id => $data) {
            $product = wc_get_product($product_id);
            
            if ($product && $product->is_visible()) {
                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
                
                $result[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price_html(),
                    'url' => get_permalink($product_id),
                    'image' => $image_url ? $image_url : WC()->plugin_url() . '/assets/images/placeholder.png'
                );
            }
        }
        
        return $result;
    }
}