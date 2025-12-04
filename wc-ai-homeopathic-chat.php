<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Chatbot flotante para recomendaciones homeopáticas con WooCommerce.
 * Version: 2.5.6
 * Author: Julio Rodríguez
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

// Definir constantes
define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '2.5.6');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);

// Incluir clases solo después de que WordPress esté cargado
add_action('plugins_loaded', 'wc_ai_homeopathic_chat_init');

function wc_ai_homeopathic_chat_init() {
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_ai_homeopathic_chat_woocommerce_missing_notice');
        return;
    }
    
    // Cargar las clases
    require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'includes/class-solutions-methods.php';
    require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'includes/class-prompt-build.php';
    require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'includes/class-symptoms-db.php';
    require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'includes/class-learning-engine.php';
    
    // Inicializar la clase principal
    new WC_AI_Homeopathic_Chat();
}

function wc_ai_homeopathic_chat_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WC AI Homeopathic Chat requiere WooCommerce para funcionar.', 'wc-ai-homeopathic-chat'); ?></p>
    </div>
    <?php
}

// Clase principal del plugin
class WC_AI_Homeopathic_Chat {
    
    private $settings;
    private $solutions_methods;
    private $prompt_build;
    private $symptoms_db;
    private $learning_engine;
    private $productos_cache;
    
    public function __construct() {
        // Inicializar clases auxiliares
        $this->solutions_methods = new WC_AI_Chat_Solutions_Methods();
        $this->prompt_build = new WC_AI_Chat_Prompt_Build();
        $this->symptoms_db = new WC_AI_Chat_Symptoms_DB();
        $this->learning_engine = new WC_AI_Chat_Learning_Engine($this->symptoms_db);
        
        // Configurar hooks
        $this->initialize_hooks();
        
        // Inicializar propiedades
        $this->productos_cache = array();
        
        // Registrar hooks de activación/desactivación
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
    }
    
    public function activate_plugin() {
        // Crear tablas de la base de datos
        $this->symptoms_db->create_tables();
        $this->learning_engine->create_tables();
        
        // Programar tareas cron si no existen
        if (!wp_next_scheduled('wc_ai_chat_auto_approve_suggestions')) {
            wp_schedule_event(time(), 'twicedaily', 'wc_ai_chat_auto_approve_suggestions');
        }
    }
    
