<?php
class Product_Analyzer {
    
    private $analyzed_products = array();
    private $symptoms_keywords = array();
    private $ailments_keywords = array();
    
    public function init() {
        add_action('save_post_product', array($this, 'analyze_product_on_save'), 10, 3);
        
        // Inicializar listas de palabras clave
        $this->initialize_keyword_lists();
    }
    
    private function initialize_keyword_lists() {
        // Listas más completas de palabras clave para homeopatía
        $this->symptoms_keywords = array(
            'dolor', 'fiebre', 'inflamación', 'tos', 'estornudos', 'picazón', 
            'ardor', 'mareo', 'náuseas', 'vómito', 'diarrea', 'estreñimiento',
            'insomnio', 'ansiedad', 'estrés', 'fatiga', 'debilidad', 'picor',
            'escozor', 'quemazón', 'hinchazón', 'rigidez', 'calambre', 'espasmo',
            'palpitaciones', 'sudoración', 'escalofríos', 'temblor', 'vértigo',
            'acúfenos', 'zumbido', 'vision borrosa', 'sequedad', 'congestión',
            'expectoración', 'dificultad respirar', 'acidez', 'gases', 'flatulencia',
            'eructos', 'pérdida apetito', 'aumento apetito', 'sed', 'orinar frecuente',
            'retención líquidos', 'edema', 'erupción', 'urticaria', 'eczema',
            'caspa', 'caída cabello', 'uñas quebradizas', 'moretones', 'sangrado',
            'anemia', 'debilidad muscular', 'calambres musculares', 'dolor articular',
            'dolor espalda', 'dolor cabeza', 'migraña', 'mareo', 'confusión',
            'pérdida memoria', 'dificultad concentración', 'irritabilidad', 'tristeza',
            'depresión', 'ataque pánico', 'fobia', 'obsesión', 'compulsión'
        );
        
        $this->ailments_keywords = array(
            'gripe', 'resfriado', 'alergia', 'migraña', 'artritis', 'ansiedad',
            'depresión', 'insomnio', 'acné', 'eczema', 'psoriasis', 'asma',
            'bronquitis', 'sinusitis', 'gastritis', 'colitis', 'hemorroides',
            'conjuntivitis', 'otitis', 'faringitis', 'amigdalitis', 'laringitis',
            'rinitis', 'sinusitis', 'bronquitis', 'neumonía', 'enfisema',
            'hipertensión', 'hipotensión', 'arritmia', 'angina', 'infarto',
            'varices', 'trombosis', 'anemia', 'diabetes', 'obesidad', 'desnutrición',
            'osteoporosis', 'artrosis', 'ciática', 'hernia discal', 'fibromialgia',
            'cistitis', 'infección urinaria', 'cálculos renales', 'incontinencia',
            'impotencia', 'infertilidad', 'menopausia', 'síndrome premenstrual',
            'endometriosis', 'ovario poliquístico', 'vaginitis', 'candidiasis',
            'herpes', 'verrugas', 'hongos', 'pie atleta', 'caspa', 'alopecia',
            'caries', 'gingivitis', 'aftas', 'halitosis', 'úlcera', 'reflujo',
            'síndrome colon irritable', 'celiaquía', 'intolerancia lactosa',
            'enfermedad crohn', 'colitis ulcerosa', 'hepatitis', 'cirrosis',
            'ictericia', 'calculos biliares', 'pancreatitis'
        );
    }
    
