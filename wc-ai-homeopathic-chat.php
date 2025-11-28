<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Chatbot flotante para recomendaciones homeopáticas con WooCommerce.
 * Version: 2.4.0
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

define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '2.4.0');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);

class WC_AI_Homeopathic_Chat {
    
    private $settings;
    private $padecimientos_humanos;
    private $productos_cache;
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_settings();
        $this->initialize_padecimientos_map();
        $this->initialize_hooks();
        $this->productos_cache = array();
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
    
    private function initialize_padecimientos_map() {
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
    
    private function initialize_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_floating_chat'));
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
    }
    
    // MÉTODOS FALTANTES - IMPLEMENTACIÓN COMPLETA
    
    private function get_products_by_tags($categorias) {
        $found_products = array();
        
        foreach ($categorias as $categoria) {
            $tag_terms = $this->get_tag_terms_for_category($categoria);
            
            foreach ($tag_terms as $tag_term) {
                $products = $this->search_products_by_tag($tag_term);
                
                foreach ($products as $product) {
                    if ($product && $product->is_visible()) {
                        $found_products[$product->get_id()] = $product;
                    }
                }
            }
        }
        
        return array_values($found_products);
    }
    
    private function get_tag_terms_for_category($categoria) {
        $tag_mapping = array(
            "infecciosas" => array('infeccioso', 'infeccion', 'gripe', 'resfriado', 'viral', 'bacteriano'),
            "cardiovasculares" => array('cardiovascular', 'corazon', 'corazón', 'presion', 'hipertension'),
            "respiratorias" => array('respiratorio', 'asma', 'alergia', 'rinitis', 'sinusitis', 'tos'),
            "digestivas" => array('digestivo', 'gastritis', 'ulcera', 'reflujo', 'colitis', 'estomago'),
            "neurologicas" => array('neurologico', 'migraña', 'dolor cabeza', 'cefalea', 'neuralgia'),
            "musculoesqueléticas" => array('muscular', 'oseo', 'artritis', 'artrosis', 'dolor articular'),
            "endocrinas" => array('endocrino', 'tiroides', 'diabetes', 'metabolismo'),
            "mentales" => array('mental', 'estres', 'ansiedad', 'depresion', 'insomnio', 'emocional'),
            "cancer" => array('cancer', 'oncology', 'tumor', 'neoplasia'),
            "sintomas_generales" => array('sintoma general', 'fatiga', 'debilidad', 'malestar'),
            "sintomas_especificos" => array('dolor', 'fiebre', 'nauseas', 'vomitos'),
            "dermatologicas" => array('dermatologico', 'piel', 'acne', 'eczema', 'psoriasis'),
            "oculares" => array('ocular', 'ojo', 'vision', 'cataratas'),
            "auditivas" => array('auditivo', 'oido', 'sordera', 'tinnitus')
        );
        
        return isset($tag_mapping[$categoria]) ? $tag_mapping[$categoria] : array($categoria);
    }
    
