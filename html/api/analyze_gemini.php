<?php
// api/analyze_gemini.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('memory_limit', '512M'); 

require '../db.php';
require '../auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? 'text'; 

if (($type === 'text' && strlen($input['content'] ?? '') < 10) || 
    (($type === 'image' || $type === 'base64') && empty($input['content']))) {
    echo json_encode(['error' => 'Contenido vacío o insuficiente.']); exit;
}

// === CONFIGURACIÓN ===
// === 3. CONFIGURACIÓN (VÍA ENV) ===
$apiKey = getenv('GEMINI_API_KEY'); // Leemos del entorno Docker

if (!$apiKey) {
    echo json_encode(['error' => 'Error de Configuración: Falta GEMINI_API_KEY en variables de entorno.']);
    exit;
}

// $modelName = "gemini-3-pro-preview"; // costo: input -> $1.00, output -> $5.00 por 1M tokens
// $modelName = "gemini-3-flash-preview"; // costo: input -> $0.50, output -> $3.00 por 1M tokens
// $modelName = "gemini-2.5-pro"; // costo: input -> $1.00, output -> $5.00 por 1M tokens
 $modelName = "gemini-2.5-flash"; // costo: input -> $0.35, output -> $1.05 por 1M tokens
//$modelName = "gemini-2.5-flash-lite"; // costo: input -> $0.10, output -> $0.40 por 1M tokens
// $modelName = "gemini-2.0-flash"; // costo: input -> $0.35, output -> $1.05 por 1M tokens
// $modelName = "gemini-2.0-flash-lite"; // costo: input -> $0.10, output -> $0.40 por 1M tokens
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$modelName:generateContent?key=" . $apiKey;

// === TUS CLÁUSULAS MAESTRAS ===
$clausulaFuerzaMayor = "CASO FORTUITO Y FUERZA MAYOR. ANC CAR S.A., no será responsable por el incumplimiento del presente Contrato, ni incurrirá en responsabilidad, penalidad, ni obligación de indemnización alguna para con EL PROVEEDOR, cuando dicho incumplimiento se origine, directa o indirectamente, por caso fortuito o fuerza mayor, y/o que de cualquier forma se derive de causas extremas como lo son, pero sin limitarse a, emergencias de salud pública, pandemias, desastres naturales, guerra, problemas políticos-gubernamentales, disturbios, incendios, embargos, accidentes, entre otros, así como también de causas que estén más allá del control razonable de ANC CAR S.A., y que de manera directa o indirecta le imposibiliten o dificulten el cumplimiento del presente Contrato. En virtud de lo anterior, ANC CAR S.A. podrá dar por terminado el presente contrato unilateral y anticipadamente, sin ninguna responsabilidad, penalidad, ni obligación de indemnización de ningún tipo para con EL PROVEEDOR.";
$clausulaTerminacion = "ANC CAR S.A., en su condición de LA CONTRATANTE, podrá dar por terminado el presente contrato unilateral y anticipadamente, sin justa causa o por razones de conveniencia y sin ninguna responsabilidad, penalidad, ni obligación de indemnización para con EL PROVEEDOR en cualquier momento, notificando a EL PROVEEDOR por escrito con al menos treinta (30) días naturales de anticipación.";

// === PROMPT DE AUDITORÍA ESTRICTA + CATEGORIZACIÓN ===
$promptText = "
ROL: Auditor Legal Senior de 'ANC CAR S.A.'.
TAREA: Clasificar y Auditar el contrato adjunto contra los ESTÁNDARES OBLIGATORIOS.

1. CATEGORIZACIÓN (CRÍTICO):
Clasifica el documento EXCLUSIVAMENTE en una de estas 3 opciones exactas (copia el texto tal cual):
- 'Servicios'
- 'Alquiler de oficinas'
- 'Otro'

2. AUDITORÍA DE ESTÁNDARES (CRITERIOS DE ACEPTACIÓN):

A. FUERZA MAYOR (Debe proteger igual o mejor que este texto):
'$clausulaFuerzaMayor'
-> REQUISITO: Debe mencionar explícitamente 'pandemias' o 'emergencias de salud' y permitir terminar sin penalidad. Si falta -> RED FLAG.

B. TERMINACIÓN ANTICIPADA (Debe proteger igual o mejor que este texto):
'$clausulaTerminacion'
-> REQUISITO: ANC CAR S.A. debe poder salir 'sin justa causa' (por conveniencia) y 'sin penalidad'. Si exige pago -> RED FLAG.

