<?php
/**
 * Template for the chat interface
 * This file is included via wp_footer()
 */
if (!defined('ABSPATH')) {
    exit;
}

// Verificar si el chat está habilitado
$enabled = get_option('wc_ai_chat_enabled', '1');
if ($enabled !== '1') {
    return;
}
?>
