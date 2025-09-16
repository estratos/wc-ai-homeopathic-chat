<?php
class AI_Chat_Admin {
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Chat IA Homeopático',
            'Chat IA Homeopático',
            'manage_options',
            'wc-ai-homeopathic-chat',
            array($this, 'render_admin_page')
        );
    }
    
    public function register_settings() {
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_api_key');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_enabled');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_position');
        
        add_settings_section(
            'wc_ai_chat_main_section',
            'Configuración del Chat IA',
            array($this, 'render_section_description'),
            'wc-ai-homeopathic-chat'
        );
        
        add_settings_field(
            'wc_ai_chat_api_key',
            'API Key de OpenAI',
            array($this, 'render_api_key_field'),
            'wc-ai-homeopathic-chat',
            'wc_ai_chat_main_section'
        );
        
        add_settings_field(
            'wc_ai_chat_enabled',
            'Habilitar Chat',
            array($this, 'render_enabled_field'),
            'wc-ai-homeopathic-chat',
            'wc_ai_chat_main_section'
        );
        
        add_settings_field(
            'wc_ai_chat_position',
            'Posición del Chat',
            array($this, 'render_position_field'),
            'wc-ai-homeopathic-chat',
            'wc_ai_chat_main_section'
        );
    }
    
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Configuración del Chat IA Homeopático</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_ai_chat_settings');
                do_settings_sections('wc-ai-homeopathic-chat');
                submit_button('Guardar Configuración');
                ?>
            </form>
            
            <hr>
            
            <h2>Estado del Sistema</h2>
            
            <div class="card">
                <h3>Información de Productos Analizados</h3>
                <?php
                $product_analyzer = WC_AI_Homeopathic_Chat::get_instance()->get_product_analyzer();
                $analyzed_products = $product_analyzer->get_analyzed_products();
                ?>
                <p>Total de productos analizados: <strong><?php echo count($analyzed_products); ?></strong></p>
                
                <h3>Prueba de Conexión con OpenAI</h3>
                <?php
                $ai_handler = WC_AI_Homeopathic_Chat::get_instance()->get_ai_handler();
                $api_key = get_option('wc_ai_chat_api_key');
                
                if (empty($api_key)) {
                    echo '<p style="color: #d63638;">❌ API Key no configurada</p>';
                } else {
                    $test_result = $ai_handler->test_api_connection();
                    if ($test_result) {
                        echo '<p style="color: #00a32a;">✅ Conexión exitosa con OpenAI</p>';
                    } else {
                        echo '<p style="color: #d63638;">❌ Error en la conexión con OpenAI</p>';
                    }
                }
                ?>
            </div>
            
            <div class="card">
                <h3>Estadísticas de Chat</h3>
                <?php
                $chat_sessions = WC_AI_Homeopathic_Chat::get_instance()->get_chat_sessions();
                $total_sessions = $chat_sessions->get_total_sessions();
                $recent_sessions = $chat_sessions->get_recent_sessions(7);
                ?>
                <p>Total de sesiones de chat: <strong><?php echo $total_sessions; ?></strong></p>
                <p>Sesiones en los últimos 7 días: <strong><?php echo count($recent_sessions); ?></strong></p>
            </div>
        </div>
        <?php
    }
    
    public function render_section_description() {
        echo '<p>Configura los parámetros del chat con inteligencia artificial para recomendaciones homeopáticas.</p>';
    }
    
    public function render_api_key_field() {
        $api_key = get_option('wc_ai_chat_api_key', '');
        echo '<input type="password" name="wc_ai_chat_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">Ingresa tu API Key de OpenAI. Puedes obtenerla en <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a></p>';
    }
    
    public function render_enabled_field() {
        $enabled = get_option('wc_ai_chat_enabled', '1');
        echo '<label><input type="checkbox" name="wc_ai_chat_enabled" value="1" ' . checked('1', $enabled, false) . '> Habilitar chat en el frontend</label>';
    }
    
    public function render_position_field() {
        $position = get_option('wc_ai_chat_position', 'bottom-right');
        ?>
        <select name="wc_ai_chat_position">
            <option value="bottom-right" <?php selected($position, 'bottom-right'); ?>>Abajo a la derecha</option>
            <option value="bottom-left" <?php selected($position, 'bottom-left'); ?>>Abajo a la izquierda</option>
        </select>
        <?php
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_wc-ai-homeopathic-chat') {
            return;
        }
        
        wp_enqueue_style('wc-ai-chat-admin-style', WC_AI_CHAT_PLUGIN_URL . 'assets/css/admin.css', array(), WC_AI_CHAT_VERSION);
    }
}
