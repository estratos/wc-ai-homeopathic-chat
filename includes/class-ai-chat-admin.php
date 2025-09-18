<?php
class AI_Chat_Admin {
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_actions'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Chat IA Homeopático',
            'Chat IA',
            'manage_options', // Capacidad requerida
            'wc-ai-homeopathic-chat',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    public function register_settings() {
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_api_key');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_api_provider');
        register_setting('wc_ai_chat_settings', 'wc_ai_chat_enabled');
        
        add_settings_section(
            'wc_ai_chat_main_section',
            'Configuración Principal',
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
            'wc_ai_chat_enabled',
            'Habilitar Chat',
            array($this, 'render_enabled_field'),
            'wc-ai-homeopathic-chat',
            'wc_ai_chat_main_section'
        );
    }
    
    public function handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-ai-homeopathic-chat') {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['action']) && check_admin_referer('wc_ai_chat_action')) {
            switch ($_GET['action']) {
                case 'analyze':
                    $this->handle_analyze_action();
                    break;
                    
                case 'test_connection':
                    $this->handle_test_connection();
                    break;
            }
        }
    }
    
    private function handle_analyze_action() {
        $analyzer = new Product_Analyzer();
        $count = $analyzer->analyze_all_products();
        
        add_settings_error(
            'wc_ai_chat_messages',
            'wc_ai_chat_analyze',
            "✅ Se analizaron {$count} productos correctamente.",
            'success'
        );
    }
    
    private function handle_test_connection() {
        $ai_handler = new AI_Handler();
        $ai_handler->init();
        $result = $ai_handler->test_api_connection();
        
        if ($result) {
            add_settings_error(
                'wc_ai_chat_messages',
                'wc_ai_chat_test',
                '✅ Conexión con la API exitosa',
                'success'
            );
        } else {
            add_settings_error(
                'wc_ai_chat_messages',
                'wc_ai_chat_test',
                '❌ Error en la conexión con la API',
                'error'
            );
        }
    }
    
    public function render_admin_page() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para acceder a esta página.');
        }
        ?>
        <div class="wrap">
            <h1>⚕️ Chat IA Homeopático</h1>
            
            <?php settings_errors('wc_ai_chat_messages'); ?>
            
            <!-- Botones de acción rápida -->
            <div style="background: #f6f7f7; padding: 20px; border: 1px solid #c3c4c7; margin: 20px 0; border-radius: 4px;">
                <h2 style="margin-top: 0;">Acciones Rápidas</h2>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=analyze'), 'wc_ai_chat_action'); ?>" class="button button-primary">
                        🔄 Analizar Productos
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=test_connection'), 'wc_ai_chat_action'); ?>" class="button button-secondary">
                        ✅ Probar Conexión
                    </a>
                </p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_ai_chat_settings');
                do_settings_sections('wc-ai-homeopathic-chat');
                submit_button('Guardar Configuración', 'primary', 'submit', true);
                ?>
            </form>
            
            <!-- Panel de estado -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin: 20px 0; border-radius: 4px;">
                <h2>📊 Estado del Sistema</h2>
                
                <?php
                $api_key = get_option('wc_ai_chat_api_key');
                $enabled = get_option('wc_ai_chat_enabled', '1');
                $provider = get_option('wc_ai_chat_api_provider', 'openai');
                
                // Contar productos
                $analyzer = new Product_Analyzer();
                $analyzed_count = count($analyzer->get_analyzed_products());
                $total_products = count(get_posts(array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'fields' => 'ids'
                )));
                ?>
                
                <table class="widefat" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Parámetro</th>
                            <th>Valor</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Plugin Activado</strong></td>
                            <td>Versión <?php echo WC_AI_CHAT_VERSION; ?></td>
                            <td>✅</td>
                        </tr>
                        <tr>
                            <td><strong>WooCommerce</strong></td>
                            <td><?php echo class_exists('WooCommerce') ? 'Activado' : 'No encontrado'; ?></td>
                            <td><?php echo class_exists('WooCommerce') ? '✅' : '❌'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Chat Habilitado</strong></td>
                            <td><?php echo $enabled ? 'Sí' : 'No'; ?></td>
                            <td><?php echo $enabled ? '✅' : '❌'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Proveedor de IA</strong></td>
                            <td><?php echo esc_html($provider); ?></td>
                            <td>⚙️</td>
                        </tr>
                        <tr>
                            <td><strong>API Key Configurada</strong></td>
                            <td><?php echo empty($api_key) ? 'No' : 'Sí'; ?></td>
                            <td><?php echo empty($api_key) ? '❌' : '✅'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Productos Analizados</strong></td>
                            <td><?php echo "{$analyzed_count} de {$total_products}"; ?></td>
                            <td><?php echo $analyzed_count > 0 ? '✅' : '⚠️'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
            .widefat {
                border-spacing: 0;
                width: 100%;
            }
            .widefat th {
                text-align: left;
                padding: 12px;
                background: #f6f7f7;
            }
            .widefat td {
                padding: 12px;
                border-bottom: 1px solid #f6f7f7;
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
        <select name="wc_ai_chat_api_provider" style="width: 300px;">
            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI</option>
            <option value="deepseek" <?php selected($provider, 'deepseek'); ?>>DeepSeek</option>
        </select>
        <p class="description">Selecciona el proveedor de servicios de IA</p>
        <?php
    }
    
    public function render_api_key_field() {
        $api_key = get_option('wc_ai_chat_api_key', '');
        ?>
        <input type="password" name="wc_ai_chat_api_key" value="<?php echo esc_attr($api_key); ?>" style="width: 300px;">
        <p class="description">
            Ingresa tu API Key de 
            <?php 
            $provider = get_option('wc_ai_chat_api_provider', 'openai');
            if ($provider === 'deepseek') {
                echo '<a href="https://platform.deepseek.com/api_keys" target="_blank">DeepSeek</a>';
            } else {
                echo '<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>';
            }
            ?>
        </p>
        <?php
    }
    
    public function render_enabled_field() {
        $enabled = get_option('wc_ai_chat_enabled', '1');
        ?>
        <label>
            <input type="checkbox" name="wc_ai_chat_enabled" value="1" <?php checked($enabled, '1'); ?>>
            Activar chat en el frontend de la tienda
        </label>
        <?php
    }
}