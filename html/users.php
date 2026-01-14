<?php
require 'db.php';
require 'auth.php';
requireLogin();

// SEGURIDAD: Solo Admin O Usuarios con Permiso Especial
// Nota: hasRole('SUPER_ADMIN') ya devuelve true/false
$canManage = (!empty($_SESSION['can_manage_users']) && $_SESSION['can_manage_users'] == 1);

// Si NO es super admin Y NO tiene permiso especial -> Fuera
if (!hasRole('SUPER_ADMIN') && !$canManage) { 
    header("Location: dashboard"); exit; 
}

$msg = '';
$error = '';

// === PROCESAMIENTO DE FORMULARIOS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Captura de datos comunes
        $role = $_POST['role'] ?? 'DEPT_HEAD';
        $notifPref = $_POST['notification_preference'] ?? 'INVOLVED';
        $permManage = isset($_POST['can_manage_users']) ? 1 : 0;

        // Regla de Negocio: Solicitante no puede tener 'ALL'
        if ($role === 'DEPT_HEAD' && $notifPref === 'ALL') {
            $notifPref = 'INVOLVED';
        }

        // ACCIÓN 1: CREAR USUARIO
        if (isset($_POST['action']) && $_POST['action'] === 'create') {
            $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, email, role, department, is_active, notification_preference, can_manage_users) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)");
            $stmt->execute([
                $_POST['username'], 
                $passHash, 
                $_POST['name'], 
                $_POST['email'], 
                $role, 
                $_POST['department'], 
                $notifPref,
                $permManage
            ]);
            
            header("Location: users?msg=created"); exit;
        }

        // ACCIÓN 2: EDITAR USUARIO
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = $_POST['user_id'];
            
            if (!empty($_POST['password'])) {
                // Con cambio de contraseña
                $passHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, department=?, username=?, password=?, notification_preference=?, can_manage_users=? WHERE id=?");
                $stmt->execute([$_POST['name'], $_POST['email'], $role, $_POST['department'], $_POST['username'], $passHash, $notifPref, $permManage, $id]);
            } else {
                // Sin cambio de contraseña
                $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=?, department=?, username=?, notification_preference=?, can_manage_users=? WHERE id=?");
                $stmt->execute([$_POST['name'], $_POST['email'], $role, $_POST['department'], $_POST['username'], $notifPref, $permManage, $id]);
            }
            header("Location: users?msg=updated"); exit;
        }

        // ACCIÓN 3: CAMBIAR ESTADO (TOGGLE)
        if (isset($_POST['toggle_status'])) {
            if ($_POST['user_id'] != $_SESSION['user_id']) {
                $newStatus = $_POST['current_status'] == 1 ? 0 : 1; 
                
                if ($newStatus == 0) {
                    // Desactivar: Quitar acceso y notificaciones
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, notification_preference = 'NONE' WHERE id = ?");
                } else {
                    // Reactivar: Solo acceso
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                }
                
                $stmt->execute([$_POST['user_id']]);
                
                $msgType = $newStatus == 1 ? 'activated' : 'deactivated';
                header("Location: users?msg=$msgType"); exit;
            } else {
                $error = "No puedes desactivar tu propia cuenta.";
            }
        }
    } catch (PDOException $e) {
        $error = ($e->getCode() == 23000) ? "El usuario o correo ya existen." : "Error BD: " . $e->getMessage();
    }
}

// === FILTROS DE BÚSQUEDA ===
$where = "1=1";
$params = [];

if (!empty($_GET['q'])) {
    $term = "%" . $_GET['q'] . "%";
    $where .= " AND (name LIKE ? OR email LIKE ? OR username LIKE ?)";
    array_push($params, $term, $term, $term);
}

if (!empty($_GET['role_filter'])) {
    $where .= " AND role = ?";
    $params[] = $_GET['role_filter'];
}

$stmtUserList = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC");
$stmtUserList->execute($params);
$users = $stmtUserList->fetchAll();

