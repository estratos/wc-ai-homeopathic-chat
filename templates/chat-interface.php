<?php
/**
 * Template for the chat interface
 * This file is included via wp_footer()
 */
if (!defined('ABSPATH')) {
    exit;
}

// Verificar si el chat está habilitado y WooCommerce está activo
$enabled = get_option('wc_ai_chat_enabled', '1');
if ($enabled !== '1' || !class_exists('WooCommerce')) {
    return;
}
?>
<!-- El chat se renderizará mediante JavaScript -->
