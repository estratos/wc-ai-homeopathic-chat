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

// Incluir clases necesarias
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-product-analyzer.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-handler.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-chat-sessions.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-admin.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-frontend.php';

class WC_AI_Homeopathic_Chat {
    
    private static $instance = null;
    public $admin;
    public $frontend;
    public $product_analyzer;
    public $ai_handler;
    public $chat_sessions;
    
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
        
        // Inicializar componentes
        $this->product_analyzer = new Product_Analyzer();
        $this->ai_handler = new AI_Handler();
        $this->chat_sessions = new Chat_Sessions();
        $this->admin = new AI_Chat_Admin();
        $this->frontend = new AI_Chat_Frontend();
        
        // Inicializar solo lo básico primero
        $this->admin->init();
        
        // Solo inicializar frontend si no es admin
        if (!is_admin()) {
            $this->frontend->init();
        }
        
        // Inicializar otros componentes después
        $this->product_analyzer->init();
        $this->ai_handler->init();
        $this->chat_sessions->init();
        
        // Cargar traducciones
        load_plugin_textdomain('wc-ai-homeopathic-chat', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-ai-homeopathic-chat') . '" style="font-weight:bold;color:#0073aa;">⚙️ Configuración</a>';
        $analyze_link = '<a href="' . admin_url('admin.php?page=wc-ai-homeopathic-chat&action=analyze') . '" style="font-weight:bold;color:#46b450;">🔍 Analizar Productos</a>';
        
        array_unshift($links, $analyze_link);
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    public function activate() {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Este plugin requiere WooCommerce. Por favor instala y activa WooCommerce primero.', 'wc-ai-homeopathic-chat'));
        }
        
        // Crear tablas necesarias
        $chat_sessions = new Chat_Sessions();
        $chat_sessions->create_tables();
        
        // Programar análisis para después de la activación
        wp_schedule_single_event(time() + 30, 'wc_ai_chat_delayed_analysis');
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('wc_ai_chat_delayed_analysis');
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>';
        echo __('WC AI Homeopathic Chat requiere que WooCommerce esté instalado y activado.', 'wc-ai-homeopathic-chat');
        echo '</p></div>';
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
function wc_ai_homeopathic_chat() {
    return WC_AI_Homeopathic_Chat::get_instance();
}

// Iniciar el plugin después de que WordPress cargue
add_action('plugins_loaded', 'wc_ai_homeopathic_chat_init');

function wc_ai_homeopathic_chat_init() {
    wc_ai_homeopathic_chat();
}