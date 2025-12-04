<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://github.com/estratos/wc-ai-homeopathic-chat
 * Description: Chatbot flotante para recomendaciones homeopáticas con WooCommerce.
 * Version: 2.5.5
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

define('WC_AI_HOMEOPATHIC_CHAT_VERSION', '2.5.5');
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME', 30 * DAY_IN_SECONDS);

// Incluir todas las clases
require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'class-solutions-methods.php';
require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'class-prompt-build.php';
require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'class-symptoms-db.php';
require_once WC_AI_HOMEOPATHIC_CHAT_PLUGIN_PATH . 'class-learning-engine.php';

class WC_AI_Homeopathic_Chat {
    
    private $settings;
    private $solutions_methods;
    private $prompt_build;
    private $symptoms_db;
    private $learning_engine;
    private $productos_cache;
    
    public function __construct() {
        // Inicializar clases
        $this->solutions_methods = new WC_AI_Chat_Solutions_Methods();
        $this->prompt_build = new WC_AI_Chat_Prompt_Build();
        $this->symptoms_db = new WC_AI_Chat_Symptoms_DB();
        $this->learning_engine = new WC_AI_Chat_Learning_Engine($this->symptoms_db);
        
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
    }
    
    public function activate_plugin() {
        // Crear tablas de la base de datos al activar el plugin
        $this->symptoms_db->create_tables();
        $this->learning_engine->create_tables();
    }
    
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_settings();
        $this->initialize_hooks();
        $this->productos_cache = array();
        
