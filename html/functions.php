<?php
// functions.php

// =============================================================
// 1. VERIFICACIÓN DE INTEGRIDAD
// =============================================================

// Verifico que db.php haya cargado correctamente las configuraciones críticas.
if (!defined('ENCRYPTION_KEY') || !defined('CIPHER_METHOD')) { 
    error_log("Error Crítico en functions.php: Constantes de seguridad no definidas.");
    die("Error del Sistema: Configuración de seguridad incompleta."); 
}

// =============================================================
// 2. FUNCIONES DE CRIPTOGRAFÍA (CORE)
// =============================================================

function encryptAndSave($sourceFile, $destPath) {
    ini_set('memory_limit', '-1');
    
    // 1. Calcular Hash del archivo ORIGINAL (para integridad futura)
    $hash = hash_file('sha256', $sourceFile);
    if ($hash === false) return false;
    
    $content = file_get_contents($sourceFile);
    if ($content === false) return false;

    // 2. Encriptar
    $ivLength = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $encrypted = openssl_encrypt($content, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
    
    // Limpiar memoria inmediata
    unset($content); 

    if ($encrypted === false) return false;
    
    // 3. Guardar (IV + Contenido Encriptado)
    $saved = file_put_contents($destPath, $iv . $encrypted);
    unset($encrypted);

    return $saved !== false ? $hash : false;
}

function decryptAndServe($filePath, $originalName, $expectedHash = null) {
    ini_set('memory_limit', '-1');

    if (!file_exists($filePath)) {
        http_response_code(404);
        die("Archivo no encontrado en el servidor.");
    }

    $content = file_get_contents($filePath);
    $ivLength = openssl_cipher_iv_length(CIPHER_METHOD);
    
    // Validación básica de integridad de archivo
    if (strlen($content) <= $ivLength) {
        die("Error: Archivo corrupto o incompleto.");
    }

    $iv = substr($content, 0, $ivLength);
    $encryptedData = substr($content, $ivLength);
    
    $decrypted = openssl_decrypt($encryptedData, CIPHER_METHOD, ENCRYPTION_KEY, 0, $iv);
    
    if ($decrypted === false) {
        error_log("Fallo desencriptación: " . openssl_error_string());
        die("Error: No se pudo desencriptar el archivo. Verifique la clave de seguridad.");
    }

    // Detección de MIME Type
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    $mimes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx'=> 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain'
    ];
    if (isset($mimes[$ext])) $mime = $mimes[$ext];

    // Servir al navegador
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($originalName) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($decrypted));

    echo $decrypted;
    exit;
}

// =============================================================
// 3. MOTOR DE NOTIFICACIONES (GIC)
// =============================================================

