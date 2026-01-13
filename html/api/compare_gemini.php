<?php
// api/compare_gemini.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Límites altos para documentos legales extensos
ini_set('memory_limit', '1024M');
ini_set('post_max_size', '100M');

require '../db.php';
require '../auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

// Leer input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    echo json_encode(['error' => 'Error recibiendo datos (JSON inválido o vacío). Longitud recibida: ' . strlen($rawInput)]); 
    exit;
}

// === CONFIGURACIÓN ===
$apiKey = getenv('GEMINI_API_KEY');

if (!$apiKey) {
    echo json_encode(['error' => 'Error: GEMINI_API_KEY no configurada en el servidor.']);
    exit;
}


// $modelName = "gemini-3-pro-preview"; // costo: input -> $1.00, output -> $5.00 por 1M tokens
// $modelName = "gemini-3-flash-preview"; // costo: input -> $0.50, output -> $3.00 por 1M tokens
// $modelName = "gemini-2.5-pro"; // costo: input -> $1.00, output -> $5.00 por 1M tokens
$modelName = "gemini-2.5-flash"; // costo: input -> $0.35, output -> $1.05 por 1M tokens
// $modelName = "gemini-2.5-flash-lite"; // costo: input -> $0.10, output -> $0.40 por 1M tokens
// $modelName = "gemini-2.0-flash"; // costo: input -> $0.35, output -> $1.05 por 1M tokens
// $modelName = "gemini-2.0-flash-lite"; // costo: input -> $0.10, output -> $0.40 por 1M tokens

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$modelName:generateContent?key=" . $apiKey;

// === PREPARAR DATOS ===
$consolidated = $input['consolidated_text'] ?? '';
$hasTextDiff = ($input['changes_count'] ?? 0) > 0;

// Recorte de seguridad (Gemini Flash soporta ~1M tokens, pero CURL puede fallar antes)
// Limitamos a 100,000 caracteres (aprox 25k tokens) para asegurar el envío HTTP.
$safeConsolidated = substr($consolidated, 0, 100000); 
if (strlen($consolidated) > 100000) {
    $safeConsolidated .= "\n...[TRUNCADO POR LÍMITE DE SEGURIDAD]...";
}

// 4. CONFIGURACIÓN DE SENSIBILIDAD (PROMPT ENGINEERING)
$sensitivity = isset($input['sensitivity']) ? (int)$input['sensitivity'] : 3; // Default 3 si no viene nada

$instructions = "";

// NOTA DE ARQUITECTURA: 
// Mantenemos Temperature en 0.0 siempre para evitar alucinaciones.
// La "sensibilidad" se controla puramente con instrucciones de filtrado en el prompt.

switch ($sensitivity) {
    case 1: // BAJA (Resumen Ejecutivo)
        $instructions = "
        - FILTRO: Considerar únicamente cambios críticos que impacten obligaciones, fechas, montos o partes involucradas.
        - RESUMEN: Elaborar un informe ejecutivo que mencione solo los cambios más relevantes.
        - ENFOQUE: ¡IMPORTANTE! Ignorar modificaciones de formato, puntuación, uso de mayúsculas/minúsculas o palabras conectivas que no alteren el significado. Aunque se presenten cambios de redacción, no incluirlos si no afectan el contenido legal del contrato.";
        break;
        
    case 2: // MEDIA (Normal)
        $instructions = "
        - FILTRO: IGNORA cambios de puntuación, mayúsculas/minúsculas, o palabras conectivas que no cambien el significado.
        - FOCO: Analiza los documentos en su contexto total y solo repórtame cambios en FECHAS, MONTOS, NOMBRES o CLÁUSULAS modificadas añadidas/borradas.
        - Si el cambio es menor, o dentro del contexto no afecta legalmente, NO lo menciones.";
        break;
        
    case 3: // ALTA (Estricto / Default)
    default:
        $instructions = "
        - FILTRO: NINGUNO. Eres un AUDITOR PARANOICO.
        - FOCO: Reporta ABSOLUTAMENTE TODO.
        - Si cambió una coma (,), repórtalo.
        - Si cambió 'a' por 'la', repórtalo.
        - Si un número cambió de '5' a '05', repórtalo.
        - Tu trabajo es encontrar hasta la más mínima discrepancia.";
        break;
}

// 5. PROMPT ADAPTATIVO
$systemPrompt = "
ACTÚA COMO: Abogado Auditor Senior y Experto en Análisis Forense de Textos.
TAREA: Analizar marcas de cambios en un contrato consolidado.

