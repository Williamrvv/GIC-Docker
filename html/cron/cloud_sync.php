<?php
// cron/cloud_sync.php
// Ejecutar solo vía consola
if (php_sapi_name() !== 'cli') die("Acceso denegado.");

require_once __DIR__ . '/../db.php'; 

echo "\n--- GIC GEO-REDUNDANCIA (Modo Service Daemon) ---\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";

// === 1. CONFIGURACIÓN ===
$tenantId     = 'd65ee34b-c754-4f66-8183-35ac0ba333e9';
$clientId     = 'be99ff0d-8dd0-4b40-a9d0-e858d5cbf689';
$clientSecret = 'I668Q~KVF7SsYOVPkuVCwhJufVx6_iJjOi0e0bhm';

// CUENTA DESTINO (Debe tener OneDrive activado)
$targetUserEmail = 'william.valverde@grupoanc.com'; // <--- CAMBIAR POR EL REAL
$rootFolder      = 'GIC_Archivo_Historico';

// === 2. HELPERS ===

// Obtener Token de Aplicación (Daemon)
function getAppToken($tid, $cid, $secret) {
    $ch = curl_init("https://login.microsoftonline.com/$tid/oauth2/v2.0/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $cid,
        'client_secret' => $secret,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $json = json_decode($res, true);
    curl_close($ch);
    return $json['access_token'] ?? null;
}

// Obtener ID del usuario destino (Requiere User.Read.All)
function getUserId($token, $email) {
    $ch = curl_init("https://graph.microsoft.com/v1.0/users/$email");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $res = curl_exec($ch);
    $json = json_decode($res, true);
    curl_close($ch);
    
    if (isset($json['id'])) return $json['id'];
    
    echo "❌ Error buscando usuario: " . ($json['error']['message'] ?? 'Desconocido') . "\n";
    return false;
}

function sanitizeName($str) {
    $str = preg_replace('/[^\w\s\.-]/u', '', $str); 
    $str = preg_replace('/\s+/', '_', trim($str));
    return substr($str, 0, 50); 
}

function uploadToGraph($token, $userId, $fullPath, $fileContent) {
    // Usamos el ID del usuario para apuntar a su drive
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $fullPath)));
    $url = "https://graph.microsoft.com/v1.0/users/$userId/drive/root:/$encodedPath:/content";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/octet-stream"
    ]);
    
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code == 201 || $code == 200) {
        return json_decode($res, true)['webUrl'];
    } else {
        echo "\n   🛑 Error Subida ($code): " . (json_decode($res, true)['error']['message'] ?? $res) . "\n";
        return false;
    }
}

// === 3. EJECUCIÓN ===

echo "1. Autenticando como App... ";
$token = getAppToken($tenantId, $clientId, $clientSecret);
if (!$token) die("❌ Error: Credenciales inválidas.\n");
echo "OK.\n";

echo "2. Localizando usuario destino ($targetUserEmail)... ";
$targetUserId = getUserId($token, $targetUserEmail);
if (!$targetUserId) die("❌ Deteniendo proceso por falta de destino.\n");
echo "OK (ID: $targetUserId)\n";

// Buscar Contratos para Sincronizar
$stmt = $pdo->query("SELECT id, title, created_at FROM contracts WHERE status = 'ARCHIVED'");
$contracts = $stmt->fetchAll();

echo "3. Iniciando barrido de " . count($contracts) . " contratos...\n";

foreach ($contracts as $c) {
    $cid = $c['id'];
    $year = date('Y', strtotime($c['created_at']));
    $cleanTitle = sanitizeName($c['title']);
    $contractFolder = "$rootFolder/$year/{$cid}_{$cleanTitle}";

    // A. CONTRATO FINAL (Solo si no está sincronizado)
    $stmtVer = $pdo->prepare("SELECT id, file_path, file_name FROM contract_versions WHERE contract_id = ? AND cloud_synced = 0 ORDER BY version_number DESC LIMIT 1");
    $stmtVer->execute([$cid]);
    $finalVer = $stmtVer->fetch();

    if ($finalVer) {
        $localPath = __DIR__ . '/../' . $finalVer['file_path'];
        if (file_exists($localPath)) {
            $raw = file_get_contents($localPath);
            $ivLen = openssl_cipher_iv_length(CIPHER_METHOD);
            $decrypted = openssl_decrypt(substr($raw, $ivLen), CIPHER_METHOD, ENCRYPTION_KEY, 0, substr($raw, 0, $ivLen));

            if ($decrypted) {
                echo "   📄 Subiendo Contrato: {$finalVer['file_name']} -> ";
                $url = uploadToGraph($token, $targetUserId, "$contractFolder/{$finalVer['file_name']}", $decrypted);
                if ($url) {
                    $pdo->prepare("UPDATE contract_versions SET cloud_synced = 1, cloud_url = ? WHERE id = ?")->execute([$url, $finalVer['id']]);
                    echo "OK.\n";
                }
            }
        }
    }

    // B. ANEXOS (Solo si no están sincronizados)
    $stmtAnn = $pdo->prepare("SELECT id, signed_path, signed_name FROM contract_annexes WHERE contract_id = ? AND status = 'SIGNED' AND cloud_synced = 0");
    $stmtAnn->execute([$cid]);
    $annexes = $stmtAnn->fetchAll();

    foreach ($annexes as $ann) {
        $localPath = __DIR__ . '/../' . $ann['signed_path'];
        if (file_exists($localPath)) {
            $raw = file_get_contents($localPath);
            $ivLen = openssl_cipher_iv_length(CIPHER_METHOD);
            $decrypted = openssl_decrypt(substr($raw, $ivLen), CIPHER_METHOD, ENCRYPTION_KEY, 0, substr($raw, 0, $ivLen));

            if ($decrypted) {
                echo "   📎 Subiendo Anexo: {$ann['signed_name']} -> ";
                $url = uploadToGraph($token, $targetUserId, "$contractFolder/Anexos/{$ann['signed_name']}", $decrypted);
                if ($url) {
                    $pdo->prepare("UPDATE contract_annexes SET cloud_synced = 1, cloud_url = ? WHERE id = ?")->execute([$url, $ann['id']]);
                    echo "OK.\n";
                }
            }
        }
    }
}

echo "🏁 Sincronización Completada.\n";
?>