$roleNames = [ 'SUPER_ADMIN' => 'Admin Sistema', 'DEPT_HEAD' => 'Solicitante', 'LEGAL' => 'Legal', 'MANAGER' => 'Gerencia' ];
$pageTitle = "Gestión de Usuarios";
require 'includes/header.php';
?>

<!-- ENCABEZADO Y BOTÓN NUEVO -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 animate-entry gap-4">
    <div>
        <h2 class="text-3xl font-bold text-slate-800 dark:text-white">Usuarios del Sistema</h2>
        <p class="text-slate-500 dark:text-slate-400">Administración de accesos.</p>
    </div>
    <!-- ESTE BOTÓN DEBE SER VISIBLE PARA TODOS LOS QUE PASEN LA VALIDACIÓN DE SEGURIDAD -->
    <button onclick="window.openUserModal('create')" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl shadow-lg flex items-center justify-center gap-2 transition-transform hover:scale-105">
        <i data-lucide="user-plus"></i> Nuevo Usuario
    </button>
</div>

<!-- ALERTAS -->
<?php if(isset($_GET['msg'])): ?>
    <div id="alert-box" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded animate-entry">Operación realizada con éxito.</div>
    <script>setTimeout(() => document.getElementById('alert-box').style.display='none', 4000);</script>