        // Inicializar motor de aprendizaje periódicamente
        $this->schedule_learning_tasks();
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'display_floating_chat'));
        add_action('wp_ajax_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_wc_ai_homeopathic_chat_send_message', array($this, 'ajax_send_message'));
        
        // Hooks para aprendizaje automático
        add_action('wc_ai_chat_auto_approve_suggestions', array($this, 'auto_approve_suggestions_cron'));
        add_action('wc_ai_chat_analyze_conversations', array($this, 'analyze_recent_conversations'));
        
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Programa tareas de aprendizaje automático
     */
    private function schedule_learning_tasks() {
        if (!wp_next_scheduled('wc_ai_chat_auto_approve_suggestions')) {
            wp_schedule_event(time(), 'twicedaily', 'wc_ai_chat_auto_approve_suggestions');
        }
        
        if (!wp_next_scheduled('wc_ai_chat_analyze_conversations')) {
            wp_schedule_event(time(), 'daily', 'wc_ai_chat_analyze_conversations');
        }
    }
    
    /**
     * AJAX handler - VERSIÓN 2.5.3 MEJORADA
     */
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
            
            // Analizar síntomas usando la clase de soluciones
            $analysis = $this->solutions_methods->analizar_sintomas_mejorado($message);
            
            // Detectar productos mencionados
            $productos_mencionados = $this->detectar_productos_en_consulta($message);
            
            // DETECCIÓN MEJORADA: Usar el método de prompt build
            $mostrar_solo_productos_mencionados = $this->prompt_build->debe_mostrar_solo_productos_mencionados($productos_mencionados, $message);
            $info_productos_mencionados = $this->prompt_build->get_info_productos_mencionados($productos_mencionados);
            
            // BÚSQUEDA MEJORADA usando la clase de soluciones
            $relevant_products = "";
            if (!$mostrar_solo_productos_mencionados) {
                $relevant_products = $this->solutions_methods->get_relevant_products_by_categories_mejorado(
                    $analysis['categorias_detectadas'], 
                    $analysis['padecimientos_encontrados']
                );
            }
            
            // DEBUG: Registrar información para análisis
            error_log("WC AI Chat Debug - Mensaje: " . $message);
            error_log("WC AI Chat Debug - Productos detectados: " . count($productos_mencionados));
            error_log("WC AI Chat Debug - Mostrar solo productos mencionados: " . ($mostrar_solo_productos_mencionados ? 'SÍ' : 'NO'));
            foreach ($productos_mencionados as $index => $producto) {
                error_log("WC AI Chat Debug - Producto " . ($index + 1) . ": " . $producto['product']->get_name() . " - Confianza: " . $producto['confianza']);
            }
            
            // Construir prompt usando la clase de prompt build
            $prompt = $this->prompt_build->build_prompt_mejorado(
                $message, 
                $analysis, 
                $relevant_products, 
                $info_productos_mencionados, 
                $productos_mencionados, 
                $mostrar_solo_productos_mencionados
            );
            
            $response = $this->call_deepseek_api($prompt);
            
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
            
            $sanitized_response = $this->sanitize_api_response($response);
            $this->cache_response($cache_key, $sanitized_response);
            
            // Aprendizaje automático: analizar la conversación si está habilitado
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
    
    /**
     * Aprobar sugerencias automáticamente (cron job)
     */
    public function auto_approve_suggestions_cron() {
        if ($this->settings['enable_learning']) {
            $approved_count = $this->learning_engine->auto_approve_high_confidence_suggestions();
            error_log("WC AI Chat Learning: Auto-approved {$approved_count} suggestions.");
        }
    }
    
    /**
     * Analizar conversaciones recientes (cron job)
     */
    public function analyze_recent_conversations() {
        // Esta función puede usarse para análisis batch de conversaciones
        // Por ejemplo, procesar logs de conversaciones del día anterior
        error_log("WC AI Chat Learning: Analyzing recent conversations.");
    }
    
    // =========================================================================
    // SISTEMA MEJORADO DE DETECCIÓN DE PRODUCTOS (mantenido en clase principal)
    // =========================================================================
    
    /**
     * Detecta productos mencionados en la consulta del usuario - VERSIÓN MEJORADA 2.5.3
     */
    private function detectar_productos_en_consulta($message) {
        $productos_detectados = array();
        $message_normalized = $this->solutions_methods->normalizar_texto($message);
        
        // Obtener todos los productos de la tienda (con cache)
        $all_products = $this->get_all_store_products();
        
        foreach ($all_products as $product) {
            $product_name = $this->solutions_methods->normalizar_texto($product->get_name());
            $product_sku = $this->solutions_methods->normalizar_texto($product->get_sku());
            
            // ESTRATEGIA 1: Búsqueda por nombre exacto (máxima prioridad)
            if ($this->buscar_coincidencia_exacta($message_normalized, $product_name)) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'nombre_exacto',
                    'confianza' => 1.0
                );
                continue;
            }
            
            // ESTRATEGIA 2: Búsqueda por SKU exacto
            if (!empty($product_sku) && $this->buscar_coincidencia_exacta($message_normalized, $product_sku)) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'sku_exacto',
                    'confianza' => 1.0
                );
                continue;
            }
            
            // ESTRATEGIA 3: Búsqueda por palabras clave principales (alta confianza)
            $keywords_result = $this->buscar_por_palabras_clave_mejorado($message_normalized, $product_name, $product->get_name());
            if ($keywords_result['encontrado'] && $keywords_result['confianza'] >= 0.7) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'palabras_principales',
                    'confianza' => $keywords_result['confianza']
                );
                continue;
            }
            
            // ESTRATEGIA 4: Búsqueda fonética mejorada
            $fonetica_result = $this->busqueda_fonetica_mejorada($message_normalized, $product_name, $product->get_name());
            if ($fonetica_result['encontrado']) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'fonetica',
                    'confianza' => $fonetica_result['confianza']
                );
                continue;
            }
            
            // ESTRATEGIA 5: Búsqueda por sinónimos de productos
            $sinonimos_result = $this->buscar_por_sinonimos_producto($message_normalized, $product->get_name());
            if ($sinonimos_result['encontrado']) {
                $productos_detectados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'sinonimo',
                    'confianza' => $sinonimos_result['confianza']
                );
            }
        }
        
        // ESTRATEGIA 6: Búsqueda en descripciones y contenido (como fallback)
        if (empty($productos_detectados)) {
            $productos_descripcion = $this->buscar_en_descripciones($message_normalized, $all_products);
            $productos_detectados = array_merge($productos_detectados, $productos_descripcion);
        }
        
        // Ordenar por confianza y eliminar duplicados
        usort($productos_detectados, function($a, $b) {
            return $b['confianza'] <=> $a['confianza'];
        });
        
        // Eliminar duplicados por ID de producto
        $unique_products = array();
        foreach ($productos_detectados as $producto) {
            $product_id = $producto['product']->get_id();
            if (!isset($unique_products[$product_id]) || $unique_products[$product_id]['confianza'] < $producto['confianza']) {
                $unique_products[$product_id] = $producto;
            }
        }
        
        return array_slice(array_values($unique_products), 0, 5);
    }
    
    /**
     * Búsqueda por coincidencia exacta mejorada
     */
    private function buscar_coincidencia_exacta($texto, $busqueda) {
        // Coincidencia exacta de palabra completa
        if (preg_match('/\b' . preg_quote($busqueda, '/') . '\b/i', $texto)) {
            return true;
        }
        
        // Coincidencia exacta sin considerar espacios extras
        $texto_sin_espacios = preg_replace('/\s+/', '', $texto);
        $busqueda_sin_espacios = preg_replace('/\s+/', '', $busqueda);
        
        if (strpos($texto_sin_espacios, $busqueda_sin_espacios) !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Búsqueda por palabras clave mejorada
     */
    private function buscar_por_palabras_clave_mejorado($texto, $nombre_producto, $nombre_original) {
        $resultado = array('encontrado' => false, 'confianza' => 0.0);
        
        // Dividir en palabras y filtrar
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
        $palabras_importantes = 0;
        
        foreach ($palabras_nombre as $palabra) {
            // Determinar importancia de la palabra
            $es_importante = $this->es_palabra_importante($palabra, $nombre_original);
            
            foreach ($palabras_texto as $palabra_texto) {
                $similitud = 0;
                similar_text($palabra, $palabra_texto, $similitud);
                
                if ($similitud >= 80) {
                    $coincidencias++;
                    if ($es_importante) {
                        $palabras_importantes++;
                    }
                    break;
                }
            }
        }
        
        $total_palabras = count($palabras_nombre);
        $confianza_base = $coincidencias / $total_palabras;
        
        // Aumentar confianza si coinciden palabras importantes
        $bonus_importancia = $palabras_importantes / max(1, $total_palabras) * 0.3;
        $confianza_final = min(1.0, $confianza_base + $bonus_importancia);
        
        if ($confianza_final >= 0.5) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = $confianza_final;
        }
        
        return $resultado;
    }
    
    /**
     * Determina si una palabra es importante en el nombre del producto
     */
    private function es_palabra_importante($palabra, $nombre_original) {
        $palabras_no_importantes = array(
            'homeopatico', 'homeopatica', 'homeopáticos', 'homeopáticas',
            'remedio', 'medicamento', 'producto', 'solucion', 'tabletas',
            'gotas', 'crema', 'gel', 'ungüento', 'extracto', 'ch', '30ch', '200ch'
        );
        
        $palabra_lower = strtolower($palabra);
        
        // Si es una palabra muy común, no es importante
        if (in_array($palabra_lower, $palabras_no_importantes)) {
            return false;
        }
        
        // Si es la primera palabra del nombre, es importante
        $primeras_palabras = array_slice(explode(' ', strtolower($nombre_original)), 0, 2);
        if (in_array($palabra_lower, $primeras_palabras)) {
            return true;
        }
        
        // Si es un nombre de marca o principio activo conocido
        $marcas_importantes = array(
            'oscillococcinum', 'arnica', 'gelsemium', 'belladonna', 'nux', 'pulsatilla',
            'ignatia', 'lycopodium', 'sulphur', 'calcarea', 'phosphorus', 'lachesis',
            'rhus', 'toxicodendron', 'ruta', 'graveolens', 'hypericum', 'calendula',
            'chamomilla', 'silicea', 'thuja', 'apis', 'melifica'
        );
        
        if (in_array($palabra_lower, $marcas_importantes)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Búsqueda fonética mejorada
     */
    private function busqueda_fonetica_mejorada($texto, $nombre_producto, $nombre_original) {
        $resultado = array('encontrado' => false, 'confianza' => 0.0);
        
        // Metaphone en español mejorado
        $metaphone_texto = $this->metaphone_espanol($texto);
        $metaphone_producto = $this->metaphone_espanol($nombre_producto);
        
        // Verificar si el metaphone del producto está contenido en el texto
        if (strpos($metaphone_texto, $metaphone_producto) !== false) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = 0.8;
            return $resultado;
        }
        
        // Búsqueda por partes del nombre
        $palabras_producto = explode(' ', $nombre_producto);
        $coincidencias_foneticas = 0;
        
        foreach ($palabras_producto as $palabra) {
            if (strlen($palabra) <= 3) continue;
            
            $metaphone_palabra = $this->metaphone_espanol($palabra);
            if (strpos($metaphone_texto, $metaphone_palabra) !== false) {
                $coincidencias_foneticas++;
            }
        }
        
        if ($coincidencias_foneticas >= 1) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = 0.6 + ($coincidencias_foneticas * 0.1);
        }
        
        return $resultado;
    }
    
    /**
     * Metaphone adaptado para español
     */
    private function metaphone_espanol($texto) {
        // Adaptaciones para español
        $adaptaciones = array(
            'c' => array('cia' => 'sia', 'cie' => 'sie', 'cio' => 'sio', 'ciu' => 'siu'),
            'z' => 's',
            'll' => 'y',
            'ñ' => 'n',
            'ch' => 'x'
        );
        
        $texto_adaptado = $texto;
        
        // Aplicar adaptaciones
        foreach ($adaptaciones as $buscar => $reemplazar) {
            if (is_array($reemplazar)) {
                foreach ($reemplazar as $patron => $sustituto) {
                    $texto_adaptado = str_replace($patron, $sustituto, $texto_adaptado);
                }
            } else {
                $texto_adaptado = str_replace($buscar, $reemplazar, $texto_adaptado);
            }
        }
        
        return metaphone($texto_adaptado);
    }
    
    /**
     * Búsqueda por sinónimos de productos
     */
    private function buscar_por_sinonimos_producto($texto, $nombre_producto) {
        $resultado = array('encontrado' => false, 'confianza' => 0.0);
        
        $sinonimos = $this->get_sinonimos_producto($nombre_producto);
        
        foreach ($sinonimos as $sinonimo) {
            $sinonimo_normalized = $this->solutions_methods->normalizar_texto($sinonimo);
            
            if (strpos($texto, $sinonimo_normalized) !== false) {
                $resultado['encontrado'] = true;
                $resultado['confianza'] = 0.9;
                return $resultado;
            }
            
            // Búsqueda fonética del sinónimo
            $metaphone_texto = $this->metaphone_espanol($texto);
            $metaphone_sinonimo = $this->metaphone_espanol($sinonimo_normalized);
            
            if (strpos($metaphone_texto, $metaphone_sinonimo) !== false) {
                $resultado['encontrado'] = true;
                $resultado['confianza'] = 0.8;
                return $resultado;
            }
        }
        
        return $resultado;
    }
    
    /**
     * Diccionario de sinónimos para productos homeopáticos comunes
     */
    private function get_sinonimos_producto($nombre_producto) {
        $sinonimos_map = array(
            'oscillococcinum' => array('oscillo', 'oscillococcinum', 'oscillo coccinum', 'oscilococcino'),
            'árnica' => array('arnica', 'arnika', 'árnica montana'),
            'gelsemium' => array('gelsemio', 'gelsemium sempervirens'),
            'belladonna' => array('belladona', 'belladonna atropa'),
            'nux vomica' => array('nux vómica', 'nux vomica', 'nuxvomica'),
            'pulsatilla' => array('pulsatila', 'pulsatilla nigricans'),
            'ignatia amara' => array('ignatia', 'ignacia', 'ignatia amara'),
            'lycopodium' => array('licopodio', 'lycopodium clavatum'),
            'rhus toxicodendron' => array('rhus tox', 'rhus toxicodendron'),
            'ruta graveolens' => array('ruta', 'ruta graveolens'),
            'hypericum perforatum' => array('hypericum', 'hipérico'),
            'calendula officinalis' => array('calendula', 'caléndula'),
            'chamomilla' => array('manzanilla', 'chamomilla'),
            'silicea' => array('silicea', 'sílice'),
            'thuja occidentalis' => array('thuja', 'tuya')
        );
        
        $nombre_lower = strtolower($nombre_producto);
        
        // Buscar coincidencia exacta
        if (isset($sinonimos_map[$nombre_lower])) {
            return $sinonimos_map[$nombre_lower];
        }
        
        // Buscar coincidencia parcial
        foreach ($sinonimos_map as $key => $sinonimos) {
            if (strpos($nombre_lower, $key) !== false || strpos($key, $nombre_lower) !== false) {
                return $sinonimos;
            }
        }
        
        return array();
    }
    
    /**
     * Búsqueda en descripciones como último recurso
     */
    private function buscar_en_descripciones($texto, $productos) {
        $encontrados = array();
        
        foreach ($productos as $product) {
            $descripcion = $this->solutions_methods->normalizar_texto($product->get_description());
            $titulo = $this->solutions_methods->normalizar_texto($product->get_name());
            
            // Buscar palabras clave del texto en la descripción
            $palabras_busqueda = array_filter(explode(' ', $texto), function($palabra) {
                return strlen($palabra) > 3;
            });
            
            $coincidencias = 0;
            foreach ($palabras_busqueda as $palabra) {
                if (strpos($descripcion, $palabra) !== false || strpos($titulo, $palabra) !== false) {
                    $coincidencias++;
                }
            }
            
            if ($coincidencias >= 2) {
                $encontrados[] = array(
                    'product' => $product,
                    'tipo_coincidencia' => 'descripcion',
                    'confianza' => min(0.7, $coincidencias * 0.2)
                );
            }
        }
        
        return $encontrados;
    }
    
    /**
     * Obtiene todos los productos de la tienda (con cache)
     */
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
    
    // MÉTODOS RESTANTES (sin cambios significativos)
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
    
    private function sanitize_api_response($response) { 
        return wp_kses_post(trim($response)); 
    }
    
    private function get_cached_response($key) { 
        return (!$this->settings['cache_enable']) ? false : get_transient($key); 
    }
    
    private function cache_response($key, $response) { 
        if ($this->settings['cache_enable']) set_transient($key, $response, WC_AI_HOMEOPATHIC_CHAT_CACHE_TIME); 
    }
    
    private function get_whatsapp_fallback_message($user_message) {
        $whatsapp_url = $this->generate_whatsapp_url($user_message);
        return sprintf(__('Parece que hay un problema con nuestro sistema. ¿Te gustaría continuar la conversación por WhatsApp?<br><br><a href="%s" target="_blank" class="wc-ai-whatsapp-link">💬 Abrir WhatsApp</a>', 'wc-ai-homeopathic-chat'), esc_url($whatsapp_url));
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
        add_options_page('WC AI Homeopathic Chat', 'Homeopathic Chat', 'manage_options', 'wc-ai-homeopathic-chat', array($this, 'options_page'));
        
        // Añadir submenú para estadísticas de aprendizaje
        add_submenu_page(
            'options-general.php',
            'WC AI Chat Learning',
            'Chat Learning',
            'manage_options',
            'wc-ai-chat-learning',
            array($this, 'learning_stats_page')
        );
    }
    
    public function options_page() {
        // Obtener padecimientos de la clase de soluciones
        $padecimientos_humanos = $this->solutions_methods->get_padecimientos_map();
        $total_padecimientos = 0;
        foreach ($padecimientos_humanos as $categoria => $padecimientos) $total_padecimientos += count($padecimientos);
        
        // Obtener estadísticas de aprendizaje
        $learning_stats = $this->learning_engine->get_learning_stats();
        $symptom_stats = $this->symptoms_db->get_symptom_stats();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración del Chat Homeopático', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <div class="card">
                <h3><?php esc_html_e('Sistema de Análisis de Síntomas - VERSIÓN 2.5.3', 'wc-ai-homeopathic-chat'); ?></h3>
                <p><?php esc_html_e('Sistema mejorado con detección avanzada de productos homeopáticos y respuestas enfocadas.', 'wc-ai-homeopathic-chat'); ?></p>
                <p><strong><?php esc_html_e('Padecimientos configurados:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo $total_padecimientos; ?> en <?php echo count($padecimientos_humanos); ?> categorías</p>
                <p><strong><?php esc_html_e('Detección de productos:', 'wc-ai-homeopathic-chat'); ?></strong> 6 estrategias de búsqueda mejoradas</p>
                <p><strong><?php esc_html_e('Respuestas enfocadas:', 'wc-ai-homeopathic-chat'); ?></strong> Muestra solo productos mencionados cuando el usuario pregunta específicamente</p>
                <p><strong><?php esc_html_e('Efecto de escritura:', 'wc-ai-homeopathic-chat'); ?></strong> Mensajes aparecen palabra por palabra</p>
                
                <h4><?php esc_html_e('Estadísticas de Aprendizaje:', 'wc-ai-homeopathic-chat'); ?></h4>
                <p><strong><?php esc_html_e('Sugerencias totales:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo $learning_stats['total_suggestions']; ?></p>
                <p><strong><?php esc_html_e('Sugerencias pendientes:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo $learning_stats['pending_suggestions']; ?></p>
                <p><strong><?php esc_html_e('Síntomas en base de datos:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo $symptom_stats['total_symptoms']; ?></p>
                <p><strong><?php esc_html_e('Relaciones síntomas-productos:', 'wc-ai-homeopathic-chat'); ?></strong> <?php echo $symptom_stats['total_relations']; ?></p>
                
                <p><a href="<?php echo admin_url('options-general.php?page=wc-ai-chat-learning'); ?>" class="button"><?php esc_html_e('Ver más estadísticas', 'wc-ai-homeopathic-chat'); ?></a></p>
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
                            <h2><?php esc_html_e('Configuración Avanzada', 'wc-ai-homeopathic-chat'); ?></h2>
                            <table class="form-table">
                                <tr><th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp"><?php esc_html_e('Número de WhatsApp', 'wc-ai-homeopathic-chat'); ?></label></th><td><input type="text" id="wc_ai_homeopathic_chat_whatsapp" name="wc_ai_homeopathic_chat_whatsapp" value="<?php echo esc_attr($this->settings['whatsapp_number']); ?>" class="regular-text" placeholder="+521234567890" /></td></tr>
                                <tr><th scope="row"><label for="wc_ai_homeopathic_chat_whatsapp_message"><?php esc_html_e('Mensaje Predeterminado', 'wc-ai-homeopathic-chat'); ?></label></th><td><textarea id="wc_ai_homeopathic_chat_whatsapp_message" name="wc_ai_homeopathic_chat_whatsapp_message" class="large-text" rows="3"><?php echo esc_textarea($this->settings['whatsapp_message']); ?></textarea></td></tr>
                                <tr><th scope="row"><?php esc_html_e('Aprendizaje Automático', 'wc-ai-homeopathic-chat'); ?></th><td><label><input type="checkbox" name="wc_ai_homeopathic_chat_learning" value="1" <?php checked($this->settings['enable_learning'], true); ?> /> <?php esc_html_e('Habilitar aprendizaje automático', 'wc-ai-homeopathic-chat'); ?></label></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <style>
        .wc-ai-chat-settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:20px;margin:20px 0}
        .card{background:#fff;padding:20px;border:1px solid #ccd0d4;border-radius:4px}
        .wc-ai-stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin:20px 0}
        .wc-ai-stat-card{background:#f8f9fa;padding:15px;border-left:4px solid #667eea;border-radius:4px}
        .wc-ai-stat-number{font-size:24px;font-weight:bold;color:#667eea}
        .wc-ai-stat-label{font-size:14px;color:#666}
        </style>
        <?php
    }
    
    public function learning_stats_page() {
        // Obtener estadísticas
        $learning_stats = $this->learning_engine->get_learning_stats();
        $symptom_stats = $this->symptoms_db->get_symptom_stats();
        $pending_count = $this->learning_engine->get_pending_suggestions_count();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Estadísticas de Aprendizaje del Chat', 'wc-ai-homeopathic-chat'); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e('Resumen General', 'wc-ai-homeopathic-chat'); ?></h2>
                
                <div class="wc-ai-stats-grid">
                    <div class="wc-ai-stat-card">
                        <div class="wc-ai-stat-number"><?php echo $learning_stats['total_suggestions']; ?></div>
                        <div class="wc-ai-stat-label"><?php esc_html_e('Sugerencias Totales', 'wc-ai-homeopathic-chat'); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <div class="wc-ai-stat-number"><?php echo $pending_count; ?></div>
                        <div class="wc-ai-stat-label"><?php esc_html_e('Sugerencias Pendientes', 'wc-ai-homeopathic-chat'); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <div class="wc-ai-stat-number"><?php echo $learning_stats['approved_suggestions']; ?></div>
                        <div class="wc-ai-stat-label"><?php esc_html_e('Sugerencias Aprobadas', 'wc-ai-homeopathic-chat'); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <div class="wc-ai-stat-number"><?php echo $learning_stats['auto_approved_suggestions']; ?></div>
                        <div class="wc-ai-stat-label"><?php esc_html_e('Auto Aprobadas', 'wc-ai-homeopathic-chat'); ?></div>
                    </div>
                </div>
                
                <div class="wc-ai-stats-grid" style="margin-top: 20px;">
                    <div class="wc-ai-stat-card">
                        <div class="wc-ai-stat-number"><?php echo $symptom_stats['total_symptoms']; ?></div>
                        <div class="wc-ai-stat-label"><?php esc_html_e('Síntomas Registrados', 'wc-ai-homeopathic-chat'); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <div class="wc-ai-stat-number"><?php echo $symptom_stats['total_relations']; ?></div>
                        <div class="wc-ai-stat-label"><?php esc_html_e('Relaciones Síntomas-Productos', 'wc-ai-homeopathic-chat'); ?></div>
                    </div>
                    
                    <div class="wc-ai-stat-card">
                        <div class="wc-ai-stat-number"><?php echo $symptom_stats['products_with_symptoms']; ?></div>
                        <div class="wc-ai-stat-label"><?php esc_html_e('Productos con Síntomas', 'wc-ai-homeopathic-chat'); ?></div>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <h3><?php esc_html_e('Acciones', 'wc-ai-homeopathic-chat'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('wc_ai_chat_learning_actions', 'wc_ai_chat_learning_nonce'); ?>
                        <p>
                            <button type="submit" name="auto_approve_now" class="button button-primary">
                                <?php esc_html_e('Aprobar Sugerencias Automáticamente', 'wc-ai-homeopathic-chat'); ?>
                            </button>
                            <span class="description"><?php esc_html_e('Aprueba sugerencias con alta confianza (>90%)', 'wc-ai-homeopathic-chat'); ?></span>
                        </p>
                    </form>
                    
                    <?php
                    if (isset($_POST['auto_approve_now']) && wp_verify_nonce($_POST['wc_ai_chat_learning_nonce'], 'wc_ai_chat_learning_actions')) {
                        $approved = $this->learning_engine->auto_approve_high_confidence_suggestions();
                        echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('Se aprobaron %d sugerencias automáticamente.', 'wc-ai-homeopathic-chat'), $approved) . '</p></div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=wc-ai-homeopathic-chat') . '">' . 
                        __('Configuración', 'wc-ai-homeopathic-chat') . '</a>';
        $learning_link = '<a href="' . admin_url('options-general.php?page=wc-ai-chat-learning') . '">' . 
                        __('Aprendizaje', 'wc-ai-homeopathic-chat') . '</a>';
        array_unshift($links, $learning_link, $settings_link);
        return $links;
    }
    
    public function woocommerce_missing_notice() {
        ?><div class="error"><p><?php esc_html_e('WC AI Homeopathic Chat requiere WooCommerce.', 'wc-ai-homeopathic-chat'); ?></p></div><?php
    }
}

new WC_AI_Homeopathic_Chat();