NIVEL DE RIGUROSIDAD: $sensitivity (3 = Máxima).
INSTRUCCIONES DE FILTRADO:
$instructions

TU INSUMO:
Texto con marcas: `<<<TEXTO ELIMINADO...>>>` y `<<<TEXTO NUEVO...>>>`.
FORMATO HTML (No menciones el formato que usas en la respuesta):
<ul>
  <li>
    <strong>[TIPO]</strong> (MODIFICACIÓN/INSERCIÓN/ELIMINACIÓN)<br>
    <em>Detalle:</em> (Cita el cambio)<br>
    <span style='color:#f54e4e'>(Impacto Legal: ...)</span>
  </li>
</ul>

Si no hay marcas relevantes, responde: 'DOCUMENTOS IDÉNTICOS'.
";

// === 6. CONSTRUCCIÓN INTELIGENTE DEL PAYLOAD ===
$parts = [];
$parts[] = ['text' => $systemPrompt];

// Validar si tenemos texto real extraído (no mensaje de error visual)
$hasTextContent = !empty($consolidated) && strpos($consolidated, "MODO VISUAL") === false;

if ($hasTextDiff) {
    // CASO 1: HAY DIFERENCIAS DE TEXTO CONFIRMADAS
    $parts[] = ['text' => "--- INICIO DOCUMENTO CONSOLIDADO CON MARCAS DE CAMBIOS ---\n" . $safeConsolidated];
    $parts[] = ['text' => "Genera el informe de impacto legal sobre los cambios marcados."];

} elseif ($hasTextContent && !$hasTextDiff) {
    // CASO 2: TEXTO EXTRAÍDO PERO IDÉNTICO (El problema actual)
    $parts[] = ['text' => "ALERTA CRÍTICA DEL SISTEMA:"];
    $parts[] = ['text' => "El motor matemático de diferencias ha comparado los textos carácter por carácter y el resultado es: 0 DIFERENCIAS."];
    $parts[] = ['text' => "Los documentos son TEXTUALMENTE IDÉNTICOS."];
    $parts[] = ['text' => "INSTRUCCIÓN: No busques diferencias de texto inexistentes. Solo responde: 'DOCUMENTOS IDÉNTICOS'."];
    
    // Opcional: Enviamos un fragmento para que 'vea' que hay texto, pero no los dos docs para evitar tentaciones.
    $parts[] = ['text' => "Muestra del contenido validado:\n" . substr($safeConsolidated, 0, 5000)];

} else {
    // CASO 3: MODO VISUAL (Imágenes/Escaneos puros)
    $parts[] = ['text' => "AVISO: No se pudo extraer texto digital. Se requiere análisis visual comparativo (OCR)."];
    $parts[] = ['text' => "--- DOC A (ORIGINAL) ---"];
    
    // Helper para añadir adjunto
    $addDoc = function($doc) {
        if (isset($doc['type']) && ($doc['type'] == 'base64' || $doc['type'] == 'image')) {
            return ['inline_data' => ['mime_type' => $doc['mime'], 'data' => $doc['content']]];
        }
        return ['text' => substr($doc['content'], 0, 15000)]; // Fallback texto
    };
    
    $parts[] = $addDoc($input['doc_a']);
    $parts[] = ['text' => "--- DOC B (NUEVO) ---"];
    $parts[] = $addDoc($input['doc_b']);
    $parts[] = ['text' => "Busca diferencias visuales o de texto en las imágenes."];
}

// LA CONFIGURACIÓN DE GENERACIÓN 
$payload = [
    "contents" => [["parts" => $parts]],
    "generationConfig" => [
        "temperature" => 0.0, // <--- SIEMPRE CERO para precisión matemática
        "maxOutputTokens" => 9000
    ]
];

// === ENVÍO CURL ===
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Más tiempo para leer docs grandes

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// === DIAGNÓSTICO DE ERROR ===
if ($httpCode !== 200) {
    $err = json_decode($response, true);
    // Extraemos el mensaje real de Google
    $googleMsg = $err['error']['message'] ?? substr($response, 0, 200); // Si no es JSON, mostrar raw
    $googleStatus = $err['error']['status'] ?? 'UNKNOWN';
    
    echo json_encode([
        'error' => "Google API ($httpCode - $googleStatus): $googleMsg",
        'debug_info' => "Intentamos enviar " . strlen($safeConsolidated) . " caracteres."
    ]);
    exit;
}

// === ÉXITO ===
$decoded = json_decode($response, true);
$aiText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
$aiText = preg_replace('/^```html\s*|\s*```$/', '', trim($aiText));

echo json_encode(['comparison' => $aiText]);
?>