<?php
// cron/backup_nas.php
if (php_sapi_name() !== 'cli') die("Solo consola.");

require_once __DIR__ . '/../db.php'; 

// CONFIGURACIÓN DE LOGS
$logFile = __DIR__ . '/nas_sync.log';

// Función para escribir en consola y archivo al mismo tiempo
function logger($msg, $type = 'INFO') {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    $formattedMsg = "[$date] [$type] $msg";
    
    // Escribir en archivo (Append)
    file_put_contents($logFile, $formattedMsg . PHP_EOL, FILE_APPEND);
    
    // Escribir en consola
    echo $formattedMsg . PHP_EOL;
}

logger("--- INICIO DE RESPALDO NAS GIC ---");

// 1. CARGAR CREDENCIALES
$nasHost = getEnvVar('NAS_HOST');
$nasUser = getEnvVar('NAS_USER');
$nasPass = getEnvVar('NAS_PASS'); 
$nasRoot = getEnvVar('NAS_FOLDER');

if (!$nasHost || !$nasPass) {
    logger("Error Crítico: Faltan credenciales NAS.", 'ERROR');
    exit(1);
}

// 2. HELPER: COMANDO SMB
function runSmbCommand($command) {
    global $nasHost, $nasUser, $nasPass;
    $auth = "-U " . escapeshellarg("$nasUser%$nasPass");
    // Opciones para evitar timeouts y mejorar compatibilidad
    $opts = "--option='client use spnego = no' --option='client ntlmv2 auth = yes' -W WORKGROUP --timeout=120"; 
    $fullCmd = "smbclient " . escapeshellarg($nasHost) . " $auth $opts -c " . escapeshellarg($command);
    return shell_exec($fullCmd . " 2>&1");
}

// 3. HELPER: LIMPIAR NOMBRE
function cleanName($str) {
    $str = preg_replace('/[^\w\s\.-]/u', '', $str);
    return trim(preg_replace('/\s+/', '_', $str));
}

// 4. HELPER: OBTENER LISTADO DE ARCHIVOS (OPTIMIZACIÓN)
// Devuelve un string con todo el contenido de la carpeta remota para buscar rápido
function getRemoteFileList($folderPath) {
    // Ejecutamos un 'ls' sobre la carpeta
    $output = runSmbCommand("cd \"$folderPath\"; ls");
    return $output; 
}

try {
    // A. Buscar Contratos Archivados
    $stmt = $pdo->query("SELECT id, title, created_at FROM contracts WHERE status = 'ARCHIVED'");
    $contracts = $stmt->fetchAll();

    logger("Contratos archivados detectados: " . count($contracts));

    // Asegurar raíz
    runSmbCommand("mkdir \"$nasRoot\"");

    foreach ($contracts as $c) {
        $cid = $c['id'];
        $year = date('Y', strtotime($c['created_at']));
        $folderName = cleanName("{$cid}_{$c['title']}");
        
        // Rutas remotas
        $remoteContractPath = "$nasRoot/$year/$folderName";
        $remoteAnnexPath = "$remoteContractPath/Anexos";

        // Estructura de carpetas (mkdir es rápido, lo ejecutamos siempre por seguridad)
        // Nota: smbclient no soporta 'mkdir -p', hay que ir paso a paso si no existen
        runSmbCommand("mkdir \"$nasRoot/$year\"; mkdir \"$remoteContractPath\"; mkdir \"$remoteAnnexPath\"");

        // --- OPTIMIZACIÓN: OBTENER SNAPSHOTS DEL CONTENIDO REMOTO ---
        // Hacemos 1 sola llamada para ver qué hay en la carpeta del contrato
        // en lugar de 1 llamada por cada archivo.
        $existingContractFiles = getRemoteFileList($remoteContractPath);
        $existingAnnexFiles = getRemoteFileList($remoteAnnexPath);

        // --- B. PROCESAR CONTRATO FINAL ---
        $stmtVer = $pdo->prepare("SELECT file_path, file_name FROM contract_versions WHERE contract_id = ? ORDER BY version_number DESC LIMIT 1");
        $stmtVer->execute([$cid]);
        $ver = $stmtVer->fetch();

        if ($ver) {
            // Verificamos contra la lista en memoria (rápido) en lugar de conectar al NAS (lento)
            if (strpos($existingContractFiles, $ver['file_name']) === false) {
                // No está en la lista, subimos
                syncFile($ver['file_path'], $ver['file_name'], $remoteContractPath);
            } else {
                // Ya existe, saltamos silenciosamente (o log debug si quieres)
            }
        }

        // --- C. PROCESAR ANEXOS ---
        $stmtAnn = $pdo->prepare("SELECT signed_path, signed_name FROM contract_annexes WHERE contract_id = ? AND status = 'SIGNED'");
        $stmtAnn->execute([$cid]);
        $annexes = $stmtAnn->fetchAll();

        foreach ($annexes as $ann) {
            // Verificamos contra la lista de anexos en memoria
            if (strpos($existingAnnexFiles, $ann['signed_name']) === false) {
                syncFile($ann['signed_path'], $ann['signed_name'], $remoteAnnexPath);
            }
        }
    }

} catch (Exception $e) {
    logger("Error Fatal en Script: " . $e->getMessage(), 'ERROR');
}

logger("--- FIN DE RESPALDO ---");


// 5. FUNCIÓN DE SUBIDA (SIN CHECK DE EXISTENCIA, YA SE HIZO ANTES)
function syncFile($localRelPath, $fileName, $remoteFolder) {
    $localFullPath = __DIR__ . '/../' . $localRelPath;
    
    if (!file_exists($localFullPath)) {
        logger("Archivo local no encontrado para respaldo: $fileName", 'WARN');
        return;
    }

    $localDir = dirname($localFullPath);
    $localBaseName = basename($localFullPath); 
    
    // Comando PUT directo
    $cmd = "cd \"$remoteFolder\"; lcd \"$localDir\"; put \"$localBaseName\" \"$fileName\"";
    $upload = runSmbCommand($cmd);

    // Analizar respuesta
    if (strpos($upload, 'putting') !== false || strpos($upload, 'average') !== false) {
        logger("Archivo respaldado exitosamente: $fileName en $remoteFolder", 'SUCCESS');
    } else {
        // Limpiamos el mensaje de error para el log (quitar saltos de linea feos)
        $cleanError = str_replace(["\r", "\n"], " ", substr($upload, 0, 200));
        logger("Fallo al subir $fileName. Detalle: $cleanError", 'ERROR');
    }
}
?>