    public function analyze_all_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );
        
        $products = get_posts($args);
        
        foreach ($products as $product_post) {
            $this->analyze_product($product_post->ID);
        }
        
        update_option('wc_ai_chat_products_analyzed', true);
        return count($products);
    }
    
    public function analyze_product_on_save($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || $post->post_type !== 'product') {
            return;
        }
        
        $this->analyze_product($post_id);
    }

    
    public function analyze_product($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return false;
        }
        
        // Obtener toda la información textual disponible
        $product_name = $product->get_name();
        $product_description = $product->get_description();
        $product_short_description = $product->get_short_description();
        
        // Obtener categorías y tags
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        $tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'));
        
        // Combinar todo el contenido para análisis
        $content_to_analyze = implode(' ', array(
            $product_name,
            $product_description,
            $product_short_description,
            implode(' ', $categories),
            implode(' ', $tags)
        ));
        
        // Convertir a minúsculas para análisis
        $content_lower = mb_strtolower($content_to_analyze, 'UTF-8');
        
        $analysis = array(
            'id' => $product_id,
            'name' => $product_name,
            'description' => $product_description,
            'short_description' => $product_short_description,
            'price' => $product->get_price(),
            'categories' => $categories,
            'tags' => $tags,
            'symptoms' => array(),
            'ailments' => array(),
            'keywords' => array(),
            'analyzed_at' => current_time('mysql'),
            'content_score' => 0
        );
        
        // Extraer síntomas del contenido
        $analysis['symptoms'] = $this->extract_keywords_from_content($content_lower, $this->symptoms_keywords);
        
        // Extraer padecimientos del contenido
        $analysis['ailments'] = $this->extract_keywords_from_content($content_lower, $this->ailments_keywords);
        
        // Extraer palabras clave generales
        $analysis['keywords'] = $this->extract_general_keywords($content_lower);
        
        // Calcular puntaje de contenido (qué tan descriptivo es el producto)
        $analysis['content_score'] = $this->calculate_content_score($analysis);
        
        // Guardar análisis
        $this->analyzed_products[$product_id] = $analysis;
        update_post_meta($product_id, '_wc_ai_chat_analysis', $analysis);
        
        return $analysis;
    }
    
