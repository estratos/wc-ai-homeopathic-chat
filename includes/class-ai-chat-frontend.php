<?php
class AI_Chat_Frontend {
    
    private $nonce_action = 'wc_ai_chat_frontend';
    
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
        
        wp_localize_script('wc-ai-chat-script', 'wc_ai_chat_params', $this->get_js_params());
    }
    
    private function get_js_params() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->nonce_action),
            'placeholder_image' => WC()->plugin_url() . '/assets/images/placeholder.png',
            'loading_text' => __('Pensando...', 'wc-ai-homeopathic-chat'),
            'error_text' => __('Error de conexión. Intenta nuevamente.', 'wc-ai-homeopathic-chat')
        );
    }
    
    private function should_load_chat() {
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        $enabled = get_option('wc_ai_chat_enabled', '1');
        if ($enabled !== '1') {
            return false;
        }
        
        return true; // Cargar en todas las páginas
    }
    
    public function render_chat_interface() {
        if (!$this->should_load_chat()) {
            return;
        }
        ?>
        <!-- Chat interface will be rendered by JavaScript -->
        <?php
    }
    
    public function handle_chat_message() {
        // Establecer tipo de contenido JSON
        header('Content-Type: application/json');
        
        try {
            // Verificar que sea una petición POST
            if ('POST' !== $_SERVER['REQUEST_METHOD']) {
                throw new Exception('Método no permitido');
            }
            
            // Leer el input JSON
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            // Si no hay datos JSON, usar POST normal
            if (empty($data)) {
                $data = $_POST;
            }
            
            // Verificar nonce
            if (!isset($data['nonce'])) {
                throw new Exception('Nonce no proporcionado');
            }
            
            $nonce = sanitize_text_field($data['nonce']);
            
            if (!wp_verify_nonce($nonce, $this->nonce_action)) {
                error_log('Nonce verification failed. Received: ' . $nonce);
                error_log('Expected: ' . wp_create_nonce($this->nonce_action));
                throw new Exception('Error de seguridad. Por favor recarga la página.');
            }
            
            // Verificar mensaje
            if (!isset($data['message'])) {
                throw new Exception('Mensaje no proporcionado');
            }
            
            $user_message = sanitize_text_field($data['message']);
            
            if (empty(trim($user_message))) {
                throw new Exception('El mensaje no puede estar vacío');
            }
            
            $session_id = isset($data['session_id']) ? sanitize_text_field($data['session_id']) : '';
            
            // Procesar mensaje
            $product_analyzer = new Product_Analyzer();
            $matched_products = $product_analyzer->find_products_by_query($user_message);
            
            $ai_handler = new AI_Handler();
            $response = $ai_handler->get_recommendations($user_message, $matched_products);
            
            if (isset($response['error'])) {
                throw new Exception($response['error']);
            }
            
            // Respuesta exitosa
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