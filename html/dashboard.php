<?php
require 'db.php';
require 'auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 1. CONFIGURACIÓN DEL LÍMITE
if (isset($_GET['limit'])) {
    $_SESSION['limit_graph'] = ($_GET['limit'] === 'all') ? 'all' : max(5, (int)$_GET['limit']);
}
$limit = isset($_SESSION['limit_graph']) ? $_SESSION['limit_graph'] : 10;

$limitClause = ($limit === 'all') ? "" : "LIMIT $limit";

// 2. QUERY PRINCIPAL
$whereBase = "1=1";
$paramsBase = [];
if ($role === 'DEPT_HEAD') {
    $whereBase .= " AND initiator_id = ?";
    $paramsBase[] = $userId;
}

// Agregamos 'observation_turn' al SELECT
$sqlMain = "SELECT status, priority, has_red_flags, category, observation_turn 
            FROM contracts 
            WHERE $whereBase 
            ORDER BY created_at DESC 
            $limitClause";

$stmt = $pdo->prepare($sqlMain);
$stmt->execute($paramsBase);
$dataset = $stmt->fetchAll();

// 3. CÁLCULOS
$total = count($dataset);
$enFlujo = 0;
$archivados = 0;
$altaPrioridad = 0;
$accionRequerida = 0;

$statusCounts = [];
$categoryCounts = [];

foreach ($dataset as $row) {
    // A. Clasificación General
    if ($row['status'] === 'ARCHIVED') {
        $archivados++;
    } elseif ($row['status'] !== 'CANCELLED') {
        $enFlujo++;
        if ($row['priority'] === 'HIGH') $altaPrioridad++;
    }

    // B. "TU ATENCIÓN" (Action Required)
    $needsAction = false;
    if ($role === 'DEPT_HEAD') {
        if ($row['status'] === 'OBSERVATIONS' && $row['observation_turn'] === 'USER') {
            $needsAction = true;
        }
    } elseif (in_array($role, ['LEGAL', 'SUPER_ADMIN'])) {
        if ($row['status'] === 'UNREVIEWED') $needsAction = true;
        if ($row['status'] === 'IN_REVIEW') $needsAction = true; 
        if ($row['status'] === 'OBSERVATIONS' && $row['observation_turn'] === 'LEGAL') $needsAction = true;
        if ($row['status'] === 'LEGAL_REVIEW_FINAL') $needsAction = true;
        if ($row['status'] === 'SIGNATURE') $needsAction = true;
    }
    
    if ($needsAction) $accionRequerida++;

    // C. Datos Gráficos
    if (!isset($statusCounts[$row['status']])) $statusCounts[$row['status']] = 0;
    $statusCounts[$row['status']]++;

    $cat = $row['category'] ?: 'Sin Categoría';
    if (!isset($categoryCounts[$cat])) $categoryCounts[$cat] = 0;
    $categoryCounts[$cat]++;
}

// 4. PREPARAR DATOS GRÁFICOS
$statusLabels = []; $statusData = []; $statusColors = []; $statusKeys = [];
$catLabels = []; $catData = []; $catKeys = [];

// === CAMBIO AQUÍ: TRADUCCIONES Y COLORES ACTUALIZADOS ===
$colorMap = [
    'UNREVIEWED' => '#facc15',          // Amarillo
    'IN_REVIEW' => '#6366f1',           // Indigo (Nuevo)
    'OBSERVATIONS' => '#fb923c',        // Naranja
    'LEGAL_REVIEW_FINAL' => '#60a5fa',  // Azul
    'INTERNAL_APPROVALS' => '#c084fc',  // Morado
    'SIGNATURE' => '#818cf8',           // Indigo Claro
    'ARCHIVED' => '#94a3b8',            // Gris
    'CANCELLED' => '#ef4444'            // Rojo
];

