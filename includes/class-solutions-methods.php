<?php
/**
 * Class WC_AI_Chat_Solutions_Methods
 * Contiene métodos para operaciones con keywords y soluciones
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_AI_Chat_Solutions_Methods {
    
    private $padecimientos_humanos;
    private $productos_cache = array();
    
    public function __construct() {
        $this->initialize_padecimientos_map();
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
    
    public function get_padecimientos_map() {
        return $this->padecimientos_humanos;
    }
    
    public function analizar_sintomas_mejorado($message) {
        $message_normalized = $this->normalizar_texto($message);
        $categorias_detectadas = array();
        $padecimientos_encontrados = array();
        $keywords_encontradas = array();
        $confianza_total = 0;
        
        foreach ($this->padecimientos_humanos as $categoria => $padecimientos) {
            foreach ($padecimientos as $padecimiento) {
                $padecimiento_normalized = $this->normalizar_texto($padecimiento);
                
                $resultado_busqueda = $this->buscar_coincidencia_avanzada(
                    $message_normalized, 
                    $padecimiento_normalized, 
                    $padecimiento
                );
                
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
            'confianza_promedio' => count($padecimientos_encontrados) > 0 ? 
                                   $confianza_total / count($padecimientos_encontrados) : 0,
            'resumen_analisis' => $this->generar_resumen_analisis_mejorado($categorias_detectadas, $padecimientos_encontrados)
        );
    }
    
    private function buscar_coincidencia_avanzada($texto, $busqueda, $padecimiento_original) {
        $resultado = array('encontrado' => false, 'confianza' => 0.0, 'tipo' => 'ninguna');
        
        if ($texto === $busqueda || strpos($texto, $busqueda) !== false) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = 1.0;
            $resultado['tipo'] = 'exacta';
            return $resultado;
        }
        
        if (strpos($busqueda, ' ') !== false) {
            $confianza = $this->coincidencia_palabras_multiples($texto, $busqueda);
            if ($confianza >= 0.6) {
                $resultado['encontrado'] = true;
                $resultado['confianza'] = $confianza;
                $resultado['tipo'] = 'parcial_multiple';
                return $resultado;
            }
        }
        
        $confianza_sinonimos = $this->buscar_sinonimos($texto, $busqueda, $padecimiento_original);
        if ($confianza_sinonimos > 0) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = $confianza_sinonimos;
            $resultado['tipo'] = 'sinonimo';
            return $resultado;
        }
        
        $confianza_fonetica = $this->busqueda_fonetica($texto, $busqueda);
        if ($confianza_fonetica >= 0.8) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = $confianza_fonetica;
            $resultado['tipo'] = 'fonetica';
            return $resultado;
        }
        
        $confianza_subcadena = $this->busqueda_subcadena_tolerante($texto, $busqueda);
        if ($confianza_subcadena >= 0.7) {
            $resultado['encontrado'] = true;
            $resultado['confianza'] = $confianza_subcadena;
            $resultado['tipo'] = 'subcadena';
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
                    $coincidencias++;
                    break;
                }
            }
        }
        
        return $total_palabras > 0 ? $coincidencias / $total_palabras : 0;
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
        if (empty($categorias)) {
            return "No se detectaron padecimientos específicos en la descripción.";
        }
        
        $total_categorias = count($categorias);
        $total_padecimientos = count($padecimientos);
        $confianza_promedio = 0;
        
        $padecimientos_principales = array();
        foreach (array_slice($padecimientos, 0, 5) as $padecimiento) {
            $padecimientos_principales[] = $padecimiento['padecimiento'] . " (" . round($padecimiento['confianza'] * 100) . "%)";
            $confianza_promedio += $padecimiento['confianza'];
        }
        
        $confianza_promedio = $total_padecimientos > 0 ? $confianza_promedio / $total_padecimientos : 0;
        
        return sprintf(
            "Se detectaron %d padecimientos en %d categorías (confianza promedio: %d%%). Principales: %s",
            $total_padecimientos,
            $total_categorias,
            round($confianza_promedio * 100),
            implode(', ', $padecimientos_principales)
        );
    }
    
    public function normalizar_texto($texto) {
        if (!is_string($texto)) {
            return '';
        }
        
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
            'insomnio' => 'insomnio', 'insonio' => 'insomnio',
            'ansiedad' => 'ansiedad', 'ansieda' => 'ansiedad', 'ansiedá' => 'ansiedad',
            'digestion' => 'digestion', 'digestión' => 'digestion',
            'problema' => 'problema', 'enfermeda' => 'enfermedad', 'sintoma' => 'sintoma'
        );
        
        return strtr($texto, $errores_comunes);
    }
    
    public function get_relevant_products_by_categories_mejorado($categorias, $padecimientos_encontrados) {
        $all_products = array();
        
        // Estrategia 1: Búsqueda por TAGS
        $products_by_tags = $this->get_products_by_tags($categorias);
        $all_products = array_merge($all_products, $products_by_tags);
        
        // Estrategia 2: Búsqueda por categorías WooCommerce
        if (empty($all_products)) {
            $products_by_categories = $this->get_products_by_wc_categories($categorias);
            $all_products = array_merge($all_products, $products_by_categories);
        }
        
        // Estrategia 3: Productos polivalentes como último recurso
        if (empty($all_products)) {
            $all_products = $this->get_polivalent_products();
        }
        
        // Limitar y formatear resultados
        return $this->format_products_info(array_slice($all_products, 0, 8));
    }
    
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
}