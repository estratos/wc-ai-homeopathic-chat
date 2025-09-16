<?php
class Product_Analyzer {
    
    private $analyzed_products = array();
    
    public function init() {
        add_action('save_post_product', array($this, 'analyze_product_on_save'), 10, 3);
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
        
        $analysis = array(
            'id' => $product_id,
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price' => $product->get_price(),
            'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
            'tags' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
            'attributes' => array(),
            'symptoms' => array(),
            'ailments' => array(),
            'benefits' => array(),
            'ingredients' => array(),
            'usage' => ''
        );
        
        // Extraer atributos del producto
        $attributes = $product->get_attributes();
        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product_id, $attribute->get_name());
                $analysis['attributes'][$attribute->get_name()] = wp_list_pluck($terms, 'name');
            } else {
                $analysis['attributes'][$attribute->get_name()] = $attribute->get_options();
            }
        }
        
        // Analizar contenido para extraer información homeopática
        $content_to_analyze = $product->get_name() . ' ' . 
                             $product->get_description() . ' ' . 
                             $product->get_short_description();
        
        // Extraer síntomas (usando palabras clave comunes en homeopatía)
        $symptoms = $this->extract_homeopathic_info($content_to_analyze, 'symptoms');
        $analysis['symptoms'] = $symptoms;
        
        // Extraer padecimientos
        $ailments = $this->extract_homeopathic_info($content_to_analyze, 'ailments');
        $analysis['ailments'] = $ailments;
        
        // Extraer beneficios
        $benefits = $this->extract_homeopathic_info($content_to_analyze, 'benefits');
        $analysis['benefits'] = $benefits;
        
        // Guardar análisis
        $this->analyzed_products[$product_id] = $analysis;
        update_post_meta($product_id, '_wc_ai_chat_analysis', $analysis);
        
        return $analysis;
    }
    
    private function extract_homeopathic_info($content, $type) {
        $keywords = array();
        $content = strtolower($content);
        
        // Listas de palabras clave para homeopatía (pueden extenderse)
        $keyword_lists = array(
            'symptoms' => array(
                'dolor', 'fiebre', 'inflamación', 'tos', 'estornudos', 'picazón', 
                'ardor', 'mareo', 'náuseas', 'vómito', 'diarrea', 'estreñimiento',
                'insomnio', 'ansiedad', 'estrés', 'fatiga', 'debilidad'
            ),
            'ailments' => array(
                'gripe', 'resfriado', 'alergia', 'migraña', 'artritis', 'ansiedad',
                'depresión', 'insomnio', 'acné', 'eczema', 'psoriasis', 'asma',
                'bronquitis', 'sinusitis', 'gastritis', 'colitis', 'hemorroides'
            ),
            'benefits' => array(
                'alivio', 'calma', 'reduce', 'mejora', 'fortalece', 'equilibra',
                'regula', 'combate', 'previene', 'alivia', 'mitiga', 'controla'
            )
        );
        
        if (isset($keyword_lists[$type])) {
            foreach ($keyword_lists[$type] as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    $keywords[] = $keyword;
                }
            }
        }
        
        return array_unique($keywords);
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
            $match_score = 0;
            
            // Verificar coincidencias en síntomas
            foreach ($symptoms as $symptom) {
                if (in_array($symptom, $analysis['symptoms'])) {
                    $match_score += 3;
                }
            }
            
            // Verificar coincidencias en padecimientos
            foreach ($symptoms as $symptom) {
                if (in_array($symptom, $analysis['ailments'])) {
                    $match_score += 2;
                }
            }
            
            // Verificar coincidencias en descripción
            $content = strtolower($analysis['name'] . ' ' . $analysis['description']);
            foreach ($symptoms as $symptom) {
                if (strpos($content, $symptom) !== false) {
                    $match_score += 1;
                }
            }
            
            if ($match_score > 0) {
                $matched_products[$product_id] = array(
                    'score' => $match_score,
                    'analysis' => $analysis
                );
            }
        }
        
        // Ordenar por puntaje de coincidencia
        uasort($matched_products, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $matched_products;
    }
}
