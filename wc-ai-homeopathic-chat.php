<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Chatbot flotante para recomendaciones homeopáticas con WooCommerce.
 * Version: 2.5.7
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

// Verificar si WooCommerce está activo antes de cargar el plugin
if (!class_exists('WooCommerce')) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><strong>WC AI Homeopathic Chat:</strong> Requiere WooCommerce para funcionar. Por favor, instala y activa WooCommerce primero.</p>
        </div>
        <?php
    });
    return;
}

// Definir constantes
define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '2.5.7');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);

// Clase principal del plugin
class WC_AI_Homeopathic_Chat {
    
    private $settings;
    private $padecimientos_humanos;
    private $productos_cache;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
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
    
    public function ajax_send_message() {
        // Verificar nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wc_ai_homeopathic_chat_nonce')) {
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
            $analysis = $this->analizar_sintomas_mejorado($message);
            
            // Detectar productos mencionados
            $productos_mencionados = $this->detectar_productos_en_consulta($message);
            
            // Determinar si mostrar solo productos mencionados
            $mostrar_solo_productos_mencionados = $this->debe_mostrar_solo_productos_mencionados($productos_mencionados, $message);
            $info_productos_mencionados = $this->get_info_productos_mencionados($productos_mencionados);
            
            // Obtener productos relevantes
            $relevant_products = "";
            if (!$mostrar_solo_productos_mencionados) {
                $relevant_products = $this->get_relevant_products_by_categories_mejorado(
                    $analysis['categorias_detectadas'], 
                    $analysis['padecimientos_encontrados']
                );
            }
            
            // Construir prompt
            $prompt = $this->build_prompt_mejorado(
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
    
    private function analizar_sintomas_mejorado($message) {
        $message_normalized = $this->normalizar_texto($message);
        $categorias_detectadas = array();
        $padecimientos_encontrados = array();
        
        foreach ($this->padecimientos_humanos as $categoria => $padecimientos) {
            foreach ($padecimientos as $padecimiento) {
                $padecimiento_normalized = $this->normalizar_texto($padecimiento);
                
                if (strpos($message_normalized, $padecimiento_normalized) !== false) {
                    if (!in_array($categoria, $categorias_detectadas)) {
                        $categorias_detectadas[] = $categoria;
                    }
                    
                    $padecimientos_encontrados[] = array(
                        'padecimiento' => $padecimiento,
                        'categoria' => $categoria,
                        'confianza' => 1.0
                    );
                }
            }
        }
        
        return array(
            'categorias_detectadas' => $categorias_detectadas,
            'padecimientos_encontrados' => $padecimientos_encontrados,
            'resumen_analisis' => $this->generar_resumen_analisis($categorias_detectadas, $padecimientos_encontrados)
        );
    }
    
    private function normalizar_texto($texto) {
        if (!is_string($texto)) return '';
        
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        
        // Remover acentos
        $acentos = array(
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u'
        );
        $texto = strtr($texto, $acentos);
        
        // Remover caracteres especiales
        $texto = preg_replace('/[^a-z0-9\s]/', '', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        
        return trim($texto);
    }
    
    private function generar_resumen_analisis($categorias, $padecimientos) {
        if (empty($categorias)) {
            return "No se detectaron padecimientos específicos en la descripción.";
        }
        
        $total_padecimientos = count($padecimientos);
        $padecimientos_principales = array();
        
        foreach (array_slice($padecimientos, 0, 5) as $padecimiento) {
            $padecimientos_principales[] = $padecimiento['padecimiento'];
        }
        
        return sprintf(
            "Se detectaron %d padecimientos en %d categorías. Principales: %s",
            $total_padecimientos,
            count($categorias),
            implode(', ', $padecimientos_principales)
        );
    }
    
    private function detectar_productos_en_consulta($message) {
        $productos_detectados = array();
        $message_normalized = $this->normalizar_texto($message);
        
        // Obtener todos los productos
        $all_products = $this->get_all_store_products();
        
        foreach ($all_products as $product) {
            $product_name = $this->normalizar_texto($product->get_name());
            $product_sku = $this->normalizar_texto($product->get_sku());
            
            // Buscar por nombre exacto
            if (strpos($message_normalized, $product_name) !== false) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'nombre_exacto',
                    'confianza' => 1.0
                );
                continue;
            }
            
            // Buscar por SKU
            if (!empty($product_sku) && strpos($message_normalized, $product_sku) !== false) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'sku_exacto',
                    'confianza' => 1.0
                );
            }
        }
        
