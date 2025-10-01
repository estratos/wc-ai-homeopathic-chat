<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_AI_Chat_Symptoms_DB {
    private $wpdb;
    private $table_symptoms;
    private $table_symptom_products;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_symptoms = $wpdb->prefix . 'wc_ai_chat_symptoms';
        $this->table_symptom_products = $wpdb->prefix . 'wc_ai_chat_symptom_products';
    }
    
    public function save_symptom($data) {
        $defaults = array(
            'symptom_name' => '',
            'symptom_description' => '',
            'synonyms' => '',
            'severity' => 'leve',
            'category' => 'general'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT symptom_id FROM {$this->table_symptoms} WHERE symptom_name = %s",
            $data['symptom_name']
        ));
        
        if ($existing) {
            return $this->wpdb->update(
                $this->table_symptoms,
                $data,
                array('symptom_id' => $existing)
            );
        } else {
            return $this->wpdb->insert($this->table_symptoms, $data);
        }
    }
    
    public function relate_product_to_symptom($symptom_id, $product_id, $relevance_score = 10, $notes = '') {
        return $this->wpdb->replace(
            $this->table_symptom_products,
            array(
                'symptom_id' => $symptom_id,
                'product_id' => $product_id,
                'relevance_score' => $relevance_score,
                'notes' => $notes,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    public function search_symptoms($term, $limit = 10) {
        $term = '%' . $this->wpdb->esc_like($term) . '%';
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT s.*, 
                    GROUP_CONCAT(sp.product_id) as related_products,
                    COUNT(sp.product_id) as product_count
             FROM {$this->table_symptoms} s
             LEFT JOIN {$this->table_symptom_products} sp ON s.symptom_id = sp.symptom_id
             WHERE s.symptom_name LIKE %s 
                OR s.synonyms LIKE %s
                OR s.symptom_description LIKE %s
             GROUP BY s.symptom_id
             ORDER BY 
                 CASE 
                     WHEN s.symptom_name LIKE %s THEN 1
                     WHEN s.synonyms LIKE %s THEN 2
                     ELSE 3
                 END,
                 s.severity DESC,
                 product_count DESC
             LIMIT %d",
            $term, $term, $term, $term, $term, $limit
        ));
    }
    
    public function get_products_for_symptom($symptom_id, $limit = 20) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT p.*, sp.relevance_score, sp.dosage_recommendation, sp.notes
             FROM {$this->table_symptom_products} sp
             INNER JOIN {$this->wpdb->posts} p ON sp.product_id = p.ID
             INNER JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_price'
             WHERE sp.symptom_id = %d 
                AND p.post_status = 'publish'
                AND p.post_type = 'product'
             ORDER BY sp.relevance_score DESC, pm.meta_value ASC
             LIMIT %d",
            $symptom_id, $limit
        ));
    }
    
    public function get_all_symptoms_with_products() {
        return $this->wpdb->get_results(
            "SELECT s.*, 
                    COUNT(sp.product_id) as product_count,
                    GROUP_CONCAT(DISTINCT sp.product_id) as product_ids
             FROM {$this->table_symptoms} s
             LEFT JOIN {$this->table_symptom_products} sp ON s.symptom_id = sp.symptom_id
             GROUP BY s.symptom_id
             ORDER BY s.category, s.symptom_name"
        );
    }
    
    public function get_symptom_by_name($symptom_name) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_symptoms} WHERE symptom_name = %s",
            $symptom_name
        ));
    }
    
    public function delete_symptom($symptom_id) {
        // Eliminar primero las relaciones
        $this->wpdb->delete(
            $this->table_symptom_products,
            array('symptom_id' => $symptom_id)
        );
        
        // Luego eliminar el síntoma
        return $this->wpdb->delete(
            $this->table_symptoms,
            array('symptom_id' => $symptom_id)
        );
    }
    
    public function get_product_symptoms($product_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT s.*, sp.relevance_score, sp.notes
             FROM {$this->table_symptom_products} sp
             INNER JOIN {$this->table_symptoms} s ON sp.symptom_id = s.symptom_id
             WHERE sp.product_id = %d
             ORDER BY sp.relevance_score DESC",
            $product_id
        ));
    }
}