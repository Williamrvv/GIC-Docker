<?php
require 'db.php'; 
require 'auth.php'; 
require 'functions.php'; 
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// CONFIGURACIÓN
$categories = ['Servicios', 'Alquiler de oficinas', 'Otro'];

// PAGINACIÓN
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;

// === LÓGICA DE CREACIÓN (POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    
    // === GENERACIÓN DE CONSECUTIVO INTELIGENTE ===
    $year = date('Y');
    
    // 1. Buscar el último consecutivo SOLO de este año
    // Ordenamos por longitud primero para que '10' sea mayor que '9'
    $stmtSeq = $pdo->prepare("SELECT id FROM contracts WHERE id LIKE ? ORDER BY LENGTH(id) DESC, id DESC LIMIT 1");
    $stmtSeq->execute(["CTR-$year-%"]);
    $lastId = $stmtSeq->fetchColumn();

    if ($lastId) {
        // Ejemplo: CTR-2025-012 -> Hacemos explode y tomamos el último número
        $parts = explode('-', $lastId);
        $lastNum = (int)end($parts); 
        $nextNum = $lastNum + 1;
    } else {
        // Si no hay contratos en 2025, empezamos en 1
        $nextNum = 1;
    }

    // 2. Formatear a 4 dígitos (CTR-2025-0001) para soportar hasta 9999
    $contractId = sprintf("CTR-%s-%04d", $year, $nextNum);
    
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileExt = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fileName = $contractId . '_v1.' . $fileExt;
        $absPath = $uploadDir . $fileName;
        $filePath = 'uploads/' . $fileName;

        $fileHash = encryptAndSave($_FILES['file']['tmp_name'], $absPath);

        if ($fileHash !== false) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO contracts (id, title, summary, supplier, category, initiator_id, status, priority, has_red_flags, red_flag_details, red_flag_count) VALUES (?, ?, ?, ?, ?, ?, 'UNREVIEWED', ?, ?, ?, ?)");
                
                $redFlagCount = isset($_POST['red_flag_count']) ? (int)$_POST['red_flag_count'] : 0;
                $hasRedFlags = ($redFlagCount > 0) ? 1 : 0;
                
                $stmt->execute([
                    $contractId, $_POST['title'], $_POST['summary'] ?? '', $_POST['supplier'], 
                    $_POST['category'], $userId, $_POST['priority'], 
                    $hasRedFlags, $_POST['red_flag_details'] ?? '', $redFlagCount
                ]);

                $stmtV = $pdo->prepare("INSERT INTO contract_versions (contract_id, version_number, file_name, file_path, file_hash, uploaded_by) VALUES (?, 1, ?, ?, ?, ?)");
                $stmtV->execute([$contractId, $_FILES['file']['name'], $filePath, $fileHash, $userId]);

                logAudit($pdo, $contractId, "Creación", "Contrato cargado");
                $pdo->commit();
                
                sendContractNotification($pdo, $contractId, "Nuevo Contrato Creado", "Categoría: " . $_POST['category'], $_SESSION['name']);

                header("Location: contracts?msg=created"); exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if(file_exists($absPath)) unlink($absPath);
                $error = "Error DB: " . $e->getMessage();
            }
        } else {
            $error = "Error al encriptar el archivo.";
        }
    } else {
        $error = "El archivo es obligatorio.";
    }
}

// === LOGICA FILTROS ===
$where = "1=1"; $params = [];

if (!empty($_GET['q'])) {
    $term = "%" . $_GET['q'] . "%";
    $where .= " AND (c.title LIKE ? OR c.supplier LIKE ? OR c.id LIKE ? OR c.summary LIKE ?)";
    array_push($params, $term, $term, $term, $term);
}
if (in_array($userRole, ['SUPER_ADMIN', 'LEGAL', 'MANAGER']) && !empty($_GET['user_filter'])) {
    $where .= " AND u.name LIKE ?"; $params[] = "%" . $_GET['user_filter'] . "%";
}
if (!empty($_GET['status_filter'])) {
    $where .= " AND c.status = ?"; $params[] = $_GET['status_filter'];
}
if (!empty($_GET['category_filter'])) {
    $where .= " AND c.category = ?"; $params[] = $_GET['category_filter'];
}
if (!empty($_GET['priority_filter'])) {
    $where .= " AND c.priority = ?"; $params[] = $_GET['priority_filter'];
}
if ($userRole === 'DEPT_HEAD' || (isset($_GET['filter']) && $_GET['filter'] == 'my')) {
    $where .= " AND c.initiator_id = ?"; $params[] = $userId;
}

