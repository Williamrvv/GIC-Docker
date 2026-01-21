<?php
// api/search_users.php
require '../db.php';
require '../auth.php';

// Seguridad: Solo usuarios logueados pueden buscar
if (!isLoggedIn()) {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');

$term = $_GET['q'] ?? '';

if (strlen($term) < 2) {
    echo json_encode([]); // Mínimo 2 letras para buscar
    exit;
}

try {
    // Buscar por Nombre O Correo
    // Limitamos a 5 resultados para no llenar la pantalla
    $stmt = $pdo->prepare("SELECT name, email, department FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 5");
    $searchTerm = "%$term%";
    $stmt->execute([$searchTerm, $searchTerm]);
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>