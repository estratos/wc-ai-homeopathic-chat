<?php
class AI_Chat_Admin {
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_quick_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
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
    
    public function handle_quick_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-ai-homeopathic-chat') {
            return;
        }
        
        if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'wc_ai_chat_action')) {
                wp_die('Acción no permitida');
            }
            
            $action = sanitize_text_field($_GET['action']);
            
            switch ($action) {
                case 'analyze_products':
                    $this->handle_analyze_products();
                    break;
                    
                case 'test_connection':
                    $this->handle_test_connection();
                    break;
                    
                case 'clear_cache':
                    $this->handle_clear_cache();
                    break;
            }
        }
    }
    
    private function handle_analyze_products() {
        $product_analyzer = WC_AI_Homeopathic_Chat::get_instance()->get_product_analyzer();
        $count = $product_analyzer->analyze_all_products();
        
        set_transient('wc_ai_chat_notice', 'Se analizaron ' . $count . ' productos correctamente.', 30);
        wp_redirect(admin_url('admin.php?page=wc-ai-homeopathic-chat'));
        exit;
    }
    
    private function handle_test_connection() {
        $ai_handler = WC_AI_Homeopathic_Chat::get_instance()->get_ai_handler();
        $result = $ai_handler->test_api_connection();
        
        if ($result) {
            set_transient('wc_ai_chat_notice', '✅ Conexión con la API exitosa', 30);
        } else {
            set_transient('wc_ai_chat_notice', '❌ Error en la conexión con la API. Verifica tu API key.', 30);
        }
        
        wp_redirect(admin_url('admin.php?page=wc-ai-homeopathic-chat'));
        exit;
    }
    
    private function handle_clear_cache() {
        global $wpdb;
        
        // Limpiar análisis de productos
        $wpdb->query("
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key = '_wc_ai_chat_analysis'
        ");
        
        set_transient('wc_ai_chat_notice', '✅ Cache de análisis limpiado correctamente', 30);
        wp_redirect(admin_url('admin.php?page=wc-ai-homeopathic-chat'));
        exit;
    }
    
    public function show_admin_notices() {
        if ($notice = get_transient('wc_ai_chat_notice')) {
            echo '<div class="notice notice-success is-dismissible"><p>' . $notice . '</p></div>';
            delete_transient('wc_ai_chat_notice');
        }
    }
    
    public function register_settings() {
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_api_key');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_api_provider');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_model');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_enabled');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_position');
        
        add_settings_section(
            'wc_ai_chat_main_section',
            'Configuración del Chat IA',
            array($this, 'render_section_description'),
            'wc-ai-homeopathic-chat'
        );
        
        add_settings_field(
            'wc_ai_chat_api_provider',
            'Proveedor de IA',
            array($this, 'render_api_provider_field'),
            'wc-ai-homeopathic-chat',
            'wc_ai_chat_main_section'
        );
        
        add_settings_field(
            'wc_ai_chat_api_key',
            'API Key',
            array($this, 'render_api_key_field'),
            'wc-ai-homeopathic-chat',
            'wc_ai_chat_main_section'
        );
        
        add_settings_field(
            'wc_ai_chat_model',
            'Modelo de IA',
            array($this, 'render_model_field'),
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
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Mostrar mensajes de éxito/error
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'wc_ai_chat_messages',
                'wc_ai_chat_message',
                __('Configuración guardada.', 'wc-ai-homeopathic-chat'),
                'success'
            );
        }
        
        settings_errors('wc_ai_chat_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Botones de acción rápida -->
            <div style="margin: 20px 0; padding: 15px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
                <h2 style="margin-top: 0;">Acciones Rápidas</h2>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=analyze_products'), 'wc_ai_chat_action'); ?>" class="button button-primary">
                        🔄 Analizar Todos los Productos
                    </a>
                    
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=test_connection'), 'wc_ai_chat_action'); ?>" class="button button-secondary">
                        🚀 Probar Conexión API
                    </a>
                    
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=clear_cache'), 'wc_ai_chat_action'); ?>" class="button button-secondary">
                        🗑️ Limpiar Cache
                    </a>
                </p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_ai_chat_settings');
                do_settings_sections('wc-ai-homeopathic-chat');
                submit_button('Guardar Configuración');
                ?>
            </form>
            
            <hr>
            
            <h2>Estado del Sistema</h2>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin: 20px 0;">
                <h3>Información de Conexión</h3>
                <?php
                $ai_handler = WC_AI_Homeopathic_Chat::get_instance()->get_ai_handler();
                $api_key = get_option('wc_ai_chat_api_key');
                $api_provider = get_option('wc_ai_chat_api_provider', 'openai');
                $model = get_option('wc_ai_chat_model');
                
                echo '<p><strong>Proveedor:</strong> ' . esc_html($api_provider) . '</p>';
                echo '<p><strong>Modelo:</strong> ' . esc_html($model) . '</p>';
                
                if (empty($api_key)) {
                    echo '<p style="color: #d63638;"><strong>API Key:</strong> ❌ No configurada</p>';
                    echo '<p>Obtén tu API key de: ';
                    if ($api_provider === 'deepseek') {
                        echo '<a href="https://platform.deepseek.com/api_keys" target="_blank">DeepSeek Platform</a>';
                    } else {
                        echo '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>';
                    }
                    echo '</p>';
                } else {
                    echo '<p style="color: #00a32a;"><strong>API Key:</strong> ✅ Configurada</p>';
                    
                    // Test de conexión
                    echo '<p><strong>Prueba de conexión:</strong> ';
                    $test_result = $ai_handler->test_api_connection();
                    if ($test_result) {
                        echo '<span style="color: #00a32a;">✅ Conexión exitosa</span>';
                    } else {
                        echo '<span style="color: #d63638;">❌ Error de conexión</span>';
                    }
                    echo '</p>';
                }
                ?>
            </div>
            
            <div class="card" style="max-width: 600px; padding: 20px; margin: 20px 0;">
                <h3>Estadísticas de Productos</h3>
                <?php
                $product_analyzer = WC_AI_Homeopathic_Chat::get_instance()->get_product_analyzer();
                $analyzed_products = $product_analyzer->get_analyzed_products();
                $total_products = wc_get_products(array('limit' => -1, 'return' => 'ids'));
                
                echo '<p><strong>Total de productos:</strong> ' . count($total_products) . '</p>';
                echo '<p><strong>Productos analizados:</strong> ' . count($analyzed_products) . '</p>';
                echo '<p><strong>Porcentaje completado:</strong> ' . round((count($analyzed_products) / max(1, count($total_products))) * 100) . '%</p>';
                ?>
            </div>
        </div>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            }
            .button {
                margin-right: 10px;
            }
        </style>
        <?php
    }
    
    public function render_section_description() {
        echo '<p>Configura los parámetros del chat con inteligencia artificial para recomendaciones homeopáticas.</p>';
    }
    
    public function render_api_provider_field() {
        $provider = get_option('wc_ai_chat_api_provider', 'openai');
        ?>
        <select name="wc_ai_chat_api_provider" id="wc_ai_chat_api_provider">
            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI</option>
            <option value="deepseek" <?php selected($provider, 'deepseek'); ?>>DeepSeek</option>
        </select>
        <p class="description">Selecciona el proveedor de servicios de IA</p>
        <?php
    }
    
    public function render_api_key_field() {
        $api_key = get_option('wc_ai_chat_api_key', '');
        echo '<input type="password" name="wc_ai_chat_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">Ingresa tu API Key del proveedor seleccionado</p>';
    }
    
    public function render_model_field() {
        $ai_handler = WC_AI_Homeopathic_Chat::get_instance()->get_ai_handler();
        $current_model = get_option('wc_ai_chat_model');
        $models = $ai_handler->get_available_models();
        ?>
        <select name="wc_ai_chat_model">
            <?php foreach ($models as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_model, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description model-description">
            <?php 
            $provider = get_option('wc_ai_chat_api_provider', 'openai');
            if ($provider === 'deepseek') {
                echo 'Modelos disponibles de DeepSeek';
            } else {
                echo 'Modelos disponibles de OpenAI';
            }
            ?>
        </p>
        <?php
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
        <p class="description">Posición del botón de chat en la página</p>
        <?php
    }
}