<?php endif; ?>
<?php if($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?= $error ?></div><?php endif; ?>

<!-- BARRA DE FILTROS -->
<div class="bg-white dark:bg-slate-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 mb-6 animate-entry">
    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
        <div class="flex-1 w-full">
            <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">Buscar Usuario</label>
            <div class="relative">
                <i data-lucide="search" class="absolute left-3 top-2.5 text-slate-400 w-4 h-4"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Nombre, correo o usuario..." class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white transition-all">
            </div>
        </div>
        <div class="w-full md:w-48">
            <label class="text-xs font-bold text-slate-500 uppercase mb-1 block">Rol</label>
            <select name="role_filter" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white cursor-pointer">
                <option value="">Todos los Roles</option>
                <?php foreach($roleNames as $key => $label): ?>
                    <option value="<?= $key ?>" <?= (isset($_GET['role_filter']) && $_GET['role_filter'] == $key) ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-slate-800 dark:bg-slate-700 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"><i data-lucide="filter" class="w-4 h-4"></i> Filtrar</button>
            <?php if(!empty($_GET['q']) || !empty($_GET['role_filter'])): ?>
                <a href="users" class="bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2" title="Limpiar"><i data-lucide="x" class="w-4 h-4"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- TABLA DE USUARIOS -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden animate-entry">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-700">
                <tr>
                    <th class="p-5 text-xs font-bold text-slate-500 uppercase w-24 text-center">Acceso</th>
                    <th class="p-5 text-xs font-bold text-slate-500 uppercase">Usuario</th>
                    <th class="p-5 text-xs font-bold text-slate-500 uppercase">Rol</th>
                    <th class="p-5 text-xs font-bold text-slate-500 uppercase">Notificaciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                <?php foreach($users as $u): ?>
                <tr onclick='window.openUserModal("edit", <?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)' 
                    class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer group">
                    
                    <!-- TOGGLE SWITCH -->
                    <td class="p-5 text-center" onclick="event.stopPropagation()">
                        <?php if($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $u['is_active'] ?>">
                                <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 <?= $u['is_active'] ? 'bg-blue-600' : 'bg-slate-200 dark:bg-slate-700' ?>" title="<?= $u['is_active'] ? 'Desactivar acceso' : 'Habilitar acceso' ?>">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-300 ease-in-out <?= $u['is_active'] ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="relative inline-flex h-6 w-11 items-center rounded-full bg-blue-600 opacity-50 cursor-not-allowed"><span class="inline-block h-4 w-4 transform rounded-full bg-white translate-x-6"></span></div>
                        <?php endif; ?>
                        <div class="text-[10px] font-bold mt-1 <?= $u['is_active'] ? 'text-blue-600' : 'text-slate-400' ?>"><?= $u['is_active'] ? 'ACTIVO' : 'INACTIVO' ?></div>
                    </td>
                    
                    <!-- DATOS -->
                    <td class="p-5 font-medium text-slate-700 dark:text-slate-200">
                         <div class="font-bold group-hover:text-blue-600 transition-colors flex items-center gap-2">
                             <?= htmlspecialchars($u['name']) ?>
                             <i data-lucide="edit-2" class="w-3 h-3 text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity"></i>
                         </div>
                         <div class="text-xs text-blue-600 dark:text-blue-400"><?= htmlspecialchars($u['email']) ?></div>
                         <div class="text-xs text-slate-400 font-normal"><?= htmlspecialchars($u['username']) ?></div>
                    </td>
                    
                    <!-- ROL -->
                    <td class="p-5 text-sm text-slate-600 dark:text-slate-300">
                        <?= $roleNames[$u['role']] ?? $u['role'] ?>
                        <?php if(!empty($u['can_manage_users']) && $u['can_manage_users']): ?>
                            <span class="block mt-1 w-fit text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded border border-indigo-200" title="Permiso Gestión Usuarios">+Admin</span>
                        <?php endif; ?>
                    </td>
                    
                    <!-- NOTIFICACIONES -->
                    <td class="p-5">
                        <?php 
                            $prefLabels = ['NONE'=>'Sin Correos', 'INVOLVED'=>'Solo Involucrado', 'ALL'=>'Todo el Sistema'];
                            $prefColor = ['NONE'=>'text-gray-400', 'INVOLVED'=>'text-blue-600', 'ALL'=>'text-purple-600 font-bold'];
                        ?>
                        <span class="text-xs <?= $prefColor[$u['notification_preference']] ?>"><?= $prefLabels[$u['notification_preference']] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL COMPLETO CON CHECKBOX -->
<div id="modalUser" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="window.closeUserModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md p-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-slate-700">
            <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center">
                <h3 id="modalTitle" class="text-xl font-bold text-slate-800 dark:text-white">Nuevo Usuario</h3>
                <button onclick="window.closeUserModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-white"><i data-lucide="x"></i></button>
            </div>
            <form method="POST" class="p-6 space-y-4" onsubmit="window.showLoading('Guardando Usuario', 'Actualizando base de datos...')">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="userId">
                
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre</label><input type="text" name="name" id="inputName" required class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg px-4 py-2 dark:text-white outline-none"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label><input type="email" name="email" id="inputEmail" required class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg px-4 py-2 dark:text-white outline-none"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Usuario</label><input type="text" name="username" id="inputUser" required class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg px-4 py-2 dark:text-white outline-none"></div>
                <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Contraseña <span id="passHint" class="text-xs text-gray-400 hidden">(Opcional al editar)</span></label><input type="password" name="password" id="inputPass" class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg px-4 py-2 dark:text-white outline-none"></div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Rol</label>
                        <select name="role" id="inputRole" class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg px-4 py-2 dark:text-white outline-none cursor-pointer">
                            <option value="DEPT_HEAD">Solicitante</option>
                            <option value="LEGAL">Legal</option>
                            <option value="MANAGER">Gerencia</option>
                            <option value="SUPER_ADMIN">Admin</option>
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Depto</label><input type="text" name="department" id="inputDept" required class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg px-4 py-2 dark:text-white outline-none"></div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notificaciones</label>
                    <select name="notification_preference" id="inputNotify" class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-lg px-4 py-2 dark:text-white outline-none cursor-pointer">
                        <option value="NONE">🔕 Desactivadas</option>
                        <option value="INVOLVED">🔔 Solo Involucrado</option>
                        <option value="ALL">📢 Todo el Sistema</option>
                    </select>
                </div>
                
                <div class="mt-4 p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-100 dark:border-indigo-800">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="can_manage_users" id="inputCanManage" value="1" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500">
                        <div>
                            <span class="block text-sm font-bold text-slate-700 dark:text-slate-200">Permiso de Gestión de Usuarios</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Permite crear y editar usuarios sin ser Admin.</span>
                        </div>
                    </label>
                </div>

                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="window.closeUserModal()" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>