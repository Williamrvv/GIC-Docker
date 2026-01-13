<?php
// api/send_annex_reminder.php
require '../db.php';
require '../auth.php';
require '../functions.php';

// 1. Seguridad
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if (!hasRole(['LEGAL', 'SUPER_ADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
    exit;
}

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$contractId = $input['contract_id'] ?? '';

if (empty($contractId)) {
    echo json_encode(['success' => false, 'error' => 'ID de contrato faltante']);
    exit;
}

try {
    // 2. Obtener datos
    $stmt = $pdo->prepare("SELECT initiator_id FROM contracts WHERE id = ?");
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();

    if (!$contract) throw new Exception("Contrato no encontrado");

    $stmtUser = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmtUser->execute([$contract['initiator_id']]);
    $userInit = $stmtUser->fetch();

    if ($userInit) {
        // 3. Construir Correo
        $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/contract_detail.php?id=$contractId";
        
        $htmlBody = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; background-color: #f3f4f6; padding: 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                <div style='background-color: #7e22ce; padding: 15px 20px;'>
                    <h2 style='color: white; margin: 0; font-size: 18px;'>GIC: Acción Requerida</h2>
                </div>
                <div style='padding: 20px;'>
                    <p>Hola <strong>{$userInit['name']}</strong>,</p>
                    <p>El departamento Legal está intentando <strong>CERRAR Y ARCHIVAR</strong> el contrato <strong>$contractId</strong>, pero el expediente está incompleto.</p>
                    
                    <div style='background-color: #fff7ed; border-left: 4px solid #f97316; padding: 15px; margin: 15px 0;'>
                        <p style='margin: 0; font-weight: bold; color: #9a3412;'>⚠️ Documentos Faltantes</p>
                        <p style='margin: 5px 0 0 0; font-size: 0.9em;'>Se requiere que subas los anexos firmados pendientes.</p>
                    </div>

                    <p style='font-size: 0.9em; color: #475569;'>Por favor ingresa a la plataforma, busca la sección <strong>'Cargar Anexos'</strong> y sube los documentos solicitados.</p>

                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$link' style='background-color: #7e22ce; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Ir al Contrato</a>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        // Enviar
        $sent = sendSmtpMail($userInit['email'], "Recordatorio: Faltan Anexos ($contractId)", $htmlBody, true, $userInit['name']);
        
        if ($sent) {
            logAudit($pdo, $contractId, "Recordatorio Enviado", "Se notificó al usuario sobre anexos faltantes (Async).");
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("El servidor SMTP falló.");
        }
    } else {
        throw new Exception("Usuario solicitante no encontrado.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>