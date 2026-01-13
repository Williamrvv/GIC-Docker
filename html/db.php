<?php
// db.php

// Función auxiliar para leer entorno de forma robusta
function getEnvVar($key, $default = null) {
    $val = getenv($key);
    if ($val !== false) return $val;
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    return $default;
}

// 1. Obtener credenciales
$host = getEnvVar('DB_HOST', 'db');
$db   = getEnvVar('DB_NAME', 'bd_aprobador');
$user = getEnvVar('DB_USER');
$pass = getEnvVar('DB_PASS');

// Validación estricta para evitar Crash
if (!$user || !$pass) {
    // Si esto pasa, enviamos un error 503 Service Unavailable explicito
    http_response_code(503);
    die(json_encode(['error' => 'Error Crítico: No se cargaron las variables de entorno (DB_USER/PASS). Reinicie el contenedor Docker.']));
}

$charset = 'utf8mb4';

// 2. Configuración Regional
date_default_timezone_set('America/Costa_Rica');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '-06:00'");
} catch (\PDOException $e) {
    // Si es una API, devolvemos JSON
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        http_response_code(500);
        die(json_encode(['error' => 'Error de Conexión BD: ' . $e->getMessage()]));
    }
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// =======================================================
// CONFIGURACIÓN DE SEGURIDAD (ENV)
// =======================================================

$passphrase = getEnvVar('ENCRYPTION_PASSPHRASE');

if (empty($passphrase)) {
    http_response_code(500);
    die(json_encode(['error' => 'Error Crítico: Falta ENCRYPTION_PASSPHRASE en el entorno.']));
}

// Definimos constantes solo si no existen
if (!defined('ENCRYPTION_PASSPHRASE')) define('ENCRYPTION_PASSPHRASE', $passphrase);
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', hash('sha256', $passphrase, true));
if (!defined('CIPHER_METHOD')) define('CIPHER_METHOD', 'AES-256-CBC');

// =======================================================
// CONFIGURACIÓN SMTP (ENV)
// =======================================================

if (!defined('SMTP_HOST')) define('SMTP_HOST', getEnvVar('SMTP_HOST', 'smtp.office365.com'));
if (!defined('SMTP_PORT')) define('SMTP_PORT', getEnvVar('SMTP_PORT', 587));
if (!defined('SMTP_USER')) define('SMTP_USER', getEnvVar('SMTP_USER'));
if (!defined('SMTP_PASS')) define('SMTP_PASS', getEnvVar('SMTP_PASS'));
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'GIC Notificaciones');
?>