<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_AI_Chat_Learning_Engine {
    private $wpdb;
    private $table_suggestions;
    private $symptoms_db;
    
    public function __construct($symptoms_db) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_suggestions = $wpdb->prefix . 'wc_ai_chat_learning_suggestions';
        $this->symptoms_db = $symptoms_db;
    }
    
    public function analyze_conversation($user_message, $ai_response) {
        $analysis = array(
            'detected_symptoms' => $this->extract_symptoms($user_message),
            'detected_products' => $this->extract_products($ai_response),
            'confidence_score' => 0
        );
        
        if (!empty($analysis['detected_symptoms']) && !empty($analysis['detected_products'])) {
            $analysis['confidence_score'] = $this->calculate_confidence_score($analysis);
            
            if ($analysis['confidence_score'] >= 0.7) {
                $this->save_learning_suggestion($user_message, $ai_response, $analysis);
            }
        }
        
        return $analysis;
    }
    
    private function extract_symptoms($message) {
        $symptoms = array();
        $common_symptoms = $this->get_common_symptom_patterns();
        
        foreach ($common_symptoms as $symptom => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($message, $pattern) !== false) {
                    $symptoms[] = $symptom;
                    break;
                }
            }
        }
        
        $db_symptoms = $this->symptoms_db->search_symptoms($message, 5);
        foreach ($db_symptoms as $symptom) {
            $symptoms[] = $symptom->symptom_name;
        }
        
        return array_unique($symptoms);
    }
    
    private function extract_products($ai_response) {
        $products = array();
        $all_products = $this->get_all_product_names();
        
        foreach ($all_products as $product_name) {
            if (stripos($ai_response, $product_name) !== false) {
                $products[] = $product_name;
            }
        }
        
        preg_match_all('/https?:\/\/[^\s]+/i', $ai_response, $url_matches);
        foreach ($url_matches[0] as $url) {
            $product_id = url_to_postid($url);
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $products[] = $product->get_name();
                }
            }
        }
        
        return array_unique($products);
    }
    
    private function calculate_confidence_score($analysis) {
        $score = 0;
        
        $score += min(count($analysis['detected_symptoms']) * 0.2, 0.4);
        $score += min(count($analysis['detected_products']) * 0.2, 0.4);
        
        foreach ($analysis['detected_products'] as $product_name) {
            if ($this->product_exists($product_name)) {
                $score += 0.1;
            }
        }
        
        return min($score, 1.0);
    }
    
    public function save_learning_suggestion($user_message, $ai_response, $analysis) {
        return $this->wpdb->insert(
            $this->table_suggestions,
            array(
                'user_message' => $user_message,
                'ai_response' => $ai_response,
                'detected_symptoms' => implode('|', $analysis['detected_symptoms']),
                'detected_products' => implode('|', $analysis['detected_products']),
                'confidence_score' => $analysis['confidence_score'],
                'status' => 'pending',
                'created_at' => current_time('mysql')
            )
        );
    }
    
    public function auto_approve_high_confidence_suggestions() {
        $suggestions = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_suggestions} 
             WHERE status = 'pending' AND confidence_score >= 0.9 
             LIMIT 10"
        );
        
        $approved_count = 0;
        foreach ($suggestions as $suggestion) {
            if ($this->process_suggestion($suggestion, true)) {
                $approved_count++;
            }
        }
        
        return $approved_count;
    }
    
    public function process_suggestion($suggestion, $auto_approve = false) {
        $symptoms = explode('|', $suggestion->detected_symptoms);
        $products = explode('|', $suggestion->detected_products);
        
        $relations_created = 0;
        
        foreach ($symptoms as $symptom_name) {
            $symptom_name = trim($symptom_name);
            if (empty($symptom_name)) continue;
            
            // Obtener o crear síntoma
            $symptom = $this->symptoms_db->get_symptom_by_name($symptom_name);
            if (!$symptom) {
                $this->symptoms_db->save_symptom(array(
                    'symptom_name' => $symptom_name,
                    'category' => 'auto_detected',
                    'severity' => 'leve'
                ));
                $symptom = $this->symptoms_db->get_symptom_by_name($symptom_name);
            }
            
            if (!$symptom) continue;
            
            foreach ($products as $product_name) {
                $product_name = trim($product_name);
                if (empty($product_name)) continue;
                
                $product_id = $this->get_product_id_by_name($product_name);
                if ($product_id && $symptom->symptom_id) {
                    $this->symptoms_db->relate_product_to_symptom(
                        $symptom->symptom_id, 
                        $product_id, 
                        intval($suggestion->confidence_score * 10),
                        'Aprendizaje automático - ' . date('Y-m-d')
                    );
                    $relations_created++;
                }
            }
        }
        
        $new_status = $auto_approve ? 'auto_approved' : 'approved';
        $this->wpdb->update(
            $this->table_suggestions,
            array(
                'status' => $new_status,
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => $auto_approve ? 0 : get_current_user_id()
            ),
            array('suggestion_id' => $suggestion->suggestion_id)
        );
        
        return $relations_created > 0;
    }
    
    private function get_common_symptom_patterns() {
        return array(
            'dolor de cabeza' => array('dolor de cabeza', 'cefalea', 'migraña', 'jaqueca', 'duele la cabeza'),
            'insomnio' => array('insomnio', 'no puedo dormir', 'dificultad para dormir', 'problemas de sueño'),
            'ansiedad' => array('ansiedad', 'nervios', 'estrés', 'angustia', 'preocupación'),
            'dolor muscular' => array('dolor muscular', 'contractura', 'calambre', 'dolor espalda', 'lumbalgia'),
            'gripe' => array('gripe', 'resfriado', 'congestión', 'tos', 'fiebre', 'catarro'),
            'digestión' => array('digestión', 'acidez', 'gastritis', 'estreñimiento', 'diarrea', 'indigestión'),
            'alergia' => array('alergia', 'estornudos', 'picazón', 'rinitis'),
            'artritis' => array('artritis', 'dolor articular', 'rigidez'),
            'depresión' => array('depresión', 'tristeza', 'desánimo', 'apatía'),
            'fatiga' => array('fatiga', 'cansancio', 'agotamiento', 'debilidad')
        );
    }
    
    private function get_all_product_names() {
        $products = get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish'
        ));
        
        $names = array();
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $names[] = $product->get_name();
            }
        }
        
        return $names;
    }
    
    private function product_exists($product_name) {
        $existing = get_page_by_title($product_name, OBJECT, 'product');
        return $existing !== null;
    }
    
    private function get_product_id_by_name($product_name) {
        $product = get_page_by_title($product_name, OBJECT, 'product');
        return $product ? $product->ID : false;
    }
    
    public function get_pending_suggestions_count() {
        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_suggestions} WHERE status = 'pending'"
        );
    }
    
    public function get_learning_stats() {
        return array(
            'total_suggestions' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_suggestions}"),
            'pending_suggestions' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_suggestions} WHERE status = 'pending'"),
            'approved_suggestions' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_suggestions} WHERE status = 'approved'"),
            'auto_approved_suggestions' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_suggestions} WHERE status = 'auto_approved'")
        );
    }
}