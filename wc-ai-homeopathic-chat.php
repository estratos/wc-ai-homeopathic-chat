<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Chatbot flotante para recomendaciones homeopáticas con WooCommerce y sistema de aprendizaje automático.
 * Version: 2.1.0
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

define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '2.1.0');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);
define('WC_AI_HOMEOPATHIC_CHAT_MAX_RETRIES', 2);
define('WC_AI_HOMEOPATHIC_CHAT_TIMEOUT', 25);

// Incluir clases
require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'includes/class-symptoms-db.php';
require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'includes/class-learning-engine.php';

class WC_AI_Homeopathic_Chat {
    
    private $settings;
    private $symptoms_db;
    private $learning_engine;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function activate() {
        $this->create_symptoms_tables();
        $this->create_learning_tables();
        $this->import_base_symptoms();
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->symptoms_db = new WC_AI_Chat_Symptoms_DB();
        $this->learning_engine = new WC_AI_Chat_Learning_Engine($this->symptoms_db);
        $this->load_settings();
        $this->initialize_hooks();
        
        // Programar tarea de auto-aprendizaje
        add_action('wc_ai_chat_auto_learn', array($this, 'auto_learn_process'));
        if (!wp_next_scheduled('wc_ai_chat_auto_learn')) {
            wp_schedule_event(time(), 'hourly', 'wc_ai_chat_auto_learn');
        }
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
            'system_prompt' => get_option('wc_ai_homeopathic_chat_system_prompt', $this->get_default_system_prompt())
        );
    }
    
    private function initialize_hooks() {
        // Frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_floating_chat'));
        
        // AJAX
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_wc_ai_chat_search_symptoms', array($this, 'ajax_search_symptoms'));
        add_action('wp_ajax_wc_ai_chat_import_base_symptoms', array($this, 'ajax_import_base_symptoms'));
        add_action('wp_ajax_wc_ai_chat_process_suggestion', array($this, 'ajax_process_suggestion'));
        
        // Admin
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'add_symptoms_admin_page'));
        add_action('admin_menu', array($this, 'add_learning_admin_page'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
    }
    
    public function create_symptoms_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_symptoms = $wpdb->prefix . 'wc_ai_chat_symptoms';
        $table_symptom_products = $wpdb->prefix . 'wc_ai_chat_symptom_products';
        
        $sql_symptoms = "CREATE TABLE $table_symptoms (
            symptom_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            symptom_name VARCHAR(255) NOT NULL,
            symptom_description TEXT,
            synonyms TEXT,
            severity ENUM('leve', 'moderado', 'grave') DEFAULT 'leve',
            category VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (symptom_id),
            UNIQUE KEY symptom_name (symptom_name),
            KEY category (category),
            KEY severity (severity)
        ) $charset_collate;";
        
        $sql_symptom_products = "CREATE TABLE $table_symptom_products (
            relation_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            symptom_id BIGINT(20) NOT NULL,
            product_id BIGINT(20) NOT NULL,
            relevance_score INT(3) DEFAULT 10,
            dosage_recommendation TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (relation_id),
            UNIQUE KEY symptom_product (symptom_id, product_id),
            KEY symptom_id (symptom_id),
            KEY product_id (product_id),
            KEY relevance_score (relevance_score)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_symptoms);
        dbDelta($sql_symptom_products);
    }
    
    public function create_learning_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_learning_suggestions = $wpdb->prefix . 'wc_ai_chat_learning_suggestions';
        
        $sql_learning = "CREATE TABLE $table_learning_suggestions (
            suggestion_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_message TEXT NOT NULL,
            ai_response TEXT NOT NULL,
            detected_symptoms TEXT,
            detected_products TEXT,
            confidence_score FLOAT DEFAULT 0,
            status ENUM('pending', 'approved', 'rejected', 'auto_approved') DEFAULT 'pending',
            admin_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            reviewed_by BIGINT(20),
            PRIMARY KEY (suggestion_id),
            KEY status (status),
            KEY confidence_score (confidence_score),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_learning);
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
                            <span class="wc-ai-chat-status">● <?php esc_html_e('En línea', 'wc-ai-homeopathic-chat'); ?></span>
                        </div>
                    </div>
                    <div class="wc-ai-chat-actions">
                        <button type="button" class="wc-ai-chat-minimize" aria-label="<?php esc_attr_e('Minimizar chat', 'wc-ai-homeopathic-chat'); ?>">
                            <svg class="wc-ai-icon-svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="3" y="11" width="18" height="2" rx="1"/>
                            </svg>
                        </button>
                        <button type="button" class="wc-ai-chat-close" aria-label="<?php esc_attr_e('Cerrar chat', 'wc-ai-homeopathic-chat'); ?>">
                            <svg class="wc-ai-icon-svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="wc-ai-chat-messages">
                    <div class="wc-ai-chat-message bot">
                        <div class="wc-ai-message-content">
                            <?php esc_html_e('¡Hola! Soy tu asesor homeopático. ¿En qué puedo ayudarte hoy?', 'wc-ai-homeopathic-chat'); ?>
                        </div>
                        <div class="wc-ai-message-time">🕒 <?php echo current_time('H:i'); ?></div>
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
            
            // Procesar mensaje
            $products_info = $this->get_optimized_products_info($message);
            $prompt = $this->build_prompt($message, $products_info);
            $response = $this->call_deepseek_api_with_retry($prompt);
            
            if (is_wp_error($response)) {
                if (!empty($this->settings['whatsapp_number'])) {
                    $response_data = array(
                        'response' => $this->get_whatsapp_fallback_message($message),
                        'whatsapp_fallback' => true
                    );
                } else {
                    throw new Exception($response->get_error_message());
                }
            } else {
                $sanitized_response = $this->sanitize_api_response($response);
                $this->cache_response($cache_key, $sanitized_response);
                
                $response_data = array(
                    'response' => $sanitized_response,
                    'from_cache' => false
                );
                
                // ANALIZAR CONVERSACIÓN PARA APRENDIZAJE
                $this->learning_engine->analyze_conversation($message, $sanitized_response);
            }
            
            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function get_optimized_products_info($user_message = '') {
        // Si hay un mensaje del usuario, buscar síntomas relacionados
        if (!empty($user_message)) {
            $relevant_products = $this->get_relevant_products_for_message($user_message);
            if (!empty($relevant_products)) {
                return $this->format_relevant_products_info($relevant_products, $user_message);
            }
        }
        
        // Fallback: obtener todos los productos
        return $this->get_all_products_info();
    }
    
    private function get_relevant_products_for_message($message) {
        $symptoms = $this->symptoms_db->search_symptoms($message, 5);
        
        $relevant_products = array();
        foreach ($symptoms as $symptom) {
            $products = $this->symptoms_db->get_products_for_symptom($symptom->symptom_id);
            foreach ($products as $product) {
                $product_id = $product->ID;
                if (!isset($relevant_products[$product_id])) {
                    $relevant_products[$product_id] = array(
                        'product' => wc_get_product($product_id),
                        'symptoms' => array(),
                        'relevance_score' => 0
                    );
                }
                $relevant_products[$product_id]['symptoms'][] = $symptom->symptom_name;
                $relevant_products[$product_id]['relevance_score'] += $product->relevance_score;
            }
        }
        
        // Ordenar por relevancia
        usort($relevant_products, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });
        
        return array_slice($relevant_products, 0, 15);
    }
    
    private function format_relevant_products_info($relevant_products, $user_message) {
        $info = "🔍 PRODUCTOS ALTAMENTE RECOMENDADOS para: \"{$user_message}\"\n\n";
        
        foreach ($relevant_products as $item) {
            $product = $item['product'];
            $symptoms = implode(', ', $item['symptoms']);
            $price = $product->get_price_html();
            $description = $this->clean_description($product->get_short_description());
            
            $info .= "⭐ " . strtoupper($product->get_name()) . "\n";
            $info .= "💰 Precio: " . $price . "\n";
            $info .= "🎯 Indicado para: " . $symptoms . "\n";
            
            if (!empty($description)) {
                $info .= "📝 " . $description . "\n";
            }
            
            $info .= "🔗 " . get_permalink($product->get_id()) . "\n";
            $info .= "---\n\n";
        }
        
        $info .= "💡 Estos productos han sido seleccionados específicamente para tus síntomas.\n";
        
        return $info;
    }
    
    private function get_all_products_info() {
        // Usar transients para cachear la información de productos por 1 hora
        $cached_info = get_transient('wc_ai_chat_products_info');
        
        if ($cached_info !== false) {
            return $cached_info;
        }

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids'
        );

        $product_ids = get_posts($args);
        $info = "CATÁLOGO COMPLETO DE PRODUCTOS HOMEOPÁTICOS:\n\n";
        $product_count = 0;

        foreach ($product_ids as $product_id) {
            $product_obj = wc_get_product($product_id);
            
            if ($product_obj && $product_obj->is_visible() && $product_obj->is_purchasable()) {
                $product_count++;
                
                $product_name = $product_obj->get_name();
                $price = $product_obj->get_price_html();
                $description = $product_obj->get_short_description() ?: $product_obj->get_description();
                $categories = $this->get_product_categories($product_obj);
                
                $clean_description = $this->clean_description($description);
                
                $info .= "PRODUCTO #{$product_count}: " . strtoupper($product_name) . "\n";
                $info .= "💰 Precio: " . $price . "\n";
                $info .= "📂 Categoría: " . $categories . "\n";
                
                if (!empty($clean_description)) {
                    $info .= "📝 Descripción: " . $clean_description . "\n";
                }
                
                $info .= "🔗 Enlace: " . get_permalink($product_id) . "\n";
                $info .= "---\n\n";
                
                if ($product_count >= 100) {
                    $info .= "... y más productos disponibles en la tienda.\n\n";
                    break;
                }
            }
        }

        $info .= "INFORMACIÓN GENERAL:\n";
        $info .= "• Total de productos en catálogo: {$product_count}\n";
        $info .= "• Todos los productos son homeopáticos y naturales\n";
        $info .= "• Envíos disponibles a toda la república\n";

        set_transient('wc_ai_chat_products_info', $info, HOUR_IN_SECONDS);

        return $info;
    }
    
    private function get_product_categories($product) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $category_names = array();
        
        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }
        
        return !empty($category_names) ? implode(', ', $category_names) : 'Sin categoría';
    }
    
    private function clean_description($description) {
        $clean_desc = wp_strip_all_tags($description);
        $clean_desc = preg_replace('/\s+/', ' ', $clean_desc);
        
        if (strlen($clean_desc) > 200) {
            $clean_desc = substr($clean_desc, 0, 200) . '...';
        }
        
        return trim($clean_desc);
    }
    
    private function build_prompt($message, $products_info) {
        $base_prompt = "CONSULTA DEL USUARIO: \"{$message}\"\n\n";
        
        $base_prompt .= "INSTRUCCIONES ESPECÍFICAS:\n";
        $base_prompt .= "1. Eres un homeópata experto de esta tienda en línea\n";
        $base_prompt .= "2. Analiza los síntomas mencionados y recomienda productos ESPECÍFICOS del catálogo\n";
        $base_prompt .= "3. Menciona el NOMBRE EXACTO del producto y su PRECIO\n";
        $base_prompt .= "4. Explica BREVEMENTE por qué ese producto ayuda con los síntomas\n";
        $base_prompt .= "5. Si es apropiado, recomienda 2-3 productos diferentes\n";
        $base_prompt .= "6. Incluye el ENLACE del producto cuando sea posible\n";
        $base_prompt .= "7. Sé empático pero profesional\n";
        $base_prompt .= "8. Si los síntomas son graves, sugiere consultar un médico\n\n";
        
        $base_prompt .= "CATÁLOGO DE PRODUCTOS DISPONIBLES:\n";
        $base_prompt .= $products_info . "\n\n";
        
        $base_prompt .= "POR FAVOR PROPORCIONA UNA RECOMENDACIÓN HOMEOPÁTICA ADECUADA:";

        return $base_prompt;
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
        
        $system_prompt = !empty($this->settings['system_prompt']) 
            ? $this->settings['system_prompt'] 
            : 'Eres un homeópata experto. Sé conciso y profesional.';
        
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
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
    
    // Sistema de aprendizaje automático
    public function auto_learn_process() {
        $auto_approved = $this->learning_engine->auto_approve_high_confidence_suggestions();
        
        if ($auto_approved > 0) {
            error_log("WC AI Chat: Auto-aprobadas {$auto_approved} sugerencias de aprendizaje");
        }
    }
    
    public function ajax_search_symptoms() {
        if (!wp_verify_nonce($_POST['nonce'], 'wc_ai_chat_symptoms_nonce')) {
            wp_die('Error de seguridad');
        }
        
        $term = sanitize_text_field($_POST['term']);
        $symptoms = $this->symptoms_db->search_symptoms($term, 10);
        
        ob_start();
        if ($symptoms) {
            echo '<ul>';
            foreach ($symptoms as $symptom) {
                echo '<li>' . esc_html($symptom->symptom_name) . ' (' . $symptom->product_count . ' productos)</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No se encontraron síntomas</p>';
        }
        
        wp_send_json_success(ob_get_clean());
    }
    
    public function ajax_import_base_symptoms() {
        if (!wp_verify_nonce($_POST['nonce'], 'wc_ai_chat_symptoms_nonce')) {
            wp_die('Error de seguridad');
        }
        
        $imported = $this->import_base_symptoms();
        wp_send_json_success(array('message' => "Importados {$imported} síntomas base"));
    }
    
    public function ajax_process_suggestion() {
        if (!wp_verify_nonce($_POST['nonce'], 'wc_ai_chat_learning_nonce')) {
            wp_die('Error de seguridad');
        }
        
        $suggestion_id = intval($_POST['suggestion_id']);
        $action_type = sanitize_text_field($_POST['action_type']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ai_chat_learning_suggestions';
        $suggestion = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE suggestion_id = %d", $suggestion_id));
        
        if (!$suggestion) {
            wp_send_json_error('Sugerencia no encontrada');
        }
        
        if ($action_type === 'approve') {
            $this->learning_engine->process_suggestion($suggestion, false);
            wp_send_json_success('Sugerencia aprobada');
        } elseif ($action_type === 'reject') {
            $wpdb->update($table, 
                array('status' => 'rejected', 'reviewed_at' => current_time('mysql'), 'reviewed_by' => get_current_user_id()),
                array('suggestion_id' => $suggestion_id)
            );
            wp_send_json_success('Sugerencia rechazada');
        }
    }
    
    private function import_base_symptoms() {
        $base_symptoms = array(
            array(
                'symptom_name' => 'dolor de cabeza',
                'synonyms' => 'cefalea, migraña, dolor cabeza, jaqueca',
                'category' => 'neurológico',
                'severity' => 'moderado'
            ),
            array(
                'symptom_name' => 'insomnio',
                'synonyms' => 'problemas para dormir, dificultad para dormir, sueño interrumpido',
                'category' => 'sueño',
                'severity' => 'leve'
            ),
            array(
                'symptom_name' => 'ansiedad',
                'synonyms' => 'nerviosismo, angustia, estrés, preocupación excesiva',
                'category' => 'emocional',
                'severity' => 'moderado'
            ),
            array(
                'symptom_name' => 'dolor muscular',
                'synonyms' => 'contractura, calambre, dolor espalda, lumbalgia',
                'category' => 'muscular',
                'severity' => 'leve'
            ),
            array(
                'symptom_name' => 'gripe',
                'synonyms' => 'resfriado, congestión, tos, fiebre, catarro',
                'category' => 'respiratorio',
                'severity' => 'moderado'
            ),
            array(
                'symptom_name' => 'acidez',
                'synonyms' => 'agruras, reflujo, indigestión, ardor estómago',
                'category' => 'digestivo',
                'severity' => 'leve'
            )
        );
        
        $imported = 0;
        foreach ($base_symptoms as $symptom) {
            if ($this->symptoms_db->save_symptom($symptom)) {
                $imported++;
            }
        }
        
        return $imported;
    }
    
    // Admin pages
    public function add_symptoms_admin_page() {
        add_submenu_page(
            'options-general.php',
            'Gestión de Síntomas',
            'Síntomas Homeopáticos',
            'manage_options',
            'wc-ai-chat-symptoms',
            array($this, 'symptoms_admin_page')
        );
    }
    
    public function add_learning_admin_page() {
        add_submenu_page(
            'options-general.php',
            'Aprendizaje del Chat',
            'Aprendizaje AI',
            'manage_options',
            'wc-ai-chat-learning',
            array($this, 'learning_admin_page')
        );
    }
    
    public function symptoms_admin_page() {
        $stats = $this->get_symptoms_stats();
        ?>
        <div class="wrap">
            <h1>Gestión de Síntomas y Productos Homeopáticos</h1>
            
            <div class="card">
                <h2>Importar Síntomas Base</h2>
                <p>Importa una base de datos inicial de síntomas homeopáticos comunes.</p>
                <button type="button" id="import-base-symptoms" class="button button-primary">
                    Importar Síntomas Base
                </button>
            </div>
            
            <div class="card">
                <h2>Búsqueda de Síntomas</h2>
                <input type="text" id="symptom-search" placeholder="Buscar síntomas..." class="regular-text">
                <div id="symptom-results" style="margin-top: 10px;"></div>
            </div>
            
            <div class="card">
                <h2>Estadísticas</h2>
                <p>Total de síntomas: <?php echo $stats['total_symptoms']; ?></p>
                <p>Total de relaciones producto-síntoma: <?php echo $stats['total_relations']; ?></p>
                <p>Productos con síntomas asignados: <?php echo $stats['products_with_symptoms']; ?></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#symptom-search').on('input', function() {
                var term = $(this).val();
                if (term.length > 2) {
                    $.post(ajaxurl, {
                        action: 'wc_ai_chat_search_symptoms',
                        term: term,
                        nonce: '<?php echo wp_create_nonce('wc_ai_chat_symptoms_nonce'); ?>'
                    }, function(response) {
                        $('#symptom-results').html(response.data);
                    });
                }
            });
            
            $('#import-base-symptoms').on('click', function() {
                $.post(ajaxurl, {
                    action: 'wc_ai_chat_import_base_symptoms',
                    nonce: '<?php echo wp_create_nonce('wc_ai_chat_symptoms_nonce'); ?>'
                }, function(response) {
                    alert(response.data.message);
                    location.reload();
                });
            });
        });
        </script>
        <?php
    }
    
    public function learning_admin_page() {
        $suggestions = $this->get_learning_suggestions();
        $stats = $this->get_learning_stats();
        ?>
        <div class="wrap">
            <h1>Sistema de Aprendizaje del Chat Homeopático</h1>
            
            <div class="card">
                <h2>Estadísticas de Aprendizaje</h2>
                <div class="learning-stats">
                    <div class="stat-box">
                        <h3><?php echo $stats['total_suggestions']; ?></h3>
                        <p>Total Sugerencias</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $stats['pending_suggestions']; ?></h3>
                        <p>Pendientes</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $stats['approved_suggestions']; ?></h3>
                        <p>Aprobadas</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo $stats['auto_approved_suggestions']; ?></h3>
                        <p>Auto-Aprobadas</p>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2>Sugerencias de Aprendizaje Pendientes</h2>
                <div class="suggestions-list">
                    <?php foreach ($suggestions as $suggestion): ?>
                    <div class="suggestion-item" data-id="<?php echo $suggestion->suggestion_id; ?>">
                        <div class="suggestion-header">
                            <strong>Confianza: <?php echo round($suggestion->confidence_score * 100); ?>%</strong>
                            <span class="date"><?php echo date('Y-m-d H:i', strtotime($suggestion->created_at)); ?></span>
                        </div>
                        <div class="suggestion-content">
                            <p><strong>Usuario:</strong> <?php echo esc_html($suggestion->user_message); ?></p>
                            <p><strong>AI:</strong> <?php echo wp_kses_post($suggestion->ai_response); ?></p>
                            <p><strong>Síntomas detectados:</strong> <?php echo esc_html(str_replace('|', ', ', $suggestion->detected_symptoms)); ?></p>
                            <p><strong>Productos detectados:</strong> <?php echo esc_html(str_replace('|', ', ', $suggestion->detected_products)); ?></p>
                        </div>
                        <div class="suggestion-actions">
                            <button class="button button-primary approve-suggestion" data-id="<?php echo $suggestion->suggestion_id; ?>">
                                Aprobar
                            </button>
                            <button class="button button-secondary reject-suggestion" data-id="<?php echo $suggestion->suggestion_id; ?>">
                                Rechazar
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <style>
        .learning-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .stat-box {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 2em;
            color: #667eea;
        }
        .suggestion-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }
        .suggestion-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .suggestion-actions {
            margin-top: 10px;
            text-align: right;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.approve-suggestion').on('click', function() {
                var suggestionId = $(this).data('id');
                processSuggestion(suggestionId, 'approve');
            });
            
            $('.reject-suggestion').on('click', function() {
                var suggestionId = $(this).data('id');
                processSuggestion(suggestionId, 'reject');
            });
            
            function processSuggestion(suggestionId, action) {
                $.post(ajaxurl, {
                    action: 'wc_ai_chat_process_suggestion',
                    suggestion_id: suggestionId,
                    action_type: action,
                    nonce: '<?php echo wp_create_nonce('wc_ai_chat_learning_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('.suggestion-item[data-id="' + suggestionId + '"]').fadeOut();
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    private function get_symptoms_stats() {
        global $wpdb;
        $table_symptoms = $wpdb->prefix . 'wc_ai_chat_symptoms';
        $table_relations = $wpdb->prefix . 'wc_ai_chat_symptom_products';
        
        return array(
            'total_symptoms' => $wpdb->get_var("SELECT COUNT(*) FROM $table_symptoms"),
            'total_relations' => $wpdb->get_var("SELECT COUNT(*) FROM $table_relations"),
            'products_with_symptoms' => $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM $table_relations")
        );
    }
    
    private function get_learning_suggestions($status = 'pending', $limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wc_ai_chat_learning_suggestions 
             WHERE status = %s 
             ORDER BY confidence_score DESC, created_at DESC 
             LIMIT %d",
            $status, $limit
        ));
    }
    
    private function get_learning_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_ai_chat_learning_suggestions';
        
        return array(
            'total_suggestions' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'pending_suggestions' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"),
            'approved_suggestions' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'approved'"),
            'auto_approved_suggestions' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'auto_approved'")
        );
    }
    
    // Resto de las funciones existentes (register_settings, add_admin_menu, options_page, etc.)
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
        register_setting('wc_ai_homeopathic_chat_settings', 'wc_ai_homeopathic_chat_system_prompt');
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
        // ... (código existente de la página de opciones)
        // Mantener el mismo código de la página de configuración que ya tenías
    }
    
    private function get_default_system_prompt() {
        return "Eres un asesor homeópata de la tienda en linea con amplia experiencia en medicina natural. Tu objetivo es ayudar a los usuarios de la tienda en línea a encontrar soluciones homeopáticas adecuadas para sus síntomas.

IMPORTANTE: 
- Sé profesional, empático y conciso
- Recomienda productos específicos de la tienda cuando sea apropiado
- Explica brevemente los beneficios de cada recomendación
- Incluye dosis sugeridas cuando sea relevante
- Recuerda que eres un asistente, no replaces la consulta médica
- Si los síntomas son graves, sugiere consultar a un profesional

Responde en el mismo idioma que el usuario.";
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