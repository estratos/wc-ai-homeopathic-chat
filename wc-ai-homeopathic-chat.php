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

// Incluir clases necesarias
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-admin.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-chat-frontend.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-product-analyzer.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-ai-handler.php';
require_once WC_AI_CHAT_PLUGIN_PATH . 'includes/class-chat-sessions.php';

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
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->product_analyzer = new Product_Analyzer();
        $this->ai_handler = new AI_Handler();
        $this->chat_sessions = new Chat_Sessions();
        $this->admin = new AI_Chat_Admin();
        $this->frontend = new AI_Chat_Frontend();
        
        // Inicializar componentes
        $this->product_analyzer->init();
        $this->ai_handler->init();
        $this->chat_sessions->init();
        $this->admin->init();
        $this->frontend->init();
        
        // Cargar traducciones
        load_plugin_textdomain('wc-ai-homeopathic-chat', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function activate() {
        // Crear tablas necesarias para las sesiones de chat
        $this->chat_sessions->create_tables();
        
        // Analizar productos existentes
        $this->product_analyzer->analyze_all_products();
    }
    
    public function deactivate() {
        // Limpiar si es necesario
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>';
        echo __('WC AI Homeopathic Chat requiere que WooCommerce esté instalado y activado.', 'wc-ai-homeopathic-chat');
        echo '</p></div>';
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
WC_AI_Homeopathic_Chat::get_instance();
