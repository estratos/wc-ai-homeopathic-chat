<?php
class Product_Analyzer {
    
    private $analyzed_products = array();
    
    public function init() {
        add_action('save_post_product', array($this, 'analyze_product_on_save'), 10, 3);
    }
    
    public function analyze_all_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 50, // Limitar para no sobrecargar
            'post_status' => 'publish',
            'fields' => 'ids'
        );
        
        $product_ids = get_posts($args);
        $analyzed_count = 0;
        
        foreach ($product_ids as $product_id) {
            if ($this->analyze_product($product_id)) {
                $analyzed_count++;
            }
        }
        
        error_log('WC AI Chat: Analizados ' . $analyzed_count . ' productos');
        return $analyzed_count;
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
            'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
            'tags' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
            'analyzed_at' => current_time('mysql')
        );
        
        // Guardar análisis
        update_post_meta($product_id, '_wc_ai_chat_analysis', $analysis);
        
        return $analysis;
    }
    
    public function get_analyzed_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_wc_ai_chat_analysis',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        );
        
        return get_posts($args);
    }
    
    public function find_products_by_query($query) {
        // Búsqueda simple por ahora
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            's' => $query
        );
        
        $products = get_posts($args);
        $results = array();
        
        foreach ($products as $product) {
            $analysis = get_post_meta($product->ID, '_wc_ai_chat_analysis', true);
            if ($analysis) {
                $results[$product->ID] = array(
                    'analysis' => $analysis,
                    'product' => wc_get_product($product->ID)
                );
            }
        }
        
        return $results;
    }
}