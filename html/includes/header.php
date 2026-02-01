<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Botón Activo (Azul siempre se ve bien)
$activeClass = "bg-blue-600 text-white shadow-lg shadow-blue-500/30";

// Botón Inactivo (ADAPTATIVO)
$inactiveClass = "text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-blue-600 dark:hover:text-white transition-colors";
?>
<!DOCTYPE html>
<html lang="es" class="light"> <!-- Clase inicial -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - GIC' : 'GIC' ?></title>
    <!-- Favicon -->
    <link rel="icon" href="assets/img/favicon.png" type="image/png">
    
    <!-- Tailwind CSS (Versión Estable Reciente con soporte Ring/Shadow avanzado) -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', // Activación manual
            theme: { extend: {} }
        }
    </script>
    
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Lógica de Persistencia de Tema (Antes de renderizar body) -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

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
    <style>
        /* ANIMACIONES */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-entry { animation: fadeInUp 0.5s ease-out forwards; opacity: 0; }
        
        /* TRANSICIONES DE TEMA */
        body, main, aside, div, tr, td, th, input, select, textarea { 
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease; 
        }

        /* ========================================= */
        /* SCROLLBAR GLOBAL (CHROME, EDGE, SAFARI)   */
        /* ========================================= */
        
        /* Aplica a TODO elemento scrollable en el sitio */
        ::-webkit-scrollbar {
            width: 8px;  /* Grosor vertical */
            height: 8px; /* Grosor horizontal */
        }

        /* El riel (fondo) */
        ::-webkit-scrollbar-track {
            background: transparent; 
        }

        /* La barra (thumb) */
        ::-webkit-scrollbar-thumb {
            background-color: #cbd5e1; /* Gris suave (Modo Claro) */
            border-radius: 20px;       /* Píldora redondeada */
            border: 2px solid transparent; /* Truco para crear "margen" alrededor */
            background-clip: content-box;
        }

        /* Al pasar el mouse sobre la barra */
        ::-webkit-scrollbar-thumb:hover {
            background-color: #94a3b8;
        }

        /* ========================================= */
        /* MODO OSCURO                               */
        /* ========================================= */
        .dark ::-webkit-scrollbar-thumb {
            background-color: #475569; /* Gris oscuro (Modo Oscuro) */
        }
        .dark ::-webkit-scrollbar-thumb:hover {
            background-color: #64748b;
        }

        /* ========================================= */
        /* SOPORTE FIREFOX                           */
        /* ========================================= */
        html {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .dark html {
            scrollbar-color: #475569 transparent;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-900 flex overflow-hidden text-slate-800 dark:text-slate-100 font-sans">

    <!-- OVERLAY MÓVIL (Fondo oscuro cuando abres menú) -->
    <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-20 hidden lg:hidden backdrop-blur-sm transition-opacity"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-white dark:bg-slate-950 border-r border-gray-200 dark:border-slate-800 h-screen fixed z-30 shadow-xl transition-transform duration-300 transform -translate-x-full lg:translate-x-0 flex flex-col">
        <!-- HEADER SIDEBAR -->
        <div class="p-6 flex items-center justify-center relative border-b border-gray-200 dark:border-slate-800">
            
            <!-- LOGO ESTILIZADO -->
            <img src="assets/img/logo.png" 
                 alt="GIC Logo" 
                 class="h-28 w-auto max-w-full object-contain bg-white rounded-2xl p-2 shadow-lg ring-1 ring-white/10 transition-transform">
            
            <!-- Botón cerrar móvil -->
            <button onclick="toggleSidebar()" class="lg:hidden absolute top-4 right-4 text-slate-500 hover:text-white p-1 rounded-md hover:bg-slate-800 transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <nav class="mt-4 px-3 space-y-2" id="sidebar-nav">
            <a href="dashboard" class="group flex items-center px-4 py-3 text-sm font-medium rounded-xl <?= $currentPage == 'dashboard' ? $activeClass : $inactiveClass ?>">
                <i data-lucide="layout-dashboard" class="mr-3 h-5 w-5"></i> Dashboard
            </a>
            <a href="contracts" class="group flex items-center px-4 py-3 text-sm font-medium rounded-xl <?= ($currentPage == 'contracts' || $currentPage == 'contract_detail') ? $activeClass : $inactiveClass ?>">
                <i data-lucide="file-text" class="mr-3 h-5 w-5"></i> Contratos
            </a>
            <!-- CARGA HISTÓRICA (SOLO ADMIN/LEGAL) -->
            <?php if(hasRole(['SUPER_ADMIN', 'LEGAL'])): ?>
            <a href="legacy_upload" class="group flex items-center px-4 py-3 text-sm font-medium rounded-xl <?= $currentPage == 'legacy_upload' ? $activeClass : $inactiveClass ?>">
                <i data-lucide="archive-restore" class="mr-3 h-5 w-5"></i> Carga Histórica
            </a>
            <?php endif; ?>
            <!-- ENLACE USUARIOS (CONDICIÓN AMPLIADA) -->
            <?php if(hasRole('SUPER_ADMIN') || (!empty($_SESSION['can_manage_users']) && $_SESSION['can_manage_users'] == 1)): ?>
            <a href="users" class="group flex items-center px-4 py-3 text-sm font-medium rounded-xl <?= $currentPage == 'users' ? $activeClass : $inactiveClass ?>">
                <i data-lucide="users" class="mr-3 h-5 w-5"></i> Usuarios
            </a>
            <?php endif; ?>
        </nav>

        <div class="absolute bottom-0 w-full p-4 border-t border-gray-200 dark:border-slate-800 ">
             <a href="logout.php" class="flex items-center px-4 py-3 text-sm font-medium text-red-400 hover:bg-red-900/20 rounded-xl transition-colors">
                <i data-lucide="log-out" class="mr-3 h-5 w-5"></i> Cerrar Sesión
            </a>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="lg:ml-64 w-full h-screen overflow-y-auto transition-all duration-300">
        <!-- HEADER MEJORADO -->
        <header class="sticky top-0 z-20 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-gray-200 dark:border-slate-800 px-6 py-4 flex justify-between items-center">
            
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="lg:hidden text-slate-500 hover:text-blue-600">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <h1 class="text-xl font-bold hidden sm:block text-slate-800 dark:text-white"><?= $pageTitle ?? 'GIC' ?></h1>
            </div>

            <div class="flex items-center gap-4">
                <button onclick="toggleTheme()" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                    <i id="theme-icon" data-lucide="moon" class="w-5 h-5 text-slate-600 dark:text-yellow-400"></i>
                </button>
                
                <!-- MENU USUARIO DROPDOWN -->
                <div class="relative" id="userMenuContainer">
                    <button onclick="document.getElementById('userDropdown').classList.toggle('hidden')" class="flex items-center gap-3 hover:bg-gray-100 dark:hover:bg-slate-800 p-2 rounded-lg transition-colors">
                        <div class="text-right hidden md:block">
                            <p class="text-sm font-bold text-slate-800 dark:text-white"><?= $_SESSION['name'] ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400"><?= $_SESSION['role'] ?></p>
                        </div>
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                            <?= substr($_SESSION['name'], 0, 1) ?>
                        </div>
                    </button>

                    <!-- Dropdown Content -->
                    <div id="userDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-gray-100 dark:border-slate-700 overflow-hidden z-50">
                        <div class="p-4 border-b border-gray-100 dark:border-slate-700">
                            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Notificaciones</p>
                            
                            <?php 
                                // Obtener preferencia actual
                                $currPref = 'INVOLVED';
                                if(isset($pdo)) {
                                    $s = $pdo->prepare("SELECT notification_preference FROM users WHERE id = ?");
                                    $s->execute([$_SESSION['user_id']]);
                                    $currPref = $s->fetchColumn() ?: 'INVOLVED';
                                }
                                
                                // Determinar si es usuario avanzado
                                $isPowerUser = in_array($_SESSION['role'], ['SUPER_ADMIN', 'LEGAL']);
                            ?>

                            <select onchange="updateNotifPref(this.value)" class="w-full text-sm bg-slate-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-600 rounded-lg p-2 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="NONE" <?= $currPref=='NONE'?'selected':'' ?>>🔕 no recibir notificaciones</option>
                                <option value="INVOLVED" <?= $currPref=='INVOLVED'?'selected':'' ?>>🔔 Recibir notificaciones</option>
                                
                                <!-- Opción visible SOLO para Admin y Legal -->
                                <?php if ($isPowerUser): ?>
                                    <option value="ALL" <?= $currPref=='ALL'?'selected':'' ?>>📢 Notificaciones de todo el sistema</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mt-auto p-4 border-t border-gray-200 dark:border-slate-800">
                            <a href="logout.php" class="flex items-center px-4 py-3 text-sm font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-colors">
                                <i data-lucide="log-out" class="mr-3 h-5 w-5"></i> Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-8 max-w-7xl mx-auto pb-20">
            
<!-- SCRIPT PARA GUARDAR PREFERENCIA -->
<script>
    function updateNotifPref(value) {
        const formData = new FormData();
        formData.append('preference', value);
        
        fetch('api/update_preferences.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if(response.ok) {
                // Feedback visual sutil (borde verde temporal)
                const select = document.querySelector('#userDropdown select');
                select.style.borderColor = '#22c55e';
                setTimeout(() => select.style.borderColor = '', 1000);
            } else {
                alert("Error al guardar preferencia");
            }
        });
    }
    
    // Cerrar dropdown si clic fuera
    document.addEventListener('click', function(e) {
        const container = document.getElementById('userMenuContainer');
        const dropdown = document.getElementById('userDropdown');
        if (container && !container.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
</script>