public function analyze_all_products() {
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'fields' => 'ids' // Solo obtener IDs para mejor performance
    );
    
    $product_ids = get_posts($args);
    $analyzed_count = 0;
    
    foreach ($product_ids as $product_id) {
        $result = $this->analyze_product($product_id);
        if ($result) {
            $analyzed_count++;
        }
        
        // Pequeña pausa para no sobrecargar el servidor
        if (count($product_ids) > 100) {
            usleep(50000); // 50ms de pausa
        }
    }
    
    update_option('wc_ai_chat_last_analysis', current_time('mysql'));
    update_option('wc_ai_chat_analyzed_count', $analyzed_count);
    
    return $analyzed_count;
}
    
    private function extract_keywords_from_content($content, $keywords) {
        $found_keywords = array();
        
        foreach ($keywords as $keyword) {
            // Buscar la palabra clave en el contenido
            if ($this->keyword_in_content($keyword, $content)) {
                $found_keywords[] = $keyword;
            }
        }
        
        return array_unique($found_keywords);
    }
    
    private function keyword_in_content($keyword, $content) {
        // Buscar la palabra completa para evitar coincidencias parciales no deseadas
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/';
        return preg_match($pattern, $content);
    }
    
    private function extract_general_keywords($content) {
        // Extraer palabras significativas (excluyendo stop words)
        $stop_words = array('el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 
                           'de', 'del', 'al', 'por', 'para', 'con', 'sin', 'sobre',
                           'entre', 'hacia', 'desde', 'hasta', 'durante', 'mediante',
                           'versus', 'via', 'y', 'o', 'pero', 'aunque', 'porque',
                           'que', 'cual', 'cuyo', 'donde', 'cuando', 'como', 'qué');
        
        // Dividir el contenido en palabras
        $words = preg_split('/\s+/', $content);
        $words = array_map('trim', $words);
        $words = array_filter($words);
        
        // Filtrar palabras
        $meaningful_words = array();
        foreach ($words as $word) {
            $word = preg_replace('/[^\p{L}\p{N}]/u', '', $word); // Remover puntuación
            if (mb_strlen($word) > 2 && !in_array($word, $stop_words)) {
                $meaningful_words[] = $word;
            }
        }
        
        // Contar frecuencia y obtener las más comunes
        $word_count = array_count_values($meaningful_words);
        arsort($word_count);
        
        return array_slice(array_keys($word_count), 0, 20); // Top 20 palabras
    }
    
    private function calculate_content_score($analysis) {
        $score = 0;
        
        // Puntos por tener descripción
        if (!empty($analysis['description'])) {
            $score += 2;
        }
        
        // Puntos por tener descripción corta
        if (!empty($analysis['short_description'])) {
            $score += 1;
        }
        
        // Puntos por categorías
        $score += count($analysis['categories']) * 0.5;
        
        // Puntos por tags
        $score += count($analysis['tags']) * 0.3;
        
        // Puntos por síntomas identificados
        $score += count($analysis['symptoms']) * 1.5;
        
        // Puntos por padecimientos identificados
        $score += count($analysis['ailments']) * 2;
        
        return min($score, 10); // Máximo 10 puntos
    }
    
    public function get_analyzed_products() {
        if (empty($this->analyzed_products)) {
            $this->load_analyzed_products();
        }
        return $this->analyzed_products;
    }
    
    private function load_analyzed_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_wc_ai_chat_analysis',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $products = get_posts($args);
        
        foreach ($products as $product) {
            $analysis = get_post_meta($product->ID, '_wc_ai_chat_analysis', true);
            if ($analysis) {
                $this->analyzed_products[$product->ID] = $analysis;
            }
        }
    }
    
    public function find_products_by_symptoms($symptoms) {
        $products = $this->get_analyzed_products();
        $matched_products = array();
        
        foreach ($products as $product_id => $analysis) {
            $match_score = $this->calculate_match_score($symptoms, $analysis);
            
            if ($match_score > 0) {
                $matched_products[$product_id] = array(
                    'score' => $match_score,
                    'analysis' => $analysis,
                    'product' => wc_get_product($product_id)
                );
            }
        }
        
        // Ordenar por puntaje de coincidencia (mayor primero)
        uasort($matched_products, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $matched_products;
    }
    
    private function calculate_match_score($user_symptoms, $product_analysis) {
        $score = 0;
        
        // Convertir síntomas del usuario a minúsculas
        $user_symptoms_lower = array_map('mb_strtolower', $user_symptoms);
        
        // Coincidencias exactas en síntomas
        foreach ($user_symptoms_lower as $symptom) {
            if (in_array($symptom, $product_analysis['symptoms'])) {
                $score += 3; // Alta prioridad para síntomas exactos
            }
        }
        
        // Coincidencias exactas en padecimientos
        foreach ($user_symptoms_lower as $symptom) {
            if (in_array($symptom, $product_analysis['ailments'])) {
                $score += 2; // Prioridad media para padecimientos
            }
        }
        
        // Coincidencias parciales en el contenido
        $content = mb_strtolower(
            $product_analysis['name'] . ' ' . 
            $product_analysis['description'] . ' ' . 
            $product_analysis['short_description'] . ' ' .
            implode(' ', $product_analysis['categories']) . ' ' .
            implode(' ', $product_analysis['tags']),
            'UTF-8'
        );
        
        foreach ($user_symptoms_lower as $symptom) {
            if (strpos($content, $symptom) !== false) {
                $score += 1; // Prioridad baja para coincidencias parciales
            }
        }
        
        // Bonus por productos con buen contenido
        $score += $product_analysis['content_score'] * 0.1;
        
        return $score;
    }
    
    public function get_analysis_stats() {
        $products = $this->get_analyzed_products();
        $stats = array(
            'total_products' => count($products),
            'products_with_symptoms' => 0,
            'products_with_ailments' => 0,
            'average_symptoms_per_product' => 0,
            'average_ailments_per_product' => 0,
            'most_common_symptoms' => array(),
            'most_common_ailments' => array()
        );
        
        $all_symptoms = array();
        $all_ailments = array();
        
        foreach ($products as $analysis) {
            if (!empty($analysis['symptoms'])) {
                $stats['products_with_symptoms']++;
                $all_symptoms = array_merge($all_symptoms, $analysis['symptoms']);
            }
            
            if (!empty($analysis['ailments'])) {
                $stats['products_with_ailments']++;
                $all_ailments = array_merge($all_ailments, $analysis['ailments']);
            }
        }
        
        // Calcular promedios
        if ($stats['products_with_symptoms'] > 0) {
            $stats['average_symptoms_per_product'] = count($all_symptoms) / $stats['products_with_symptoms'];
        }
        
        if ($stats['products_with_ailments'] > 0) {
            $stats['average_ailments_per_product'] = count($all_ailments) / $stats['products_with_ailments'];
        }
        
        // Síntomas más comunes
        $symptom_counts = array_count_values($all_symptoms);
        arsort($symptom_counts);
        $stats['most_common_symptoms'] = array_slice($symptom_counts, 0, 10, true);
        
        // Padecimientos más comunes
        $ailment_counts = array_count_values($all_ailments);
        arsort($ailment_counts);
        $stats['most_common_ailments'] = array_slice($ailment_counts, 0, 10, true);
        
        return $stats;
    }
    
    public function regenerate_all_analyses() {
        delete_option('wc_ai_chat_products_analyzed');
        $this->analyzed_products = array();
        return $this->analyze_all_products();
    }
}
