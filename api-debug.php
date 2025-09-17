<?php
/**
 * Debug script para probar la API de DeepSeek manualmente
 */
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('No tienes permisos de administrador');
}

$api_key = get_option('wc_ai_chat_api_key');
$provider = get_option('wc_ai_chat_api_provider', 'openai');

echo '<h1>Debug de API - WC AI Chat</h1>';
echo '<p><strong>Proveedor:</strong> ' . $provider . '</p>';
echo '<p><strong>API Key:</strong> ' . ($api_key ? '✅ Configurada' : '❌ No configurada') . '</p>';

if (!$api_key) {
    die('API key no configurada');
}

// Configurar según el proveedor
$url = ($provider === 'deepseek') 
    ? 'https://api.deepseek.com/v1/chat/completions' 
    : 'https://api.openai.com/v1/chat/completions';

$headers = array(
    'Authorization' => 'Bearer ' . $api_key,
    'Content-Type' => 'application/json'
);

$body = array(
    'model' => ($provider === 'deepseek') ? 'deepseek-chat' : 'gpt-3.5-turbo',
    'messages' => array(
        array('role' => 'system', 'content' => 'Responde solo con OK'),
        array('role' => 'user', 'content' => 'Test de conexión')
    ),
    'temperature' => 0.7,
    'max_tokens' => 10
);

echo '<h2>Request:</h2>';
echo '<pre>';
echo 'URL: ' . $url . "\n";
echo 'Headers: ' . print_r($headers, true) . "\n";
echo 'Body: ' . json_encode($body, JSON_PRETTY_PRINT) . "\n";
echo '</pre>';

// Hacer la solicitud
$response = wp_remote_post($url, array(
    'headers' => $headers,
    'body' => json_encode($body),
    'timeout' => 30,
    'sslverify' => false
));

echo '<h2>Response:</h2>';
echo '<pre>';

if (is_wp_error($response)) {
    echo 'ERROR: ' . $response->get_error_message();
} else {
    echo 'Status: ' . wp_remote_retrieve_response_code($response) . "\n";
    echo 'Body: ' . wp_remote_retrieve_body($response);
}

echo '</pre>';