function sendContractNotification($pdoArg, $contractId, $subjectOrAction, $details = '', $actorName = '') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $currentUserId = $_SESSION['user_id'] ?? 0;

    // Normalización de argumentos (Soporte legacy si no se pasa $pdo)
    if ($pdoArg instanceof PDO) {
        $pdo = $pdoArg;
    } else {
        global $pdo;
        // Si no se pasó PDO, los argumentos se desplazan
        $actorName = $details;
        $details = $subjectOrAction;
        $subjectOrAction = $contractId;
        $contractId = $pdoArg; 
    }

    $stmt = $pdo->prepare("SELECT initiator_id, title, status, priority FROM contracts WHERE id = ?");
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();

    if (!$contract) return false;

    // Traducción de Estados
    $statusMap = [
        'UNREVIEWED'         => 'Pendiente de Revisión',
        'IN_REVIEW'          => 'En Revisión Legal',
        'OBSERVATIONS'       => 'Con Observaciones',
        'LEGAL_REVIEW_FINAL' => 'Revisión Final',
        'SIGNATURE'          => 'Listo para Firma',
        'ARCHIVED'           => 'Archivado',
        'CANCELLED'          => 'Cancelado',
        'CREATED'            => 'Creado',
        'APPROVED'           => 'Aprobado'
    ];

    $priorityMap = ['LOW' => 'Baja', 'MEDIUM' => 'Media', 'HIGH' => 'Alta'];

    $statusEs   = $statusMap[$contract['status']] ?? $contract['status'];
    $priorityEs = $priorityMap[$contract['priority']] ?? $contract['priority'];

    // Traducción dinámica en Asunto/Detalle
    $actionEs = $subjectOrAction;
    $detailsEs = $details;
    
    foreach ($statusMap as $eng => $esp) {
        if (strpos($actionEs, $eng) !== false) $actionEs = str_replace($eng, $esp, $actionEs);
    }

    // Selección de Destinatarios
    $recipients = []; 

    // CASO A: Nuevo Contrato
    if (stripos($subjectOrAction, 'Nuevo Contrato') !== false || $subjectOrAction === 'CREATED') {
        $sqlLegal = "SELECT id, email, name FROM users 
                     WHERE (role = 'LEGAL' OR notification_preference = 'ALL') 
                     AND is_active = 1 
                     AND notification_preference != 'NONE'";
        
        $users = $pdo->query($sqlLegal)->fetchAll();
        foreach ($users as $u) {
            if ($u['id'] == $currentUserId) continue;
            $recipients[$u['email']] = $u['name'];
        }
        
        // Notificar también al creador
        $stmtInit = $pdo->prepare("SELECT id, email, name FROM users WHERE id = ? AND notification_preference != 'NONE'");
        $stmtInit->execute([$contract['initiator_id']]);
        $initiator = $stmtInit->fetch();
        if ($initiator) $recipients[$initiator['email']] = $initiator['name'];

    } else {
        // CASO B: Actualización (Smart Routing)
        $sqlInvolved = "
            SELECT id, email, name FROM users WHERE id = ? AND is_active = 1 AND notification_preference != 'NONE'
            UNION
            SELECT u.id, u.email, u.name FROM users u INNER JOIN audit_logs al ON al.performed_by = u.id WHERE al.contract_id = ? AND u.is_active = 1 AND u.notification_preference != 'NONE'
            UNION
            SELECT u.id, u.email, u.name FROM users u INNER JOIN observations o ON o.user_id = u.id WHERE o.contract_id = ? AND u.is_active = 1 AND u.notification_preference != 'NONE'
            UNION
            SELECT id, email, name FROM users WHERE notification_preference = 'ALL' AND is_active = 1
        ";
        $stmtInv = $pdo->prepare($sqlInvolved);
        $stmtInv->execute([$contract['initiator_id'], $contractId, $contractId]);
        $involvedUsers = $stmtInv->fetchAll();

        foreach ($involvedUsers as $u) {
            if ($u['id'] == $currentUserId) continue;
            $recipients[$u['email']] = $u['name'];
        }
    }

    if (empty($recipients)) return;

    // Construcción de Links
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $contractUrl = "$protocol://$host/contract_detail.php?id=$contractId";

    // Asunto del Correo
    $cleanSubject = strip_tags($actionEs);
    $emailSubject = (strlen($cleanSubject) < 30) 
        ? "GIC: $cleanSubject - $contractId" 
        : "GIC: Actualización $contractId";
    
    // Envío a cada destinatario
    foreach ($recipients as $email => $name) {
        $htmlBody = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; background-color: #f3f4f6; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                
                <div style='background-color: #2563eb; padding: 15px 20px;'>
                    <h2 style='color: white; margin: 0; font-size: 18px;'>GIC Notificaciones</h2>
                </div>

                <div style='padding: 20px;'>
                    <p style='margin-top: 0;'>Hola <strong>$name</strong>,</p>
                    <p>Se ha registrado una actualización reciente:</p>
                    
                    <div style='background-color: #eff6ff; padding: 15px; border-left: 4px solid #3b82f6; margin: 15px 0; border-radius: 4px;'>
                        <p style='margin: 5px 0;'><strong>Evento:</strong> $actionEs</p>
                        <p style='margin: 5px 0; color: #475569;'>$detailsEs</p>
                    </div>

                    <table style='width: 100%; margin-top: 20px; border-collapse: collapse; font-size: 14px;'>
                        <tr style='background-color: #f8fafc;'>
                            <td style='padding: 8px; border: 1px solid #e2e8f0; width: 40%;'><strong>Contrato:</strong></td>
                            <td style='padding: 8px; border: 1px solid #e2e8f0;'>{$contract['title']} <span style='color:#64748b'>($contractId)</span></td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #e2e8f0;'><strong>Estado:</strong></td>
                            <td style='padding: 8px; border: 1px solid #e2e8f0;'>
                                <span style='background-color: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;'>$statusEs</span>
                            </td>
                        </tr>
                        <tr style='background-color: #f8fafc;'>
                            <td style='padding: 8px; border: 1px solid #e2e8f0;'><strong>Prioridad:</strong></td>
                            <td style='padding: 8px; border: 1px solid #e2e8f0;'>$priorityEs</td>
                        </tr>
                    </table>

                    <div style='text-align: center; margin: 30px 0 10px 0;'>
                        <a href='$contractUrl' style='background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>
                            Ver Contrato
                        </a>
                    </div>
                    
                    <p style='font-size: 0.9em; color: #64748b; margin-top: 20px; text-align: center;'>
                        Acción realizada por: <strong>$actorName</strong>
                    </p>
                </div>
                
                <div style='background-color: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #94a3b8;'>
                    GIC (Gestión Inteligente de Contratos) &copy; " . date('Y') . "
                </div>
            </div>
        </body>
        </html>
        ";
        
        sendSmtpMail($email, $emailSubject, $htmlBody, true, $name);
    }
}