$labelMap = [
    'UNREVIEWED' => 'Sin Revisar',
    'IN_REVIEW' => 'En Revisión',       // (Nuevo)
    'OBSERVATIONS' => 'Obs. Legal',
    'LEGAL_REVIEW_FINAL' => 'Aprob. Legal',
    'INTERNAL_APPROVALS' => 'Visto Bueno',
    'SIGNATURE' => 'Firma',
    'ARCHIVED' => 'Archivado',
    'CANCELLED' => 'Anulado'
];

foreach ($statusCounts as $status => $count) {
    $statusKeys[] = $status;
    $labelName = $labelMap[$status] ?? $status;
    $statusLabels[] = "$labelName ($count)"; 
    $statusData[] = $count;
    $statusColors[] = $colorMap[$status] ?? '#cbd5e1';
}

foreach ($categoryCounts as $cat => $count) {
    $catKeys[] = $cat;
    $catLabels[] = $cat;
    $catData[] = $count;
}

// 5. LOGS
$logsLimit = ($limit === 'all') ? 100 : $limit; 
$logsSql = "
    SELECT l.*, u.name as user_name, c.title as contract_title 
    FROM audit_logs l 
    JOIN users u ON l.performed_by = u.id 
    JOIN contracts c ON l.contract_id = c.id 
    WHERE 1=1 " . ($role === 'DEPT_HEAD' ? " AND c.initiator_id = ?" : "") . "
    ORDER BY l.timestamp DESC LIMIT $logsLimit";

$stmtLogs = $pdo->prepare($logsSql);
if ($role === 'DEPT_HEAD') $stmtLogs->execute([$userId]);
else $stmtLogs->execute();
$logs = $stmtLogs->fetchAll();

$pageTitle = "Dashboard";
require 'includes/header.php'; 
?>

<!-- HEADER -->
<div class="flex justify-between items-center mb-6 animate-entry">
    <h2 class="text-3xl font-bold text-slate-800 dark:text-white">Dashboard</h2>
    
    <form method="GET" class="flex items-center gap-2 bg-white dark:bg-slate-800 p-2 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700">
        <label class="text-xs font-bold text-slate-500 uppercase ml-1">DATOS:</label>
        <select name="limit" onchange="this.form.submit()" class="bg-slate-100 dark:bg-slate-900 border-none rounded-lg px-3 py-1 text-sm outline-none cursor-pointer dark:text-white font-bold text-blue-600">
            <?php foreach([10, 20, 30, 50] as $val): ?>
                <option value="<?= $val ?>" <?= $limit == $val ? 'selected' : '' ?>>Últimos <?= $val ?></option>
            <?php endforeach; ?>
            <option value="all" <?= $limit === 'all' ? 'selected' : '' ?>>Todos</option>
        </select>
    </form>
</div>

