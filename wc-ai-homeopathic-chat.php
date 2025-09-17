<?php
/**
 * Plugin Name: WC AI Homeopathic Chat
 * Plugin URI: https://tu-sitio.com
 * Description: Chat con IA para recomendar productos homeopáticos basados en síntomas
 * Version: 1.0.0
 * Author: Tu Nombre
 * License: GPL v2 or later
 * Text Domain: wc-ai-homeopathic-chat
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WC_AI_CHAT_VERSION', '1.0.0');
define('WC_AI_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));

class WC_AI_Homeopathic_Chat {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Añadir enlaces de acción inmediatamente
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
    }
    
    public function init() {
        // Verificar si WooCommerce está activo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Incluir clases necesarias
        $this->include_dependencies();
        
        // Inicializar administración
        $this->init_admin();
        
        // Inicializar frontend solo si no es admin
        if (!is_admin()) {
            $this->init_frontend();
        }
        
        // Cargar traducciones
        load_plugin_textdomain('wc-ai-homeopathic-chat', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function include_dependencies() {
        $includes_path = WC_AI_CHAT_PLUGIN_PATH . 'includes/';
        
        require_once $includes_path . 'class-product-analyzer.php';
        require_once $includes_path . 'class-ai-handler.php';
        require_once $includes_path . 'class-chat-sessions.php';
        require_once $includes_path . 'class-ai-chat-admin.php';
        require_once $includes_path . 'class-ai-chat-frontend.php';
    }
    
    private function init_admin() {
        // Crear página de administración independiente
        add_action('admin_menu', array($this, 'create_admin_menu'));
        
        // Inicializar el manejador de admin
        $this->admin = new AI_Chat_Admin();
        $this->admin->init();
    }
    
    private function init_frontend() {
        $this->frontend = new AI_Chat_Frontend();
        $this->frontend->init();
        
        $this->product_analyzer = new Product_Analyzer();
        $this->product_analyzer->init();
        
        $this->ai_handler = new AI_Handler();
        $this->ai_handler->init();
        
        $this->chat_sessions = new Chat_Sessions();
        $this->chat_sessions->init();
    }
    
    public function create_admin_menu() {
        // Menú principal independiente
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
    
    public function render_admin_page() {
        // Delegar la renderización a la clase admin
        if (isset($this->admin)) {
            $this->admin->render_admin_page();
        } else {
            echo '<div class="wrap"><h1>Error: Admin no inicializado</h1></div>';
        }
    }
    
    public function add_plugin_action_links($links) {
        $custom_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=wc-ai-homeopathic-chat') . '">' . __('Configuración', 'wc-ai-homeopathic-chat') . '</a>',
            'analyze' => '<a href="' . admin_url('admin.php?page=wc-ai-homeopathic-chat&action=analyze') . '">' . __('Analizar Productos', 'wc-ai-homeopathic-chat') . '</a>'
        );
        
        return array_merge($custom_links, $links);
    }
    
    public function activate() {
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Este plugin requiere WooCommerce. Por favor instala y activa WooCommerce primero.', 'wc-ai-homeopathic-chat'));
        }
        
        // Incluir dependencias para la activación
        $this->include_dependencies();
        
        // Crear tablas
        $chat_sessions = new Chat_Sessions();
        $chat_sessions->create_tables();
        
        // Programar análisis para después de la activación
        wp_schedule_single_event(time() + 30, 'wc_ai_chat_delayed_analysis');
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('wc_ai_chat_delayed_analysis');
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>WC AI Homeopathic Chat:</strong> 
                <?php _e('Este plugin requiere WooCommerce. Por favor instala y activa WooCommerce primero.', 'wc-ai-homeopathic-chat'); ?>
            </p>
        </div>
        <?php
    }
}

// Función para análisis diferido
function wc_ai_chat_delayed_analysis() {
    require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-product-analyzer.php';
    $analyzer = new Product_Analyzer();
    $analyzer->analyze_all_products();
}
add_action('wc_ai_chat_delayed_analysis', 'wc_ai_chat_delayed_analysis');

// Inicializar el plugin
function wc_ai_homeopathic_chat_init() {
    // Esperar a que todos los plugins estén cargados
    if (did_action('plugins_loaded')) {
        WC_AI_Homeopathic_Chat::get_instance();
    } else {
        add_action('plugins_loaded', 'wc_ai_homeopathic_chat_init');
    }
}

// Iniciar el plugin
add_action('init', 'wc_ai_homeopathic_chat_init');

// Hook para asegurar que se cargue después de WooCommerce
add_action('woocommerce_loaded', function() {
    if (class_exists('WooCommerce')) {
        WC_AI_Homeopathic_Chat::get_instance();
    }
});

// Añadir estilos para el enlace de administración
add_action('admin_head', function() {
    ?>
    <style>
        .wc-ai-chat-links {
            display: inline-block;
            margin-left: 10px;
            padding: 2px 8px;
            background: #2271b1;
            color: white;
            border-radius: 3px;
            text-decoration: none;
            font-size: 12px;
        }
        .wc-ai-chat-links:hover {
            background: #135e96;
            color: white;
        }
    </style>
    <?php
});

// Añadir enlace visible en la lista de plugins
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-ai-homeopathic-chat') . '" class="wc-ai-chat-links">⚙️ Configuración</a>';
    $analyze_link = '<a href="' . admin_url('admin.php?page=wc-ai-homeopathic-chat&action=analyze') . '" class="wc-ai-chat-links">🔍 Analizar Productos</a>';
    
    array_unshift($links, $analyze_link);
    array_unshift($links, $settings_link);
    
    return $links;
});

// Añadir enlaces de meta
add_filter('plugin_row_meta', function($links, $file) {
    if (plugin_basename(__FILE__) !== $file) {
        return $links;
    }
    
    $row_meta = array(
        'docs' => '<a href="https://github.com/tu-usuario/wc-ai-homeopathic-chat" target="_blank">Documentación</a>',
        'support' => '<a href="https://wordpress.org/support/plugin/wc-ai-homeopathic-chat" target="_blank">Soporte</a>'
    );
    
    return array_merge($links, $row_meta);
}, 10, 2);