<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Un chat de inteligencia artificial para recomendaciones homeopáticas en WooCommerce.
 * Version: 1.4.1
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
define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '1.4.1');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);
define('WC_AI_HOMEOPATHIC_CHAT_MAX_RETRIES', 2);
define('WC_AI_HOMEOPATHIC_CHAT_TIMEOUT', 25);

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
            'error_text' => __('Error temporal del servicio. Por favor, intenta nuevamente en unos momentos.', 'wc-ai-homeopathic-chat'),
            'connection_error_text' => __('Problema de conexión. Verifica tu internet e intenta nuevamente.', 'wc-ai-homeopathic-chat'),
            'empty_message_text' => __('Por favor escribe un mensaje.', 'wc-ai-homeopathic-chat'),
            'api_error_text' => __('El servicio está temporalmente no disponible. Intenta en unos minutos.', 'wc-ai-homeopathic-chat')
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
            return '<p class="wc-ai-chat-error">' . esc_html__('El chat no está configurado correctamente.', 'wc-ai-homeopathic-chat') . '</p>';
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
                <div class="wc-ai-homeopathic-chat-actions">
                    <button type="button" class="wc-ai-homeopathic-chat-send"><?php esc_html_e('Enviar', 'wc-ai-homeopathic-chat'); ?></button>
                    <span class="wc-ai-homeopathic-chat-typing-indicator" style="display: none;">
                        <span class="typing-dots"></span>
                        <?php esc_html_e('Escribiendo...', 'wc-ai-homeopathic-chat'); ?>
                    </span>
                </div>
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
            
            // Verificar caché primero
            $cached_response = $this->get_cached_response($cache_key);
            if ($cached_response !== false) {
                wp_send_json_success(array(
                    'response' => $cached_response,
                    'from_cache' => true
                ));
            }
            
            // Obtener información de productos
            $products_info = $this->get_optimized_products_info();
            $prompt = $this->build_prompt($message, $products_info);
            
            // Intentar llamar a la API con reintentos
            $response = $this->call_deepseek_api_with_retry($prompt);
            
            if (is_wp_error($response)) {
                $this->log_api_error($response->get_error_message());
                throw new Exception($this->get_user_friendly_error($response));
            }
            
            // Validar y sanitizar respuesta
            $sanitized_response = $this->sanitize_api_response($response);
            
            // Almacenar en caché
            $this->cache_response($cache_key, $sanitized_response);
            
            wp_send_json_success(array(
                'response' => $sanitized_response,
                'from_cache' => false
            ));
            
        } catch (Exception $e) {
            $this->log_error($e->getMessage());
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
        
        if (empty(trim($message))) {
            throw new Exception(__('Por favor escribe un mensaje.', 'wc-ai-homeopathic-chat'));
        }
        
        if (strlen($message) > 500) {
            throw new Exception(__('El mensaje es demasiado largo. Máximo 500 caracteres.', 'wc-ai-homeopathic-chat'));
        }
        
        return $message;
    }
    
    private function call_deepseek_api_with_retry($prompt, $retry_count = 0) {
        for ($attempt = 1; $attempt <= WC_AI_HOMEOPATHIC_CHAT_MAX_RETRIES + 1; $attempt++) {
            $response = $this->call_deepseek_api($prompt, $attempt);
            
            if (!is_wp_error($response)) {
                return $response;
            }
            
            // Si es el último intento, devolver el error
            if ($attempt > WC_AI_HOMEOPATHIC_CHAT_MAX_RETRIES) {
                return $response;
            }
            
            // Esperar antes del reintento (backoff exponencial)
            $delay = pow(2, $attempt - 1) * 1000000; // microsegundos
            usleep($delay);
        }
        
        return new WP_Error('max_retries_exceeded', __('Número máximo de reintentos alcanzado.', 'wc-ai-homeopathic-chat'));
    }
    
    private function call_deepseek_api($prompt, $attempt = 1) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('La clave de API no está configurada.', 'wc-ai-homeopathic-chat'));
        }
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'User-Agent' => 'WC-AI-Homeopathic-Chat/1.4.1'
        );
        
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'Eres un homeópata experto. Proporciona recomendaciones basadas en los productos disponibles. Siempre aclara que no eres un sustituto de un profesional médico.'
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
            'timeout' => WC_AI_HOMEOPATHIC_CHAT_TIMEOUT,
            'redirection' => 3,
            'httpversion' => '1.1',
            'blocking' => true,
            'sslverify' => true
        );
        
        $start_time = microtime(true);
        $response = wp_remote_post($this->api_url, $args);
        $response_time = round((microtime(true) - $start_time) * 1000, 2);
        
        $this->log_api_call($attempt, $response_time, is_wp_error($response));
        
        if (is_wp_error($response)) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message();
            
            // Clasificar errores
            if (strpos($error_message, 'cURL error 28') !== false) {
                return new WP_Error('timeout', __('Timeout de conexión con el servicio.', 'wc-ai-homeopathic-chat'));
            } elseif (strpos($error_message, 'cURL error 6') !== false) {
                return new WP_Error('dns_error', __('Error de resolución DNS.', 'wc-ai-homeopathic-chat'));
            } elseif (strpos($error_message, 'cURL error 7') !== false) {
                return new WP_Error('connection_error', __('Error de conexión con el servidor.', 'wc-ai-homeopathic-chat'));
            }
            
            return new WP_Error('http_error', sprintf(__('Error HTTP: %s', 'wc-ai-homeopathic-chat'), $error_message));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $error_info = '';
            
            try {
                $error_data = json_decode($response_body, true);
                if (isset($error_data['error']['message'])) {
                    $error_info = $error_data['error']['message'];
                }
            } catch (Exception $e) {
                $error_info = $response_body;
            }
            
            switch ($response_code) {
                case 400:
                    return new WP_Error('bad_request', __('Solicitud incorrecta a la API.', 'wc-ai-homeopathic-chat'));
                case 401:
                    return new WP_Error('unauthorized', __('Clave de API inválida.', 'wc-ai-homeopathic-chat'));
                case 429:
                    return new WP_Error('rate_limit', __('Límite de tasa excedido. Intenta más tarde.', 'wc-ai-homeopathic-chat'));
                case 500:
                case 502:
                case 503:
                    return new WP_Error('server_error', __('Error temporal del servidor. Intenta nuevamente.', 'wc-ai-homeopathic-chat'));
                default:
                    return new WP_Error('api_error', sprintf(__('Error %d: %s', 'wc-ai-homeopathic-chat'), $response_code, $error_info));
            }
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('invalid_response', __('Respuesta inválida del servicio.', 'wc-ai-homeopathic-chat'));
        }
        
        return trim($data['choices'][0]['message']['content']);
    }
    
    private function get_user_friendly_error($wp_error) {
        $error_messages = array(
            'timeout' => __('El servicio está tardando más de lo esperado. Intenta nuevamente.', 'wc-ai-homeopathic-chat'),
            'connection_error' => __('Problema de conexión. Verifica tu internet.', 'wc-ai-homeopathic-chat'),
            'dns_error' => __('Error de conexión con el servicio.', 'wc-ai-homeopathic-chat'),
            'rate_limit' => __('Demasiadas solicitudes. Por favor, espera unos minutos.', 'wc-ai-homeopathic-chat'),
            'server_error' => __('El servicio está temporalmente no disponible.', 'wc-ai-homeopathic-chat'),
            'unauthorized' => __('Error de configuración del servicio.', 'wc-ai-homeopathic-chat'),
            'default' => __('Error temporal del servicio. Intenta nuevamente.', 'wc-ai-homeopathic-chat')
        );
        
        $error_code = $wp_error->get_error_code();
        return $error_messages[$error_code] ?? $error_messages['default'];
    }
    
    private function sanitize_api_response($response) {
        // Limpiar y formatear la respuesta
        $response = wp_kses_post($response);
        $response = trim($response);
        $response = force_balance_tags($response);
        
        // Asegurar que la respuesta no esté vacía
        if (empty($response)) {
            return __('Lo siento, no pude generar una respuesta. Por favor, intenta con otra pregunta.', 'wc-ai-homeopathic-chat');
        }
        
        return $response;
    }
    
    private function build_prompt($message, $products_info) {
        return "Eres un experto homeópata y asistente de tienda.

INVENTARIO DE LA TIENDA:
{$products_info}

SOLICITUD DEL USUARIO:
\"{$message}\"

INSTRUCCIONES:
- Analiza el inventario proporcionado
- Prioriza productos relevantes a la solicitud
- Proporciona recomendaciones educativas
- Sé claro que no eres un sustituto de profesional médico
- Mantén respuestas concisas y útiles

Responde en español de manera natural y profesional.";
    }
    
    // ... (resto de los métodos permanecen igual que en la versión anterior)
    
    private function get_optimized_products_info() {
        // Limitar la cantidad de productos para evitar timeouts
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 30, // Reducido para mejor performance
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC'
        );
        
        $products = get_posts($args);
        $products_info = "PRODUCTOS DESTACADOS:\n\n";
        
        if (!empty($products)) {
            foreach ($products as $product) {
                $product_info = $this->get_product_data($product);
                if ($product_info) {
                    $products_info .= $product_info . "---\n";
                }
            }
        }
        
        $total_products = wp_count_posts('product')->publish;
        $products_info .= "\nTotal de productos en tienda: {$total_products}";
        
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
        
        // Limitar longitud
        if (strlen($short_description) > 100) {
            $short_description = substr($short_description, 0, 97) . '...';
        }
        
        return "📦 {$title} | 💰 {$price}\n📝 {$short_description}";
    }
    
    private function log_api_call($attempt, $response_time, $is_error) {
        $log_entry = sprintf(
            "[%s] Intento %d - Tiempo: %dms - %s",
            current_time('mysql'),
            $attempt,
            $response_time,
            $is_error ? 'ERROR' : 'ÉXITO'
        );
        
        error_log("WC AI Chat: " . $log_entry);
    }
    
    private function log_api_error($error_message) {
        error_log("WC AI Chat Error: " . $error_message);
    }
    
    private function log_error($error_message) {
        error_log("WC AI Chat General Error: " . $error_message);
    }
    
    // ... (resto de los métodos de cache, settings, etc.)
    
    private function is_api_configured() {
        return !empty(trim($this->api_key));
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
        // ... (código de la página de opciones igual que antes)
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración del Chat Homeopático AI', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e('Estado del Sistema', 'wc-ai-homeopathic-chat'); ?></h2>
                <p><strong><?php esc_html_e('Estado de la API:', 'wc-ai-homeopathic-chat'); ?></strong> 
                   <span style="color: <?php echo $this->is_api_configured() ? 'green' : 'red'; ?>">
                   <?php echo $this->is_api_configured() ? '✅ Configurada' : '❌ No configurada'; ?>
                   </span>
                </p>
                <p><strong><?php esc_html_e('Consejos para mejor conexión:', 'wc-ai-homeopathic-chat'); ?></strong></p>
                <ul>
                    <li><?php esc_html_e('Verifica que tu clave de API sea válida', 'wc-ai-homeopathic-chat'); ?></li>
                    <li><?php esc_html_e('Asegura una conexión a internet estable', 'wc-ai-homeopathic-chat'); ?></li>
                    <li><?php esc_html_e('El sistema incluye reintentos automáticos', 'wc-ai-homeopathic-chat'); ?></li>
                </ul>
            </div>
            
            <!-- Resto del formulario de configuración -->
        </div>
        <?php
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