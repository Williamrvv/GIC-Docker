<?php
require '../db.php';
require '../auth.php';

// Validar sesión
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);
$contractId = $input['id'] ?? '';

if (empty($contractId)) {
    echo json_encode(['success' => false, 'error' => 'ID faltante']);
    exit;
}

try {
    // Solo Legal o Admin pueden revertir esto
    if (!hasRole(['LEGAL', 'SUPER_ADMIN'])) {
        throw new Exception("Permisos insuficientes");
    }

    // Actualizar estado a UNREVIEWED
    // (Opcional: Se podría borrar el log de auditoría si se quisiera "borrar rastro" total, 
    // pero por seguridad solo cambiamos el estado).
    $stmt = $pdo->prepare("UPDATE contracts SET status = 'UNREVIEWED' WHERE id = ?");
    $stmt->execute([$contractId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>