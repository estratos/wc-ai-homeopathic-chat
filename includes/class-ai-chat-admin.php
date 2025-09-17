<?php
class AI_Chat_Admin {
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
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
    
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Chat IA Homeopático - Configuración</h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_ai_chat_settings');
                do_settings_sections('wc-ai-homeopathic-chat');
                submit_button('Guardar Configuración');
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px; padding: 20px; background: #f6f7f7; border: 1px solid #ccd0d4;">
                <h3>Estado del Sistema</h3>
                <?php
                $api_key = get_option('wc_ai_chat_api_key');
                $enabled = get_option('wc_ai_chat_enabled', '1');
                
                echo '<p><strong>Estado:</strong> ' . ($enabled ? '✅ Activado' : '❌ Desactivado') . '</p>';
                echo '<p><strong>API Key:</strong> ' . (empty($api_key) ? '❌ No configurada' : '✅ Configurada') . '</p>';
                
                $product_analyzer = new Product_Analyzer();
                $analyzed_count = count($product_analyzer->get_analyzed_products());
                $total_products = count(get_posts(array('post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids')));
                
                echo '<p><strong>Productos analizados:</strong> ' . $analyzed_count . ' / ' . $total_products . '</p>';
                ?>
            </div>
        </div>
        
        <style>
            .card {
                border-radius: 4px;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
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
        <select name="wc_ai_chat_api_provider">
            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI</option>
            <option value="deepseek" <?php selected($provider, 'deepseek'); ?>>DeepSeek</option>
        </select>
        <p class="description">Selecciona el proveedor de IA</p>
        <?php
    }
    
    public function render_api_key_field() {
        $api_key = get_option('wc_ai_chat_api_key', '');
        ?>
        <input type="password" name="wc_ai_chat_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
        <p class="description">Ingresa tu API Key</p>
        <?php
    }
    
    public function render_enabled_field() {
        $enabled = get_option('wc_ai_chat_enabled', '1');
        ?>
        <label>
            <input type="checkbox" name="wc_ai_chat_enabled" value="1" <?php checked($enabled, '1'); ?>>
            Activar chat en el frontend
        </label>
        <?php
    }
}