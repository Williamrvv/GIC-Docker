<?php
require 'db.php';

header('Content-Type: text/plain');

// CORREO DESTINO PARA LA PRUEBA
$toTest = "william.valverde@grupoanc.com";

echo "=== PRUEBA SMTP SSL (PUERTO 465) ===\n";
echo "Host: " . SMTP_HOST . "\n";
echo "User: " . SMTP_USER . "\n";

// 1. Detección automática de protocolo SSL
$protocol = (SMTP_PORT == 465) ? 'ssl://' : '';
$connectHost = $protocol . SMTP_HOST;

echo "Conectando a: $connectHost ...\n";

$socket = fsockopen($connectHost, SMTP_PORT, $errno, $errstr, 15);

if (!$socket) {
    die("❌ ERROR CONEXIÓN: $errno - $errstr\n");
}

echo "✅ Conexión SSL establecida.\n";

function server_response($socket) {
    $response = "";
    while($str = fgets($socket, 515)) {
        $response .= $str;
        if(substr($str, 3, 1) == " ") break;
    }
    echo "S: $response"; 
    return $response;
}

function send_command($socket, $cmd) {
    echo "C: $cmd\n";
    fputs($socket, $cmd . "\r\n");
}

server_response($socket); // Saludo del servidor

// En SSL (465) no enviamos STARTTLS, vamos directo al saludo y autenticación
send_command($socket, "EHLO " . $_SERVER['HTTP_HOST']);
server_response($socket);

send_command($socket, "AUTH LOGIN");
server_response($socket, 334);

send_command($socket, base64_encode(SMTP_USER));
server_response($socket, 334);

send_command($socket, base64_encode(SMTP_PASS));
$authResult = server_response($socket, 235);

if (strpos($authResult, '235') === false) {
    echo "\n❌ ERROR DE AUTENTICACIÓN. Revisa tu contraseña.\n";
    exit;
}

echo "\n✅ AUTENTICADO. Enviando correo a $toTest...\n";

send_command($socket, "MAIL FROM: <".SMTP_USER.">");
server_response($socket);

send_command($socket, "RCPT TO: <$toTest>");
server_response($socket);

send_command($socket, "DATA");
server_response($socket, 354);

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/plain; charset=UTF-8\r\n";
$headers .= "From: LegalFlow <".SMTP_USER.">\r\n";
$headers .= "To: $toTest\r\n";
$headers .= "Subject: Prueba LegalFlow Cpanel SSL\r\n";

$msg = "Esta es una prueba de conexión exitosa desde LegalFlow usando SSL en puerto 465.";

send_command($socket, $headers . "\r\n" . $msg . "\r\n.");
server_response($socket);

send_command($socket, "QUIT");
fclose($socket);

echo "\n=== FIN DE PRUEBA ===\n";
?>