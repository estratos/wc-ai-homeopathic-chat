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
        
        wp_enqueue_style('wc-ai-chat-style', WC_AI_CHAT_PLUGIN_URL . 'assets/css/ai-chat.css', array(), WC_AI_CHAT_VERSION);
        wp_enqueue_script('wc-ai-chat-script', WC_AI_CHAT_PLUGIN_URL . 'assets/js/ai-chat.js', array('jquery'), WC_AI_CHAT_VERSION, true);
        
        wp_localize_script('wc-ai-chat-script', 'wc_ai_chat_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ai_chat_nonce')
        ));
    }
    
    public function render_chat_interface() {
        if (!class_exists('WooCommerce') || is_admin()) {
            return;
        }
        
        $enabled = get_option('wc_ai_chat_enabled', '1');
        if ($enabled !== '1') {
            return;
        }
        ?>
        <div id="wc-ai-chat-container">
            <button id="wc-ai-chat-button">💬</button>
            <div id="wc-ai-chat-window">
                <div class="wc-ai-chat-header">
                    <h3>Asistente Homeopático</h3>
                    <button class="wc-ai-chat-close">×</button>
                </div>
                <div class="wc-ai-chat-messages"></div>
                <div class="wc-ai-chat-input">
                    <form class="wc-ai-chat-input-form">
                        <input type="text" class="wc-ai-chat-input-field" placeholder="Describe tus síntomas..." required>
                        <button type="submit">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function handle_chat_message() {
        check_ajax_referer('wc_ai_chat_nonce', 'nonce');
        
        if (!isset($_POST['message']) || empty($_POST['message'])) {
            wp_send_json_error(array('message' => 'Mensaje vacío'));
            return;
        }
        
        $user_message = sanitize_text_field($_POST['message']);
        
        $product_analyzer = new Product_Analyzer();
        $matched_products = $product_analyzer->find_products_by_query($user_message);
        
        $ai_handler = new AI_Handler();
        $response = $ai_handler->get_recommendations($user_message, $matched_products);
        
        if (isset($response['error'])) {
            wp_send_json_error(array('message' => $response['error']));
            return;
        }
        
        wp_send_json_success(array(
            'response' => $response['response'],
            'products' => $this->prepare_products_response($matched_products)
        ));
    }
    
    private function prepare_products_response($products) {
        $result = array();
        
        foreach ($products as $product_id => $data) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_visible()) {
                $result[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price_html(),
                    'url' => get_permalink($product_id),
                    'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')
                );
            }
        }
        
        return $result;
    }
}