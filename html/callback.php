<?php
require 'db.php';
require 'auth.php'; // Esto inicia la sesión automáticamente
require 'auth_o365.php';

// Verificación de seguridad básica
if (!isset($_GET['code'])) {
    die("Error: No se recibió código de autenticación de Microsoft.");
}

// =======================================================
// 1. CANJEAR EL CÓDIGO POR UN TOKEN DE ACCESO
// =======================================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, O365_TOKEN_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Desactivar verificación SSL para entorno local/dev (Docker)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => O365_CLIENT_ID,
    'scope'         => 'User.Read',
    'code'          => $_GET['code'],
    'redirect_uri'  => O365_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
    'client_secret' => O365_CLIENT_SECRET
]));

$response = curl_exec($ch);
$tokenData = json_decode($response, true);
curl_close($ch);

if (!isset($tokenData['access_token'])) {
    // Debug: Mostrar error si falla la conexión con Azure
    echo "<h1>Error de Autenticación</h1>";
    echo "<p>No se pudo obtener el token de acceso. Verifique Client Secret y Redirect URI en Azure.</p>";
    echo "<pre>"; print_r($tokenData); echo "</pre>";
    exit;
}

// =======================================================
// 2. OBTENER DATOS DEL USUARIO (MICROSOFT GRAPH)
// =======================================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, O365_GRAPH_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $tokenData['access_token'],
    'Content-Type: application/json'
]);

$graphResponse = curl_exec($ch);
$msUser = json_decode($graphResponse, true);
curl_close($ch);

if (!isset($msUser['id'])) {
    die("Error: No se pudo obtener el perfil del usuario desde Microsoft Graph.");
}

// Preferimos 'mail', si no, usamos el 'userPrincipalName' (login)
$email = $msUser['mail'] ?? $msUser['userPrincipalName'];
$name = $msUser['displayName'];

// =======================================================
// 3. LÓGICA DE LOGIN / REGISTRO EN LEGALFLOW
// =======================================================
try {
    // Buscar si el usuario ya existe por EMAIL
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $localUser = $stmt->fetch();

    if ($localUser) {
        // --- A. EL USUARIO YA EXISTE ---
        
        // Validar que no esté desactivado localmente
        if ($localUser['is_active'] == 0) {
            die("
                <div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>
                    <h1 style='color: #ef4444;'>Acceso Denegado</h1>
                    <p>Su cuenta corporativa es válida, pero su acceso a <strong>GIC</strong> ha sido desactivado por un administrador.</p>
                    <a href='index.php' style='color: #3b82f6;'>Volver al inicio</a>
                </div>
            ");
        }
        
        // Crear variables de sesión
        $_SESSION['user_id'] = $localUser['id'];
        $_SESSION['name'] = $localUser['name'];
        $_SESSION['role'] = $localUser['role'];
        $_SESSION['can_manage_users'] = $localUser['can_manage_users'];
        
    } else {
        // --- B. USUARIO NUEVO (AUTO-REGISTRO) ---
        // Se crea automáticamente con rol DEPT_HEAD (Solicitante)
        
        $username = explode('@', $email)[0]; // Crear un username basado en el correo
        
        // Verificar si el username ya existe (por si acaso hay colisión) y agregar sufijo
        $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkUser->execute([$username]);
        if($checkUser->fetch()) {
            $username = $username . rand(100,999); 
        }

        // Insertar nuevo usuario
        // Password 'O365_AUTH' es un placeholder, ya que entran por token
        // Notification Preference por defecto: INVOLVED
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, name, email, role, department, is_active, notification_preference) VALUES (?, 'O365_AUTH', ?, ?, 'DEPT_HEAD', 'General', 1, 'INVOLVED')");
        $stmtInsert->execute([$username, $name, $email]);
        
        // Iniciar sesión con el ID recién creado
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['name'] = $name;
        $_SESSION['role'] = 'DEPT_HEAD';
    }

    // =======================================================
    // 4. REDIRECCIÓN INTELIGENTE (Deep Linking)
    // =======================================================
    
    // Por defecto vamos al Dashboard
    $target = 'dashboard';
    
    // Si el usuario intentó entrar a un link específico antes de loguearse (guardado en auth.php)
    if (isset($_SESSION['redirect_url'])) {
        $target = $_SESSION['redirect_url'];
        unset($_SESSION['redirect_url']); // Limpiar para futuras sesiones
    }

    header("Location: $target");
    exit;

} catch (Exception $e) {
    die("Error Crítico de Base de Datos: " . $e->getMessage());
}
?>