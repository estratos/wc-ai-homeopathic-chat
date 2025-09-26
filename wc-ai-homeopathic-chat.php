<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Un chat de inteligencia artificial para recomendaciones homeopáticas en WooCommerce.
 * Version: 1.4.0
 * Author: Esteban Rodríguez
 * Author URI: https://github.com/estratos
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-homeopathic-chat
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '1.4.0');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);

class WC_AI_Homeopathic_Chat {
    
    private $api_key;
    private $api_url;
    private $cache_enabled;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Verificar si WooCommerce está activo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Cargar configuración
        $this->load_settings();
        
        // Inicializar hooks
        $this->initialize_hooks();
    }
    
    private function load_settings() {
        $this->api_key = get_option('wc_ai_homeopathic_chat_api_key', '');
        $this->api_url = get_option('wc_ai_homeopathic_chat_api_url', 'https://api.deepseek.com/v1/chat/completions');
        $this->cache_enabled = get_option('wc_ai_homeopathic_chat_cache_enable', true);
    }
    
    private function initialize_hooks() {
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('woocommerce_after_single_product', array($this, 'display_chat_button'));
        
        // Shortcode
        add_shortcode('wc_ai_homeopathic_chat', array($this, 'chat_shortcode'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        
        // Cache hooks
        add_action('wp_scheduled_delete', array($this, 'clear_expired_cache'));
    }
    
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=wc-ai-homeopathic-chat') . '">' . 
                        __('Configuración', 'wc-ai-homeopathic-chat') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function enqueue_scripts() {
        if (!$this->should_enqueue_scripts()) {
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
            'error_text' => __('Error al conectar con el servicio. Intenta nuevamente.', 'wc-ai-homeopathic-chat'),
            'empty_message_text' => __('Por favor escribe un mensaje.', 'wc-ai-homeopathic-chat')
        ));
    }
    
    private function should_enqueue_scripts() {
        return is_product() || is_page() || is_single() || is_shop();
    }
    
    public function display_chat_button() {
        if (!$this->is_api_configured()) {
            return;
        }
        
        echo '<div class="wc-ai-homeopathic-chat-container">';
        echo '<button id="wc-ai-homeopathic-chat-toggle" class="wc-ai-homeopathic-chat-toggle">';
        echo esc_html__('¿Necesitas asesoramiento homeopático?', 'wc-ai-homeopathic-chat');
        echo '</button>';
        echo $this->get_chat_interface();
        echo '</div>';
    }
    
    public function chat_shortcode($atts) {
        if (!$this->is_api_configured()) {
            return '<p>' . esc_html__('El chat no está configurado correctamente.', 'wc-ai-homeopathic-chat') . '</p>';
        }
        
        return $this->get_chat_interface();
    }
    
    private function get_chat_interface() {
        ob_start();
        ?>
        <div id="wc-ai-homeopathic-chat" class="wc-ai-homeopathic-chat" style="display: none;">
            <div class="wc-ai-homeopathic-chat-header">
                <h3><?php esc_html_e('Asesor Homeopático AI', 'wc-ai-homeopathic-chat'); ?></h3>
                <button type="button" class="wc-ai-homeopathic-chat-close" aria-label="<?php esc_attr_e('Cerrar chat', 'wc-ai-homeopathic-chat'); ?>">&times;</button>
            </div>
            <div class="wc-ai-homeopathic-chat-messages">
                <div class="wc-ai-homeopathic-chat-message bot">
                    <?php esc_html_e('¡Hola! Soy tu asesor homeopático. Puedo recomendarte productos basados en tus síntomas y necesidades. Por favor, describe cómo te sientes o qué síntomas experimentas.', 'wc-ai-homeopathic-chat'); ?>
                </div>
            </div>
            <div class="wc-ai-homeopathic-chat-input">
                <textarea placeholder="<?php esc_attr_e('Escribe tus síntomas o preguntas aquí...', 'wc-ai-homeopathic-chat'); ?>" rows="2" maxlength="500"></textarea>
                <button type="button" class="wc-ai-homeopathic-chat-send"><?php esc_html_e('Enviar', 'wc-ai-homeopathic-chat'); ?></button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_send_message() {
        try {
            $this->validate_ajax_request();
            
            $message = $this->sanitize_message();
            $cache_key = 'wc_ai_chat_' . md5($message);
            
            // Intentar obtener respuesta desde caché
            $cached_response = $this->get_cached_response($cache_key);
            if ($cached_response !== false) {
                wp_send_json_success(array(
                    'response' => $cached_response,
                    'from_cache' => true
                ));
            }
            
            // Obtener información de productos y generar respuesta
            $products_info = $this->get_optimized_products_info();
            $prompt = $this->build_prompt($message, $products_info);
            $response = $this->call_deepseek_api($prompt);
            
            if (is_wp_error($response)) {
                wp_send_json_error($response->get_error_message());
            }
            
            // Almacenar respuesta en caché
            $this->cache_response($cache_key, $response);
            
            wp_send_json_success(array(
                'response' => $response,
                'from_cache' => false
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function validate_ajax_request() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_ai_homeopathic_chat_nonce')) {
            throw new Exception(__('Error de seguridad. Intenta recargar la página.', 'wc-ai-homeopathic-chat'));
        }
        
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            throw new Exception(__('Acceso no permitido.', 'wc-ai-homeopathic-chat'));
        }
    }
    
    private function sanitize_message() {
        $message = sanitize_text_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            throw new Exception(__('Por favor escribe un mensaje.', 'wc-ai-homeopathic-chat'));
        }
        
        if (strlen($message) > 500) {
            throw new Exception(__('El mensaje es demasiado largo. Máximo 500 caracteres.', 'wc-ai-homeopathic-chat'));
        }
        
        return $message;
    }
    
    private function build_prompt($message, $products_info) {
        return "Eres un experto homeópata y asistente de tienda. Contexto completo:

INVENTARIO DE LA TIENDA:
{$products_info}

SOLICITUD DEL USUARIO:
\"{$message}\"

INSTRUCCIONES:
1. Analiza el inventario completo proporcionado
2. Prioriza productos de categorías: homeopathic, wellness, natural, supplements
3. Proporciona recomendaciones educativas basadas en la información de productos
4. Siempre aclara que no eres un sustituto de profesional médico
5. Incluye información práctica cuando sea relevante
6. Mantén un tono profesional pero accesible

FORMATO DE RESPUESTA:
- Explicación breve del enfoque
- Recomendaciones específicas con justificación
- Referencia a categorías relevantes
- Precauciones y recomendaciones generales";
    }
    
    private function get_category_summary() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 20 // Limitar para evitar sobrecarga
        ));
        
        if (is_wp_error($categories) || empty($categories)) {
            return "No se pudieron cargar las categorías de productos.\n";
        }
        
        $category_summary = "RESUMEN ESTADÍSTICO DEL INVENTARIO:\n";
        $total_products = 0;
        
        foreach ($categories as $category) {
            $product_count = $category->count;
            $total_products += $product_count;
            $category_summary .= "📂 {$category->name}: {$product_count} productos\n";
        }
        
        $category_summary .= "\n📦 TOTAL PRODUCTOS: {$total_products}\n";
        return $category_summary;
    }
    
    private function get_featured_products_by_category($category_slug, $limit = 5) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => array($category_slug)
                )
            ),
            'orderby' => 'modified',
            'order' => 'DESC'
        );
        
        $products = get_posts($args);
        return is_wp_error($products) ? array() : $products;
    }
    
    private function get_optimized_products_info() {
        $category_summary = $this->get_category_summary();
        
        // Categorías prioritarias
        $priority_categories = array('homeopathic', 'wellness', 'natural', 'supplements', 'health');
        $priority_products = array();
        
        foreach ($priority_categories as $category) {
            $category_products = $this->get_featured_products_by_category($category, 5);
            $priority_products = array_merge($priority_products, $category_products);
        }
        
        // Obtener muestras de otras categorías
        $all_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'fields' => 'slugs',
            'number' => 10
        ));
        
        $other_products = array();
        if (!is_wp_error($all_categories)) {
            foreach ($all_categories as $category_slug) {
                if (!in_array($category_slug, $priority_categories)) {
                    $category_sample = $this->get_featured_products_by_category($category_slug, 2);
                    $other_products = array_merge($other_products, $category_sample);
                }
            }
        }
        
        // Combinar y eliminar duplicados
        $all_products = array_merge($priority_products, $other_products);
        $unique_products = array();
        $used_ids = array();
        
        foreach ($all_products as $product) {
            if (!in_array($product->ID, $used_ids)) {
                $unique_products[] = $product;
                $used_ids[] = $product->ID;
            }
        }
        
        // Procesar información de productos
        $products_info = $category_summary . "\n\nPRODUCTOS DESTACADOS:\n\n";
        
        foreach ($unique_products as $product) {
            $product_info = $this->get_product_data($product);
            if ($product_info) {
                $products_info .= $product_info . "---\n";
            }
        }
        
        $total_products = wp_count_posts('product')->publish;
        $products_info .= "\n💼 INVENTARIO COMPLETO: {$total_products} productos disponibles.\n";
        
        return $products_info;
    }
    
    private function get_product_data($product) {
        $product_obj = wc_get_product($product->ID);
        
        if (!$product_obj || !$product_obj->is_visible()) {
            return false;
        }
        
        $title = $product_obj->get_name();
        $short_description = wp_strip_all_tags($product_obj->get_short_description() ?: '');
        $price = $product_obj->get_price_html();
        $sku = $product_obj->get_sku() ?: 'N/A';
        $stock_status = $product_obj->get_stock_status();
        
        // Categorías
        $categories = wp_get_post_terms($product->ID, 'product_cat', array(
            'fields' => 'names',
            'number' => 3
        ));
        $categories_str = !empty($categories) ? implode(', ', $categories) : 'General';
        
        // Etiquetas
        $tags = wp_get_post_terms($product->ID, 'product_tag', array(
            'fields' => 'names',
            'number' => 5
        ));
        $tags_str = !empty($tags) ? implode(', ', $tags) : '';
        
        $info = "=== {$title} ===\n";
        $info .= "📦 SKU: {$sku} | 💰 {$price} | 📊 {$stock_status} | 📂 {$categories_str}\n";
        
        if (!empty($short_description)) {
            $short_description = strlen($short_description) > 150 ? 
                substr($short_description, 0, 147) . '...' : $short_description;
            $info .= "📝 {$short_description}\n";
        }
        
        if (!empty($tags_str)) {
            $info .= "🏷️ {$tags_str}\n";
        }
        
        return $info;
    }
    
    private function get_cached_response($key) {
        if (!$this->cache_enabled) {
            return false;
        }
        
        $cached = get_transient($key);
        if ($cached !== false) {
            $this->log_cache_hit();
            return $cached;
        }
        
        return false;
    }
    
    private function cache_response($key, $response) {
        if (!$this->cache_enabled) {
            return;
        }
        
        set_transient($key, $response, WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME);
        
        $cache_keys = get_option('wc_ai_homeopathic_chat_cache_keys', array());
        if (!in_array($key, $cache_keys)) {
            $cache_keys[] = $key;
            update_option('wc_ai_homeopathic_chat_cache_keys', $cache_keys);
        }
        
        $this->log_cache_miss();
    }
    
    private function log_cache_hit() {
        $stats = get_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0, 'misses' => 0, 'total_requests' => 0
        ));
        
        $stats['hits']++;
        $stats['total_requests']++;
        update_option('wc_ai_homeopathic_chat_cache_stats', $stats);
    }
    
    private function log_cache_miss() {
        $stats = get_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0, 'misses' => 0, 'total_requests' => 0
        ));
        
        $stats['misses']++;
        $stats['total_requests']++;
        update_option('wc_ai_homeopathic_chat_cache_stats', $stats);
    }
    
    private function call_deepseek_api($prompt) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('La clave de API no está configurada.', 'wc-ai-homeopathic-chat'));
        }
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key
        );
        
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Eres un homeópata experto. Siempre aclaras que no eres un sustituto de un profesional médico.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 800,
            'stream' => false
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        );
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('api_error', sprintf(__('Error en la API: código %d', 'wc-ai-homeopathic-chat'), $response_code));
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['choices'][0]['message']['content'])) {
            return trim($response_body['choices'][0]['message']['content']);
        }
        
        return new WP_Error('invalid_response', __('Respuesta inválida de la API.', 'wc-ai-homeopathic-chat'));
    }
    
    private function is_api_configured() {
        return !empty($this->api_key);
    }
    
    public function register_settings() {
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_api_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://api.deepseek.com/v1/chat/completions'
        ));
        
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_cache_enable', array(
            'type' => 'boolean',
            'default' => true
        ));
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('WC AI Homeopathic Chat Settings', 'wc-ai-homeopathic-chat'),
            __('Homeopathic Chat', 'wc-ai-homeopathic-chat'),
            'manage_options',
            'wc-ai-homeopathic-chat',
            array($this, 'options_page')
        );
    }
    
    public function options_page() {
        $cache_stats = get_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0, 'misses' => 0, 'total_requests' => 0
        ));
        
        $cache_efficiency = $cache_stats['total_requests'] > 0 ? 
            round(($cache_stats['hits'] / $cache_stats['total_requests']) * 100, 2) : 0;
        
        $cache_keys = get_option('wc_ai_homeopathic_chat_cache_keys', array());
        $cache_count = count($cache_keys);
        
        $total_products = wp_count_posts('product')->publish;
        
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => 50
        ));
        
        $categories_count = (is_wp_error($categories) || !is_array($categories)) ? 0 : count($categories);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración del Chat Homeopático AI', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <?php if (isset($_GET['cache_cleared']) && $_GET['cache_cleared'] === 'true') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Caché limpiado correctamente.', 'wc-ai-homeopathic-chat'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php esc_html_e('Estadísticas del Sistema', 'wc-ai-homeopathic-chat'); ?></h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <strong><?php esc_html_e('Total de solicitudes:', 'wc-ai-homeopathic-chat'); ?></strong> 
                        <span><?php echo intval($cache_stats['total_requests']); ?></span>
                    </div>
                    <div class="stat-item">
                        <strong><?php esc_html_e('Aciertos de caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                        <span><?php echo intval($cache_stats['hits']); ?></span>
                    </div>
                    <div class="stat-item">
                        <strong><?php esc_html_e('Fallos de caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                        <span><?php echo intval($cache_stats['misses']); ?></span>
                    </div>
                    <div class="stat-item">
                        <strong><?php esc_html_e('Eficiencia de caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                        <span><?php echo esc_html($cache_efficiency); ?>%</span>
                    </div>
                    <div class="stat-item">
                        <strong><?php esc_html_e('Elementos en caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                        <span><?php echo intval($cache_count); ?></span>
                    </div>
                    <div class="stat-item">
                        <strong><?php esc_html_e('Productos en tienda:', 'wc-ai-homeopathic-chat'); ?></strong> 
                        <span><?php echo intval($total_products); ?></span>
                    </div>
                    <div class="stat-item">
                        <strong><?php esc_html_e('Categorías:', 'wc-ai-homeopathic-chat'); ?></strong> 
                        <span><?php echo intval($categories_count); ?></span>
                    </div>
                </div>
                
                <p>
                    <a href="<?php echo esc_url(wp_nonce_url(
                        admin_url('options-general.php?page=wc-ai-homeopathic-chat&action=clear_cache'), 
                        'clear_cache', 
                        '_nonce'
                    )); ?>" class="button button-secondary">
                        <?php esc_html_e('Limpiar Caché', 'wc-ai-homeopathic-chat'); ?>
                    </a>
                </p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('wc_ai_homeopathic_chat_settings'); ?>
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
                                   value="<?php echo esc_attr($this->api_key); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Introduce tu clave de API de DeepSeek.', 'wc-ai-homeopathic-chat'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wc_ai_homeopathic_chat_api_url">
                                <?php esc_html_e('API URL', 'wc-ai-homeopathic-chat'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="wc_ai_homeopathic_chat_api_url"
                                   name="wc_ai_homeopathic_chat_api_url" 
                                   value="<?php echo esc_url($this->api_url); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('URL del endpoint de la API.', 'wc-ai-homeopathic-chat'); ?>
                            </p>
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
                                       <?php checked($this->cache_enabled, true); ?> />
                                <?php esc_html_e('Almacenar respuestas en caché', 'wc-ai-homeopathic-chat'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Mejora el rendimiento almacenando respuestas.', 'wc-ai-homeopathic-chat'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        
        <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .stat-item {
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .stat-item strong {
            display: block;
            margin-bottom: 5px;
        }
        </style>
        <?php
        
        $this->handle_cache_clear();
    }
    
    private function handle_cache_clear() {
        if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && isset($_GET['_nonce'])) {
            if (wp_verify_nonce($_GET['_nonce'], 'clear_cache')) {
                $this->clear_all_cache();
                wp_redirect(admin_url('options-general.php?page=wc-ai-homeopathic-chat&cache_cleared=true'));
                exit;
            }
        }
    }
    
    public function clear_expired_cache() {
        // WordPress limpia automáticamente los transients expirados
    }
    
    public function clear_all_cache() {
        $cache_keys = get_option('wc_ai_homeopathic_chat_cache_keys', array());
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
        
        update_option('wc_ai_homeopathic_chat_cache_keys', array());
        update_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0, 'misses' => 0, 'total_requests' => 0
        ));
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e('WC AI Homeopathic Chat requiere que WooCommerce esté instalado y activado.', 'wc-ai-homeopathic-chat'); ?></p>
        </div>
        <?php
    }
}

// Inicializar el plugin
new WC_AI_Homeopathic_Chat();
