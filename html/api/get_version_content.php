<?php

// 1. FORZAR REPORTE DE ERRORES VISIBLE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Aumentar memoria por si el archivo es grande
ini_set('memory_limit', '512M');

try {
    // 2. VERIFICAR RUTAS
    if (!file_exists('../db.php')) throw new Exception("No se encuentra el archivo ../db.php");
    
    // 3. CARGA DE DEPENDENCIAS (Con atrapado de errores)
    require '../db.php';
    require '../auth.php';
    // functions.php a veces tiene conflictos si re-define constantes, lo cargamos con cuidado
    require_once '../functions.php';

    // 4. VERIFICAR SESIÓN
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("No hay sesión de usuario activa.");
    }

    // 5. VERIFICAR CONSTANTES CRÍTICAS (Las del .env)
    if (!defined('ENCRYPTION_KEY')) throw new Exception("La constante ENCRYPTION_KEY no está definida. Revisa db.php y .env");
    
    // Validar que la clave tenga la longitud correcta (32 bytes para AES-256)
    $keyLen = strlen(ENCRYPTION_KEY);
    if ($keyLen !== 32) {
        throw new Exception("Error de Configuración: ENCRYPTION_KEY debe tener 32 bytes (tiene $keyLen). ¿Problema con el hash en db.php?");
    }

    // 6. OBTENER ID
    $versionId = $_GET['id'] ?? '';
    if (empty($versionId)) throw new Exception("Falta el parámetro ID en la URL.");

    // 7. BUSCAR EN BD
    $stmt = $pdo->prepare("SELECT file_path, file_name FROM contract_versions WHERE id = ?");
    $stmt->execute([$versionId]);
    $version = $stmt->fetch();

    if (!$version) throw new Exception("El archivo ID $versionId no existe en la base de datos.");

    $realPath = __DIR__ . '/../' . $version['file_path'];

    // 8. VERIFICAR ARCHIVO FÍSICO
    if (!file_exists($realPath)) {
        throw new Exception("Error de Integridad: El archivo físico no existe en la ruta: $realPath");
    }

    // 9. LEER Y DESENCRIPTAR
    $content = file_get_contents($realPath);
    if ($content === false) throw new Exception("No se pudo leer el contenido del archivo.");

    $ivLength = openssl_cipher_iv_length(CIPHER_METHOD);
    if (strlen($content) <= $ivLength) throw new Exception("El archivo está vacío o corrupto (menor que el IV).");

    $iv = substr($content, 0, $ivLength);
    $encryptedData = substr($content, $ivLength);
    
    $decrypted = openssl_decrypt($encryptedData, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);

    if ($decrypted === false) {
        throw new Exception("FALLO DE CRIPTOGRAFÍA: La contraseña del .env no coincide con la usada para guardar este archivo. OpenSSL Error: " . openssl_error_string());
    }

    // 10. RESPUESTA EXITOSA
    // Limpiamos cualquier echo anterior
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    $ext = strtolower(pathinfo($version['file_name'], PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    if ($ext == 'pdf') $mime = 'application/pdf';
    if ($ext == 'docx') $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    if ($ext == 'txt') $mime = 'text/plain';

    echo json_encode([
        'mime' => $mime,
        'name' => $version['file_name'],
        'content' => base64_encode($decrypted)
    ]);

} catch (Exception $e) {
    // Enviar error como JSON válido pero con código 500 para que el JS lo note
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode(['error' => $e->getMessage()]);
}
?>