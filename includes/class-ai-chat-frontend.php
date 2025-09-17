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
        
        // Solo cargar en páginas relevantes
        if (!$this->should_load_chat()) {
            return;
        }
        
        wp_enqueue_style('wc-ai-chat-style', WC_AI_CHAT_PLUGIN_URL . 'assets/css/ai-chat.css', array(), WC_AI_CHAT_VERSION);
        wp_enqueue_script('wc-ai-chat-script', WC_AI_CHAT_PLUGIN_URL . 'assets/js/ai-chat.js', array('jquery'), WC_AI_CHAT_VERSION, true);
        
        // Pasar variables a JavaScript
        wp_localize_script('wc-ai-chat-script', 'wc_ai_chat_params', $this->get_js_params());
    }
    
    private function get_js_params() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ai_chat_frontend_nonce'),
            'placeholder_image' => WC()->plugin_url() . '/assets/images/placeholder.png',
            'loading_text' => __('Pensando...', 'wc-ai-homeopathic-chat'),
            'error_text' => __('Error de conexión. Intenta nuevamente.', 'wc-ai-homeopathic-chat')
        );
    }
    
    private function should_load_chat() {
        // Verificar si WooCommerce está activo
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        // Verificar si el chat está habilitado
        $enabled = get_option('wc_ai_chat_enabled', '1');
        if ($enabled !== '1') {
            return false;
        }
        
        // Cargar solo en páginas relevantes
        return is_shop() || is_product_category() || is_product() || is_page() || is_front_page();
    }
    
    public function render_chat_interface() {
        if (!$this->should_load_chat()) {
            return;
        }
        ?>
        <!-- El chat se renderiza mediante JavaScript -->
        <?php
    }
    
    public function handle_chat_message() {
        try {
            // Verificar nonce
            if (!check_ajax_referer('wc_ai_chat_frontend_nonce', 'nonce', false)) {
                throw new Exception('Error de seguridad. Nonce inválido.');
            }
            
            // Verificar método HTTP
            if ('POST' !== $_SERVER['REQUEST_METHOD']) {
                throw new Exception('Método no permitido.');
            }
            
            // Verificar que el mensaje existe
            if (!isset($_POST['message'])) {
                throw new Exception('Mensaje no proporcionado.');
            }
            
            $user_message = sanitize_text_field($_POST['message']);
            
            if (empty(trim($user_message))) {
                throw new Exception('El mensaje no puede estar vacío.');
            }
            
            $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
            
            // Procesar el mensaje
            $product_analyzer = new Product_Analyzer();
            $matched_products = $product_analyzer->find_products_by_query($user_message);
            
            $ai_handler = new AI_Handler();
            $response = $ai_handler->get_recommendations($user_message, $matched_products);
            
            if (isset($response['error'])) {
                throw new Exception($response['error']);
            }
            
            // Preparar respuesta exitosa
            $result = array(
                'response' => $response['response'],
                'products' => $this->prepare_products_response($matched_products)
            );
            
            if ($session_id) {
                $result['session_id'] = $session_id;
            }
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            error_log('WC AI Chat Error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ), 400);
        }
        
        wp_die(); // Siempre terminar con wp_die() en AJAX
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