<?php
require 'db.php';
require 'auth.php';
require 'functions.php';
requireLogin();

// CASO 1: DESCARGA DE VERSIÓN DE CONTRATO (Histórico y Actual)
if (isset($_GET['id'])) {
    // Obtener ruta y HASH de la BD
    $stmt = $pdo->prepare("SELECT file_path, file_name, file_hash FROM contract_versions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $version = $stmt->fetch();

    if (!$version) die("Archivo no encontrado.");

    $realPath = __DIR__ . '/' . $version['file_path'];

    // Pasamos el hash para verificar integridad al vuelo
    decryptAndServe($realPath, $version['file_name'], $version['file_hash']);
    exit;
}

// CASO 2: DESCARGA DE ANEXOS (Plantillas o Firmados)
if (isset($_GET['annex_tmpl']) || isset($_GET['annex_signed'])) {
    
    // Determinar qué columna buscar
    $isTemplate = isset($_GET['annex_tmpl']);
    $colPath = $isTemplate ? 'template_path' : 'signed_path';
    $colName = $isTemplate ? 'template_name' : 'signed_name';
    $annexId = $isTemplate ? $_GET['annex_tmpl'] : $_GET['annex_signed'];

    $stmt = $pdo->prepare("SELECT $colPath as path, $colName as name FROM contract_annexes WHERE id = ?");
    $stmt->execute([$annexId]);
    $file = $stmt->fetch();

    if ($file && !empty($file['path'])) {
        $realPath = __DIR__ . '/' . $file['path'];
        // Los anexos no guardan hash en su tabla actualmente, así que pasamos null
        decryptAndServe($realPath, $file['name']);
    } else {
        die("Documento anexo no encontrado o aún no ha sido cargado.");
    }
    exit;
}

// SI LLEGA AQUÍ, NO SE ENVIARON PARÁMETROS VÁLIDOS
http_response_code(400);
die("Error: Solicitud inválida. Faltan parámetros (id, annex_tmpl o annex_signed).");
?>