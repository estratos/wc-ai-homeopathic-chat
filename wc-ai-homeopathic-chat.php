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
    exit; // Exit if accessed directly
}

define('WC_AI_CHAT_VERSION', '1.0.0');
define('WC_AI_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_AI_CHAT_PLUGIN_PATH', plugin_dir_path(__FILE__));

class WC_AI_Homeopathic_Chat {
    
    private static $instance = null;
    private $admin;
    private $frontend;
    private $product_analyzer;
    private $ai_handler;
    private $chat_sessions;
    
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
    }
    
    public function init() {
        // Verificar si WooCommerce está activo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Incluir clases necesarias
        $this->include_dependencies();
        
        // Inicializar componentes
        $this->product_analyzer = new Product_Analyzer();
        $this->ai_handler = new AI_Handler();
        $this->chat_sessions = new Chat_Sessions();
        $this->admin = new AI_Chat_Admin();
        $this->frontend = new AI_Chat_Frontend();
        
        $this->product_analyzer->init();
        $this->ai_handler->init();
        $this->chat_sessions->init();
        $this->admin->init();
        $this->frontend->init();
        
        // Cargar traducciones
        load_plugin_textdomain('wc-ai-homeopathic-chat', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    private function include_dependencies() {
        require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-product-analyzer.php';
        require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-handler.php';
        require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-chat-sessions.php';
        require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-admin.php';
        require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-frontend.php';
    }
    
    public function activate() {
        // Verificar WooCommerce primero
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Este plugin requiere WooCommerce. Por favor instala y activa WooCommerce primero.', 'wc-ai-homeopathic-chat'));
        }
        
        // Incluir clases necesarias para la activación
        $this->include_dependencies();
        
        // Crear tablas necesarias para las sesiones de chat
        $chat_sessions = new Chat_Sessions();
        $chat_sessions->create_tables();
        
        // Analizar productos existentes (solo si WooCommerce está activo)
        if (class_exists('WooCommerce')) {
            $product_analyzer = new Product_Analyzer();
            $product_analyzer->analyze_all_products();
        }
    }
    
    public function deactivate() {
        // Limpiar opciones si es necesario
        // delete_option('wc_ai_chat_products_analyzed');
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p>
                <?php 
                printf(
                    __('WC AI Homeopathic Chat requiere que %s esté instalado y activado.', 'wc-ai-homeopathic-chat'),
                    '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
                ); 
                ?>
            </p>
        </div>
        <?php
    }
    
    public function get_product_analyzer() {
        return $this->product_analyzer;
    }
    
    public function get_ai_handler() {
        return $this->ai_handler;
    }
    
    public function get_chat_sessions() {
        return $this->chat_sessions;
    }
}

// Inicializar el plugin
function wc_ai_homeopathic_chat() {
    return WC_AI_Homeopathic_Chat::get_instance();
}

// Iniciar el plugin después de que WordPress cargue
add_action('plugins_loaded', 'wc_ai_homeopathic_chat_init');

function wc_ai_homeopathic_chat_init() {
    wc_ai_homeopathic_chat();
}