        // Ordenar por confianza y limitar
        usort($productos_detectados, function($a, $b) {
            return $b['confianza'] <=> $a['confianza'];
        });
        
        return array_slice($productos_detectados, 0, 5);
    }
    
    private function debe_mostrar_solo_productos_mencionados($productos_mencionados, $message) {
        if (empty($productos_mencionados)) {
            return false;
        }
        
        // Si hay productos con alta confianza
        foreach ($productos_mencionados as $producto) {
            if ($producto['confianza'] >= 0.9) {
                return true;
            }
        }
        
        // Verificar si pregunta específicamente por productos
        $message_lower = strtolower($message);
        $palabras_especificas = array('comprar', 'precio', 'cuesta', 'vale', 'cotizar', 'quiero', 'necesito');
        
        foreach ($palabras_especificas as $palabra) {
            if (strpos($message_lower, $palabra) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_info_productos_mencionados($productos_mencionados) {
        if (empty($productos_mencionados)) {
            return "";
        }
        
        $info = "🎯 PRODUCTOS ESPECÍFICOS MENCIONADOS EN LA CONSULTA:\n\n";
        
        foreach ($productos_mencionados as $item) {
            $product = $item['product'];
            $info .= $this->format_detailed_product_info($product, $item) . "\n---\n";
        }
        
        $info .= "\n💡 INFORMACIÓN IMPORTANTE:\n- INCLUYE PRECIO Y SKU EN TODAS LAS RESPUESTAS";
        
        return $info;
    }
    
    private function format_detailed_product_info($product, $detection_info = null) {
        $title = $product->get_name();
        $sku = $product->get_sku() ?: 'No disponible';
        $price = $product->get_price_html();
        $short_description = wp_strip_all_tags($product->get_short_description() ?: '');
        $stock_status = $product->get_stock_status();
        $stock_text = $stock_status === 'instock' ? '✅ Disponible' : '⏳ Consultar stock';
        
        // Construir información
        $info = "🟢 PRODUCTO: {$title}\n";
        $info .= "🆔 SKU: {$sku}\n";
        $info .= "💰 PRECIO: {$price}\n";
        $info .= "📊 Stock: {$stock_text}\n";
        
        if ($short_description) {
            $desc_clean = substr($short_description, 0, 120);
            $info .= "📝 {$desc_clean}\n";
        }
        
        return $info;
    }
    
    private function get_relevant_products_by_categories_mejorado($categorias, $padecimientos_encontrados) {
        $all_products = array();
        
        // Buscar productos por tags
        $products_by_tags = $this->get_products_by_tags($categorias);
        $all_products = array_merge($all_products, $products_by_tags);
        
        // Si no hay productos, buscar por categorías WooCommerce
        if (empty($all_products)) {
            $products_by_categories = $this->get_products_by_wc_categories($categorias);
            $all_products = array_merge($all_products, $products_by_categories);
        }
        
        // Si aún no hay productos, usar productos polivalentes
        if (empty($all_products)) {
            $all_products = $this->get_polivalent_products();
        }
        
        // Formatear información
        return $this->format_products_info(array_slice($all_products, 0, 8));
    }
    
    private function get_products_by_tags($categorias) {
        $found_products = array();
        
        foreach ($categorias as $categoria) {
            $tag_terms = $this->get_tag_terms_for_category($categoria);
            
            foreach ($tag_terms as $tag_term) {
                $args = array(
                    'post_type' => 'product',
                    'posts_per_page' => 10,
                    'post_status' => 'publish',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'product_tag',
                            'field' => 'name',
                            'terms' => $tag_term,
                            'operator' => 'LIKE'
                        )
                    )
                );
                
                $query = new WP_Query($args);
                
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $product = wc_get_product(get_the_ID());
                        if ($product && $product->is_visible()) {
                            $found_products[$product->get_id()] = $product;
                        }
                    }
                }
                wp_reset_postdata();
            }
        }
        
        return array_values($found_products);
    }
    
    private function get_tag_terms_for_category($categoria) {
        $tag_mapping = array(
            "infecciosas" => array('infeccioso', 'infeccion', 'gripe', 'resfriado'),
            "cardiovasculares" => array('cardiovascular', 'corazon', 'presion'),
            "respiratorias" => array('respiratorio', 'asma', 'alergia', 'tos'),
            "digestivas" => array('digestivo', 'gastritis', 'reflujo'),
            "neurologicas" => array('neurologico', 'migraña', 'dolor cabeza'),
            "musculoesqueléticas" => array('muscular', 'artritis', 'dolor articular'),
            "mentales" => array('mental', 'estres', 'ansiedad', 'insomnio'),
            "dermatologicas" => array('dermatologico', 'piel', 'acne')
        );
        
        return isset($tag_mapping[$categoria]) ? $tag_mapping[$categoria] : array($categoria);
    }
    
    private function get_products_by_wc_categories($categorias) {
        $found_products = array();
        
        foreach ($categorias as $categoria) {
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => 10,
                'post_status' => 'publish',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'name',
                        'terms' => $categoria,
                        'operator' => 'LIKE'
                    )
                )
            );
            
            $query = new WP_Query($args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if ($product && $product->is_visible()) {
                        $found_products[$product->get_id()] = $product;
                    }
                }
            }
            wp_reset_postdata();
        }
        
        return array_values($found_products);
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
                    'terms' => array('polivalente', 'general'),
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
        
        foreach ($products as $product) {
            $title = $product->get_name();
            $sku = $product->get_sku() ?: 'N/A';
            $price = $product->get_price_html();
            $products_info .= "📦 {$title}\n🆔 SKU: {$sku}\n💰 {$price}\n---\n";
        }
        
        return $products_info;
    }
    
    private function build_prompt_mejorado($message, $analysis, $relevant_products, $info_productos_mencionados, $productos_mencionados, $mostrar_solo_productos_mencionados) {
        $categorias_text = !empty($analysis['categorias_detectadas']) ? 
            "CATEGORÍAS DETECTADAS: " . implode(', ', $analysis['categorias_detectadas']) : 
            "No se detectaron categorías específicas.";
        
        $prompt = "Eres un homeópata experto. Analiza la consulta y proporciona información útil sobre productos homeopáticos.\n\n";
        
        if (!empty($productos_mencionados)) {
            $prompt .= "{$info_productos_mencionados}\n\n";
            
            if ($mostrar_solo_productos_mencionados) {
                $prompt .= "🎯 INSTRUCCIÓN: El usuario pregunta específicamente por estos productos. Proporciona información detallada SOLO de los productos mencionados, incluyendo precio, SKU y disponibilidad. NO recomiendes otros productos.\n\n";
            }
        }
        
        $prompt .= "CONSULTA DEL PACIENTE:\n\"{$message}\"\n\n";
        $prompt .= "ANÁLISIS DE SÍNTOMAS:\n{$analysis['resumen_analisis']}\n{$categorias_text}\n\n";
        
        if (!$mostrar_solo_productos_mencionados && !empty($relevant_products)) {
            $prompt .= "INVENTARIO DE PRODUCTOS RECOMENDADOS:\n{$relevant_products}\n\n";
        }
        
        $prompt .= "INSTRUCCIONES:\n";
        $prompt .= "1. Proporciona información clara y directa\n";
        $prompt .= "2. Usa formato legible con saltos de línea\n";
        $prompt .= "3. INCLUYE precio y SKU cuando hables de productos\n";
        $prompt .= "4. Sé empático pero profesional\n";
        $prompt .= "5. Siempre aclara: \"Consulta con un profesional de la salud para diagnóstico preciso\"\n";
        $prompt .= "6. Responde en español\n";
        $prompt .= "7. Limita la respuesta a 3-4 productos principales\n\n";
        
        $prompt .= "Responde de manera natural y práctica. Usa formato claro y fácil de leer.";
        
        return $prompt;
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
                    'content' => 'Eres un homeópata experto. Proporciona recomendaciones precisas y prácticas.'
                ),
                array('role' => 'user', 'content' => $prompt)
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
            return new WP_Error('http_error', 'Error en la conexión con la API');
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['choices'][0]['message']['content'])) {
            return trim($response_body['choices'][0]['message']['content']);
        }
        
        return new WP_Error('invalid_response', 'Respuesta inválida de la API');
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
            'loading_text' => 'Analizando tus síntomas...',
            'error_text' => 'Error temporal. ¿Deseas continuar por WhatsApp?',
            'empty_message_text' => 'Por favor describe tus síntomas.',
            'whatsapp_btn' => 'Continuar por WhatsApp',
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
                            <img src="<?php echo esc_url(WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL . 'assets/image/ai-bot-doctor.png'); ?>" alt="Asistente Homeopático" width="36" height="36">
                        </div>
                        <div class="wc-ai-chat-title">
                            <h4>Asesor Homeopático</h4>
                            <span class="wc-ai-chat-status">En línea</span>
                        </div>
                    </div>
                    <div class="wc-ai-chat-actions">
                        <button type="button" class="wc-ai-chat-minimize" aria-label="Minimizar chat">
                            <svg class="wc-ai-icon-svg" viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M20 14H4v-4h16"/>
                            </svg>
                        </button>
                        <button type="button" class="wc-ai-chat-close" aria-label="Cerrar chat">
                            <svg class="wc-ai-icon-svg" viewBox="0 0 24 24" width="14" height="14">
                                <path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="wc-ai-chat-messages">
                    <div class="wc-ai-chat-message bot">
                        <div class="wc-ai-message-content">
                            ¡Hola! Soy tu asesor homeopático. Describe tus síntomas o padecimientos y te recomendaré los productos más adecuados.
                        </div>
                        <div class="wc-ai-message-time"><?php echo current_time('H:i'); ?></div>
                    </div>
                </div>
                
                <div class="wc-ai-chat-input-container">
                    <div class="wc-ai-chat-input">
                        <textarea placeholder="Ej: Tengo dolor de cabeza, estrés y problemas digestivos..." rows="1" maxlength="500"></textarea>
                        <button type="button" class="wc-ai-chat-send">
                            <span class="wc-ai-send-icon">↑</span>
                        </button>
                    </div>
                    <?php if ($whatsapp_available): ?>
                    <div class="wc-ai-chat-fallback">
                        <button type="button" class="wc-ai-whatsapp-fallback">
                            <span class="wc-ai-whatsapp-icon">💬</span>
                            Continuar por WhatsApp
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
        return 'Parece que hay un problema con nuestro sistema. ¿Te gustaría continuar la conversación por WhatsApp?<br><br>' .
               '<a href="' . esc_url($whatsapp_url) . '" target="_blank" class="wc-ai-whatsapp-link">💬 Abrir WhatsApp</a>';
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
            <h1>Configuración del Chat Homeopático</h1>
            
            <div class="card">
                <h3>Sistema de Análisis de Síntomas - VERSIÓN 2.5.3</h3>
                <p>Sistema mejorado con detección avanzada de productos homeopáticos y respuestas enfocadas.</p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('wc_ai_homeopathic_chat_settings'); ?>
                
                <div class="wc-ai-chat-settings-grid">
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2>Configuración de API</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_api_key">DeepSeek API Key</label></th>
                                    <td><input type="password" id="wc_ai_homeopathic_chat_api_key" name="wc_ai_homeopathic_chat_api_key" value="<?php echo esc_attr($this->settings['api_key']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_api_url">URL de API</label></th>
                                    <td><input type="url" id="wc_ai_homeopathic_chat_api_url" name="wc_ai_homeopathic_chat_api_url" value="<?php echo esc_attr($this->settings['api_url']); ?>" class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">Habilitar Caché</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wc_ai_homeopathic_chat_cache_enable" value="1" <?php checked($this->settings['cache_enable'], true); ?> />
                                            Usar caché para mejor rendimiento
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2>Apariencia</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Posición del Chat</th>
                                    <td>
                                        <select name="wc_ai_homeopathic_chat_position">
                                            <option value="right" <?php selected($this->settings['chat_position'], 'right'); ?>>Derecha</option>
                                            <option value="left" <?php selected($this->settings['chat_position'], 'left'); ?>>Izquierda</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Chat Flotante</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wc_ai_homeopathic_chat_floating" value="1" <?php checked($this->settings['enable_floating'], true); ?> />
                                            Mostrar chat flotante
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Mostrar en Productos</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="wc_ai_homeopathic_chat_products" value="1" <?php checked($this->settings['show_on_products'], true); ?> />
                                            Mostrar en páginas de producto
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="wc-ai-settings-column">
                        <div class="card">
                            <h2>Configuración de WhatsApp</h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp">Número de WhatsApp</label></th>
                                    <td><input type="text" id="wc_ai_homeopathic_chat_whatsapp" name="wc_ai_homeopathic_chat_whatsapp" value="<?php echo esc_attr($this->settings['whatsapp_number']); ?>" class="regular-text" placeholder="+521234567890" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp_message">Mensaje Predeterminado</label></th>
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
        $settings_link = '<a href="' . admin_url('options-general.php?page=wc-ai-homeopathic-chat') . '">Configuración</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Inicializar el plugin
$GLOBALS['wc_ai_homeopathic_chat'] = new WC_AI_Homeopathic_Chat();