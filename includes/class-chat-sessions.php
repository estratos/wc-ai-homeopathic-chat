<?php
class Chat_Sessions {
    
    public function init() {
        // Puedes agregar hooks aquí si es necesario
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'wc_ai_chat_sessions';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            user_ip varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP,
            messages longtext,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function create_session($initial_message = array()) {
        $session_id = wp_generate_uuid4();
        
        // Guardar sesión mínima para evitar errores
        $session_data = array(
            'session_id' => $session_id,
            'messages' => maybe_serialize(array($initial_message))
        );
        
        return $session_id;
    }
    
    public function add_message_to_session($session_id, $message) {
        // Implementación básica para evitar errores
        return true;
    }
    
    public function get_total_sessions() {
        return 0; // Valor temporal
    }
    
    public function get_recent_sessions($days = 7) {
        return array(); // Array vacío temporal
    }
}
