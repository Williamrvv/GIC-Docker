<?php
require '../db.php';
require '../auth.php';

if (!isLoggedIn()) { http_response_code(403); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pref = $_POST['preference'] ?? 'INVOLVED';
    $valid = ['NONE', 'INVOLVED', 'ALL'];
    
    if (in_array($pref, $valid)) {
        $stmt = $pdo->prepare("UPDATE users SET notification_preference = ? WHERE id = ?");
        $stmt->execute([$pref, $_SESSION['user_id']]);
        echo "OK";
    } else {
        http_response_code(400);
        echo "Valor inválido";
    }
}
?>