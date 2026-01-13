<?php
// Evitar que cualquier warning ensucie el JSON
ob_start();

ini_set('display_errors', 0);
ini_set('memory_limit', '512M'); 
ini_set('max_execution_time', '90');

header('Content-Type: application/json; charset=utf-8');

// Función para terminar con error limpio
function outputError($msg, $details = null) {
    ob_clean();
    echo json_encode(['error' => $msg, 'details' => $details]);
    exit;
}

try {
    // Cargas esenciales
    if(!file_exists('../db.php')) outputError("Falta db.php");
    require '../db.php';
    require '../auth.php';

    // 1. Validar Sesión
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) outputError("Sesión expirada");

    // 2. Validar API Key
    $apiKey = "AIzaSyAh_b5v0dNLB6oScPXAOBdir6AHkOIFoxk"; 
    
    if ($apiKey === "AIzaSyAh_b5v0dNLB6oScPXAOBdir6AHkOIFoxk" || strlen($apiKey) < 10) {
        outputError("API Key de Gemini no configurada en el servidor.");
    }

    // 3. Leer Payload
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) outputError("No se recibieron datos válidos.");

    // 4. Preparar Gemini
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    $prompt = "Actúa como abogado experto. Analiza este contrato. 
    Responde ÚNICAMENTE un JSON válido (sin markdown) con:
    {
        \"category\": \"Una de: CONFIDENCIALIDAD (NDA), PRESTACIÓN DE SERVICIOS, LABORAL, ARRENDAMIENTO, LICENCIA DE SOFTWARE, COMPRAVENTA, OTRO\",
        \"summary\": \"Resumen ejecutivo (max 3 lineas)\",
        \"red_flags\": [\"Lista de riesgos\"],
        \"red_flag_count\": (int) número de riesgos
    }";

    $parts = [["text" => $prompt]];

    if (!empty($input['text'])) {
        $parts[] = ["text" => "Contenido:\n" . substr($input['text'], 0, 30000)];
    } elseif (!empty($input['content'])) {
        $parts[] = [
            "inline_data" => [
                "mime_type" => $input['mimeType'],
                "data" => $input['content']
            ]
        ];
    } else {
        outputError("El archivo está vacío o no es legible.");
    }

    $body = [
        "contents" => [["parts" => $parts]],
        "generationConfig" => ["response_mime_type" => "application/json"]
    ];

    // 5. Ejecutar CURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) outputError("Error de conexión Google", curl_error($ch));
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $errData = json_decode($response, true);
        outputError("Gemini rechazó la solicitud ($httpCode)", $errData['error']['message'] ?? $response);
    }

    // 6. Procesar Respuesta
    $result = json_decode($response, true);
    $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$text) outputError("Gemini devolvió una respuesta vacía.");

    // Limpiar Markdown ```json ... ```
    $text = preg_replace('/^```json\s*|\s*```$/', '', $text);
    
    // Validar JSON final
    if (!json_decode($text)) outputError("La IA no devolvió un JSON válido.", $text);

    ob_clean();
    echo $text;

} catch (Exception $e) {
    outputError("Error Interno", $e->getMessage());
}
?>