    public function deactivate_plugin() {
        // Limpiar tareas cron
        wp_clear_scheduled_hook('wc_ai_chat_auto_approve_suggestions');
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
            'show_on_pages' => get_option('wc_ai_homeopathic_chat_pages', false),
            'enable_learning' => get_option('wc_ai_homeopathic_chat_learning', true)
        );
    }
    
    private function initialize_hooks() {
        add_action('init', array($this, 'load_settings'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_floating_chat'));
        
        // AJAX handlers
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        
        // Admin hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        
        // Cron hooks para aprendizaje
        add_action('wc_ai_chat_auto_approve_suggestions', array($this->learning_engine, 'auto_approve_high_confidence_suggestions'));
    }
    
    public function ajax_send_message() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_ai_homeopathic_chat_nonce')) {
            wp_send_json_error('Error de seguridad.');
        }
        
        // Sanitizar mensaje
        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty(trim($message))) {
            wp_send_json_error('Por favor describe tus síntomas.');
        }
        
        try {
            $cache_key = 'wc_ai_chat_' . md5($message);
            
            // Verificar caché
            if ($this->settings['cache_enable']) {
                $cached_response = get_transient($cache_key);
                if ($cached_response !== false) {
                    wp_send_json_success(array(
                        'response' => $cached_response,
                        'from_cache' => true
                    ));
                }
            }
            
            // Analizar síntomas
            $analysis = $this->solutions_methods->analizar_sintomas_mejorado($message);
            
            // Detectar productos mencionados
            $productos_mencionados = $this->detectar_productos_en_consulta($message);
            
            // Determinar si mostrar solo productos mencionados
            $mostrar_solo_productos_mencionados = $this->prompt_build->debe_mostrar_solo_productos_mencionados($productos_mencionados, $message);
            $info_productos_mencionados = $this->prompt_build->get_info_productos_mencionados($productos_mencionados);
            
            // Obtener productos relevantes
            $relevant_products = "";
            if (!$mostrar_solo_productos_mencionados) {
                $relevant_products = $this->solutions_methods->get_relevant_products_by_categories_mejorado(
                    $analysis['categorias_detectadas'], 
                    $analysis['padecimientos_encontrados']
                );
            }
            
            // Construir prompt
            $prompt = $this->prompt_build->build_prompt_mejorado(
                $message, 
                $analysis, 
                $relevant_products, 
                $info_productos_mencionados, 
                $productos_mencionados, 
                $mostrar_solo_productos_mencionados
            );
            
            // Llamar a la API
            $response = $this->call_deepseek_api($prompt);
            
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
            
            $sanitized_response = wp_kses_post(trim($response));
            
            // Guardar en caché
            if ($this->settings['cache_enable']) {
                set_transient($cache_key, $sanitized_response, WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME);
            }
            
            // Aprendizaje automático
            if ($this->settings['enable_learning']) {
                $this->learning_engine->analyze_conversation($message, $sanitized_response);
            }
            
            wp_send_json_success(array(
                'response' => $sanitized_response,
                'from_cache' => false,
                'analysis' => $analysis,
                'productos_mencionados' => $productos_mencionados,
                'mostrar_solo_productos_mencionados' => $mostrar_solo_productos_mencionados
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function call_deepseek_api($prompt) {
        if (empty($this->settings['api_key'])) {
            return new WP_Error('no_api_key', 'API no configurada');
        }
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->settings['api_key']
        );
        
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'system', 
                    'content' => 'Eres un homeópata experto con amplio conocimiento de remedios homeopáticos. Proporciona recomendaciones precisas y prácticas basadas en el análisis de síntomas. Sé empático pero mantén el profesionalismo.'
                ),
                array(
                    'role' => 'user', 
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7,
            'max_tokens' => 1000
        );
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        );
        
        $response = wp_remote_post($this->settings['api_url'], $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('http_error', 'Error ' . $response_code);
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['choices'][0]['message']['content'])) {
            return trim($response_body['choices'][0]['message']['content']);
        }
        
        return new WP_Error('invalid_response', 'Respuesta inválida de la API');
    }
    
    private function detectar_productos_en_consulta($message) {
        $productos_detectados = array();
        $message_normalized = $this->solutions_methods->normalizar_texto($message);
        
        // Obtener todos los productos
        $all_products = $this->get_all_store_products();
        
        foreach ($all_products as $product) {
            $product_name = $this->solutions_methods->normalizar_texto($product->get_name());
            $product_sku = $this->solutions_methods->normalizar_texto($product->get_sku());
            
            // Estrategia 1: Búsqueda por nombre exacto
            if ($this->buscar_coincidencia_exacta($message_normalized, $product_name)) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'nombre_exacto',
                    'confianza' => 1.0
                );
                continue;
            }
            
            // Estrategia 2: Búsqueda por SKU exacto
            if (!empty($product_sku) && $this->buscar_coincidencia_exacta($message_normalized, $product_sku)) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'sku_exacto',
                    'confianza' => 1.0
                );
                continue;
            }
            
            // Estrategia 3: Búsqueda por palabras clave
            $keywords_result = $this->buscar_por_palabras_clave_mejorado($message_normalized, $product_name, $product->get_name());
            if ($keywords_result['encontrado'] && $keywords_result['confianza'] >= 0.7) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'palabras_principales',
                    'confianza' => $keywords_result['confianza']
                );
            }
        }
        
        // Ordenar por confianza
        usort($productos_detectados, function($a, $b) {
            return $b['confianza'] <=> $a['confianza'];
        });
        
        // Eliminar duplicados
        $unique_products = array();
        foreach ($productos_detectados as $producto) {
            $product_id = $producto['product']->get_id();
            if (!isset($unique_products[$product_id]) || $unique_products[$product_id]['confianza'] < $producto['confianza']) {
                $unique_products[$product_id] = $producto;
            }
        }
        
        return array_slice(array_values($unique_products), 0, 5);
    }
    
    private function buscar_coincidencia_exacta($texto, $busqueda) {
        if (preg_match('/\b' . preg_quote($busqueda, '/') . '\b/i', $texto)) {
            return true;
        }
        
        $texto_sin_espacios = preg_replace('/\s+/', '', $texto);
        $busqueda_sin_espacios = preg_replace('/\s+/', '', $busqueda);
        
        return strpos($texto_sin_espacios, $busqueda_sin_espacios) !== false;
    }
    
    private function buscar_por_palabras_clave_mejorado($texto, $nombre_producto, $nombre_original) {
        $resultado = array('encontrado' => false, 'confianza' => 0.0);
        
        $palabras_nombre = array_filter(explode(' ', $nombre_producto), function($palabra) {
            return strlen($palabra) > 2 && !in_array($palabra, array('y', 'de', 'para', 'con', 'sin', 'los', 'las', 'el', 'la'));
        });
        
        $palabras_texto = array_filter(explode(' ', $texto), function($palabra) {
            return strlen($palabra) > 2;
        });
        
        if (empty($palabras_nombre)) {
            return $resultado;
        }
        
        $coincidencias = 0;
        foreach ($palabras_nombre as $palabra) {
            foreach ($palabras_texto as $palabra_texto) {
                $similitud = 0;
                similar_text($palabra, $palabra_texto, $similitud);
                
                if ($similitud >= 80) {
                    $coincidencias++;
                    break;
                }
            }
        }
        
        $confianza_final = $coincidencias / count($palabras_nombre);
        
        if ($confianza_final >= 0.5) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = $confianza_final;
        }
        
        return $resultado;
    }
    
    private function get_all_store_products() {
        if (!empty($this->productos_cache)) {
            return $this->productos_cache;
        }
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        );
        
        $product_ids = get_posts($args);
        $products = array();
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_visible()) {
                $products[] = $product;
            }
        }
        
        $this->productos_cache = $products;
        return $products;
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
            'loading_text' => __('Analizando tus síntomas...', 'wc-ai-homeopathic-chat'),
            'error_text' => __('Error temporal. ¿Deseas continuar por WhatsApp?', 'wc-ai-homeopathic-chat'),
            'empty_message_text' => __('Por favor describe tus síntomas.', 'wc-ai-homeopathic-chat'),
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
            <div id="wc-ai-chat-launcher" class="wc-ai-chat-launcher">
                <div class="wc-ai-chat-icon">💬</div>
                <div class="wc-ai-chat-pulse"></div>
            </div>
            
            <div id="wc-ai-chat-window" class="wc-ai-chat-window">
                <div class="wc-ai-chat-header">
                    <div class="wc-ai-chat-header-info">
                        <div class="wc-ai-chat-avatar">
                            <img src="<?php echo esc_url(WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/image/ai-bot-doctor.png'); ?>" alt="ai avatar" width="36" height="36">
                        </div>
                        <div class="wc-ai-chat-title">
                            <h4><?php esc_html_e('Asesor Homeopático', 'wc-ai-homeopathic-chat'); ?></h4>
                            <span class="wc-ai-chat-status"><?php esc_html_e('En línea', 'wc-ai-homeopathic-chat'); ?></span>
                        </div>
                    </div>
                    <div class="wc-ai-chat-actions">
                        <button type="button" class="wc-ai-chat-minimize" aria-label="<?php esc_attr_e('Minimizar chat', 'wc-ai-homeopathic-chat'); ?>">
                            <svg class="wc-ai-icon-svg" viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M20 14H4v-4h16"/>
                            </svg>
                        </button>
                        <button type="button" class="wc-ai-chat-close" aria-label="<?php esc_attr_e('Cerrar chat', 'wc-ai-homeopathic-chat'); ?>">
                            <svg class="wc-ai-icon-svg" viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="wc-ai-chat-messages">
                    <div class="wc-ai-chat-message bot">
                        <div class="wc-ai-message-content">
                            <?php esc_html_e('¡Hola! Soy tu asesor homeopático. Describe tus síntomas o padecimientos y te recomendaré los productos más adecuados.', 'wc-ai-homeopathic-chat'); ?>
                        </div>
                        <div class="wc-ai-message-time"><?php echo current_time('H:i'); ?></div>
                    </div>
                </div>
                
                <div class="wc-ai-chat-input-container">
                    <div class="wc-ai-chat-input">
                        <textarea placeholder="<?php esc_attr_e('Ej: Tengo dolor de cabeza, estrés y problemas digestivos...', 'wc-ai-homeopathic-chat'); ?>" rows="1" maxlength="500"></textarea>
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
    
    private function get_whatsapp_fallback_message($user_message) {
        $whatsapp_url = $this->generate_whatsapp_url($user_message);
        return sprintf(
            __('Parece que hay un problema con nuestro sistema. ¿Te gustaría continuar la conversación por WhatsApp?<br><br><a href="%s" target="_blank" class="wc-ai-whatsapp-link">💬 Abrir WhatsApp</a>', 'wc-ai-homeopathic-chat'),
            esc_url($whatsapp_url)
        );
    }
    
    private function generate_whatsapp_url($message = '') {
        $base_message = $this->settings['whatsapp_message'];
        $full_message = $message ? 
            $base_message . "\n\nMi consulta: " . $message : 
            $base_message;
        
        $encoded_message = urlencode($full_message);
        $phone = preg_replace('/[^0-9]/', '', $this->settings['whatsapp_number']);
        
        return "https://wa.me/{$phone}?text={$encoded_message}";
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
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_learning');
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
        $padecimientos_humanos = $this->solutions_methods->get_padecimientos_map();
        $total_padecimientos = 0;
        foreach ($padecimientos_humanos as $categoria => $padecimientos) {
            $total_padecimientos += count($padecimientos);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración del Chat Homeopático', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <div class="card">
                <h3><?php esc_html_e('Sistema de Análisis de Síntomas - VERSIÓN 2.5.3', 'wc-ai-homeopathic-chat'); ?></h3>
                <p><?php esc_html_e('Sistema mejorado con detección avanzada de productos homeopáticos y respuestas enfocadas.', 'wc-ai-homeopathic-chat'); ?></p>
                <p><strong><?php esc_html_e('Padecimientos configurados:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo $total_padecimientos; ?> en <?php echo count($padecimientos_humanos); ?> categorías</p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('wc_ai_homeopathic_chat_settings'); ?>
                <div class="wc-ai-chat-settings-grid">
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Configuración de API', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_api_key"><?php esc_html_e('DeepSeek API Key', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td><input type="password" id="wc_ai_homeopathic_chat_api_key" name="wc_ai_homeopathic_chat_api_key" value="<?php echo esc_attr($this->settings['api_key']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_api_url"><?php esc_html_e('URL de API', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td><input type="url" id="wc_ai_homeopathic_chat_api_url" name="wc_ai_homeopathic_chat_api_url" value="<?php echo esc_attr($this->settings['api_url']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Habilitar Caché', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wc_ai_homeopathic_chat_cache_enable" value="1" <?php checked($this->settings['cache_enable'], true); ?> />
                                            <?php esc_html_e('Usar caché para mejor rendimiento', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Apariencia', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Posición del Chat', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td>
                                        <select name="wc_ai_homeopathic_chat_position">
                                            <option value="right" <?php selected($this->settings['chat_position'], 'right'); ?>><?php esc_html_e('Derecha', 'wc-ai-homeopathic-chat'); ?></option>
                                            <option value="left" <?php selected($this->settings['chat_position'], 'left'); ?>><?php esc_html_e('Izquierda', 'wc-ai-homeopathic-chat'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Chat Flotante', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wc_ai_homeopathic_chat_floating" value="1" <?php checked($this->settings['enable_floating'], true); ?> />
                                            <?php esc_html_e('Mostrar chat flotante', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Mostrar en Productos', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wc_ai_homeopathic_chat_products" value="1" <?php checked($this->settings['show_on_products'], true); ?> />
                                            <?php esc_html_e('Mostrar en páginas de producto', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Configuración Avanzada', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp"><?php esc_html_e('Número de WhatsApp', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td><input type="text" id="wc_ai_homeopathic_chat_whatsapp" name="wc_ai_homeopathic_chat_whatsapp" value="<?php echo esc_attr($this->settings['whatsapp_number']); ?>" class="regular-text" placeholder="+521234567890" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp_message"><?php esc_html_e('Mensaje Predeterminado', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td><textarea id="wc_ai_homeopathic_chat_whatsapp_message" name="wc_ai_homeopathic_chat_whatsapp_message" class="large-text" rows="3"><?php echo esc_textarea($this->settings['whatsapp_message']); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Aprendizaje Automático', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wc_ai_homeopathic_chat_learning" value="1" <?php checked($this->settings['enable_learning'], true); ?> />
                                            <?php esc_html_e('Habilitar aprendizaje automático', 'wc-ai-homeopathic-chat'); ?>
                                        </label>
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
        .card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        </style>
        <?php
    }
    
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=wc-ai-homeopathic-chat') . '">' . 
                        __('Configuración', 'wc-ai-homeopathic-chat') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}