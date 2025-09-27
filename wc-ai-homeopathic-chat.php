<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Chatbot flotante para recomendaciones homeopáticas con WooCommerce.
 * Version: 2.0.1
 * Author: Julio Rodríguez
 * Author URI: https://github.com/estratos
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-homeopathic-chat
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '2.0.0');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);
define('WC_AI_HOMEOPATHIC_CHAT_MAX_RETRIES', 2);
define('WC_AI_HOMEOPATHIC_CHAT_TIMEOUT', 25);

class WC_AI_Homeopathic_Chat {
    
    private $settings;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_settings();
        $this->initialize_hooks();
    }
    
    private function load_settings() {
        $this->settings = array(
            'api_key' => get_option('wc_ai_homeopathic_chat_api_key', ''),
            'api_url' => get_option('wc_ai_homeopathic_chat_api_url', 'https://api.deepseek.com/v1/chat/completions'),
            'cache_enable' => get_option('wc_ai_homeopathic_chat_cache_enable', true),
            'chat_position' => get_option('wc_ai_homeopathic_chat_position', 'right'),
            'whatsapp_number' => get_option('wc_ai_homeopathic_chat_whatsapp', ''),
            'whatsapp_message' => get_option('wc_ai_homeopathic_chat_whatsapp_message', 'Hola, me interesa obtener asesoramiento homeopático'),
            'enable_floating' => get_option('wc_ai_homeopathic_chat_floating', true),
            'show_on_products' => get_option('wc_ai_homeopathic_chat_products', true),
            'show_on_pages' => get_option('wc_ai_homeopathic_chat_pages', false)
        );
    }
    
    private function initialize_hooks() {
        // Frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_floating_chat'));
        
        // AJAX
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        
        // Admin
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
    }
    
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=wc-ai-homeopathic-chat') . '">' . 
                        __('Configuración', 'wc-ai-homeopathic-chat') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function enqueue_scripts() {
        if (!$this->should_display_chat()) {
            return;
        }
        
        wp_enqueue_style(
            'wc-ai-homeopathic-chat-style', 
            WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/css/chat-style.css', 
            array(), 
            WC_AI_HOMEOPATHIC_CHAT_VERSION
        );
        
        wp_enqueue_script(
            'wc-ai-homeopathic-chat-script', 
            WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/js/chat-script.js', 
            array('jquery'), 
            WC_AI_HOMEOPATHIC_CHAT_VERSION, 
            true
        );
        
        wp_localize_script('wc-ai-homeopathic-chat-script', 'wc_ai_homeopathic_chat_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_ai_homeopathic_chat_nonce'),
            'loading_text' => __('Consultando recomendaciones...', 'wc-ai-homeopathic-chat'),
            'error_text' => __('Error temporal. ¿Deseas continuar por WhatsApp?', 'wc-ai-homeopathic-chat'),
            'empty_message_text' => __('Por favor escribe un mensaje.', 'wc-ai-homeopathic-chat'),
            'whatsapp_btn' => __('Continuar por WhatsApp', 'wc-ai-homeopathic-chat'),
            'position' => $this->settings['chat_position'],
            'whatsapp_number' => $this->settings['whatsapp_number'],
            'whatsapp_message' => $this->settings['whatsapp_message'],
            'api_configured' => !empty($this->settings['api_key'])
        ));
    }
    
    private function should_display_chat() {
        if (!$this->settings['enable_floating']) {
            return false;
        }
        
        if (is_product() && $this->settings['show_on_products']) {
            return true;
        }
        
        if ((is_page() || is_single()) && $this->settings['show_on_pages']) {
            return true;
        }
        
        return is_shop() || is_product_category();
    }
    
    public function display_floating_chat() {
        if (!$this->should_display_chat()) {
            return;
        }
        
        $position_class = 'wc-ai-chat-position-' . $this->settings['chat_position'];
        $whatsapp_available = !empty($this->settings['whatsapp_number']);
        ?>
        <div id="wc-ai-homeopathic-chat-container" class="<?php echo esc_attr($position_class); ?>">
            <!-- Botón flotante -->
            <div id="wc-ai-chat-launcher" class="wc-ai-chat-launcher">
                <div class="wc-ai-chat-icon">💬</div>
                <div class="wc-ai-chat-pulse"></div>
            </div>
            
            <!-- Ventana del chat -->
            <div id="wc-ai-chat-window" class="wc-ai-chat-window">
                <div class="wc-ai-chat-header">
                    <div class="wc-ai-chat-header-info">
                        <div class="wc-ai-chat-avatar">⚕️</div>
                        <div class="wc-ai-chat-title">
                            <h4><?php esc_html_e('Asesor Homeopático', 'wc-ai-homeopathic-chat'); ?></h4>
                            <span class="wc-ai-chat-status"><?php esc_html_e('En línea', 'wc-ai-homeopathic-chat'); ?></span>
                        </div>
                    </div>
                    <div class="wc-ai-chat-actions">
                        <button type="button" class="wc-ai-chat-minimize" aria-label="<?php esc_attr_e('Minimizar chat', 'wc-ai-homeopathic-chat'); ?>">−</button>
                        <button type="button" class="wc-ai-chat-close" aria-label="<?php esc_attr_e('Cerrar chat', 'wc-ai-homeopathic-chat'); ?>">×</button>
                    </div>
                </div>
                
                <div class="wc-ai-chat-messages">
                    <div class="wc-ai-chat-message bot">
                        <div class="wc-ai-message-content">
                            <?php esc_html_e('¡Hola! Soy tu asesor homeopático. ¿En qué puedo ayudarte hoy?', 'wc-ai-homeopathic-chat'); ?>
                        </div>
                        <div class="wc-ai-message-time"><?php echo current_time('H:i'); ?></div>
                    </div>
                </div>
                
                <div class="wc-ai-chat-input-container">
                    <div class="wc-ai-chat-input">
                        <textarea placeholder="<?php esc_attr_e('Escribe tu mensaje...', 'wc-ai-homeopathic-chat'); ?>" 
                                  rows="1" 
                                  maxlength="500"></textarea>
                        <button type="button" class="wc-ai-chat-send">
                            <span class="wc-ai-send-icon">↑</span>
                        </button>
                    </div>
                    <?php if ($whatsapp_available): ?>
                    <div class="wc-ai-chat-fallback">
                        <button type="button" class="wc-ai-whatsapp-fallback">
                            <span class="wc-ai-whatsapp-icon">💬</span>
                            <?php esc_html_e('Continuar por WhatsApp', 'wc-ai-homeopathic-chat'); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_send_message() {
        try {
            $this->validate_ajax_request();
            
            $message = $this->sanitize_message();
            $cache_key = 'wc_ai_chat_' . md5($message);
            
            // Verificar caché
            $cached_response = $this->get_cached_response($cache_key);
            if ($cached_response !== false) {
                wp_send_json_success(array(
                    'response' => $cached_response,
                    'from_cache' => true
                ));
            }
            
            // Intentar con la API
            $products_info = $this->get_optimized_products_info();
            $prompt = $this->build_prompt($message, $products_info);
            $response = $this->call_deepseek_api_with_retry($prompt);
            
            if (is_wp_error($response)) {
                // Fallback a WhatsApp si está configurado
                if (!empty($this->settings['whatsapp_number'])) {
                    wp_send_json_success(array(
                        'response' => $this->get_whatsapp_fallback_message($message),
                        'whatsapp_fallback' => true
                    ));
                } else {
                    throw new Exception($response->get_error_message());
                }
            }
            
            $sanitized_response = $this->sanitize_api_response($response);
            $this->cache_response($cache_key, $sanitized_response);
            
            wp_send_json_success(array(
                'response' => $sanitized_response,
                'from_cache' => false
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function get_whatsapp_fallback_message($user_message) {
        $whatsapp_url = $this->generate_whatsapp_url($user_message);
        
        return sprintf(
            __('Parece que hay un problema con nuestro sistema. ¿Te gustaría continuar la conversación por WhatsApp?<br><br><a href="%s" target="_blank" class="wc-ai-whatsapp-link">💬 Abrir WhatsApp</a>', 'wc-ai-homeopathic-chat'),
            esc_url($whatsapp_url)
        );
    }
    
    private function generate_whatsapp_url($message = '') {
        $base_message = $this->settings['whatsapp_message'];
        $full_message = $message ? $base_message . "\n\nMi consulta: " . $message : $base_message;
        $encoded_message = urlencode($full_message);
        $phone = preg_replace('/[^0-9]/', '', $this->settings['whatsapp_number']);
        
        return "https://wa.me/{$phone}?text={$encoded_message}";
    }
    
    private function validate_ajax_request() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_ai_homeopathic_chat_nonce')) {
            throw new Exception(__('Error de seguridad.', 'wc-ai-homeopathic-chat'));
        }
    }
    
    private function sanitize_message() {
        $message = sanitize_text_field($_POST['message'] ?? '');
        
        if (empty(trim($message))) {
            throw new Exception(__('Por favor escribe un mensaje.', 'wc-ai-homeopathic-chat'));
        }
        
        return $message;
    }
    
    private function call_deepseek_api_with_retry($prompt) {
        if (empty($this->settings['api_key'])) {
            return new WP_Error('no_api_key', __('API no configurada', 'wc-ai-homeopathic-chat'));
        }
        
        for ($attempt = 1; $attempt <= WC_AI_HOMEOPATHIC_CHAT_MAX_RETRIES + 1; $attempt++) {
            $response = $this->call_deepseek_api($prompt, $attempt);
            
            if (!is_wp_error($response)) {
                return $response;
            }
            
            if ($attempt > WC_AI_HOMEOPATHIC_CHAT_MAX_RETRIES) {
                return $response;
            }
            
            usleep(pow(2, $attempt - 1) * 1000000);
        }
        
        return new WP_Error('max_retries', __('Máximo de reintentos alcanzado', 'wc-ai-homeopathic-chat'));
    }
    
    private function call_deepseek_api($prompt, $attempt = 1) {
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->settings['api_key']
        );
        
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Eres un homeópata experto. Sé conciso y profesional.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 600
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => WC_AI_HOMEOPATHIC_CHAT_TIMEOUT
        );
        
        $response = wp_remote_post($this->settings['api_url'], $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', sprintf(__('Error %d', 'wc-ai-homeopathic-chat'), $response_code));
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['choices'][0]['message']['content'])) {
            return trim($response_body['choices'][0]['message']['content']);
        }
        
        return new WP_Error('invalid_response', __('Respuesta inválida', 'wc-ai-homeopathic-chat'));
    }
    
    private function build_prompt($message, $products_info) {
        return "Usuario pregunta: {$message}\n\nProductos disponibles:\n{$products_info}\n\nResponde como homeópata experto, sé conciso y útil.";
    }
    
    private function get_optimized_products_info() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC'
        );
        
        $products = get_posts($args);
        $info = "Productos en tienda:\n";
        
        foreach ($products as $product) {
            $product_obj = wc_get_product($product->ID);
            if ($product_obj && $product_obj->is_visible()) {
                $info .= "- " . $product_obj->get_name() . " (" . $product_obj->get_price_html() . ")\n";
            }
        }
        
        return $info;
    }
    
    private function sanitize_api_response($response) {
        return wp_kses_post(trim($response));
    }
    
    private function get_cached_response($key) {
        if (!$this->settings['cache_enable']) {
            return false;
        }
        return get_transient($key);
    }
    
    private function cache_response($key, $response) {
        if ($this->settings['cache_enable']) {
            set_transient($key, $response, WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME);
        }
    }
    
    public function register_settings() {
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_api_key');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_api_url');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_cache_enable');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_position');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_whatsapp');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_whatsapp_message');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_floating');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_products');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_pages');
    }
    
    public function add_admin_menu() {
        add_options_page(
            'WC AI Homeopathic Chat',
            'Homeopathic Chat',
            'manage_options',
            'wc-ai-homeopathic-chat',
            array($this, 'options_page')
        );
    }
    
    public function options_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración del Chat Homeopático', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wc_ai_homeopathic_chat_settings'); ?>
                
                <div class="wc-ai-chat-settings-grid">
                    <!-- Columna 1: Configuración API -->
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Configuración de API', 'wc-ai-homeopathic-chat'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wc_ai_homeopathic_chat_api_key">
                                            <?php esc_html_e('DeepSeek API Key', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input type="password" 
                                               id="wc_ai_homeopathic_chat_api_key"
                                               name="wc_ai_homeopathic_chat_api_key" 
                                               value="<?php echo esc_attr($this->settings['api_key']); ?>" 
                                               class="regular-text" />
                                        <p class="description">
                                            <?php esc_html_e('Clave de API para DeepSeek', 'wc-ai-homeopathic-chat'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wc_ai_homeopathic_chat_api_url">
                                            <?php esc_html_e('URL de API', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input type="url" 
                                               id="wc_ai_homeopathic_chat_api_url"
                                               name="wc_ai_homeopathic_chat_api_url" 
                                               value="<?php echo esc_attr($this->settings['api_url']); ?>" 
                                               class="regular-text" />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Habilitar Caché', 'wc-ai-homeopathic-chat'); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="wc_ai_homeopathic_chat_cache_enable" 
                                                   value="1" 
                                                   <?php checked($this->settings['cache_enable'], true); ?> />
                                            <?php esc_html_e('Usar caché para mejor rendimiento', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Columna 2: Apariencia -->
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Apariencia', 'wc-ai-homeopathic-chat'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Posición del Chat', 'wc-ai-homeopathic-chat'); ?>
                                    </th>
                                    <td>
                                        <select name="wc_ai_homeopathic_chat_position">
                                            <option value="right" <?php selected($this->settings['chat_position'], 'right'); ?>>
                                                <?php esc_html_e('Derecha', 'wc-ai-homeopathic-chat'); ?>
                                            </option>
                                            <option value="left" <?php selected($this->settings['chat_position'], 'left'); ?>>
                                                <?php esc_html_e('Izquierda', 'wc-ai-homeopathic-chat'); ?>
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Chat Flotante', 'wc-ai-homeopathic-chat'); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="wc_ai_homeopathic_chat_floating" 
                                                   value="1" 
                                                   <?php checked($this->settings['enable_floating'], true); ?> />
                                            <?php esc_html_e('Mostrar chat flotante', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Mostrar en Productos', 'wc-ai-homeopathic-chat'); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="wc_ai_homeopathic_chat_products" 
                                                   value="1" 
                                                   <?php checked($this->settings['show_on_products'], true); ?> />
                                            <?php esc_html_e('Mostrar en páginas de producto', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e('Mostrar en Páginas', 'wc-ai-homeopathic-chat'); ?>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   name="wc_ai_homeopathic_chat_pages" 
                                                   value="1" 
                                                   <?php checked($this->settings['show_on_pages'], true); ?> />
                                            <?php esc_html_e('Mostrar en páginas y entradas', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Columna 3: WhatsApp -->
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Configuración de WhatsApp', 'wc-ai-homeopathic-chat'); ?></h2>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="wc_ai_homeopathic_chat_whatsapp">
                                            <?php esc_html_e('Número de WhatsApp', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <input type="text" 
                                               id="wc_ai_homeopathic_chat_whatsapp"
                                               name="wc_ai_homeopathic_chat_whatsapp" 
                                               value="<?php echo esc_attr($this->settings['whatsapp_number']); ?>" 
                                               class="regular-text" 
                                               placeholder="+521234567890" />
                                        <p class="description">
                                            <?php esc_html_e('Número con código de país (ej: +521234567890)', 'wc-ai-homeopathic-chat'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wc_ai_homeopathic_chat_whatsapp_message">
                                            <?php esc_html_e('Mensaje Predeterminado', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </th>
                                    <td>
                                        <textarea id="wc_ai_homeopathic_chat_whatsapp_message"
                                                  name="wc_ai_homeopathic_chat_whatsapp_message"
                                                  class="large-text"
                                                  rows="3"><?php echo esc_textarea($this->settings['whatsapp_message']); ?></textarea>
                                        <p class="description">
                                            <?php esc_html_e('Mensaje inicial para WhatsApp', 'wc-ai-homeopathic-chat'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <style>
        .wc-ai-chat-settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .wc-ai-settings-column .card {
            height: 100%;
        }
        </style>
        <?php
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e('WC AI Homeopathic Chat requiere WooCommerce.', 'wc-ai-homeopathic-chat'); ?></p>
        </div>
        <?php
    }
}

new WC_AI_Homeopathic_Chat();