if (isset($_GET['filter'])) {
    switch ($_GET['filter']) {
        case 'active': $where .= " AND c.status NOT IN ('ARCHIVED', 'CANCELLED')"; break;
        case 'risk':   $where .= " AND c.has_red_flags = 1 AND c.status != 'ARCHIVED'"; break;
        case 'priority_high': $where .= " AND c.priority = 'HIGH' AND c.status != 'ARCHIVED'"; break;
        case 'pending': $where .= " AND c.status IN ('LEGAL_REVIEW_FINAL')"; break; 
        case 'ARCHIVED': $where .= " AND c.status = 'ARCHIVED'"; break;
        
        case 'action_required':
            if ($userRole == 'DEPT_HEAD') {
                // LÓGICA ACTUALIZADA:
                // Muestra si es su turno de responder observaciones O SI faltan anexos por subir
                $where .= " AND (
                    (c.status = 'OBSERVATIONS' AND c.observation_turn = 'USER') OR 
                    (c.waiting_for_annexes = 1 AND c.status NOT IN ('ARCHIVED', 'CANCELLED'))
                )";
            } elseif (in_array($userRole, ['LEGAL', 'SUPER_ADMIN'])) {
                // Incluye IN_REVIEW y UNREVIEWED
                $where .= " AND (
                    c.status = 'UNREVIEWED' OR 
                    c.status = 'IN_REVIEW' OR
                    c.status = 'LEGAL_REVIEW_FINAL' OR 
                    c.status = 'SIGNATURE' OR 
                    (c.status = 'OBSERVATIONS' AND c.observation_turn = 'LEGAL')
                )";
            } elseif ($userRole == 'MANAGER') {
                // Dejamos vacío o lógica futura si manager vuelve
                $where .= " AND 1=0"; // No hay acciones para manager en este flujo simplificado
            }
            break;
    }
}

// CONSULTAS
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM contracts c JOIN users u ON c.initiator_id = u.id WHERE $where");
$stmtCount->execute($params);
$totalRecords = $stmtCount->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// SELECT INCLUYENDO observation_turn
$sql = "SELECT c.*, u.name as initiator_name, u.department 
        FROM contracts c 
        JOIN users u ON c.initiator_id = u.id 
        WHERE $where 
        ORDER BY c.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$contracts = $stmt->fetchAll();

// === DICCIONARIOS VISUALES ACTUALIZADOS ===
// Aquí defino qué estados aparecen en el filtro y cómo se ven
$statusConfig = [
    'UNREVIEWED' => [
        'label' => 'Sin Revisar', 
        'class' => 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-700'
    ],
    'IN_REVIEW' => [
        'label' => 'En Revisión', 
        'class' => 'bg-indigo-100 text-indigo-800 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:border-indigo-700'
    ],
    'OBSERVATIONS' => [
        'label' => 'Obs. Legal', 
        'class' => 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-700'
    ],
    'LEGAL_REVIEW_FINAL' => [
        'label' => 'Aprob. Legal', 
        'class' => 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-700'
    ],
    // ELIMINADO: INTERNAL_APPROVALS
    'SIGNATURE' => [
        'label' => 'Firma', 
        'class' => 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700'
    ],
    'ARCHIVED' => [
        'label' => 'Archivado', 
        'class' => 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-600'
    ],
    'CANCELLED' => [
        'label' => 'Anulado', 
        'class' => 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-700'
    ]
];

$pageTitle = "Contratos"; require 'includes/header.php';
?>


<?php
// === LÓGICA DE UI: FILTROS ACTIVOS (PREMIUM & DASHBOARD SUPPORT) ===
$activeFilters = [];

// Helper para limpiar URL
function getUrlWithout($param) {
    $params = $_GET;
    unset($params[$param]);
    unset($params['page']); 
    return '?' . http_build_query($params);
}