<!-- KPIS -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8 animate-entry">
    <!-- 1. TOTAL -->
    <a href="contracts" 
       class="bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 hover:shadow-md transition-all group relative cursor-help"
       data-tooltip="Total histórico de documentos cargados en el sistema según la cantidad de datos seleccionada"
       data-color="bg-slate-800">
        <p class="text-xs font-bold text-slate-400 uppercase">MUESTRA DOCS</p>
        <h3 class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?= $total ?></h3>
    </a>
    
    <!-- 2. EN PROCESO -->
    <a href="contracts?filter=active" 
       class="bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 hover:shadow-md transition-all group cursor-help"
       data-tooltip="Contratos activos (excluye Archivados y Cancelados) a partir de los últimos <?= $total ?> documentos"
       data-color="bg-green-600">
        <div class="flex justify-between">
            <div><p class="text-xs font-bold text-slate-400 uppercase">EN PROCESO</p><h3 class="text-2xl font-bold text-slate-800 dark:text-white mt-1"><?= $enFlujo ?></h3></div>
            <i data-lucide="activity" class="text-green-500 opacity-50"></i>
        </div>
    </a>

    <!-- 3. ARCHIVADOS -->
    <a href="contracts?filter=ARCHIVED" 
       class="bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 hover:shadow-md transition-all group cursor-help"
       data-tooltip="Expedientes cerrados y finalizados exitosamente a partir de los últimos <?= $total ?> documentos"
       data-color="bg-slate-600">
        <div class="flex justify-between">
            <div><p class="text-xs font-bold text-slate-400 uppercase">ARCHIVADOS</p><h3 class="text-2xl font-bold text-slate-600 dark:text-slate-400 mt-1"><?= $archivados ?></h3></div>
            <i data-lucide="archive" class="text-slate-400 opacity-50"></i>
        </div>
    </a>

    <!-- 4. ALTA PRIORIDAD -->
    <a href="contracts?filter=priority_high" 
       class="bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 hover:shadow-md transition-all group cursor-help"
       data-tooltip="Contratos marcados como Urgentes/Críticos a partir de los últimos <?= $total ?> documentos"
       data-color="bg-orange-600">
        <div class="flex justify-between">
            <div><p class="text-xs font-bold text-slate-400 uppercase">ALTA PRIO.</p><h3 class="text-2xl font-bold text-orange-500 mt-1"><?= $altaPrioridad ?></h3></div>
            <i data-lucide="flame" class="text-orange-500 opacity-50"></i>
        </div>
    </a>

    <!-- KPI "TU ATENCIÓN"  -->
    <?php 
    $globalActionCount = 0;
    if ($role === 'DEPT_HEAD') {
        // LÓGICA SOLICITANTE: Observaciones (Turno User) O Faltan Anexos
        $sqlG = "SELECT COUNT(*) FROM contracts 
                 WHERE initiator_id = ? 
                 AND (
                    (status = 'OBSERVATIONS' AND observation_turn = 'USER') OR 
                    (waiting_for_annexes = 1 AND status NOT IN ('ARCHIVED', 'CANCELLED'))
                 )";
        $stmtG = $pdo->prepare($sqlG); $stmtG->execute([$userId]);
        $globalActionCount = $stmtG->fetchColumn();
    } elseif (in_array($role, ['LEGAL', 'SUPER_ADMIN'])) {
        // LÓGICA LEGAL: Cualquier cosa pendiente de revisión o firma
        $sqlG = "SELECT COUNT(*) FROM contracts WHERE 
            status IN ('UNREVIEWED', 'IN_REVIEW', 'LEGAL_REVIEW_FINAL', 'SIGNATURE') OR 
            (status='OBSERVATIONS' AND observation_turn='LEGAL')";
        $globalActionCount = $pdo->query($sqlG)->fetchColumn();
    }
    ?>
    
    <!-- 5. TU ATENCIÓN -->
    <a href="contracts?filter=action_required" 
       class="bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 hover:shadow-md transition-all group relative overflow-hidden cursor-help"
       data-tooltip="Tareas pendientes que requieren tu intervención (cantidad total)"
       data-color="bg-red-600">
        <div class="absolute right-0 top-0 h-full w-1 <?= $globalActionCount > 0 ? 'bg-red-500' : 'bg-slate-200' ?>"></div>
        <div class="flex justify-between items-center">
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">TU ATENCIÓN</p>
                <h3 class="text-2xl font-bold <?= $globalActionCount > 0 ? 'text-red-600' : 'text-slate-400' ?> mt-1"><?= $globalActionCount ?></h3>
            </div>
            <div class="p-3 <?= $globalActionCount > 0 ? 'bg-red-50 dark:bg-red-900/20 text-red-600 animate-pulse' : 'bg-slate-50 dark:bg-slate-800 text-slate-300' ?> rounded-xl">
                <i data-lucide="bell-ring" class="w-6 h-6"></i>
            </div>
        </div>
    </a>
</div>

<!-- GRÁFICOS -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 animate-entry">
        <h3 class="text-lg font-bold mb-4 text-slate-800 dark:text-white">Estados (Muestra: <?= $limit === 'all' ? 'Todo' : $total ?>)</h3>
        <div class="h-64 flex justify-center"><canvas id="statusChart"></canvas></div>
    </div>
    <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 animate-entry">
        <h3 class="text-lg font-bold mb-4 text-slate-800 dark:text-white">Categorías (Muestra: <?= $limit === 'all' ? 'Todo' : $total ?>)</h3>
        <div class="h-64 flex justify-center"><canvas id="categoryChart"></canvas></div>
    </div>
