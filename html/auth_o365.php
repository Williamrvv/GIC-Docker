<?php
// auth_o365.php

// 1. Cargar variables de entorno
$clientId     = getenv('O365_CLIENT_ID');
$tenantId     = getenv('O365_TENANT_ID');
$clientSecret = getenv('O365_CLIENT_SECRET');
$redirectUri  = getenv('O365_REDIRECT_URI');

// Validación de seguridad (Fail-fast)
if (!$clientId || !$tenantId || !$clientSecret || !$redirectUri) {
    die("Error Crítico: Faltan variables de entorno de Office 365.");
}

define('O365_CLIENT_ID',     $clientId);
define('O365_TENANT_ID',     $tenantId);
define('O365_CLIENT_SECRET', $clientSecret);
// URL exacta donde Microsoft devolverá al usuario después del login
define('O365_REDIRECT_URI',  $redirectUri);

// Endpoints de Microsoft
define('O365_AUTH_URL',      'https://login.microsoftonline.com/'.O365_TENANT_ID.'/oauth2/v2.0/authorize');
define('O365_TOKEN_URL',     'https://login.microsoftonline.com/'.O365_TENANT_ID.'/oauth2/v2.0/token');
define('O365_GRAPH_URL',     'https://graph.microsoft.com/v1.0/me');

// Función para generar la URL de Login
function getO365LoginUrl() {
    $params = [
        'client_id'     => O365_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri'  => O365_REDIRECT_URI,
        'response_mode' => 'query',
        'scope'         => 'User.Read', 
        'state'         => bin2hex(random_bytes(5)) 
    ];
    return O365_AUTH_URL . '?' . http_build_query($params);
}
?>