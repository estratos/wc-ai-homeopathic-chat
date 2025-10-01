<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_AI_Chat_Symptoms_DB
{
    private $table_symptoms;
    private $table_symptom_products;

    public function __construct()
    {
        global $wpdb;
        $this->table_symptoms = $wpdb->prefix . 'wc_ai_chat_symptoms';
        $this->table_symptom_products = $wpdb->prefix . 'wc_ai_chat_symptom_products';
    }

    public function search_symptoms($term, $limit = 10)
    {
        global $wpdb;
        
        if (empty($term)) {
            return array();
        }
        
        $like_term = '%' . $wpdb->esc_like($term) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, COUNT(sp.product_id) as product_count 
             FROM {$this->table_symptoms} s 
             LEFT JOIN {$this->table_symptom_products} sp ON s.symptom_id = sp.symptom_id 
             WHERE s.symptom_name LIKE %s OR s.synonyms LIKE %s 
             GROUP BY s.symptom_id 
             ORDER BY 
                 CASE 
                     WHEN s.symptom_name LIKE %s THEN 1 
                     ELSE 2 
                 END,
                 product_count DESC 
             LIMIT %d",
            $like_term, $like_term, $like_term, $limit
        ));
    }

    public function get_products_for_symptom($symptom_id)
    {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, sp.relevance_score 
             FROM {$this->table_symptom_products} sp 
             INNER JOIN {$wpdb->posts} p ON sp.product_id = p.ID 
             WHERE sp.symptom_id = %d AND p.post_status = 'publish' 
             ORDER BY sp.relevance_score DESC",
            $symptom_id
        ));
    }

    public function save_symptom($symptom_data)
    {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table_symptoms,
            $symptom_data,
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false ? $wpdb->insert_id : false;
    }

    // NUEVOS MÉTODOS REQUERIDOS POR LA CLASE DE APRENDIZAJE
    
    public function get_symptom_by_name($symptom_name)
    {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_symptoms} WHERE symptom_name = %s",
            $symptom_name
        ));
    }

    public function relate_product_to_symptom($symptom_id, $product_id, $relevance_score = 10, $notes = '')
    {
        global $wpdb;
        
        // Verificar si la relación ya existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT relation_id FROM {$this->table_symptom_products} 
             WHERE symptom_id = %d AND product_id = %d",
            $symptom_id, $product_id
        ));
        
        if ($existing) {
            // Actualizar relación existente
            return $wpdb->update(
                $this->table_symptom_products,
                array(
                    'relevance_score' => $relevance_score,
                    'notes' => $notes,
                    'created_at' => current_time('mysql')
                ),
                array('relation_id' => $existing)
            );
        } else {
            // Crear nueva relación
            return $wpdb->insert(
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
    }

    public function get_all_symptoms()
    {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_symptoms} ORDER BY symptom_name"
        );
    }

    public function get_symptom_stats()
    {
        global $wpdb;
        
        return array(
            'total_symptoms' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_symptoms}"),
            'total_relations' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_symptom_products}"),
            'products_with_symptoms' => $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM {$this->table_symptom_products}")
        );
    }

    public function delete_symptom($symptom_id)
    {
        global $wpdb;
        
        // Eliminar relaciones primero
        $wpdb->delete(
            $this->table_symptom_products,
            array('symptom_id' => $symptom_id)
        );
        
        // Eliminar síntoma
        return $wpdb->delete(
            $this->table_symptoms,
            array('symptom_id' => $symptom_id)
        );
    }
}