</div>

<!-- TABLA LOGS -->
<div class="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 animate-entry overflow-hidden">
    <h3 class="text-lg font-bold mb-4 text-slate-800 dark:text-white">Movimientos Recientes</h3>
    <div class="overflow-y-auto max-h-64">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                <?php foreach($logs as $log): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer" onclick="window.location.href='contract_detail?id=<?= $log['contract_id'] ?>'">
                    <td class="py-3 px-2">
                        <span class="font-bold block text-slate-700 dark:text-slate-200"><?= htmlspecialchars($log['contract_title']) ?></span>
                        <span class="text-xs text-slate-500"><?= htmlspecialchars($log['action']) ?> por <b><?= htmlspecialchars($log['user_name']) ?></b></span>
                    </td>
                    <td class="py-3 px-2 text-right text-xs text-slate-400 font-mono"><?= date('H:i d/m', strtotime($log['timestamp'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    const chartConfig = {
        status: {
            keys: <?= json_encode($statusKeys) ?>,
            labels: <?= json_encode($statusLabels) ?>,
            data: <?= json_encode($statusData) ?>,
            colors: <?= json_encode($statusColors) ?>
        },
        category: {
            keys: <?= json_encode($catKeys) ?>,
            labels: <?= json_encode($catLabels) ?>,
            data: <?= json_encode($catData) ?>
        }
    };

    window.initDashboardCharts = function() {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#e2e8f0' : '#64748b';

        // 1. CHART ESTADOS
        const ctxStatus = document.getElementById('statusChart');
        if (ctxStatus) {
            if (window.statusChartInstance) window.statusChartInstance.destroy();
            window.statusChartInstance = new Chart(ctxStatus.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: chartConfig.status.labels,
                    datasets: [{
                        data: chartConfig.status.data,
                        backgroundColor: chartConfig.status.colors,
                        borderWidth: 0, hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '65%',
                    plugins: { 
                        legend: { position: 'right', labels: { color: textColor, boxWidth: 12, font: {size: 11} } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    label = label.split('(')[0].trim();
                                    let value = context.raw;
                                    let total = context.chart._metasets[context.datasetIndex].total;
                                    let percentage = Math.round((value / total) * 100) + '%';
                                    return `${label}: ${value} docs (${percentage})`;
                                }
                            }
                        }
                    },
                    onClick: (evt, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const statusKey = chartConfig.status.keys[index];
                            window.location.href = `contracts?status_filter=${statusKey}`;
                        }
                    },
                    onHover: (event, chartElement) => {
                        event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
                    }
                }
            });
        }

        // 2. CHART CATEGORÍAS
        const ctxCat = document.getElementById('categoryChart');
        if (ctxCat) {
            if (window.catChartInstance) window.catChartInstance.destroy();
            window.catChartInstance = new Chart(ctxCat.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: chartConfig.category.labels,
                    datasets: [{
                        label: 'Contratos',
                        data: chartConfig.category.data,
                        backgroundColor: '#3b82f6', borderRadius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: isDark ? '#334155' : '#e2e8f0' }, ticks: { color: textColor } },
                        x: { grid: { display: false }, ticks: { color: textColor } }
                    },
                    plugins: { legend: { display: false } },
                    onClick: (evt, elements) => {
                        if (elements.length > 0) {
                            const index = elements[0].index;
                            const catKey = chartConfig.category.keys[index];
                            window.location.href = `contracts?category_filter=${encodeURIComponent(catKey)}`;
                        }
                    },
                    onHover: (event, chartElement) => {
                        event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
                    }
                }
            });
        }
    };

    document.addEventListener('DOMContentLoaded', window.initDashboardCharts);
</script>

<?php require 'includes/footer.php'; ?>