// =============================================================
// 4. CLIENTE SMTP NATIVO (SOCKETS)
// =============================================================

function sendSmtpMail($to, $subject, $body, $isHtml = true, $recipientName = '', $attachments = []) {
    
    // Usamos las constantes definidas en db.php (cargadas desde .env)
    $host = SMTP_HOST; 
    $port = SMTP_PORT; 
    $user = SMTP_USER; 
    $pass = SMTP_PASS;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'GIC System';

    $boundary = "----=_NextPart_" . md5(time());
    $debugRecipient = empty($recipientName) ? $to : "$recipientName <$to>";

    $logEntry = ['Hora' => date('H:i:s'), 'Para' => $debugRecipient, 'Asunto' => $subject, 'Adjuntos' => count($attachments), 'Estado' => 'Iniciando...'];

    try {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
        $protocol = ($port == 465) ? 'ssl://' : 'tcp://';
        
        // Timeout de conexión 10s
        $socket = @stream_socket_client($protocol . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) throw new Exception("Socket Error ($errno): $errstr");

        // Funciones helper para hablar SMTP
        $read = function() use ($socket) { 
            $s=''; 
            while($str = fgets($socket, 515)) { 
                $s .= $str; 
                if(substr($str, 3, 1) == ' ') break; 
            } 
            return $s; 
        };
        $send = function($c) use ($socket) { fputs($socket, $c . "\r\n"); };

        $read(); // Banner inicial
        $send("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost')); $read();

        // STARTTLS para puerto 587
        if ($port == 587) {
            $send("STARTTLS"); 
            if (strpos($read(), '220') !== false) {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                $send("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost')); $read();
            }
        }
        
        // Autenticación
        $send("AUTH LOGIN"); $read();
        $send(base64_encode($user)); $read();
        $send(base64_encode($pass)); 
        $authRes = $read();
        
        if (strpos($authRes, '235') === false) {
            throw new Exception("Auth Fallida. Respuesta SMTP: " . trim($authRes));
        }

        // Envío
        $send("MAIL FROM: <$user>"); $read();
        $send("RCPT TO: <$to>"); $read();
        $send("DATA"); $read();

        // Construcción del Mensaje (Multipart/Mixed)
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "From: $fromName <$user>\r\n"; // Nombre personalizado
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

        $msg = "--$boundary\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $msg .= $body . "\r\n\r\n";

        // Adjuntos
        foreach ($attachments as $att) {
            if (isset($att['content']) && isset($att['name'])) {
                $msg .= "--$boundary\r\n";
                $msg .= "Content-Type: application/octet-stream; name=\"{$att['name']}\"\r\n";
                $msg .= "Content-Transfer-Encoding: base64\r\n";
                $msg .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\r\n\r\n";
                $msg .= chunk_split(base64_encode($att['content'])) . "\r\n";
            }
        }
        $msg .= "--$boundary--";

        $send($headers . "\r\n" . $msg . "\r\n."); 
        $read();
        $send("QUIT"); 
        fclose($socket);

        $logEntry['Estado'] = '✅ Enviado (SMTP OK)';
        
    } catch (Exception $e) {
        $logEntry['Estado'] = '❌ Error: ' . $e->getMessage();
        // Log al sistema para monitoreo
        error_log("GIC SMTP Error: " . $e->getMessage());
    }

    // Guardar log en sesión para depuración en footer
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['mail_debug'])) $_SESSION['mail_debug'] = [];
    $_SESSION['mail_debug'][] = $logEntry;

    return strpos($logEntry['Estado'], '✅') !== false;
}
?>