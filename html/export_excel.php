<?php
// export_excel.php
require 'db.php';
require 'auth.php';
requireLogin();

// 1. REPLICAR LÓGICA DE FILTROS (Misma que contracts.php)
$where = "1=1"; 
$params = [];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if (!empty($_GET['q'])) {
    $term = "%" . $_GET['q'] . "%";
    $where .= " AND (c.title LIKE ? OR c.supplier LIKE ? OR c.id LIKE ? OR c.summary LIKE ?)";
    array_push($params, $term, $term, $term, $term);
}
if (!empty($_GET['status_filter'])) { $where .= " AND c.status = ?"; $params[] = $_GET['status_filter']; }
if (!empty($_GET['category_filter'])) { $where .= " AND c.category = ?"; $params[] = $_GET['category_filter']; }
if (!empty($_GET['priority_filter'])) { $where .= " AND c.priority = ?"; $params[] = $_GET['priority_filter']; }
if (in_array($userRole, ['SUPER_ADMIN', 'LEGAL', 'MANAGER']) && !empty($_GET['user_filter'])) {
    $where .= " AND u.name LIKE ?"; $params[] = "%" . $_GET['user_filter'] . "%";
}
if ($userRole === 'DEPT_HEAD' || (isset($_GET['filter']) && $_GET['filter'] == 'my')) {
    $where .= " AND c.initiator_id = ?"; $params[] = $userId;
}

// Filtros Especiales
if (isset($_GET['filter'])) {
    switch ($_GET['filter']) {
        case 'active': $where .= " AND c.status NOT IN ('ARCHIVED', 'CANCELLED')"; break;
        case 'risk':   $where .= " AND c.has_red_flags = 1 AND c.status != 'ARCHIVED'"; break;
        case 'priority_high': $where .= " AND c.priority = 'HIGH' AND c.status != 'ARCHIVED'"; break;
        case 'ARCHIVED': $where .= " AND c.status = 'ARCHIVED'"; break;
        case 'action_required':
            if ($userRole == 'DEPT_HEAD') {
                $where .= " AND ((c.status = 'OBSERVATIONS' AND c.observation_turn = 'USER') OR (c.waiting_for_annexes = 1 AND c.status NOT IN ('ARCHIVED', 'CANCELLED')))";
            } elseif (in_array($userRole, ['LEGAL', 'SUPER_ADMIN'])) {
                $where .= " AND (c.status IN ('UNREVIEWED', 'IN_REVIEW', 'LEGAL_REVIEW_FINAL', 'SIGNATURE') OR (c.status = 'OBSERVATIONS' AND c.observation_turn = 'LEGAL'))";
            }
            break;
    }
}

// 2. CONSULTA (SIN LÍMITE DE PAGINACIÓN)
$sql = "SELECT c.*, u.name as initiator_name, u.department 
        FROM contracts c 
        JOIN users u ON c.initiator_id = u.id 
        WHERE $where 
        ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

// 3. CONFIGURAR CABECERAS EXCEL
$filename = "Reporte_GIC_" . date('Y-m-d_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 4. MAPAS DE TEXTO
$statusMap = [
    'UNREVIEWED' => 'Sin Revisar', 'IN_REVIEW' => 'En Revisión', 'OBSERVATIONS' => 'Observaciones',
    'LEGAL_REVIEW_FINAL' => 'Aprobado Legal', 'SIGNATURE' => 'En Firma', 
    'ARCHIVED' => 'Archivado', 'CANCELLED' => 'Anulado'
];
$priorityMap = ['HIGH' => 'Alta', 'MEDIUM' => 'Media', 'LOW' => 'Baja'];
?>

<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <!-- Estilos CSS inline para Excel -->
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #1e3a8a; color: white; font-weight: bold; border: 1px solid #000000; padding: 10px; text-align: center; }
        td { border: 1px solid #cccccc; padding: 8px; vertical-align: middle; }
        .prio-HIGH { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
        .status-badge { background-color: #f1f5f9; border: 1px solid #cbd5e1; }
        .title { font-size: 14px; font-weight: bold; color: #1e293b; }
        .meta { font-size: 11px; color: #64748b; }
        .risk { background-color: #ef4444; color: white; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th width="150">ID Referencia</th>
                <th width="350">Detalle del Contrato</th>
                <th width="200">Estado y Gestión</th>
                <th width="200">Responsable</th>
                <th width="120">Fecha Creación</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($contracts as $c): ?>
            <tr>
                <!-- COL 1: ID y Riesgo -->
                <td align="center" style="vertical-align: top;">
                    <strong><?= $c['id'] ?></strong>
                    <?php if($c['has_red_flags']): ?>
                        <br><br><div class="risk">RIESGOS DETECTADOS</div>
                    <?php endif; ?>
                </td>

                <!-- COL 2: Info Principal Agrupada -->
                <td style="vertical-align: top;">
                    <div class="title"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="meta">Proveedor: <?= htmlspecialchars($c['supplier']) ?></div>
                    <div class="meta">Categoría: <?= htmlspecialchars($c['category']) ?></div>
                    <?php if($c['summary']): ?>
                        <br><i style="color:#475569; font-size:10px;">"<?= substr(htmlspecialchars($c['summary']), 0, 100) ?>..."</i>
                    <?php endif; ?>
                </td>

                <!-- COL 3: Gestión -->
                <td style="vertical-align: top;">
                    Status: <strong><?= $statusMap[$c['status']] ?? $c['status'] ?></strong>
                    <br>
                    Prioridad: <span class="prio-<?= $c['priority'] ?>"><?= $priorityMap[$c['priority']] ?></span>
                    <?php if($c['waiting_for_annexes']): ?>
                        <br><span style="color:#9333ea; font-weight:bold;">Pendiente Anexos</span>
                    <?php endif; ?>
                </td>

                <!-- COL 4: Usuario -->
                <td style="vertical-align: top;">
                    <?= htmlspecialchars($c['initiator_name']) ?>
                    <br>
                    <span class="meta"><?= htmlspecialchars($c['department']) ?></span>
                </td>

                <!-- COL 5: Fecha -->
                <td align="center" style="vertical-align: top;">
                    <?= date('d/m/Y', strtotime($c['created_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>