<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Chatbot flotante para recomendaciones homeopáticas con WooCommerce.
 * Version: 3.0.0
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

// Verificar si WooCommerce está activo ANTES de definir constantes
add_action('plugins_loaded', 'wc_ai_homeopathic_chat_init');

function wc_ai_homeopathic_chat_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'wc_ai_homeopathic_chat_woocommerce_missing_notice');
        return;
    }
    
    // Definir constantes solo si WooCommerce está activo
    define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '3.0.0');
    define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
    define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);
    
    // Cargar clases auxiliares
    wc_ai_homeopathic_chat_load_classes();
    
    // Inicializar el plugin
    global $wc_ai_homeopathic_chat;
    $wc_ai_homeopathic_chat = new WC_AI_Homeopathic_Chat();
}

function wc_ai_homeopathic_chat_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><strong>WC AI Homeopathic Chat:</strong> Requiere WooCommerce para funcionar. Por favor, instala y activa WooCommerce primero.</p>
    </div>
    <?php
}

function wc_ai_homeopathic_chat_load_classes() {
    // Cargar todas las clases requeridas
    $required_classes = array(
        'class-solutions-methods.php'    => 'WC_AI_Chat_Solutions_Methods',
        'class-prompt-build.php'         => 'WC_AI_Chat_Prompt_Build',
        'class-chat-learning-engine.php' => 'WC_AI_Chat_Learning_Engine',
        'class-chat-solutions-db.php'    => 'WC_AI_Chat_Solutions_DB'
    );

    foreach ($required_classes as $file => $class_name) {
        $file_path = WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'includes/' . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

// =========================================================================
// CLASE PRINCIPAL - ARQUITECTURA MODULAR
// =========================================================================

class WC_AI_Homeopathic_Chat
{
    // Propiedades
    private $settings;
    private $padecimientos_humanos;
    private $productos_cache;
    
    // Instancias de clases auxiliares
    private $solutions_methods;
    private $prompt_build;
    private $learning_engine;
    private $solutions_db;

    public function __construct()
    {
        $this->initialize();
    }

    private function initialize()
    {
        $this->load_settings();
        $this->initialize_padecimientos_map();
        $this->initialize_classes();
        $this->initialize_hooks();
        $this->productos_cache = array();
        
        // Registrar hook para procesamiento de aprendizaje
        add_action('wc_ai_homeopathic_process_learning', array($this, 'handle_process_learning'), 10, 2);
    }

    // =========================================================================
    // MÉTODOS DE INICIALIZACIÓN
    // =========================================================================

    private function initialize_classes()
    {
        // Si las clases no existen, crear versiones básicas
        if (!class_exists('WC_AI_Solutions_Methods')) {
            require_once __DIR__ . '/includes/class-solutions-methods.php';
        }
        
        if (!class_exists('WC_AI_Prompt_Build')) {
            require_once __DIR__ . '/includes/class-prompt-build.php';
        }
        
        // Inicializar clases
        $this->solutions_methods = new WC_AI_Solutions_Methods($this->padecimientos_humanos);
        $this->prompt_build = new WC_AI_Prompt_Build();
        
        // Clases opcionales
        if (class_exists('WC_AI_Chat_Solutions_DB')) {
            $this->solutions_db = new WC_AI_Chat_Solutions_DB();
            
            if (class_exists('WC_AI_Chat_Learning_Engine')) {
                $this->learning_engine = new WC_AI_Chat_Learning_Engine($this->solutions_db);
            }
        }
    }

    private function load_settings()
    {
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
            'color_primary' => get_option('wc_ai_homeopathic_chat_color_primary', '#667eea'),
            'color_secondary' => get_option('wc_ai_homeopathic_chat_color_secondary', '#764ba2'),
            'color_scheme' => get_option('wc_ai_homeopathic_chat_color_scheme', 'homeopatico'),
            'enable_learning' => get_option('wc_ai_homeopathic_chat_enable_learning', false)
        );
    }

    private function initialize_padecimientos_map()
    {
        $this->padecimientos_humanos = array(
            "infecciosas" => array("gripe", "influenza", "resfriado", "covid", "coronavirus", "neumonía", "bronquitis", "tuberculosis", "hepatitis", "VIH", "sida", "herpes", "varicela", "sarampión", "paperas", "rubeola", "dengue", "malaria", "cólera"),
            "cardiovasculares" => array("hipertensión", "presión alta", "infarto", "ataque cardíaco", "arritmia", "insuficiencia cardíaca", "angina de pecho", "accidente cerebrovascular", "derrame cerebral", "trombosis", "varices", "arterioesclerosis"),
            "respiratorias" => array("asma", "alergia", "rinitis", "sinusitis", "epoc", "enfisema", "apnea del sueño", "tos crónica", "insuficiencia respiratoria"),
            "digestivas" => array("gastritis", "úlcera", "reflujo", "acidez", "colitis", "síndrome de intestino irritable", "estreñimiento", "diarrea", "hemorroides", "cirrosis", "hígado graso", "pancreatitis", "diverticulitis"),
            "neurologicas" => array("migraña", "dolor de cabeza", "cefalea", "epilepsia", "alzheimer", "parkinson", "esclerosis múltiple", "neuralgia", "neuropatía", "demencia"),
            "musculoesqueléticas" => array("artritis", "artrosis", "osteoporosis", "lumbalgia", "ciática", "fibromialgia", "tendinitis", "bursitis", "escoliosis", "hernia discal"),
            "endocrinas" => array("diabetes", "tiroides", "hipotiroidismo", "hipertiroidismo", "obesidad", "sobrepeso", "colesterol alto", "triglicéridos", "gota", "osteoporosis"),
            "mentales" => array("depresión", "ansiedad", "estrés", "ataque de pánico", "trastorno bipolar", "esquizofrenia", "TOC", "trastorno de estrés postraumático", "insomnio"),
            "cancer" => array("cáncer", "tumor", "leucemia", "linfoma", "melanoma", "cáncer de pulmón", "cáncer de mama", "cáncer de próstata", "cáncer de colon", "cáncer de piel"),
            "sintomas_generales" => array("fiebre", "dolor", "malestar", "fatiga", "cansancio", "debilidad", "mareo", "náuseas", "vómitos", "pérdida de peso", "aumento de peso", "inapetencia", "sed", "sudoración"),
            "sintomas_especificos" => array("dolor abdominal", "dolor torácico", "dolor articular", "dolor muscular", "tos", "estornudos", "congestión nasal", "dificultad para respirar", "palpitaciones", "hinchazón", "picazón", "erupción cutánea", "sangrado", "moretones"),
            "dermatologicas" => array("acné", "eczema", "psoriasis", "urticaria", "dermatitis", "rosácea", "vitíligo", "hongos", "micosis", "verrugas"),
            "oculares" => array("miopía", "astigmatismo", "presbicia", "cataratas", "glaucoma", "conjuntivitis", "ojo seco", "degeneración macular"),
            "auditivas" => array("sordera", "pérdida auditiva", "tinnitus", "acúfenos", "otitis", "infección de oído", "vértigo")
        );
    }

    private function initialize_hooks()
    {
        // Hooks principales
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_floating_chat'), 9999);
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        
        // Hooks de administración
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
        
        // Hooks para aprendizaje (si está habilitado)
        if ($this->settings['enable_learning'] && $this->learning_engine) {
            add_action('wc_ai_homeopathic_chat_after_response', array($this, 'process_learning'), 10, 2);
        }
    }

    // =========================================================================
    // MÉTODO AJAX PRINCIPAL (USANDO LAS CLASES SEPARADAS)
    // =========================================================================

    public function ajax_send_message()
    {
        try {
            // Validación
            $this->validate_ajax_request();
            $message = $this->sanitize_message();
            
            // Verificar que las clases estén inicializadas
            if (!$this->solutions_methods || !$this->prompt_build) {
                throw new Exception(__('Error interno: Sistema no inicializado correctamente.', 'wc-ai-homeopathic-chat'));
            }
            
            $cache_key = 'wc_ai_homeopathic_chat_' . md5($message);

            // Verificar caché
            $cached_response = $this->get_cached_response($cache_key);
            if ($cached_response !== false) {
                wp_send_json_success(array(
                    'response' => $cached_response,
                    'from_cache' => true
                ));
            }

            // ==============================================
            // PROCESAMIENTO USANDO CLASES SEPARADAS
            // ==============================================
            
            // 1. Análisis de síntomas
            $analysis = $this->solutions_methods->analizar_sintomas_mejorado($message);
            
            // 2. Detección de productos
            $productos_mencionados = $this->solutions_methods->detectar_productos_en_consulta($message);
            
            // 3. Estrategia de respuesta
            $mostrar_solo_productos_mencionados = $this->solutions_methods->debe_mostrar_solo_productos_mencionados($productos_mencionados, $message);
            $info_productos_mencionados = $this->solutions_methods->get_info_productos_mencionados($productos_mencionados);
            
            // 4. Búsqueda de productos relevantes
            $relevant_products = "";
            if (!$mostrar_solo_productos_mencionados) {
                $relevant_products = $this->solutions_methods->get_relevant_products_by_categories_mejorado(
                    $analysis['categorias_detectadas'],
                    $analysis['padecimientos_encontrados']
                );
            }

            // 5. Construir prompt
            $prompt = $this->prompt_build->build_prompt_mejorado(
                $message, 
                $analysis, 
                $relevant_products, 
                $info_productos_mencionados, 
                $productos_mencionados, 
                $mostrar_solo_productos_mencionados,
                'homeopatico' // Especialidad
            );
            
            // 6. Llamar a la API
            $response = $this->call_deepseek_api($prompt);

            // 7. Manejar errores
            if (is_wp_error($response)) {
                if (!empty($this->settings['whatsapp_number'])) {
                    wp_send_json_success(array(
                        'response' => $this->get_whatsapp_fallback_message($message),
                        'whatsapp_fallback' => true
                    ));
                } else {
                    throw new Exception($response->get_error_message());
                }
            }

            // 8. Procesar respuesta
            $sanitized_response = $this->sanitize_api_response($response);
            $this->cache_response($cache_key, $sanitized_response);

            // 9. Aprendizaje automático
            if ($this->settings['enable_learning'] && $this->learning_engine) {
                $this->trigger_learning($message, $sanitized_response);
            }

            // 10. Enviar respuesta final
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

    // =========================================================================
    // MÉTODOS DE APOYO
    // =========================================================================

    private function trigger_learning($user_message, $ai_response)
    {
        if (!$this->learning_engine) {
            return;
        }
        
        // Ejecutar aprendizaje de manera asíncrona
        wp_schedule_single_event(time() + 2, 'wc_ai_homeopathic_process_learning', array(
            'user_message' => $user_message,
            'ai_response' => $ai_response
        ));
    }

    public function handle_process_learning($user_message, $ai_response)
    {
        $this->process_learning($user_message, $ai_response);
    }

    public function process_learning($user_message, $ai_response)
    {
        if (!$this->settings['enable_learning'] || !$this->learning_engine) {
            return;
        }

        try {
            $this->learning_engine->analyze_conversation($user_message, $ai_response);
        } catch (Exception $e) {
            error_log("WC AI Homeopathic Chat - Error en aprendizaje: " . $e->getMessage());
        }
    }

    // =========================================================================
    // MÉTODOS DE VALIDACIÓN Y UTILIDAD
    // =========================================================================

    private function validate_ajax_request()
    {
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'wc_ai_homeopathic_chat_nonce')) {
            throw new Exception(__('Error de seguridad.', 'wc-ai-homeopathic-chat'));
        }
    }

    private function sanitize_message()
    {
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        if (empty(trim($message))) {
            throw new Exception(__('Por favor describe tus síntomas.', 'wc-ai-homeopathic-chat'));
        }
        return $message;
    }

    private function sanitize_api_response($response)
    {
        return wp_kses_post(trim($response));
    }

    private function get_cached_response($key)
    {
        if (!$this->settings['cache_enable']) {
            return false;
        }
        return get_transient($key);
    }

    private function cache_response($key, $response)
    {
        if ($this->settings['cache_enable']) {
            set_transient($key, $response, WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME);
        }
    }

    private function call_deepseek_api($prompt)
    {
        if (empty($this->settings['api_key'])) {
            return new WP_Error('no_api_key', __('API no configurada', 'wc-ai-homeopathic-chat'));
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
                    'content' => 'Eres un homeópata experto con amplio conocimiento en remedios homeopáticos, síntomas y tratamientos. Proporcionas recomendaciones precisas y prácticas basadas en el análisis de síntomas. Eres profesional pero accesible.'
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
            return new WP_Error('http_error', sprintf(__('Error %d', 'wc-ai-homeopathic-chat'), $response_code));
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($response_body['choices'][0]['message']['content'])) {
            return trim($response_body['choices'][0]['message']['content']);
        }

        return new WP_Error('invalid_response', __('Respuesta inválida', 'wc-ai-homeopathic-chat'));
    }

    private function get_whatsapp_fallback_message($user_message)
    {
        $whatsapp_url = $this->generate_whatsapp_url($user_message);
        return sprintf(
            __('Parece que hay un problema con nuestro sistema. ¿Te gustaría continuar la conversación por WhatsApp?<br><br><a href="%s" target="_blank" class="wc-ai-whatsapp-link">💬 Abrir WhatsApp</a>', 'wc-ai-homeopathic-chat'),
            esc_url($whatsapp_url)
        );
    }

    private function generate_whatsapp_url($message = '')
    {
        $base_message = $this->settings['whatsapp_message'];
        $full_message = $message ?
            $base_message . "\n\nMi consulta: " . $message :
            $base_message;

        $encoded_message = urlencode($full_message);
        $phone = preg_replace('/[^0-9]/', '', $this->settings['whatsapp_number']);
        return "https://wa.me/{$phone}?text={$encoded_message}";
    }

    // =========================================================================
    // MÉTODOS DE INTERFAZ DE USUARIO
    // =========================================================================

    public function enqueue_scripts()
    {
        if (!$this->should_display_chat()) {
            return;
        }

        wp_enqueue_style('wc-ai-homeopathic-chat-style', 
            WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/css/chat-style.css', 
            array(), 
            WC_AI_HOMEOPATHIC_CHAT_VERSION);
            
        wp_enqueue_script('wc-ai-homeopathic-chat-script', 
            WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/js/chat-script.js', 
            array('jquery'), 
            WC_AI_HOMEOPATHIC_CHAT_VERSION, 
            true);

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
            'api_configured' => !empty($this->settings['api_key']),
            'colors' => array(
                'primary' => $this->settings['color_primary'],
                'secondary' => $this->settings['color_secondary'],
                'scheme' => $this->settings['color_scheme']
            )
        ));
    }

    private function should_display_chat()
    {
        if (!$this->settings['enable_floating']) {
            return false;
        }

        if (is_front_page() || is_home()) {
            return true;
        }

        if (is_product()) {
            return $this->settings['show_on_products'];
        }

        if (is_page() || is_single()) {
            return $this->settings['show_on_pages'];
        }

        if (is_shop() || is_product_category()) {
            return true;
        }

        if (is_post_type_archive('product')) {
            return true;
        }

        return false;
    }

    public function display_floating_chat()
    {
        if (!$this->should_display_chat()) {
            return;
        }

        $position_class = 'wc-ai-chat-position-' . $this->settings['chat_position'];
        $whatsapp_available = !empty($this->settings['whatsapp_number']);
        ?>
        <div id="wc-ai-homeopathic-chat-container" class="<?php echo esc_attr($position_class); ?>" 
             data-color-primary="<?php echo esc_attr($this->settings['color_primary']); ?>"
             data-color-secondary="<?php echo esc_attr($this->settings['color_secondary']); ?>">
            <div id="wc-ai-chat-launcher" class="wc-ai-chat-launcher">
                <div class="wc-ai-chat-icon">
                    <img src="<?php echo esc_url(WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/image/ai-bot-doctor.png'); ?>"
                        alt="<?php esc_attr_e('Asistente Homeopático', 'wc-ai-homeopathic-chat'); ?>"
                        width="36"
                        height="36">
                </div>
                <div class="wc-ai-chat-pulse"></div>
            </div>

            <div id="wc-ai-chat-window" class="wc-ai-chat-window">
                <div class="wc-ai-chat-header">
                    <div class="wc-ai-chat-header-info">
                        <div class="wc-ai-chat-avatar">
                            <div class="wc-ai-chat-avatar-icon">
                                <img src="<?php echo esc_url(WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/image/ai-bot-doctor.png'); ?>" 
                                     alt="avatar homeopático" width="36" height="36">
                            </div>
                        </div>
                        <div class="wc-ai-chat-title">
                            <h4><?php esc_html_e('Asesor Homeopático', 'wc-ai-homeopathic-chat'); ?></h4>
                            <span class="wc-ai-chat-status"><?php esc_html_e('En línea', 'wc-ai-homeopathic-chat'); ?></span>
                        </div>
                    </div>
                    <div class="wc-ai-chat-actions">
                        <button type="button" id="wc-ai-chat-close-btn" class="wc-ai-chat-close-btn" 
                                aria-label="<?php esc_attr_e('Cerrar chat', 'wc-ai-homeopathic-chat'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
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

    // =========================================================================
    // MÉTODOS DE ADMINISTRACIÓN
    // =========================================================================

    public function register_settings()
    {
        // Configuración básica
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_api_key');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_api_url');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_cache_enable');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_position');
        
        // Configuración de WhatsApp
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_whatsapp');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_whatsapp_message');
        
        // Configuración de visualización
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_floating');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_products');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_pages');
        
        // Configuración de colores
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_color_primary');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_color_secondary');
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_color_scheme');
        
        // Configuración de aprendizaje
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_enable_learning');
    }

    public function add_admin_menu()
    {
        add_options_page(
            'WC AI Homeopathic Chat',
            'Homeopathic Chat',
            'manage_options',
            'wc-ai-homeopathic-chat',
            array($this, 'options_page')
        );
        
        // Solo agregar submenú si hay base de datos de soluciones
        if (class_exists('WC_AI_Chat_Solutions_DB')) {
            add_submenu_page(
                'options-general.php',
                'Soluciones Aprendidas',
                'Soluciones Chat',
                'manage_options',
                'wc-ai-homeopathic-solutions',
                array($this, 'solutions_page')
            );
        }
    }

    public function options_page()
    {
        $total_padecimientos = 0;
        foreach ($this->padecimientos_humanos as $categoria => $padecimientos) {
            $total_padecimientos += count($padecimientos);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración del Chat Homeopático', 'wc-ai-homeopathic-chat'); ?></h1>
            <div class="card">
                <h3><?php esc_html_e('Sistema de Análisis de Síntomas - VERSIÓN 3.0.0', 'wc-ai-homeopathic-chat'); ?></h3>
                <p><?php esc_html_e('Sistema especializado en productos homeopáticos con arquitectura modular.', 'wc-ai-homeopathic-chat'); ?></p>
                <p><strong><?php esc_html_e('Arquitectura:', 'wc-ai-homeopathic-chat'); ?></strong> 4 clases especializadas (Métodos, Prompt, Aprendizaje, DB)</p>
                <p><strong><?php esc_html_e('Categorías:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo count($this->padecimientos_humanos); ?> categorías con <?php echo $total_padecimientos; ?> padecimientos</p>
                <p><strong><?php esc_html_e('Detección:', 'wc-ai-homeopathic-chat'); ?></strong> 6 estrategias de búsqueda de productos</p>
                <p><strong><?php esc_html_e('Personalización:', 'wc-ai-homeopathic-chat'); ?></strong> Esquemas de color predefinidos</p>
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
                                    <td><label><input type="checkbox" name="wc_ai_homeopathic_chat_cache_enable" value="1" <?php checked($this->settings['cache_enable'], true); ?> /> <?php esc_html_e('Usar caché para mejor rendimiento', 'wc-ai-homeopathic-chat'); ?></label></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Apariencia y Colores', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Posición del Chat', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td><select name="wc_ai_homeopathic_chat_position">
                                            <option value="right" <?php selected($this->settings['chat_position'], 'right'); ?>><?php esc_html_e('Derecha', 'wc-ai-homeopathic-chat'); ?></option>
                                            <option value="left" <?php selected($this->settings['chat_position'], 'left'); ?>><?php esc_html_e('Izquierda', 'wc-ai-homeopathic-chat'); ?></option>
                                        </select></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Chat Flotante', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td><label><input type="checkbox" name="wc_ai_homeopathic_chat_floating" value="1" <?php checked($this->settings['enable_floating'], true); ?> /> <?php esc_html_e('Mostrar chat flotante', 'wc-ai-homeopathic-chat'); ?></label></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Mostrar en Productos', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td><label><input type="checkbox" name="wc_ai_homeopathic_chat_products" value="1" <?php checked($this->settings['show_on_products'], true); ?> /> <?php esc_html_e('Mostrar en páginas de producto', 'wc-ai-homeopathic-chat'); ?></label></td>
                                </tr>
                                
                                <!-- Configuración de Colores -->
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_color_primary"><?php esc_html_e('Color Primario', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td>
                                        <input type="color" id="wc_ai_homeopathic_chat_color_primary" name="wc_ai_homeopathic_chat_color_primary" value="<?php echo esc_attr($this->settings['color_primary']); ?>" />
                                        <input type="text" id="wc_ai_homeopathic_chat_color_primary_hex" name="wc_ai_homeopathic_chat_color_primary_hex" value="<?php echo esc_attr($this->settings['color_primary']); ?>" class="regular-text color-hex" style="width: 100px; font-family: monospace;" />
                                        <p class="description"><?php esc_html_e('Color principal para botones y gradientes', 'wc-ai-homeopathic-chat'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_color_secondary"><?php esc_html_e('Color Secundario', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td>
                                        <input type="color" id="wc_ai_homeopathic_chat_color_secondary" name="wc_ai_homeopathic_chat_color_secondary" value="<?php echo esc_attr($this->settings['color_secondary']); ?>" />
                                        <input type="text" id="wc_ai_homeopathic_chat_color_secondary_hex" name="wc_ai_homeopathic_chat_color_secondary_hex" value="<?php echo esc_attr($this->settings['color_secondary']); ?>" class="regular-text color-hex" style="width: 100px; font-family: monospace;" />
                                        <p class="description"><?php esc_html_e('Color secundario para gradientes y efectos', 'wc-ai-homeopathic-chat'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_color_scheme"><?php esc_html_e('Esquema de Color Predefinido', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td>
                                        <select id="wc_ai_homeopathic_chat_color_scheme" name="wc_ai_homeopathic_chat_color_scheme">
                                            <option value=""><?php esc_html_e('Personalizado', 'wc-ai-homeopathic-chat'); ?></option>
                                            <option value="homeopatico" <?php selected($this->settings['color_scheme'], 'homeopatico'); ?>><?php esc_html_e('Homeopático (Azul-Verde)', 'wc-ai-homeopathic-chat'); ?></option>
                                            <option value="natural" <?php selected($this->settings['color_scheme'], 'natural'); ?>><?php esc_html_e('Natural (Verde)', 'wc-ai-homeopathic-chat'); ?></option>
                                            <option value="tranquilo" <?php selected($this->settings['color_scheme'], 'tranquilo'); ?>><?php esc_html_e('Tranquilo (Azul)', 'wc-ai-homeopathic-chat'); ?></option>
                                            <option value="profesional" <?php selected($this->settings['color_scheme'], 'profesional'); ?>><?php esc_html_e('Profesional (Azul Oscuro)', 'wc-ai-homeopathic-chat'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Selecciona un esquema predefinido o usa colores personalizados', 'wc-ai-homeopathic-chat'); ?></p>
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
                                    <th scope="row"><?php esc_html_e('Habilitar Aprendizaje', 'wc-ai-homeopathic-chat'); ?></th>
                                    <td><label><input type="checkbox" name="wc_ai_homeopathic_chat_enable_learning" value="1" <?php checked($this->settings['enable_learning'], true); ?> /> <?php esc_html_e('Aprender automáticamente de las conversaciones', 'wc-ai-homeopathic-chat'); ?></label>
                                    <p class="description"><?php esc_html_e('El sistema mejorará sus recomendaciones con el tiempo', 'wc-ai-homeopathic-chat'); ?></p></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp"><?php esc_html_e('Número de WhatsApp', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td><input type="text" id="wc_ai_homeopathic_chat_whatsapp" name="wc_ai_homeopathic_chat_whatsapp" value="<?php echo esc_attr($this->settings['whatsapp_number']); ?>" class="regular-text" placeholder="+521234567890" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp_message"><?php esc_html_e('Mensaje Predeterminado', 'wc-ai-homeopathic-chat'); ?></label></th>
                                    <td><textarea id="wc_ai_homeopathic_chat_whatsapp_message" name="wc_ai_homeopathic_chat_whatsapp_message" class="large-text" rows="3"><?php echo esc_textarea($this->settings['whatsapp_message']); ?></textarea></td>
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
                margin: 20px 0
            }
            .card {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            input[type="color"] {
                width: 50px;
                height: 30px;
                vertical-align: middle;
                margin-right: 10px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
                cursor: pointer;
            }
            .color-hex {
                width: 100px !important;
                font-family: monospace;
                font-size: 13px;
            }
        </style>
        <script>
            // Presets de colores para homeopatía
            const colorPresets = {
                homeopatico: { primary: '#667eea', secondary: '#48bb78' },
                natural: { primary: '#38a169', secondary: '#2f855a' },
                tranquilo: { primary: '#3498db', secondary: '#2c3e50' },
                profesional: { primary: '#2c5282', secondary: '#2d3748' }
            };

            document.addEventListener('DOMContentLoaded', function() {
                const colorPrimary = document.getElementById('wc_ai_homeopathic_chat_color_primary');
                const colorPrimaryHex = document.getElementById('wc_ai_homeopathic_chat_color_primary_hex');
                const colorSecondary = document.getElementById('wc_ai_homeopathic_chat_color_secondary');
                const colorSecondaryHex = document.getElementById('wc_ai_homeopathic_chat_color_secondary_hex');
                const colorScheme = document.getElementById('wc_ai_homeopathic_chat_color_scheme');
                
                // Sincronizar primario
                colorPrimary.addEventListener('change', function() {
                    colorPrimaryHex.value = this.value;
                    colorScheme.value = '';
                });
                colorPrimaryHex.addEventListener('input', function() {
                    if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                        colorPrimary.value = this.value;
                        colorScheme.value = '';
                    }
                });
                
                // Sincronizar secundario
                colorSecondary.addEventListener('change', function() {
                    colorSecondaryHex.value = this.value;
                    colorScheme.value = '';
                });
                colorSecondaryHex.addEventListener('input', function() {
                    if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                        colorSecondary.value = this.value;
                        colorScheme.value = '';
                    }
                });
                
                // Aplicar preset
                colorScheme.addEventListener('change', function() {
                    const preset = this.value;
                    if (preset && colorPresets[preset]) {
                        colorPrimary.value = colorPresets[preset].primary;
                        colorSecondary.value = colorPresets[preset].secondary;
                        colorPrimaryHex.value = colorPresets[preset].primary;
                        colorSecondaryHex.value = colorPresets[preset].secondary;
                    }
                });
            });
        </script>
        <?php
    }

    public function solutions_page()
    {
        if (!class_exists('WC_AI_Chat_Solutions_DB')) {
            echo '<div class="notice notice-warning"><p>' . __('La base de datos de soluciones no está disponible.', 'wc-ai-homeopathic-chat') . '</p></div>';
            return;
        }
        
        $solutions_db = new WC_AI_Chat_Solutions_DB();
        $solutions = $solutions_db->get_all_solutions();
        $stats = $solutions_db->get_solution_stats();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Soluciones Homeopáticas Aprendidas', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <div class="card">
                <h3><?php esc_html_e('Base de Datos de Soluciones Homeopáticas', 'wc-ai-homeopathic-chat'); ?></h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                    <div class="stat-card">
                        <h4><?php esc_html_e('Total Soluciones', 'wc-ai-homeopathic-chat'); ?></h4>
                        <p class="stat-number"><?php echo $stats['total_solutions']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h4><?php esc_html_e('Relaciones', 'wc-ai-homeopathic-chat'); ?></h4>
                        <p class="stat-number"><?php echo $stats['total_relations']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h4><?php esc_html_e('Productos', 'wc-ai-homeopathic-chat'); ?></h4>
                        <p class="stat-number"><?php echo $stats['products_with_solutions']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h4><?php esc_html_e('Categorías', 'wc-ai-homeopathic-chat'); ?></h4>
                        <p class="stat-number"><?php echo $stats['categories']; ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (empty($solutions)): ?>
                <div class="card">
                    <p><?php esc_html_e('No hay soluciones guardadas todavía.', 'wc-ai-homeopathic-chat'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('import_default_solutions_homeopathic', 'import_nonce'); ?>
                        <button type="submit" name="import_default_solutions_homeopathic" class="button button-primary">
                            <?php esc_html_e('Importar Soluciones Predeterminadas', 'wc-ai-homeopathic-chat'); ?>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="card">
                    <h3><?php esc_html_e('Lista de Soluciones', 'wc-ai-homeopathic-chat'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Nombre', 'wc-ai-homeopathic-chat'); ?></th>
                                <th><?php esc_html_e('Categoría', 'wc-ai-homeopathic-chat'); ?></th>
                                <th><?php esc_html_e('Productos', 'wc-ai-homeopathic-chat'); ?></th>
                                <th><?php esc_html_e('Severidad', 'wc-ai-homeopathic-chat'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solutions as $solution): ?>
                                <tr>
                                    <td><?php echo esc_html($solution->solution_name); ?></td>
                                    <td><?php echo esc_html($solution->category); ?></td>
                                    <td><?php echo $solution->product_count; ?></td>
                                    <td>
                                        <span class="severity-badge severity-<?php echo $solution->severity; ?>">
                                            <?php echo ucfirst($solution->severity); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <style>
        .stat-card {
            background: #f8f9fa;
            border-left: 4px solid #48bb78;
            padding: 15px;
            border-radius: 4px;
        }
        .stat-card h4 {
            margin: 0 0 10px 0;
            color: #555;
            font-size: 14px;
            text-transform: uppercase;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #48bb78;
            margin: 0;
        }
        .severity-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .severity-critica { background: #dc3545; color: white; }
        .severity-alta { background: #fd7e14; color: white; }
        .severity-media { background: #ffc107; color: #333; }
        .severity-baja { background: #28a745; color: white; }
        </style>
        <?php
        
        if (isset($_POST['import_default_solutions_homeopathic']) && check_admin_referer('import_default_solutions_homeopathic', 'import_nonce')) {
            $imported = $solutions_db->import_default_solutions();
            if ($imported > 0) {
                echo '<div class="updated"><p>' . 
                    sprintf(__('Se importaron %d soluciones predeterminadas.', 'wc-ai-homeopathic-chat'), $imported) . 
                    '</p></div>';
                echo '<script>setTimeout(function(){ location.reload(); }, 1500);</script>';
            }
        }
    }

    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=wc-ai-homeopathic-chat') . '">' .
            __('Configuración', 'wc-ai-homeopathic-chat') . '</a>';
        array_unshift($links, $settings_link);
        
        if (class_exists('WC_AI_Chat_Solutions_DB')) {
            $solutions_link = '<a href="' . admin_url('options-general.php?page=wc-ai-homeopathic-solutions') . '">' .
                __('Soluciones', 'wc-ai-homeopathic-chat') . '</a>';
            array_unshift($links, $solutions_link);
        }
        
        return $links;
    }

    public function woocommerce_missing_notice()
    {
        ?>
        <div class="error">
            <p><?php esc_html_e('WC AI Homeopathic Chat requiere WooCommerce.', 'wc-ai-homeopathic-chat'); ?></p>
        </div>
        <?php
    }
}