C. JURISDICCIÓN:
-> REQUISITO: Disputas en domicilio de ANC CAR S.A. (Costa Rica). Si es extranjero -> RED FLAG.

INSTRUCCIONES DE SALIDA:
- Si el contrato no tiene cláusula de Fuerza Mayor o Terminación, asume que NO existen -> RED FLAG.
- Sé breve.

FORMATO JSON OBLIGATORIO:
{
    \"title\": \"Título corto del contrato\",
    \"supplier\": \"Nombre del Proveedor detectado\",
    \"category\": \"Servicios\",  <-- AQUÍ DEBE IR UNA DE LAS 3 OPCIONES EXACTAS
    \"date\": \"YYYY-MM-DD\", 
    \"summary\": \"Resumen ejecutivo de 2 líneas.\",
    \"red_flags\": {
        \"count\": (int),
        \"details\": [
            \"Riesgo 1: ...\",
            \"Riesgo 2: ...\"
        ]
    }
}
";

// === CONSTRUCCIÓN DEL PAYLOAD ===
$parts = [];
$parts[] = ["text" => $promptText];

if ($type === 'base64' || $type === 'image') {
    $parts[] = [
        "inline_data" => [
            "mime_type" => $input['mime'] ?? 'application/pdf',
            "data" => $input['content']
        ]
    ];
} else {
    $cleanText = substr($input['content'], 0, 150000); 
    $parts[] = ["text" => "\n\n--- DOCUMENTO A AUDITAR ---\n" . $cleanText];
}

$payload = [
    "contents" => [["parts" => $parts]],
    "generationConfig" => [
        "temperature" => 0.0, // CERO creatividad, pura comparación lógica
        "maxOutputTokens" => 3000,
        "response_mime_type" => "application/json"
    ]
];

// === EJECUCIÓN ===
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) { echo json_encode(['error' => 'Error Conexión: ' . $curlError]); exit; }
if ($httpCode !== 200) {
    $err = json_decode($response, true);
    echo json_encode(['error' => "Gemini API ($httpCode): " . ($err['error']['message'] ?? $response)]); exit;
}

// === PROCESAMIENTO ===
$decoded = json_decode($response, true);
$aiText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
$aiText = preg_replace('/^```json\s*/i', '', $aiText);
$aiText = preg_replace('/\s*```$/', '', $aiText);

$resultData = json_decode($aiText, true);

if (!$resultData) {
    echo json_encode(['error' => 'Error parseando respuesta IA', 'raw' => $aiText]);
} else {
    // HTML Visual
    $htmlOutput = "<div class='space-y-4'>";
    $htmlOutput .= "<div class='border-b pb-2'><h3 class='text-lg font-bold text-blue-800'>" . htmlspecialchars($resultData['title']) . "</h3>";
    $htmlOutput .= "<p class='text-sm text-gray-500'>Proveedor: <strong>" . htmlspecialchars($resultData['supplier']) . "</strong></p></div>";
    
    $htmlOutput .= "<div class='bg-slate-50 p-3 rounded text-sm text-slate-700'><strong>Resumen:</strong> " . htmlspecialchars($resultData['summary']) . "</div>";
    
    if ($resultData['red_flags']['count'] > 0) {
        $htmlOutput .= "<div class='bg-red-50 border-l-4 border-red-500 p-4'>";
        $htmlOutput .= "<h4 class='font-bold text-red-700 mb-2 flex items-center gap-2'>⚠️ INCUMPLIMIENTOS (" . $resultData['red_flags']['count'] . ")</h4>";
        $htmlOutput .= "<ul class='list-disc list-inside text-red-800 text-sm space-y-1'>";
        foreach ($resultData['red_flags']['details'] as $flag) {
            $htmlOutput .= "<li>" . htmlspecialchars($flag) . "</li>";
        }
        $htmlOutput .= "</ul></div>";
    } else {
        $htmlOutput .= "<div class='bg-green-100 border-l-4 border-green-500 p-4 text-green-800 font-bold flex items-center gap-2'>";
        $htmlOutput .= "<i data-lucide='shield-check' class='w-6 h-6'></i> CONTRATO BLINDADO: Cumple estándares ANC CAR S.A.";
        $htmlOutput .= "</div>";
    }
    $htmlOutput .= "</div>";

    $resultData['analysis'] = $htmlOutput;
    echo json_encode($resultData);
}
?>