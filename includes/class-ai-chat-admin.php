<?php
class AI_Chat_Admin {
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Chat IA Homeopático',
            'Chat IA',
            'manage_options',
            'wc-ai-homeopathic-chat',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_wc-ai-homeopathic-chat') {
            return;
        }
        
        wp_enqueue_style('wc-ai-chat-admin', WC_AI_CHAT_PLUGIN_URL . 'assets/css/admin.css', array(), WC_AI_CHAT_VERSION);
    }
    
    public function handle_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wc-ai-homeopathic-chat') {
            return;
        }
        
        if (isset($_GET['action'])) {
            $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
            
            if (!wp_verify_nonce($nonce, 'wc_ai_chat_action')) {
                wp_die('Acción no válida');
            }
            
            switch ($_GET['action']) {
                case 'analyze':
                    $this->handle_analyze_action();
                    break;
                    
                case 'test_connection':
                    $this->handle_test_connection();
                    break;
                    
                case 'debug_api':
                    $this->handle_debug_api();
                    break;
            }
        }
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
                '❌ Error en la conexión con la API. Usa la herramienta de debug para más detalles.',
                'error'
            );
        }
        
        wp_redirect(admin_url('admin.php?page=wc-ai-homeopathic-chat'));
        exit;
    }
    
    private function handle_debug_api() {
        $ai_handler = new AI_Handler();
        $ai_handler->init();
        $debug_result = $ai_handler->debug_api_connection();
        
        set_transient('wc_ai_chat_debug_result', $debug_result, 60);
        
        wp_redirect(admin_url('admin.php?page=wc-ai-homeopathic-chat&tab=debug'));
        exit;
    }
    
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para acceder a esta página');
        }
        
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        ?>
        <div class="wrap">
            <h1>⚕️ Chat IA Homeopático</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=wc-ai-homeopathic-chat&tab=settings'); ?>" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    ⚙️ Configuración
                </a>
                <a href="<?php echo admin_url('admin.php?page=wc-ai-homeopathic-chat&tab=debug'); ?>" class="nav-tab <?php echo $current_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
                    🐛 Debug API
                </a>
                <a href="<?php echo admin_url('admin.php?page=wc-ai-homeopathic-chat&tab=status'); ?>" class="nav-tab <?php echo $current_tab === 'status' ? 'nav-tab-active' : ''; ?>">
                    📊 Estado
                </a>
            </h2>
            
            <?php settings_errors('wc_ai_chat_messages'); ?>
            
            <div class="wc-ai-chat-admin-content">
                <?php
                switch ($current_tab) {
                    case 'debug':
                        $this->render_debug_tab();
                        break;
                    case 'status':
                        $this->render_status_tab();
                        break;
                    default:
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_settings_tab() {
        ?>
        <!-- Botones de acción rápida -->
        <div class="wc-ai-chat-card">
            <h2>🚀 Acciones Rápidas</h2>
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=analyze'), 'wc_ai_chat_action'); ?>" class="button button-primary">
                    🔄 Analizar Productos
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=test_connection'), 'wc_ai_chat_action'); ?>" class="button button-secondary">
                    ✅ Probar Conexión
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=debug_api'), 'wc_ai_chat_action'); ?>" class="button button-secondary">
                    🐛 Debug API
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
        <?php
    }
    
    private function render_debug_tab() {
        $debug_result = get_transient('wc_ai_chat_debug_result');
        $api_key = get_option('wc_ai_chat_api_key');
        $provider = get_option('wc_ai_chat_api_provider', 'openai');
        ?>
        
        <div class="wc-ai-chat-card">
            <h2>🐛 Debug de Conexión API</h2>
            
            <div class="debug-info">
                <h3>Información de Configuración</h3>
                <ul>
                    <li><strong>Proveedor:</strong> <?php echo esc_html($provider); ?></li>
                    <li><strong>API Key:</strong> <?php echo empty($api_key) ? '❌ No configurada' : '✅ Configurada'; ?></li>
                    <li><strong>URL API:</strong> <?php echo ($provider === 'deepseek') ? 'https://api.deepseek.com/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions'; ?></li>
                </ul>
            </div>
            
            <?php if ($debug_result) : ?>
                <div class="debug-result">
                    <h3>Resultado del Test</h3>
                    <div class="notice notice-<?php echo $debug_result['success'] ? 'success' : 'error'; ?>">
                        <p><strong><?php echo $debug_result['message']; ?></strong></p>
                    </div>
                    
                    <h4>Detalles Técnicos:</h4>
                    <pre style="background: #f6f7f7; padding: 15px; border: 1px solid #ccc; overflow: auto; max-height: 400px;"><?php 
                        echo htmlspecialchars(json_encode($debug_result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); 
                    ?></pre>
                </div>
            <?php else : ?>
                <div class="debug-action">
                    <p>Ejecuta el test de debug para obtener información detallada de la conexión.</p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wc-ai-homeopathic-chat&action=debug_api'), 'wc_ai_chat_action'); ?>" class="button button-primary">
                        🐛 Ejecutar Debug API
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="debug-tips">
                <h3>🔧 Soluciones Comunes</h3>
                <ul>
                    <li>Verifica que la API key sea correcta</li>
                    <li>Asegúrate de que la API key tenga saldo suficiente</li>
                    <li>Comprueba que no haya restricciones geográficas</li>
                    <li>Si usas DeepSeek, verifica que la key sea válida</li>
                    <li>Prueba desactivar temporalmente plugins de seguridad</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    private function render_status_tab() {
        $api_key = get_option('wc_ai_chat_api_key');
        $enabled = get_option('wc_ai_chat_enabled', '1');
        $provider = get_option('wc_ai_chat_api_provider', 'openai');
        
        $analyzer = new Product_Analyzer();
        $analyzed_count = count($analyzer->get_analyzed_products());
        $total_products = count(get_posts(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids'
        )));
        ?>
        
        <div class="wc-ai-chat-card">
            <h2>📊 Estado del Sistema</h2>
            
            <table class="widefat fixed" cellspacing="0">
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
        <?php
    }
    
    // ... (el resto de los métodos permanecen igual)
}