    private function search_products_by_tag($tag_name) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field' => 'name',
                    'terms' => $tag_name,
                    'operator' => 'LIKE'
                )
            )
        );
        
        $products = array();
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $products[] = $product;
                }
            }
        }
        
        wp_reset_postdata();
        return $products;
    }
    
    private function get_products_by_wc_categories($categorias) {
        $found_products = array();
        
        foreach ($categorias as $categoria) {
            $category_terms = $this->get_wc_category_terms_for_category($categoria);
            
            foreach ($category_terms as $category_term) {
                $products = $this->search_products_by_wc_category($category_term);
                
                foreach ($products as $product) {
                    if ($product && $product->is_visible()) {
                        $found_products[$product->get_id()] = $product;
                    }
                }
            }
        }
        
        return array_values($found_products);
    }
    
    private function get_wc_category_terms_for_category($categoria) {
        $category_mapping = array(
            "infecciosas" => array('infecciosas', 'enfermedades-infecciosas'),
            "cardiovasculares" => array('cardiovasculares', 'corazon'),
            "respiratorias" => array('respiratorias', 'vias-respiratorias'),
            "digestivas" => array('digestivas', 'sistema-digestivo'),
            "neurologicas" => array('neurologicas', 'sistema-nervioso'),
            "mentales" => array('salud-mental', 'emocional'),
            "musculoesqueléticas" => array('musculoesqueleticas', 'muscular', 'huesos'),
            "endocrinas" => array('endocrinas', 'metabolismo'),
            "cancer" => array('cancer', 'oncology'),
            "dermatologicas" => array('dermatologicas', 'piel'),
            "oculares" => array('oculares', 'ojos'),
            "auditivas" => array('auditivas', 'oidos')
        );
        
        return isset($category_mapping[$categoria]) ? $category_mapping[$categoria] : array($categoria);
    }
    
    private function search_products_by_wc_category($category_name) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 20,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'name', 
                    'terms' => $category_name,
                    'operator' => 'LIKE'
                )
            )
        );
        
        $products = array();
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $products[] = $product;
                }
            }
        }
        
        wp_reset_postdata();
        return $products;
    }
    
    private function get_polivalent_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_tag',
                    'field' => 'name',
                    'terms' => array('polivalente', 'general', 'multiusos'),
                    'operator' => 'IN'
                )
            )
        );
        
        $products = array();
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product) {
                    $products[] = $product;
                }
            }
        }
        
        wp_reset_postdata();
        return $products;
    }
    
    private function format_products_info($products) {
        if (empty($products)) {
            return "No hay productos específicos para recomendar basados en el análisis.";
        }
        
        $products_info = "PRODUCTOS HOMEOPÁTICOS RECOMENDADOS:\n\n";
        $found_products = 0;
        
        foreach ($products as $product) {
            $products_info .= $this->format_product_info_basico($product) . "\n---\n";
            $found_products++;
        }
        
        $products_info .= "\n💊 Total de productos encontrados: {$found_products}";
        return $products_info;
    }
    
    private function format_product_info_basico($product) {
        $title = $product->get_name();
        $sku = $product->get_sku() ?: 'N/A';
        $price = $product->get_price_html();
        $short_description = wp_strip_all_tags($product->get_short_description() ?: '');
        $stock_status = $product->get_stock_status();
        $stock_text = $stock_status === 'instock' ? '✅ Disponible' : '⏳ Consultar stock';
        
        if (strlen($short_description) > 100) {
            $short_description = substr($short_description, 0, 97) . '...';
        }
        
        return "📦 {$title}\n🆔 SKU: {$sku}\n💰 {$price}\n📊 {$stock_text}\n📝 {$short_description}";
    }
    
    // MÉTODOS RESTANTES (sin cambios)
    
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
        
        wp_enqueue_style('wc-ai-homeopathic-chat-style', WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/css/chat-style.css', array(), WC_AI_HOMEOPATHIC_CHAT_VERSION);
        wp_enqueue_script('wc-ai-homeopathic-chat-script', WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/js/chat-script.js', array('jquery'), WC_AI_HOMEOPATHIC_CHAT_VERSION, true);
        
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
    
    public function ajax_send_message() {
        try {
            $this->validate_ajax_request();
            
            $message = $this->sanitize_message();
            $cache_key = 'wc_ai_chat_' . md5($message);
            
            $cached_response = $this->get_cached_response($cache_key);
            if ($cached_response !== false) {
                wp_send_json_success(array('response' => $cached_response, 'from_cache' => true));
            }
            
            $analysis = $this->analizar_sintomas_mejorado($message);
            $productos_mencionados = $this->detectar_productos_en_consulta($message);
            $info_productos_mencionados = $this->get_info_productos_mencionados($productos_mencionados);
            
            $relevant_products = $this->get_relevant_products_by_categories($analysis['categorias_detectadas']);
            
            if (empty($analysis['categorias_detectadas'])) {
                $relevant_products = $this->get_polivalent_products();
            }
            
            $prompt = $this->build_prompt($message, $analysis, $relevant_products, $info_productos_mencionados, $productos_mencionados);
            $response = $this->call_deepseek_api($prompt);
            
            if (is_wp_error($response)) {
                if (!empty($this->settings['whatsapp_number'])) {
                    wp_send_json_success(array('response' => $this->get_whatsapp_fallback_message($message), 'whatsapp_fallback' => true));
                } else {
                    throw new Exception($response->get_error_message());
                }
            }
            
            $sanitized_response = $this->sanitize_api_response($response);
            $this->cache_response($cache_key, $sanitized_response);
            
            wp_send_json_success(array(
                'response' => $sanitized_response,
                'from_cache' => false,
                'analysis' => $analysis,
                'productos_mencionados' => $productos_mencionados
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function get_relevant_products_by_categories($categorias) {
        $all_products = array();
        
        $products_by_tags = $this->get_products_by_tags($categorias);
        $all_products = array_merge($all_products, $products_by_tags);
        
        if (empty($all_products)) {
            $products_by_categories = $this->get_products_by_wc_categories($categorias);
            $all_products = array_merge($all_products, $products_by_categories);
        }
        
        if (empty($all_products)) {
            $all_products = $this->get_polivalent_products();
        }
        
        return $this->format_products_info(array_slice($all_products, 0, 15));
    }
    
    // ... (los demás métodos de análisis, normalización, etc. se mantienen igual)
    
    private function analizar_sintomas_mejorado($message) {
        $message_normalized = $this->normalizar_texto($message);
        $categorias_detectadas = array();
        $padecimientos_encontrados = array();
        $keywords_encontradas = array();
        $confianza_total = 0;
        
        foreach ($this->padecimientos_humanos as $categoria => $padecimientos) {
            foreach ($padecimientos as $padecimiento) {
                $padecimiento_normalized = $this->normalizar_texto($padecimiento);
                
                $resultado_busqueda = $this->buscar_coincidencia_avanzada($message_normalized, $padecimiento_normalized, $padecimiento);
                
                if ($resultado_busqueda['encontrado']) {
                    if (!in_array($categoria, $categorias_detectadas)) {
                        $categorias_detectadas[] = $categoria;
                    }
                    
                    $padecimientos_encontrados[] = array(
                        'padecimiento' => $padecimiento,
                        'padecimiento_normalized' => $padecimiento_normalized,
                        'categoria' => $categoria,
                        'confianza' => $resultado_busqueda['confianza'],
                        'tipo_coincidencia' => $resultado_busqueda['tipo']
                    );
                    
                    $keywords_encontradas[] = $padecimiento;
                    $confianza_total += $resultado_busqueda['confianza'];
                }
            }
        }
        
        usort($padecimientos_encontrados, function($a, $b) {
            return $b['confianza'] <=> $a['confianza'];
        });
        
        return array(
            'categorias_detectadas' => $categorias_detectadas,
            'padecimientos_encontrados' => $padecimientos_encontrados,
            'keywords_encontradas' => $keywords_encontradas,
            'confianza_promedio' => count($padecimientos_encontrados) > 0 ? $confianza_total / count($padecimientos_encontrados) : 0,
            'resumen_analisis' => $this->generar_resumen_analisis_mejorado($categorias_detectadas, $padecimientos_encontrados)
        );
    }
    
    private function normalizar_texto($texto) {
        if (!is_string($texto)) return '';
        
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = $this->remover_acentos($texto);
        $texto = $this->corregir_errores_comunes($texto);
        $texto = preg_replace('/[^a-z0-9\s]/', '', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        
        return trim($texto);
    }
    
    private function remover_acentos($texto) {
        $acentos = array(
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ã' => 'a', 'ñ' => 'n', 'ç' => 'c',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'À' => 'a', 'È' => 'e', 'Ì' => 'i', 'Ò' => 'o', 'Ù' => 'u',
            'Ä' => 'a', 'Ë' => 'e', 'Ï' => 'i', 'Ö' => 'o', 'Ü' => 'u',
            'Â' => 'a', 'Ê' => 'e', 'Î' => 'i', 'Ô' => 'o', 'Û' => 'u',
            'Ã' => 'a', 'Ñ' => 'n', 'Ç' => 'c'
        );
        
        return strtr($texto, $acentos);
    }
    
    private function corregir_errores_comunes($texto) {
        $errores_comunes = array(
            'canser' => 'cancer', 'cansér' => 'cancer', 'kancer' => 'cancer', 'kanser' => 'cancer',
            'dolór' => 'dolor', 'dolores' => 'dolor', 'cabeza' => 'cabeza', 'kabeza' => 'cabeza',
            'estres' => 'estres', 'estre s' => 'estres', 'estrés' => 'estres',
            'insomnio' => 'insomnio', 'insomnio' => 'insomnio', 'insonio' => 'insomnio',
            'ansiedad' => 'ansiedad', 'ansieda' => 'ansiedad', 'ansiedá' => 'ansiedad',
            'digestion' => 'digestion', 'digestión' => 'digestion',
            'problema' => 'problema', 'enfermeda' => 'enfermedad', 'sintoma' => 'sintoma'
        );
        
        return strtr($texto, $errores_comunes);
    }
    
    private function buscar_coincidencia_avanzada($texto, $busqueda, $padecimiento_original) {
        $resultado = array('encontrado' => false, 'confianza' => 0.0, 'tipo' => 'ninguna');
        
        if ($texto === $busqueda || strpos($texto, $busqueda) !== false) {
            $resultado['encontrado'] = true; $resultado['confianza'] = 1.0; $resultado['tipo'] = 'exacta';
            return $resultado;
        }
        
        if (strpos($busqueda, ' ') !== false) {
            $confianza = $this->coincidencia_palabras_multiples($texto, $busqueda);
            if ($confianza >= 0.6) {
                $resultado['encontrado'] = true; $resultado['confianza'] = $confianza; $resultado['tipo'] = 'parcial_multiple';
                return $resultado;
            }
        }
        
        $confianza_sinonimos = $this->buscar_sinonimos($texto, $busqueda, $padecimiento_original);
        if ($confianza_sinonimos > 0) {
            $resultado['encontrado'] = true; $resultado['confianza'] = $confianza_sinonimos; $resultado['tipo'] = 'sinonimo';
            return $resultado;
        }
        
        $confianza_fonetica = $this->busqueda_fonetica($texto, $busqueda);
        if ($confianza_fonetica >= 0.8) {
            $resultado['encontrado'] = true; $resultado['confianza'] = $confianza_fonetica; $resultado['tipo'] = 'fonetica';
            return $resultado;
        }
        
        $confianza_subcadena = $this->busqueda_subcadena_tolerante($texto, $busqueda);
        if ($confianza_subcadena >= 0.7) {
            $resultado['encontrado'] = true; $resultado['confianza'] = $confianza_subcadena; $resultado['tipo'] = 'subcadena';
            return $resultado;
        }
        
        return $resultado;
    }
    
    private function coincidencia_palabras_multiples($texto, $busqueda) {
        $palabras_busqueda = explode(' ', $busqueda);
        $palabras_texto = explode(' ', $texto);
        
        $coincidencias = 0;
        $total_palabras = count($palabras_busqueda);
        
        foreach ($palabras_busqueda as $palabra) {
            if (strlen($palabra) <= 2) continue;
            
            foreach ($palabras_texto as $palabra_texto) {
                if ($this->es_coincidencia_similar($palabra, $palabra_texto)) {
                    $coincidencias++; break;
                }
            }
        }
        
        return $coincidencias / $total_palabras;
    }
    
    private function buscar_sinonimos($texto, $busqueda, $padecimiento_original) {
        $sinonimos = $this->get_sinonimos_padecimiento($padecimiento_original);
        
        foreach ($sinonimos as $sinonimo) {
            $sinonimo_normalized = $this->normalizar_texto($sinonimo);
            if (strpos($texto, $sinonimo_normalized) !== false) {
                return 0.9;
            }
        }
        
        return 0;
    }
    
    private function busqueda_fonetica($texto, $busqueda) {
        $metaphone_texto = metaphone($texto);
        $metaphone_busqueda = metaphone($busqueda);
        
        $similitud = 0;
        similar_text($metaphone_texto, $metaphone_busqueda, $similitud);
        
        return $similitud / 100;
    }
    
    private function busqueda_subcadena_tolerante($texto, $busqueda) {
        $longitud_busqueda = strlen($busqueda);
        $longitud_texto = strlen($texto);
        
        if ($longitud_busqueda > $longitud_texto) return 0;
        
        for ($i = 0; $i <= $longitud_texto - $longitud_busqueda; $i++) {
            $subcadena = substr($texto, $i, $longitud_busqueda);
            $similitud = 0;
            similar_text($subcadena, $busqueda, $similitud);
            
            if ($similitud >= 80) return $similitud / 100;
        }
        
        return 0;
    }
    
    private function es_coincidencia_similar($palabra1, $palabra2) {
        if ($palabra1 === $palabra2) return true;
        
        $similitud = 0;
        similar_text($palabra1, $palabra2, $similitud);
        
        return $similitud >= 80;
    }
    
    private function get_sinonimos_padecimiento($padecimiento) {
        $sinonimos = array(
            'cáncer' => array('cancer', 'tumor', 'neoplasia', 'malignidad'),
            'dolor de cabeza' => array('cefalea', 'migraña', 'jaqueca', 'dolor cabeza', 'dolor de cabezas'),
            'estrés' => array('estres', 'tension', 'nervios', 'agobio'),
            'ansiedad' => array('angustia', 'nerviosismo', 'inquietud', 'desasosiego'),
            'insomnio' => array('desvelo', 'no puedo dormir', 'problemas de sueño', 'dificultad para dormir'),
            'fatiga' => array('cansancio', 'agotamiento', 'debilidad', 'desgano'),
            'gripe' => array('influenza', 'resfriado', 'catarro', 'constipado'),
            'diabetes' => array('azucar alta', 'glucosa alta'),
            'hipertensión' => array('presion alta', 'tension alta', 'presion arterial alta'),
            'artritis' => array('dolor articular', 'inflamacion articular'),
            'depresión' => array('tristeza', 'desanimo', 'melancolia', 'desesperanza')
        );
        
        return isset($sinonimos[$padecimiento]) ? $sinonimos[$padecimiento] : array();
    }
    
    private function generar_resumen_analisis_mejorado($categorias, $padecimientos) {
        if (empty($categorias)) return "No se detectaron padecimientos específicos en la descripción.";
        
        $total_categorias = count($categorias);
        $total_padecimientos = count($padecimientos);
        $confianza_promedio = 0;
        
        $padecimientos_principales = array();
        foreach (array_slice($padecimientos, 0, 5) as $padecimiento) {
            $padecimientos_principales[] = $padecimiento['padecimiento'] . " (" . round($padecimiento['confianza'] * 100) . "%)";
            $confianza_promedio += $padecimiento['confianza'];
        }
        
        $confianza_promedio = $total_padecimientos > 0 ? $confianza_promedio / $total_padecimientos : 0;
        
        return sprintf("Se detectaron %d padecimientos en %d categorías (confianza promedio: %d%%). Principales: %s",
            $total_padecimientos, $total_categorias, round($confianza_promedio * 100), implode(', ', $padecimientos_principales));
    }
    
    private function detectar_productos_en_consulta($message) {
        $productos_detectados = array();
        $message_normalized = $this->normalizar_texto($message);
        $all_products = $this->get_all_store_products();
        
        foreach ($all_products as $product) {
            $product_name = $this->normalizar_texto($product->get_name());
            $product_sku = $this->normalizar_texto($product->get_sku());
            
            if ($this->buscar_coincidencia_producto($message_normalized, $product_name, $product->get_name())) {
                $productos_detectados[] = array('product' => $product, 'tipo_coincidencia' => 'nombre', 'confianza' => 0.9);
                continue;
            }
            
            if (!empty($product_sku) && $this->buscar_coincidencia_producto($message_normalized, $product_sku, $product->get_sku())) {
                $productos_detectados[] = array('product' => $product, 'tipo_coincidencia' => 'sku', 'confianza' => 1.0);
                continue;
            }
            
            $keywords_coincidencia = $this->buscar_por_palabras_clave($message_normalized, $product_name, $product->get_name());
            if ($keywords_coincidencia['encontrado']) {
                $productos_detectados[] = array('product' => $product, 'tipo_coincidencia' => 'palabras_clave', 'confianza' => $keywords_coincidencia['confianza']);
            }
        }
        
        usort($productos_detectados, function($a, $b) { return $b['confianza'] <=> $a['confianza']; });
        
        $unique_products = array();
        foreach ($productos_detectados as $producto) {
            $product_id = $producto['product']->get_id();
            if (!isset($unique_products[$product_id]) || $unique_products[$product_id]['confianza'] < $producto['confianza']) {
                $unique_products[$product_id] = $producto;
            }
        }
        
        return array_slice(array_values($unique_products), 0, 5);
    }
    
    private function get_all_store_products() {
        if (!empty($this->productos_cache)) return $this->productos_cache;
        
        $args = array('post_type' => 'product', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids');
        $product_ids = get_posts($args);
        $products = array();
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product && $product->is_visible()) $products[] = $product;
        }
        
        $this->productos_cache = $products;
        return $products;
    }
    
    private function buscar_coincidencia_producto($texto, $busqueda, $original) {
        if (strpos($texto, $busqueda) !== false) return true;
        
        $metaphone_texto = metaphone($texto);
        $metaphone_busqueda = metaphone($busqueda);
        
        if (strpos($metaphone_texto, $metaphone_busqueda) !== false) return true;
        
        return false;
    }
    
    private function buscar_por_palabras_clave($texto, $nombre_producto, $nombre_original) {
        $resultado = array('encontrado' => false, 'confianza' => 0.0);
        
        $palabras_nombre = explode(' ', $nombre_producto);
        $palabras_texto = explode(' ', $texto);
        
        $coincidencias = 0; $total_palabras_significativas = 0;
        
        foreach ($palabras_nombre as $palabra) {
            if (strlen($palabra) <= 3 || in_array($palabra, array('y', 'de', 'para', 'con', 'sin'))) continue;
            
            $total_palabras_significativas++;
            
            foreach ($palabras_texto as $palabra_texto) {
                if (strlen($palabra_texto) <= 2) continue;
                
                $similitud = 0; similar_text($palabra, $palabra_texto, $similitud);
                
                if ($similitud >= 75) { $coincidencias++; break; }
            }
        }
        
        if ($total_palabras_significativas > 0) {
            $confianza = $coincidencias / $total_palabras_significativas;
            if ($confianza >= 0.5) { $resultado['encontrado'] = true; $resultado['confianza'] = $confianza; }
        }
        
        return $resultado;
    }
    
    private function get_info_productos_mencionados($productos_mencionados) {
        if (empty($productos_mencionados)) return "";
        
        $info = "📦 PRODUCTOS MENCIONADOS EN LA CONSULTA:\n\n";
        
        foreach ($productos_mencionados as $item) {
            $product = $item['product'];
            $info .= $this->format_detailed_product_info($product, $item) . "\n---\n";
        }
        
        $info .= "\n💡 El usuario ha mostrado interés en estos productos específicos.";
        return $info;
    }
    
    private function format_detailed_product_info($product, $detection_info = null) {
        $title = $product->get_name();
        $sku = $product->get_sku() ?: 'N/A';
        $price = $product->get_price_html();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $short_description = wp_strip_all_tags($product->get_short_description() ?: '');
        $description = wp_strip_all_tags($product->get_description() ?: '');
        $stock_status = $product->get_stock_status();
        $stock_quantity = $product->get_stock_quantity();
        $weight = $product->get_weight();
        $dimensions = $product->get_dimensions();
        
        $stock_text = $stock_status === 'instock' ? '✅ Disponible' . ($stock_quantity ? " ({$stock_quantity} unidades)" : '') : '⏳ Consultar stock';
        
        $categories = $product->get_category_ids();
        $category_names = array();
        foreach ($categories as $category_id) {
            $category = get_term($category_id, 'product_cat');
            if ($category && !is_wp_error($category)) $category_names[] = $category->name;
        }
        
        $tags = $product->get_tag_ids();
        $tag_names = array();
        foreach ($tags as $tag_id) {
            $tag = get_term($tag_id, 'product_tag');
            if ($tag && !is_wp_error($tag)) $tag_names[] = $tag->name;
        }
        
        $detection_text = "";
        if ($detection_info) {
            $confianza_porcentaje = round($detection_info['confianza'] * 100);
            $detection_text = "🎯 Detectado por: {$detection_info['tipo_coincidencia']} ({$confianza_porcentaje}% confianza)\n";
        }
        
        $info = "📦 {$title}\n{$detection_text}🆔 SKU: {$sku}\n💰 Precio: {$price}\n";
        
        if ($sale_price && $regular_price != $sale_price) {
            $descuento = round((($regular_price - $sale_price) / $regular_price) * 100);
            $info .= "🎁 Descuento: {$descuento}% OFF\n";
        }
        
        $info .= "📊 Stock: {$stock_text}\n";
        
        if ($weight) $info .= "⚖️ Peso: {$weight} kg\n";
        if ($dimensions) $info .= "📏 Dimensiones: {$dimensions}\n";
        if (!empty($category_names)) $info .= "📁 Categorías: " . implode(', ', $category_names) . "\n";
        if (!empty($tag_names)) $info .= "🏷️ Tags: " . implode(', ', $tag_names) . "\n";
        
        if ($short_description) {
            if (strlen($short_description) > 150) $short_description = substr($short_description, 0, 147) . '...';
            $info .= "📝 Descripción corta: {$short_description}\n";
        }
        
        if ($description && $description != $short_description) $info .= "📋 Información adicional disponible\n";
        
        $product_url = get_permalink($product->get_id());
        $info .= "🔗 Enlace: {$product_url}";
        
        return $info;
    }
    
    private function build_prompt($message, $analysis, $relevant_products, $info_productos_mencionados = "", $productos_mencionados = array()) {
        $categorias_text = !empty($analysis['categorias_detectadas']) ? "CATEGORÍAS DETECTADAS: " . implode(', ', $analysis['categorias_detectadas']) : "No se detectaron categorías específicas.";
        $padecimientos_text = !empty($analysis['padecimientos_encontrados']) ? "PADECIMIENTOS IDENTIFICADOS: " . implode(', ', array_slice($analysis['padecimientos_encontrados'], 0, 8)) : "No se identificaron padecimientos específicos.";
        
        $hay_productos_mencionados = !empty($productos_mencionados);
        $instrucciones_especiales = "";
        
        if ($hay_productos_mencionados) {
            $nombres_productos = array();
            foreach ($productos_mencionados as $item) $nombres_productos[] = $item['product']->get_name();
            
            $instrucciones_especiales = "\n\nINFORMACIÓN ESPECIAL - PRODUCTOS MENCIONADOS:\nEl usuario ha mencionado o mostrado interés en estos productos específicos: " . implode(', ', $nombres_productos) . "\n\nINSTRUCCIONES ADICIONALES:\n1. PRIORIZA la información sobre los productos mencionados\n2. Proporciona detalles específicos sobre estos productos\n3. Si el usuario pregunta por características, precio o disponibilidad, usa la información proporcionada\n4. Relaciona los productos mencionados con los síntomas descritos\n5. Si hay productos recomendados que complementan los productos mencionados, explícalo";
        }
        
        $prompt = "Eres un homeópata experto. Analiza la consulta y recomienda productos específicos basándote en el análisis de síntomas.";

        if ($hay_productos_mencionados) $prompt .= "\n\n{$info_productos_mencionados}";

        $prompt .= "\n\nCONSULTA DEL PACIENTE:\n\"{$message}\"\n\nANÁLISIS DE SÍNTOMAS:\n{$analysis['resumen_analisis']}\n{$categorias_text}\n{$padecimientos_text}\n\nINVENTARIO DE PRODUCTOS RECOMENDADOS:\n{$relevant_products}{$instrucciones_especiales}\n\nINSTRUCCIONES PARA LA RECOMENDACIÓN:\n1. BASATE EXCLUSIVAMENTE en los productos listados arriba\n2. Prioriza los productos más específicos para los padecimientos detectados\n3. Para condiciones complejas, considera combinaciones de remedios\n4. Explica BREVEMENTE la indicación homeopática de cada producto\n5. Sé honesto si ningún producto es perfectamente adecuado\n6. Siempre aclara: \"Consulta con un profesional de la salud para diagnóstico preciso\"\n7. Recomienda máximo 3-4 productos principales\n8. Mantén un tono empático pero profesional\n9. " . ($hay_productos_mencionados ? "RESPONDE específicamente sobre los productos que el usuario mencionó" : "Si el usuario pregunta por productos específicos, sugiere consultar el catálogo completo") . "\n\nResponde en español de manera natural y práctica.";

        return $prompt;
    }
    
    private function get_whatsapp_fallback_message($user_message) {
        $whatsapp_url = $this->generate_whatsapp_url($user_message);
        return sprintf(__('Parece que hay un problema con nuestro sistema. ¿Te gustaría continuar la conversación por WhatsApp?<br><br><a href="%s" target="_blank" class="wc-ai-whatsapp-link">💬 Abrir WhatsApp</a>', 'wc-ai-homeopathic-chat'), esc_url($whatsapp_url));
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
        if (empty(trim($message))) throw new Exception(__('Por favor describe tus síntomas.', 'wc-ai-homeopathic-chat'));
        return $message;
    }
    
    private function call_deepseek_api($prompt) {
        if (empty($this->settings['api_key'])) return new WP_Error('no_api_key', __('API no configurada', 'wc-ai-homeopathic-chat'));
        
        $headers = array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->settings['api_key']);
        $body = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array('role' => 'system', 'content' => 'Eres un homeópata experto con amplio conocimiento de remedios homeopáticos. Proporciona recomendaciones precisas y prácticas basadas en el análisis de síntomas. Sé empático pero mantén el profesionalismo.'),
                array('role' => 'user', 'content' => $prompt)
            ),
            'temperature' => 0.7, 'max_tokens' => 1000
        );
        
        $args = array('headers' => $headers, 'body' => json_encode($body), 'timeout' => 30);
        $response = wp_remote_post($this->settings['api_url'], $args);
        
        if (is_wp_error($response)) return $response;
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) return new WP_Error('http_error', sprintf(__('Error %d', 'wc-ai-homeopathic-chat'), $response_code));
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($response_body['choices'][0]['message']['content'])) return trim($response_body['choices'][0]['message']['content']);
        
        return new WP_Error('invalid_response', __('Respuesta inválida', 'wc-ai-homeopathic-chat'));
    }
    
    private function sanitize_api_response($response) { return wp_kses_post(trim($response)); }
    private function get_cached_response($key) { return (!$this->settings['cache_enable']) ? false : get_transient($key); }
    private function cache_response($key, $response) { if ($this->settings['cache_enable']) set_transient($key, $response, WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME); }
    
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
        add_options_page('WC AI Homeopathic Chat', 'Homeopathic Chat', 'manage_options', 'wc-ai-homeopathic-chat', array($this, 'options_page'));
    }
    
    public function options_page() {
        $total_padecimientos = 0;
        foreach ($this->padecimientos_humanos as $categoria => $padecimientos) $total_padecimientos += count($padecimientos);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración del Chat Homeopático', 'wc-ai-homeopathic-chat'); ?></h1>
            <div class="card">
                <h3><?php esc_html_e('Sistema de Análisis de Síntomas', 'wc-ai-homeopathic-chat'); ?></h3>
                <p><?php esc_html_e('El chat ahora analiza síntomas complejos y recomienda productos específicos basados en TAGS de productos.', 'wc-ai-homeopathic-chat'); ?></p>
                <p><strong><?php esc_html_e('Padecimientos configurados:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo $total_padecimientos; ?> en <?php echo count($this->padecimientos_humanos); ?> categorías</p>
                <p><strong><?php esc_html_e('Búsqueda por:', 'wc-ai-homeopathic-chat'); ?></strong> TAGS de productos → Categorías WooCommerce → Productos polivalentes</p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('wc_ai_homeopathic_chat_settings'); ?>
                <div class="wc-ai-chat-settings-grid">
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Configuración de API', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr><th scope="row"><label for="wc_ai_homeopathic_chat_api_key"><?php esc_html_e('DeepSeek API Key', 'wc-ai-homeopathic-chat'); ?></label></th><td><input type="password" id="wc_ai_homeopathic_chat_api_key" name="wc_ai_homeopathic_chat_api_key" value="<?php echo esc_attr($this->settings['api_key']); ?>" class="regular-text" /></td></tr>
                                <tr><th scope="row"><label for="wc_ai_homeopathic_chat_api_url"><?php esc_html_e('URL de API', 'wc-ai-homeopathic-chat'); ?></label></th><td><input type="url" id="wc_ai_homeopathic_chat_api_url" name="wc_ai_homeopathic_chat_api_url" value="<?php echo esc_attr($this->settings['api_url']); ?>" class="regular-text" /></td></tr>
                                <tr><th scope="row"><?php esc_html_e('Habilitar Caché', 'wc-ai-homeopathic-chat'); ?></th><td><label><input type="checkbox" name="wc_ai_homeopathic_chat_cache_enable" value="1" <?php checked($this->settings['cache_enable'], true); ?> /> <?php esc_html_e('Usar caché para mejor rendimiento', 'wc-ai-homeopathic-chat'); ?></label></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Apariencia', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr><th scope="row"><?php esc_html_e('Posición del Chat', 'wc-ai-homeopathic-chat'); ?></th><td><select name="wc_ai_homeopathic_chat_position"><option value="right" <?php selected($this->settings['chat_position'], 'right'); ?>><?php esc_html_e('Derecha', 'wc-ai-homeopathic-chat'); ?></option><option value="left" <?php selected($this->settings['chat_position'], 'left'); ?>><?php esc_html_e('Izquierda', 'wc-ai-homeopathic-chat'); ?></option></select></td></tr>
                                <tr><th scope="row"><?php esc_html_e('Chat Flotante', 'wc-ai-homeopathic-chat'); ?></th><td><label><input type="checkbox" name="wc_ai_homeopathic_chat_floating" value="1" <?php checked($this->settings['enable_floating'], true); ?> /> <?php esc_html_e('Mostrar chat flotante', 'wc-ai-homeopathic-chat'); ?></label></td></tr>
                                <tr><th scope="row"><?php esc_html_e('Mostrar en Productos', 'wc-ai-homeopathic-chat'); ?></th><td><label><input type="checkbox" name="wc_ai_homeopathic_chat_products" value="1" <?php checked($this->settings['show_on_products'], true); ?> /> <?php esc_html_e('Mostrar en páginas de producto', 'wc-ai-homeopathic-chat'); ?></label></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2><?php esc_html_e('Configuración de WhatsApp', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr><th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp"><?php esc_html_e('Número de WhatsApp', 'wc-ai-homeopathic-chat'); ?></label></th><td><input type="text" id="wc_ai_homeopathic_chat_whatsapp" name="wc_ai_homeopathic_chat_whatsapp" value="<?php echo esc_attr($this->settings['whatsapp_number']); ?>" class="regular-text" placeholder="+521234567890" /></td></tr>
                                <tr><th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp_message"><?php esc_html_e('Mensaje Predeterminado', 'wc-ai-homeopathic-chat'); ?></label></th><td><textarea id="wc_ai_homeopathic_chat_whatsapp_message" name="wc_ai_homeopathic_chat_whatsapp_message" class="large-text" rows="3"><?php echo esc_textarea($this->settings['whatsapp_message']); ?></textarea></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <style>.wc-ai-chat-settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:20px;margin:20px 0}.card{background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px}</style>
        <?php
    }
    
    public function woocommerce_missing_notice() {
        ?><div class="error"><p><?php esc_html_e('WC AI Homeopathic Chat requiere WooCommerce.', 'wc-ai-homeopathic-chat'); ?></p></div><?php
    }
}

new WC_AI_Homeopathic_Chat();