// 1. BÚSQUEDA
if (!empty($_GET['q'])) {
    $activeFilters[] = [
        'icon'  => 'search',
        'label' => '"' . htmlspecialchars($_GET['q']) . '"',
        'url'   => getUrlWithout('q'),
        'style' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800'
    ];
}

// 2. ESTADO
if (!empty($_GET['status_filter'])) {
    $label = $statusConfig[$_GET['status_filter']]['label'] ?? $_GET['status_filter'];
    $activeFilters[] = [
        'icon'  => 'activity',
        'label' => $label,
        'url'   => getUrlWithout('status_filter'),
        'style' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-800'
    ];
}

// 3. CATEGORÍA
if (!empty($_GET['category_filter'])) {
    $activeFilters[] = [
        'icon'  => 'tag',
        'label' => htmlspecialchars($_GET['category_filter']),
        'url'   => getUrlWithout('category_filter'),
        'style' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800'
    ];
}

// 4. PRIORIDAD
if (!empty($_GET['priority_filter'])) {
    $prioLabels = ['HIGH'=>'Alta', 'MEDIUM'=>'Media', 'LOW'=>'Baja'];
    $activeFilters[] = [
        'icon'  => 'flame',
        'label' => 'Prioridad: ' . ($prioLabels[$_GET['priority_filter']] ?? $_GET['priority_filter']),
        'url'   => getUrlWithout('priority_filter'),
        'style' => 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300 dark:border-orange-800'
    ];
}

// 5. USUARIO
if (!empty($_GET['user_filter'])) {
    $activeFilters[] = [
        'icon'  => 'user',
        'label' => htmlspecialchars($_GET['user_filter']),
        'url'   => getUrlWithout('user_filter'),
        'style' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800'
    ];
}

