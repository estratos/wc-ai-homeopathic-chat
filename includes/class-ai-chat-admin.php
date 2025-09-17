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
        </div>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
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
        <select name="wc_ai_chat_api_provider" id="wc_ai_chat_api_provider">
            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI</option>
            <option value="deepseek" <?php selected($provider, 'deepseek'); ?>>DeepSeek</option>
        </select>
        <p class="description">Selecciona el proveedor de servicios de IA</p>
        <script>
        jQuery(document).ready(function($) {
            $('#wc_ai_chat_api_provider').change(function() {
                var provider = $(this).val();
                if (provider === 'deepseek') {
                    $('.model-description').text('Modelos disponibles de DeepSeek');
                } else {
                    $('.model-description').text('Modelos disponibles de OpenAI');
                }
            });
        });
        </script>
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
