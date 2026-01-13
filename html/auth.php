<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // 1. Guardar la URL actual en sesión para volver después
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // 2. Redirigir al login con un mensaje
        header("Location: index.php?msg=required");
        exit;
    }

    // Headers anti-caché...
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

function hasRole($roles) {
    if (!isset($_SESSION['role'])) return false;
    if (!is_array($roles)) $roles = [$roles];
    return in_array($_SESSION['role'], $roles);
}

function logAudit($pdo, $contract_id, $action, $details) {
    if(isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (contract_id, action, details, performed_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$contract_id, $action, $details, $_SESSION['user_id']]);
    }
}
 ?>