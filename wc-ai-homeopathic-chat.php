<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Un chat de inteligencia artificial para recomendaciones homeopáticas en WooCommerce.
 * Version: 1.2.0
 * Author: Esteban Rodríguez
 * Author URI: https://github.com/estratos
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-ai-homeopathic-chat
 * Domain Path: /languages
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '1.2.0');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS); // 30 días de caché

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
        $this->api_key = get_option('wc_ai_homeopathic_chat_api_key');
        $this->api_url = get_option('wc_ai_homeopathic_chat_api_url', 'https://api.deepseek.com/v1/chat/completions');
        $this->cache_enabled = get_option('wc_ai_homeopathic_chat_cache_enable', true);
        
        // Inicializar hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('woocommerce_after_single_product', array($this, 'display_chat_button'));
        
        // Shortcode para mostrar el chat
        add_shortcode('wc_ai_homeopathic_chat', array($this, 'chat_shortcode'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Hook para limpiar caché (opcional)
        add_action('wp_scheduled_delete', array($this, 'clear_expired_cache'));
    }
    
    public function enqueue_scripts() {
        if (is_product() || is_page() || is_single()) {
            wp_enqueue_style('wc-ai-homeopathic-chat-style', WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/css/chat-style.css', array(), WC_AI_HOMEOPATHIC_CHAT_VERSION);
            wp_enqueue_script('wc-ai-homeopathic-chat-script', WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/js/chat-script.js', array('jquery'), WC_AI_HOMEOPATHIC_CHAT_VERSION, true);
            
            wp_localize_script('wc-ai-homeopathic-chat-script', 'wc_ai_homeopathic_chat_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_ai_homeopathic_chat_nonce'),
                'loading_text' => __('Consultando recomendaciones...', 'wc-ai-homeopathic-chat'),
                'error_text' => __('Error al conectar con el servicio. Intenta nuevamente.', 'wc-ai-homeopathic-chat')
            ));
        }
    }
    
    public function display_chat_button() {
        echo '<div class="wc-ai-homeopathic-chat-container">';
        echo '<button id="wc-ai-homeopathic-chat-toggle" class="wc-ai-homeopathic-chat-toggle">';
        echo __('¿Necesitas asesoramiento homeopático?', 'wc-ai-homeopathic-chat');
        echo '</button>';
        echo $this->get_chat_interface();
        echo '</div>';
    }
    
    public function chat_shortcode($atts) {
        return $this->get_chat_interface();
    }
    
    private function get_chat_interface() {
        ob_start();
        ?>
        <div id="wc-ai-homeopathic-chat" class="wc-ai-homeopathic-chat" style="display: none;">
            <div class="wc-ai-homeopathic-chat-header">
                <h3><?php _e('Asesor Homeopático AI', 'wc-ai-homeopathic-chat'); ?></h3>
                <button class="wc-ai-homeopathic-chat-close">&times;</button>
            </div>
            <div class="wc-ai-homeopathic-chat-messages"></div>
            <div class="wc-ai-homeopathic-chat-input">
                <textarea placeholder="<?php _e('Escribe tus síntomas o preguntas aquí...', 'wc-ai-homeopathic-chat'); ?>" rows="2"></textarea>
                <button class="wc-ai-homeopathic-chat-send"><?php _e('Enviar', 'wc-ai-homeopathic-chat'); ?></button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function ajax_send_message() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_ai_homeopathic_chat_nonce')) {
            wp_send_json_error(__('Error de seguridad. Intenta recargar la página.', 'wc-ai-homeopathic-chat'));
        }
        
        // Sanitizar y validar entrada
        $message = sanitize_text_field($_POST['message']);
        
        if (empty($message)) {
            wp_send_json_error(__('Por favor escribe un mensaje.', 'wc-ai-homeopathic-chat'));
        }
        
        // Generar hash único para la consulta (para usar como clave de caché)
        $cache_key = 'wc_ai_chat_' . md5($message);
        
        // Intentar obtener respuesta desde caché
        $cached_response = $this->get_cached_response($cache_key);
        
        if ($cached_response !== false && $this->cache_enabled) {
            // Usar respuesta en caché
            wp_send_json_success(array(
                'response' => $cached_response,
                'from_cache' => true
            ));
        }
        
        // Obtener información del producto actual si está disponible
        $product_info = '';
        if (is_product()) {
            global $product;
            if ($product) {
                $product_info = " El usuario está viendo el producto: " . $product->get_name() . ". Descripción: " . strip_tags($product->get_short_description() ?: $product->get_description());
            }
        }
        
        // Preparar prompt para DeepSeek
        $prompt = "Eres un experto homeópata. Responde al usuario de manera profesional y ética, recordando siempre que eres un asistente virtual y no un sustituto de un profesional de la salud. 
        
        El usuario dice: {$message}. {$product_info}
        
        Proporciona recomendaciones homeopáticas generales basadas en los síntomas descritos, pero siempre aclara que es importante consultar con un profesional de la salud para un diagnóstico preciso. 
        Si hay un producto relacionado, puedes mencionarlo pero sin hacer afirmaciones médicas directas.";
        
        // Llamar a la API de DeepSeek
        $response = $this->call_deepseek_api($prompt);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        // Almacenar respuesta en caché
        if ($this->cache_enabled) {
            $this->cache_response($cache_key, $response);
        }
        
        wp_send_json_success(array(
            'response' => $response,
            'from_cache' => false
        ));
    }
    
    /**
     * Obtener respuesta desde caché
     */
    private function get_cached_response($key) {
        $cached = get_transient($key);
        
        if ($cached !== false) {
            // Registrar estadísticas de uso de caché
            $this->log_cache_hit();
            return $cached;
        }
        
        return false;
    }
    
    /**
     * Almacenar respuesta en caché
     */
    private function cache_response($key, $response) {
        set_transient($key, $response, WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME);
        
        // Registrar en la lista de claves de caché para poder gestionarlas después
        $cache_keys = get_option('wc_ai_homeopathic_chat_cache_keys', array());
        if (!in_array($key, $cache_keys)) {
            $cache_keys[] = $key;
            update_option('wc_ai_homeopathic_chat_cache_keys', $cache_keys);
        }
        
        // Registrar estadísticas
        $this->log_cache_miss();
    }
    
    /**
     * Registrar acierto de caché para estadísticas
     */
    private function log_cache_hit() {
        $stats = get_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0,
            'misses' => 0,
            'total_requests' => 0
        ));
        
        $stats['hits']++;
        $stats['total_requests']++;
        
        update_option('wc_ai_homeopathic_chat_cache_stats', $stats);
    }
    
    /**
     * Registrar fallo de caché para estadísticas
     */
    private function log_cache_miss() {
        $stats = get_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0,
            'misses' => 0,
            'total_requests' => 0
        ));
        
        $stats['misses']++;
        $stats['total_requests']++;
        
        update_option('wc_ai_homeopathic_chat_cache_stats', $stats);
    }
    
    /**
     * Limpiar caché expirado (ejecutado automáticamente por WordPress)
     */
    public function clear_expired_cache() {
        // WordPress limpia automáticamente los transients expirados
        // Este método es para lógica adicional si es necesaria
    }
    
    /**
     * Limpiar todo el caché manualmente (para usar en admin)
     */
    public function clear_all_cache() {
        $cache_keys = get_option('wc_ai_homeopathic_chat_cache_keys', array());
        
        foreach ($cache_keys as $key) {
            delete_transient($key);
        }
        
        update_option('wc_ai_homeopathic_chat_cache_keys', array());
        update_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0,
            'misses' => 0,
            'total_requests' => 0
        ));
    }
    
    /**
     * Llamar a la API de DeepSeek
     */
    private function call_deepseek_api($prompt) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('La clave de API de DeepSeek no está configurada.', 'wc-ai-homeopathic-chat'));
        }
        
        if (empty($this->api_url)) {
            return new WP_Error('no_api_url', __('La URL de API de DeepSeek no está configurada.', 'wc-ai-homeopathic-chat'));
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
                    'content' => 'Eres un homeópata experto que proporciona recomendaciones generales. Siempre aclaras que no eres un sustituto de un profesional médico y recomiendas consultar con un especialista.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 500,
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
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_message = __('Error en la API de DeepSeek: ', 'wc-ai-homeopathic-chat');
            if (isset($response_body['error']['message'])) {
                $error_message .= $response_body['error']['message'];
            } else {
                $error_message .= __('Código de error ', 'wc-ai-homeopathic-chat') . $response_code;
            }
            return new WP_Error('api_error', $error_message);
        }
        
        if (isset($response_body['choices'][0]['message']['content'])) {
            return trim($response_body['choices'][0]['message']['content']);
        }
        
        return new WP_Error('invalid_response', __('Respuesta inválida de la API de DeepSeek.', 'wc-ai-homeopathic-chat'));
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
        // Obtener estadísticas de caché
        $cache_stats = get_option('wc_ai_homeopathic_chat_cache_stats', array(
            'hits' => 0,
            'misses' => 0,
            'total_requests' => 0
        ));
        
        $cache_efficiency = $cache_stats['total_requests'] > 0 ? 
            round(($cache_stats['hits'] / $cache_stats['total_requests']) * 100, 2) : 0;
        
        // Obtener número de elementos en caché
        $cache_keys = get_option('wc_ai_homeopathic_chat_cache_keys', array());
        $cache_count = count($cache_keys);
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración del Chat Homeopático AI', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <?php if (isset($_GET['cache_cleared']) && $_GET['cache_cleared'] === 'true') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Caché limpiado correctamente.', 'wc-ai-homeopathic-chat'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('Estadísticas de Caché', 'wc-ai-homeopathic-chat'); ?></h2>
                <p>
                    <strong><?php _e('Total de solicitudes:', 'wc-ai-homeopathic-chat'); ?></strong> 
                    <?php echo $cache_stats['total_requests']; ?>
                </p>
                <p>
                    <strong><?php _e('Aciertos de caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                    <?php echo $cache_stats['hits']; ?>
                </p>
                <p>
                    <strong><?php _e('Fallos de caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                    <?php echo $cache_stats['misses']; ?>
                </p>
                <p>
                    <strong><?php _e('Eficiencia de caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                    <?php echo $cache_efficiency; ?>%
                </p>
                <p>
                    <strong><?php _e('Elementos en caché:', 'wc-ai-homeopathic-chat'); ?></strong> 
                    <?php echo $cache_count; ?>
                </p>
                
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=wc-ai-homeopathic-chat&action=clear_cache'), 'clear_cache', '_nonce'); ?>" class="button">
                        <?php _e('Limpiar Caché', 'wc-ai-homeopathic-chat'); ?>
                    </a>
                </p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_ai_homeopathic_chat_settings');
                do_settings_sections('wc_ai_homeopathic_chat_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('DeepSeek API Key', 'wc-ai-homeopathic-chat'); ?></th>
                        <td>
                            <input type="password" name="wc_ai_homeopathic_chat_api_key" value="<?php echo esc_attr(get_option('wc_ai_homeopathic_chat_api_key')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Introduce tu clave de API de DeepSeek para habilitar el chat.', 'wc-ai-homeopathic-chat'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('DeepSeek API URL', 'wc-ai-homeopathic-chat'); ?></th>
                        <td>
                            <input type="text" name="wc_ai_homeopathic_chat_api_url" value="<?php echo esc_attr(get_option('wc_ai_homeopathic_chat_api_url', 'https://api.deepseek.com/v1/chat/completions')); ?>" class="regular-text" />
                            <p class="description"><?php _e('URL del endpoint de la API de DeepSeek.', 'wc-ai-homeopathic-chat'); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Habilitar Caché', 'wc-ai-homeopathic-chat'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_ai_homeopathic_chat_cache_enable" value="1" <?php checked(get_option('wc_ai_homeopathic_chat_cache_enable', true), 1); ?> />
                                <?php _e('Almacenar respuestas en caché para mejorar el rendimiento', 'wc-ai-homeopathic-chat'); ?>
                            </label>
                            <p class="description"><?php _e('Las respuestas se almacenarán durante 30 días.', 'wc-ai-homeopathic-chat'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
        
        // Manejar la limpieza de caché
        if (isset($_GET['action']) && $_GET['action'] === 'clear_cache' && isset($_GET['_nonce'])) {
            if (wp_verify_nonce($_GET['_nonce'], 'clear_cache')) {
                $this->clear_all_cache();
                wp_redirect(admin_url('options-general.php?page=wc-ai-homeopathic-chat&cache_cleared=true'));
                exit;
            }
        }
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('WC AI Homeopathic Chat requiere que WooCommerce esté instalado y activado.', 'wc-ai-homeopathic-chat'); ?></p>
        </div>
        <?php
    }
}

// Inicializar el plugin
new WC_AI_Homeopathic_Chat();