// 6. FILTROS ESPECIALES (DASHBOARD/URL DIRECTA) - ¡NUEVO!
if (!empty($_GET['filter'])) {
    $f = $_GET['filter'];
    $chip = null;

    switch ($f) {
        case 'active':
            $chip = ['label' => 'Solo Activos', 'icon' => 'play-circle', 'style' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300'];
            break;
        case 'risk':
            $chip = ['label' => 'Con Riesgos', 'icon' => 'alert-triangle', 'style' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300'];
            break;
        case 'priority_high':
            $chip = ['label' => 'Alta Prioridad (Global)', 'icon' => 'flame', 'style' => 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-900/30 dark:text-orange-300'];
            break;
        case 'ARCHIVED':
            $chip = ['label' => 'Archivo Histórico', 'icon' => 'archive', 'style' => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400'];
            break;
        case 'action_required':
            $chip = ['label' => 'Tu Atención Requerida', 'icon' => 'bell-ring', 'style' => 'bg-pink-50 text-pink-700 border-pink-200 dark:bg-pink-900/30 dark:text-pink-300'];
            break;
    }

    if ($chip) {
        $activeFilters[] = [
            'icon'  => $chip['icon'],
            'label' => $chip['label'],
            'url'   => getUrlWithout('filter'),
            'style' => $chip['style']
        ];
    }
}
?>

<div class="flex flex-col gap-4 mb-6 animate-entry">
    <div class="flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
            <h2 class="text-3xl font-bold text-slate-800 dark:text-white">Contratos</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Visualizando <?= count($contracts) ?> de <?= $totalRecords ?> documentos.
            </p>
        </div>
        <!-- CÁLCULO DEL CONTADOR "TU ATENCIÓN" -->
        <?php 
            $globalActionCount = 0;
            if ($userRole === 'DEPT_HEAD') {
                // Para Solicitante: Observaciones (Turno User) O Faltan Anexos
                $sqlG = "SELECT COUNT(*) FROM contracts WHERE initiator_id = ? AND (
                    (status = 'OBSERVATIONS' AND observation_turn = 'USER') OR 
                    (waiting_for_annexes = 1 AND status NOT IN ('ARCHIVED', 'CANCELLED'))
                )";
                $stmtG = $pdo->prepare($sqlG); $stmtG->execute([$userId]);
                $globalActionCount = $stmtG->fetchColumn();
            } elseif (in_array($userRole, ['LEGAL', 'SUPER_ADMIN'])) {
                // Para Legal: Todos los estados pendientes de acción
                $sqlG = "SELECT COUNT(*) FROM contracts WHERE 
                    status IN ('UNREVIEWED', 'IN_REVIEW', 'LEGAL_REVIEW_FINAL', 'SIGNATURE') OR 
                    (status = 'OBSERVATIONS' AND observation_turn = 'LEGAL')";
                $globalActionCount = $pdo->query($sqlG)->fetchColumn();
            }
        ?>
        <div class="flex gap-2 flex-wrap">
            <!-- BOTÓN TU ATENCIÓN -->
            <?php 
                // === LÓGICA DEL BOTÓN TOGGLE "TU ATENCIÓN" ===
                $isActionActive = (isset($_GET['filter']) && $_GET['filter'] == 'action_required');

                // Clona los parámetros actuales de la URL para no perder búsquedas o categorías
                $toggleParams = $_GET;
                // para evitar buscar datos en una página que no existe en el nuevo set de resultados.
                unset($toggleParams['page']); 

                if ($isActionActive) {
                    // Si ya está activo, al hacer clic queremos QUITAR el filtro
                    unset($toggleParams['filter']);
                } else {
                    // Si no está activo, lo agregamos
                    $toggleParams['filter'] = 'action_required';
                }

                // Construye la URL final
                $toggleUrl = "contracts?" . http_build_query($toggleParams);

                // Definimos las clases visuales según el estado
                $actionClass = $isActionActive 
                    ? "bg-red-600 text-white shadow-lg ring-2 ring-red-300 dark:ring-red-900 scale-105" 
                    : "bg-white dark:bg-slate-800 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900 hover:bg-red-50 dark:hover:bg-red-900/20";
            ?>
            <!-- BOTÓN TU ATENCIÓN (BADGE FLOTANTE) -->
            <a href="<?= $toggleUrl ?>" class="<?= $actionClass ?> relative px-4 py-2 rounded-xl font-bold flex items-center gap-2 transition-all duration-300 group" title="<?= $isActionActive ? 'Ver todos los contratos' : 'Ver solo lo que requiere mi atención' ?>">
                
                <i data-lucide="<?= $isActionActive ? 'bell-off' : 'bell-ring' ?>" class="w-4 h-4 <?= $isActionActive ? '' : 'animate-pulse' ?>"></i> 
                <span class="hidden sm:inline"><?= $isActionActive ? 'Ver Todo' : 'Tu Atención' ?></span>
                
                <!-- BADGE TIPO APP (FLOTANTE) -->
                <?php if($globalActionCount > 0): ?>
                    <span class="absolute -top-2 -right-2 flex min-w-[20px] h-5 items-center justify-center rounded-full bg-red-600 text-[10px] font-bold text-white shadow-md ring-2 ring-white dark:ring-slate-800 px-1 z-10">
                        <?= $globalActionCount > 99 ? '99+' : $globalActionCount ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <!-- BOTÓN TOGGLE FILTROS -->
            <button onclick="toggleFilters()" class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 px-4 py-2 rounded-lg font-medium flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                <i data-lucide="filter" class="w-4 h-4"></i> Filtros
            </button>
            
            <!-- BOTÓN EXPORTAR EXCEL -->
            <button onclick="window.location.href='export_excel.php'+window.location.search" 
                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl font-medium flex items-center gap-2 shadow-sm transition-transform active:scale-95"
                    title="Descargar reporte según filtros actuales">
                <i data-lucide="sheet" class="w-4 h-4"></i> <span class="hidden sm:inline">Excel</span>
            </button>

            <!-- BOTÓN NUEVO -->
            <button onclick="window.openContractModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 shadow-lg transition-transform active:scale-95">
                <i data-lucide="plus"></i> <span class="hidden sm:inline">Nuevo</span>
            </button>
        </div>
    </div>
    
    <!-- BARRA DE FILTROS -->
    <div id="filterSection" class="hidden bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 transition-all duration-300">
        <form method="GET" action="contracts" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            
            <?php if(isset($_GET['filter'])): ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($_GET['filter']) ?>">
            <?php endif; ?>

            <!-- 1. Buscador -->
            <div class="relative md:col-span-1">
                <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400 w-4 h-4"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Buscar texto, ID..." class="w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none dark:text-white transition-all focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- 2. Estado (Actualizado con IN_REVIEW y sin Visto Bueno) -->
            <div>
                <select name="status_filter" class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none dark:text-white cursor-pointer transition-all focus:ring-2 focus:ring-blue-500">
                    <option value="">Estado: Todos</option>
                    <?php foreach($statusConfig as $key => $conf): ?>
                        <option value="<?= $key ?>" <?= (isset($_GET['status_filter']) && $_GET['status_filter'] == $key) ? 'selected' : '' ?>><?= $conf['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- 3. Categoría -->
            <div>
                <select name="category_filter" class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none dark:text-white cursor-pointer transition-all focus:ring-2 focus:ring-blue-500">
                    <option value="">Categoría: Todas</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= (isset($_GET['category_filter']) && $_GET['category_filter'] == $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 4. Prioridad -->
            <div>
                <select name="priority_filter" class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none dark:text-white cursor-pointer transition-all focus:ring-2 focus:ring-blue-500">
                    <option value="">Prioridad: Todas</option>
                    <option value="HIGH" <?= (isset($_GET['priority_filter']) && $_GET['priority_filter'] == 'HIGH') ? 'selected' : '' ?>>Alta</option>
                    <option value="MEDIUM" <?= (isset($_GET['priority_filter']) && $_GET['priority_filter'] == 'MEDIUM') ? 'selected' : '' ?>>Media</option>
                    <option value="LOW" <?= (isset($_GET['priority_filter']) && $_GET['priority_filter'] == 'LOW') ? 'selected' : '' ?>>Baja</option>
                </select>
            </div>

            <!-- 5. Filtro Usuario (Solo Roles Altos) -->
            <?php if(in_array($userRole, ['SUPER_ADMIN', 'LEGAL', 'MANAGER'])): ?>
            <div class="md:col-span-2">
                <input type="text" name="user_filter" value="<?= htmlspecialchars($_GET['user_filter'] ?? '') ?>" placeholder="Filtrar por nombre de solicitante..." class="w-full px-3 py-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none dark:text-white transition-all focus:ring-2 focus:ring-blue-500">
            </div>
            <?php else: ?>
                <div class="md:col-span-2 hidden md:block"></div> 
            <?php endif; ?>

            <div class="flex gap-2 md:col-span-2 justify-end">
                <button type="submit" class="bg-slate-800 dark:bg-slate-700 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-slate-700 transition-colors">Aplicar Filtros</button>
                <?php if(!empty($_GET)): ?>
                    <!-- <a href="contracts" class="px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-lg hover:bg-red-200 transition-colors flex items-center gap-1"><i data-lucide="x" class="w-4 h-4"></i> Limpiar</a> -->
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- CHIPS DE FILTROS ACTIVOS (DISEÑO MODERNO) -->
<?php if (!empty($activeFilters)): ?>
    <div class="flex flex-wrap items-center gap-2 mb-5 animate-fade-in-up px-1">
        
        <!-- Label sutil -->
        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider mr-1 flex items-center gap-1 select-none">
            <i data-lucide="filter" class="w-3 h-3"></i> Filtros:
        </span>
        
        <?php foreach ($activeFilters as $filter): ?>
            <a href="<?= $filter['url'] ?>" 
               class="group relative flex items-center gap-2 px-3 py-1 text-xs font-bold rounded-full border shadow-sm transition-all duration-200 hover:shadow-md hover:scale-105 active:scale-95 <?= $filter['style'] ?>"
               title="Clic para remover filtro">
                
                <!-- Ícono del tipo de filtro -->
                <i data-lucide="<?= $filter['icon'] ?>" class="w-3 h-3 opacity-70 group-hover:hidden"></i>
                
                <!-- Ícono de cerrar (Aparece al hover) -->
                <i data-lucide="x" class="w-3 h-3 hidden group-hover:block text-current"></i>
                
                <span><?= $filter['label'] ?></span>
            </a>
        <?php endforeach; ?>

    </div>
<?php endif; ?>


<!-- CONTENEDOR PRINCIPAL (Estilos y Bordes) -->
<div class="<?= $containerClass ?> rounded-2xl animate-entry transition-all duration-300 relative flex flex-col">   
    
    <!-- LISTA DE CONTRATOS (CONTENEDOR DINÁMICO) -->
<?php
    $isFiltered = !empty($activeFilters);
    
    if ($isFiltered) {
        $theadClass = "bg-gradient-to-r from-blue-50 via-white to-white dark:from-blue-900/40 dark:via-slate-900/50 dark:to-slate-900/50 text-blue-700 dark:text-blue-300 border-b border-blue-100 dark:border-blue-800/50";
        $iconClass = "text-blue-500 animate-pulse"; // El ícono de la columna se moverá sutilmente
    } else {
        $theadClass = "bg-slate-50 dark:bg-slate-900/50 text-slate-500 border-b border-gray-100 dark:border-slate-700";
        $iconClass = "text-slate-400";
    }
?>

<!-- LISTA DE CONTRATOS (CONTENEDOR MEJORADO + STICKY FIX) -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden animate-entry flex flex-col">
    
    <!-- HEADER DE ESTADO (ORDEN INVERTIDO) -->
    <?php if ($isFiltered): ?>
    <div class="bg-indigo-50/50 dark:bg-indigo-900/20 border-b border-indigo-100 dark:border-indigo-800/50 px-4 py-2.5 flex items-center justify-between gap-4 shrink-0 transition-all">
        
        <!-- IZQUIERDA: INDICADOR VISUAL -->
        <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-indigo-500"></span>
            </span>
            <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-300 uppercase tracking-widest">
                Vista Filtrada
            </span>
        </div>

        <!-- DERECHA: BOTÓN DE BORRAR -->
        <a href="contracts" 
           class="flex items-center gap-2 text-xs font-bold text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 transition-colors group"
           title="Eliminar todos los filtros">
            <span class="underline decoration-dotted underline-offset-2 hidden sm:inline">Borrar Filtros</span>
            <div class="p-1.5 bg-white dark:bg-slate-800 rounded-md shadow-sm border border-red-100 dark:border-red-900/30 group-hover:border-red-300">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </div>
        </a>

    </div>
    <?php endif; ?>

    <!-- CONTENEDOR DE SCROLL (AQUÍ ESTÁ LA MAGIA) -->
    <!-- max-h-[70vh]: Define la altura máxima para que el scroll ocurra AQUÍ y el sticky funcione -->
    <div class="overflow-auto max-h-[70vh] w-full custom-scrollbar">
        <table class="w-full text-left border-collapse">
            
            <!-- HEADER STICKY -->
            <!-- z-20: Encima de las filas -->
            <!-- bg-slate-50: Fondo sólido para tapar el contenido al scrollear -->
            <thead class="sticky top-0 z-20 bg-slate-50 dark:bg-slate-900 text-xs uppercase text-slate-500 font-bold border-b border-gray-200 dark:border-slate-700 shadow-sm">
                <tr>
                    <!-- Columna 1: Documento -->
                    <th class="p-4 w-1/3 text-left">
                        <div class="flex items-center gap-2">
                            Documento
                        </div>
                    </th>
                    
                    <th class="p-4 text-left">Estado / Categoría</th>
                    <th class="p-4 text-left">Solicitante</th>
                    
                    <!-- Columna 4: FECHA (LIMPIA) -->
                    <th class="p-4 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <span>Fecha</span>
                            
                            <!-- Solo mostramos botón de Refrescar si NO hay filtros (Modo normal) -->
                            <!-- Si hay filtros, la acción de limpiar ya está en la barra superior -->
                            <?php if (empty($activeFilters)): ?>
                                <a href="contracts" 
                                   class="group flex items-center justify-center w-6 h-6 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-300 hover:text-slate-600 dark:hover:text-slate-200 transition-all duration-300"
                                   data-tooltip="Actualizar lista"
                                   data-color="bg-slate-800">
                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5 group-hover:rotate-180 transition-transform duration-500"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </th>
                </tr>
            </thead>
            
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                <?php foreach($contracts as $c): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer group" onclick="window.location.href='contract_detail?id=<?= $c['id'] ?>'">
                    
                    <td class="p-4 align-top">
                        <div class="flex items-start gap-3">
                            <div class="mt-1">
                                <?php 
                                    $prioLabel = $c['priority'] == 'HIGH' ? 'Prioridad: Alta' : ($c['priority'] == 'LOW' ? 'Prioridad: Baja' : 'Prioridad: Media');
                                    if ($c['priority'] == 'HIGH') echo '<i data-tooltip="'.$prioLabel.'" data-color="bg-slate-800" data-lucide="flame" class="text-red-500 w-5 h-5 cursor-help"></i>';
                                    elseif ($c['priority'] == 'LOW') echo '<i data-tooltip="'.$prioLabel.'" data-color="bg-slate-800" data-lucide="arrow-down" class="text-slate-400 w-5 h-5 cursor-help"></i>';
                                    else echo '<i data-tooltip="'.$prioLabel.'" data-color="bg-slate-800" data-lucide="file-text" class="text-blue-500 w-5 h-5 cursor-help"></i>';
                                ?>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 dark:text-white group-hover:text-blue-600 transition-colors text-sm w-fit cursor-help" data-tooltip="Título del Contrato">
                                    <?= htmlspecialchars($c['title']) ?>
                                    <?php if($c['has_red_flags']): ?>
                                        <span class="inline-block w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse ml-1" data-tooltip="Riesgos Detectados" data-color="bg-red-600"></span>
                                    <?php endif; ?>
                                </h4>
                                <span class="text-xs text-slate-400 font-mono block w-fit cursor-help mt-0.5" data-tooltip="Código de Referencia"><?= $c['id'] ?></span>
                                <p class="text-xs text-slate-500 mt-1 w-fit cursor-help flex items-center gap-1" data-tooltip="Proveedor / Contraparte">
                                   <i data-lucide="building-2" class="w-3 h-3"></i> <?= htmlspecialchars($c['supplier']) ?>
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="p-4 align-top">
                        <div class="flex flex-col items-start gap-1.5">
                            <?php if ($c['waiting_for_annexes'] == 1): ?>
                                <span class="flex items-center gap-1.5 text-[10px] font-bold px-2 py-1 rounded-md border shadow-sm bg-purple-100 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-700" title="El solicitante debe cargar documentos anexos"><i data-lucide="files" class="w-3 h-3"></i> Faltan Anexos</span>
                            <?php endif; ?>
                            <?php $conf = $statusConfig[$c['status']] ?? $statusConfig['UNREVIEWED']; ?>
                            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold border uppercase tracking-wide <?= $conf['class'] ?>"><?= $conf['label'] ?></span>
                            
                            <?php if ($c['status'] == 'OBSERVATIONS'): ?>
                                <?php if ($c['observation_turn'] == 'USER'): ?>
                                    <span class="flex items-center gap-1.5 text-[10px] font-bold px-2 py-1 rounded-md border shadow-sm bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:border-orange-500/30 cursor-help" data-tooltip="Usuario solicitante debe responder" data-color="bg-orange-700"><i data-lucide="user" class="w-3 h-3"></i> Espera Solicitante</span>
                                <?php elseif ($c['observation_turn'] == 'LEGAL'): ?>
                                    <span class="flex items-center gap-1.5 text-[10px] font-bold px-2 py-1 rounded-md border shadow-sm bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:border-blue-500/30 cursor-help" data-tooltip="El abogado está revisando cambios" data-color="bg-blue-700"><i data-lucide="scale" class="w-3 h-3"></i> Espera Legal</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <span class="text-[11px] px-2 py-0.5 rounded border bg-gray-50 text-slate-500 border-gray-100 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700"><?= htmlspecialchars($c['category']) ?></span>
                        </div>
                    </td>

                    <td class="p-4 align-top">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 flex items-center justify-center text-[10px] font-bold"><?= substr($c['initiator_name'], 0, 1) ?></div>
                            <div class="text-xs"><p class="font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($c['initiator_name']) ?></p><p class="text-slate-400"><?= htmlspecialchars($c['department']) ?></p></div>
                        </div>
                    </td>
                    <td class="p-4 align-top text-right"><span class="text-xs font-mono text-slate-500"><?= date('d/m/Y', strtotime($c['created_at'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if($totalPages > 1): ?>
    <div class="p-4 border-t border-gray-100 dark:border-slate-700 flex justify-center gap-1 flex-wrap shrink-0 bg-white dark:bg-slate-800 z-20 relative">
        <?php
        $q = $_GET; unset($q['page']); $lnk = "?" . (http_build_query($q) ? http_build_query($q).'&' : '') . "page=";
        if($page > 1) echo "<a href='{$lnk}".($page-1)."' class='px-3 py-1 text-sm rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'>&laquo;</a>";
        for($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++) echo "<a href='{$lnk}{$i}' class='px-3 py-1 text-sm rounded border ".(($i==$page)?'bg-blue-600 text-white border-blue-600':'bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700 text-slate-600 dark:text-slate-400')."'>$i</a>";
        if($page < $totalPages) echo "<a href='{$lnk}".($page+1)."' class='px-3 py-1 text-sm rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'>&raquo;</a>";
        ?>
    </div>
    <?php endif; ?>
</div>


<script>
    async function analyzeDocument() {
        const fileInput = document.getElementById('fileInput');
        const file = fileInput.files[0];
        if (!file) return;

        const loader = document.getElementById('aiLoading');
        const btn = document.getElementById('btnAnalyze');
        const form = document.getElementById('formFields');
        
        loader.classList.remove('hidden');
        btn.disabled = true;
        if(form) form.style.opacity = "0.5";
        document.getElementById('aiError').classList.add('hidden');

        try {
            let payload = {};
            const toBase64 = f => new Promise((r, j) => { const x=new FileReader(); x.onload=()=>r(x.result); x.onerror=e=>j(e); x.readAsDataURL(f); });

            if (file.name.endsWith('.docx')) {
                const arrayBuffer = await file.arrayBuffer();
                const result = await mammoth.extractRawText({arrayBuffer: arrayBuffer});
                payload = { type: 'text', content: result.value };
            }
            else if (file.type.startsWith('image/')) {
                const b64 = await toBase64(file);
                payload = { type: 'image', mime: file.type, content: b64.split(',')[1] };
            }
            else if (file.type === 'application/pdf') {
                const ab = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: ab }).promise;
                let txt = "";
                for(let i=1; i<=Math.min(pdf.numPages, 3); i++) txt += (await (await pdf.getPage(i)).getTextContent()).items.map(s=>s.str).join(' ')+"\n";
                
                if (txt.trim().length < 50) {
                    const pg = await pdf.getPage(1);
                    const vp = pg.getViewport({ scale: 1.5 });
                    const cvs = document.createElement('canvas'); cvs.width = vp.width; cvs.height = vp.height;
                    await pg.render({ canvasContext: cvs.getContext('2d'), viewport: vp }).promise;
                    payload = { type: 'image', mime: 'image/jpeg', content: cvs.toDataURL('image/jpeg', 0.8).split(',')[1] };
                } else {
                    payload = { type: 'text', content: txt };
                }
            } else throw new Error("Formato no soportado");

            const res = await fetch('api/analyze_gemini.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            document.getElementById('inputTitle').value = data.title||'';
            document.getElementById('inputSupplier').value = data.supplier||'';
            document.getElementById('inputSummary').value = data.summary||'';
            const cat = document.getElementById('inputCategory');
            for(let i=0; i<cat.options.length; i++) if(cat.options[i].value===data.category) cat.selectedIndex=i;

            if (data.red_flags && data.red_flags.count > 0) {
                document.getElementById('aiResults').classList.remove('hidden');
                document.getElementById('displayFlagCount').innerText = data.red_flags.count;
                document.getElementById('displayFlagDetails').innerText = data.red_flags.details.join(', ');
                document.getElementById('aiFlagCount').value = data.red_flags.count;
                document.getElementById('aiFlagDetails').value = data.red_flags.details.join(', ');
            } else {
                document.getElementById('aiResults').classList.add('hidden');
                document.getElementById('aiFlagCount').value = 0;
            }

        } catch (e) {
            console.error(e);
            document.getElementById('aiError').classList.remove('hidden');
        } finally {
            loader.classList.add('hidden');
            btn.disabled = false;
            if(form) form.style.opacity = "1";
        }
    }
</script>

<script>
    function toggleFilters() {
        const section = document.getElementById('filterSection');
        if(section.classList.contains('hidden')) {
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
        }
    }
</script>

<?php require 'includes/footer.php'; ?>