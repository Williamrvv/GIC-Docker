<?php
require 'db.php';
require 'auth.php';
require 'auth_o365.php'; // Configuración de Azure

// Si ya hay sesión activa, redirigir al dashboard
if (isLoggedIn()) {
    header("Location: dashboard");
    exit;
}

$error = '';
if (isset($_GET['msg']) && $_GET['msg'] == 'required') {
    // Usamos la variable $error para mostrar el mensaje en la alerta roja existente
    // O puedes crear una variable $info separada si prefieres color azul/amarillo
    $error = "🔒 Por seguridad, inicia sesión para ver ese documento.";
}

// Lógica de Login Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Buscamos al usuario
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();

        // 1. Verificar si existe y si la contraseña es válida
        if ($user && password_verify($_POST['password'], $user['password'])) {
            
            if ($user['is_active'] == 0) {
                $error = "Usuario no válido.";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['can_manage_users'] = $user['can_manage_users'];
                
                // --- NUEVA LÓGICA DE REDIRECCIÓN ---
                $target = 'dashboard'; // Destino por defecto
                
                if (isset($_SESSION['redirect_url'])) {
                    $target = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']); // Limpiar para no afectar futuros logins
                }
                
                header("Location: $target");
                exit;
                // -----------------------------------
            }

        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } catch (Exception $e) {
        $error = "Error del sistema: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GIC</title>
    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    <!-- ESTILOS TAILWIND (Crucial para que se vea bien) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Animación de la tarjeta (Sube) */
        @keyframes fadeInUp { 
            from { opacity: 0; transform: translateY(20px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        /* Animación del Logo (Baja y rebota levemente) */
        @keyframes floatDown { 
            0% { opacity: 0; transform: translateY(-40px) scale(0.9); } 
            80% { transform: translateY(5px) scale(1.02); } /* Rebote */
            100% { opacity: 1; transform: translateY(0) scale(1); } 
        }

        .animate-card { animation: fadeInUp 0.6s ease-out forwards; opacity: 0; }
        
        /* El logo espera 0.2s antes de caer */
        .animate-logo { animation: floatDown 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; opacity: 0; animation-delay: 0.2s; }
    </style>
</head>
<body class="bg-slate-900 flex items-center justify-center h-screen px-4 font-sans">
    
    <div class="relative bg-white p-8 pt-28 rounded-[2rem] shadow-2xl w-full max-w-sm animate-card overflow-visible">
        
        <!-- LOGO FLOTANTE (Con Sombra Extra y Animación Desfasada) -->
        <div class="absolute -top-24 left-0 w-full flex justify-center">
            <div class="animate-logo"> <!-- Contenedor animado -->
                <div class="bg-white p-4 rounded-3xl shadow-[0_20px_60px_0px_rgba(0,0,0,0.3)] ring-1 ring-slate-900/5">
                    <img src="assets/img/logo.png" 
                         alt="GIC Logo" 
                         class="h-32 w-auto object-contain rounded-xl select-none"> <!-- select-none evita que se ponga azul si lo arrastras -->
                </div>
            </div>
        </div>

        <!-- Título y Subtítulo -->
        <!-- <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-slate-800 tracking-tight">GIC</h1>
            <p class="text-slate-500 font-medium text-sm mt-2">Gestión Inteligente de Contratos</p>
        </div> -->


        <!-- Mensaje de Error -->
        <?php if(!empty($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg text-sm flex items-start gap-2 shadow-sm">
                <span><?= $error ?></span>
            </div>
        <?php endif; ?>

        <!-- BOTÓN MICROSOFT 365 -->
        <a href="<?= getO365LoginUrl() ?>" class="w-full flex items-center justify-center gap-3 bg-slate-800 hover:bg-slate-900 text-white font-medium rounded-xl px-5 py-3 transition-all shadow-lg hover:shadow-xl mb-6 border border-slate-700 group">
            <!-- Icono Microsoft -->
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 23 23"><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>
            <span>Ingresar con Microsoft 365</span>
        </a>

        <!-- Separador -->
        <div class="relative flex py-2 items-center">
            <div class="flex-grow border-t border-gray-200"></div>
            <span class="flex-shrink-0 mx-4 text-gray-400 text-xs uppercase font-bold tracking-wider">O acceso manual</span>
            <div class="flex-grow border-t border-gray-200"></div>
        </div>

        <!-- Formulario Manual (Restaurado) -->
        <form method="POST" class="space-y-5 mt-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5 ml-1">Usuario</label>
                <input type="text" name="username" class="w-full bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all" placeholder="Nombre de usuario" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1.5 ml-1">Contraseña</label>
                <input type="password" name="password" class="w-full bg-gray-50 border border-gray-300 text-slate-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block p-3 outline-none transition-all" placeholder="••••••••" required>
            </div>
            
            <button type="submit" class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-bold rounded-xl text-sm px-5 py-3 text-center transition-all transform hover:scale-[1.02] shadow-lg shadow-blue-500/30">
                Iniciar Sesión
            </button>
        </form>
        
        <p class="mt-8 text-center text-xs text-slate-400">
            &copy; <?= date('Y') ?> GIC GrupoANC
        </p>
    </div>
</body>
</html>