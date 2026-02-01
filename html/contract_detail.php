<?php
require 'db.php';
require 'auth.php';
require 'functions.php';
requireLogin();

$id = $_GET['id'] ?? '';
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 1. OBTENER CONTRATO
$stmt = $pdo->prepare("SELECT c.*, u.name as initiator_name 
                       FROM contracts c 
                       JOIN users u ON c.initiator_id = u.id 
                       WHERE c.id = ?");
$stmt->execute([$id]);
$contract = $stmt->fetch();
// --- INICIO AGREGADO ANEXOS ---
$stmtAnn = $pdo->prepare("SELECT * FROM contract_annexes WHERE contract_id = ?");
$stmtAnn->execute([$id]);
$annexes = $stmtAnn->fetchAll();
$pendingAnnexesCount = 0;
foreach($annexes as $ann) if($ann['status'] == 'PENDING') $pendingAnnexesCount++;
// --- FIN AGREGADO ANEXOS ---

if (!$contract) die("Contrato no encontrado");

// Seguridad: Solicitante solo ve lo suyo
if ($role == 'DEPT_HEAD' && $contract['initiator_id'] != $userId) {
    header("Location: contracts.php"); exit;
}

// Variables de control UI
$showReviewToast = false;

// === LÓGICA AUTOMÁTICA: ACUSE DE RECIBO ===
if (in_array($role, ['LEGAL', 'SUPER_ADMIN']) && $contract['status'] == 'UNREVIEWED') {
    $stmtUpd = $pdo->prepare("UPDATE contracts SET status = 'IN_REVIEW' WHERE id = ?");
    $stmtUpd->execute([$id]);
    $contract['status'] = 'IN_REVIEW';
    $showReviewToast = true;
}

// === LÓGICA BACKEND ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. DEFINICIÓN DE VARIABLES (CRÍTICO: ESTO DEBE IR PRIMERO)
    $action = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    // ------------------------------------------------------------------
    // LÓGICA ANEXOS: BORRAR (Solo Legal y si no está archivado)
    // ------------------------------------------------------------------
    if ($action === 'delete_annex' && hasRole(['LEGAL', 'SUPER_ADMIN'])) {
        // Validar estado del contrato
        if (in_array($contract['status'], ['ARCHIVED', 'CANCELLED'])) {
            die("No se pueden eliminar anexos en contratos archivados o cancelados.");
        }

        $annexId = $_POST['annex_id'];
        
        // Obtener rutas para borrar archivos físicos
        $stmtGet = $pdo->prepare("SELECT template_path, signed_path, doc_label FROM contract_annexes WHERE id = ? AND contract_id = ?");
        $stmtGet->execute([$annexId, $id]);
        $annData = $stmtGet->fetch();

        if ($annData) {
            // Borrar físico
            if ($annData['template_path'] && file_exists(__DIR__.'/'.$annData['template_path'])) unlink(__DIR__.'/'.$annData['template_path']);
            if ($annData['signed_path'] && file_exists(__DIR__.'/'.$annData['signed_path'])) unlink(__DIR__.'/'.$annData['signed_path']);

            // Borrar BD
            $pdo->prepare("DELETE FROM contract_annexes WHERE id = ?")->execute([$annexId]);
            
            // Verificar si quedan pendientes para actualizar bandera
            $pend = $pdo->query("SELECT COUNT(*) FROM contract_annexes WHERE contract_id = '$id' AND status = 'PENDING'")->fetchColumn();
            if ($pend == 0) {
                 $pdo->prepare("UPDATE contracts SET waiting_for_annexes = 0 WHERE id = ?")->execute([$id]);
            }

            logAudit($pdo, $id, "Anexo Eliminado", "Se eliminó el anexo: " . $annData['doc_label']);
            
            header("Location: contract_detail?id=$id&msg=annex_deleted"); exit;
        }
    }

    // ------------------------------------------------------------------
    // LÓGICA ANEXOS: LEGAL SOLICITA (Sustitución + Envío Adjunto)
    // ------------------------------------------------------------------
    if ($action === 'request_annexes' && hasRole(['LEGAL', 'SUPER_ADMIN'])) {
        try {
            $pdo->beginTransaction();
            $types = ['CODE_CONDUCT'=>'Código de Conducta', 'ANTI_CORRUPTION'=>'Política Anticorrupción', 'NDA'=>'NDA', 'OTHER'=>'Otro'];
            $countAdded = 0;
            $countReplaced = 0;
            $emailAttachments = []; // Array para enviar al correo

            foreach ($types as $key => $label) {
                if (isset($_FILES['file_'.$key]) && $_FILES['file_'.$key]['error'] === UPLOAD_ERR_OK) {
                    $f = $_FILES['file_'.$key];
                    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                    $encName = $id . '_ANNEX_TMPL_' . $key . '.' . $ext;
                    $destPath = __DIR__ . '/uploads/' . $encName;

                    // Leemos el contenido RAW antes de encriptar para adjuntarlo al correo
                    $rawContent = file_get_contents($f['tmp_name']);
                    
                    // Encriptar y guardar en disco
                    if (encryptAndSave($f['tmp_name'], $destPath)) {
                        
                        // Guardar en array para el correo
                        $emailAttachments[] = [
                            'name' => $f['name'],
                            'content' => $rawContent
                        ];

                        // VERIFICAR SI YA EXISTE (Sustitución)
                        $stmtCheck = $pdo->prepare("SELECT id, template_path FROM contract_annexes WHERE contract_id = ? AND doc_type = ?");
                        $stmtCheck->execute([$id, $key]);
                        $existing = $stmtCheck->fetch();

                        if ($existing) {
                            // UPDATE: Reemplazar existente
                            // Borrar archivo viejo si tiene nombre diferente (opcional)
                            if ($existing['template_path'] != 'uploads/'.$encName && file_exists(__DIR__.'/'.$existing['template_path'])) {
                                unlink(__DIR__.'/'.$existing['template_path']);
                            }

                            $stmtUpd = $pdo->prepare("UPDATE contract_annexes SET doc_label=?, template_path=?, template_name=?, status='PENDING', signed_path=NULL, signed_name=NULL WHERE id=?");
                            $stmtUpd->execute([$label, 'uploads/'.$encName, $f['name'], $existing['id']]);
                            $countReplaced++;
                        } else {
                            // INSERT: Nuevo registro
                            $stmtIns = $pdo->prepare("INSERT INTO contract_annexes (contract_id, doc_type, doc_label, template_path, template_name, status) VALUES (?, ?, ?, ?, ?, 'PENDING')");
                            $stmtIns->execute([$id, $key, $label, 'uploads/'.$encName, $f['name']]);
                            $countAdded++;
                        }
                    }
                }
            }

            if ($countAdded > 0 || $countReplaced > 0) {
                // Marcar bandera
                $pdo->prepare("UPDATE contracts SET waiting_for_annexes = 1 WHERE id = ?")->execute([$id]);
                
                // Auditoría
                $auditMsg = "Legal gestionó anexos: $countAdded nuevos, $countReplaced actualizados.";
                logAudit($pdo, $id, "Gestión Anexos", $auditMsg);
                
                $pdo->commit();
                
                // NOTIFICACIÓN CON ADJUNTOS (FORMATO ESTÁNDAR)
                $stmtUser = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                $stmtUser->execute([$contract['initiator_id']]);
                $userInit = $stmtUser->fetch();

                if ($userInit) {
                    // 1. Definir el contenido específico
                    $contentHtml = "
                        <p>El departamento Legal ha cargado o actualizado documentos que requieren ser gestionados con el proveedor.</p>
                        
                        <div style='background-color: #eff6ff; padding: 15px; border-left: 4px solid #3b82f6; margin: 15px 0; border-radius: 4px;'>
                            <p style='margin: 5px 0; font-weight: bold; color: #1e40af;'>Instrucciones:</p>
                            <ul style='margin: 5px 0 0 20px; color: #1e3a8a; padding: 0;'>
                                <li>Se adjuntan los documentos a este correo.</li>
                                <li>Solicita la firma al proveedor.</li>
                                <li>Sube los documentos firmados en el detalle del contrato.</li>
                            </ul>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/contract_detail.php?id=$id' style='background-color: #2563eb; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>
                                Ir al Contrato
                            </a>
                        </div>
                    ";

                    // 2. Envolver en la Plantilla Corporativa (La misma de functions.php)
                    $fullHtml = "
                    <html>
                    <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; background-color: #f3f4f6; padding: 20px;'>
                        <div style='max-width: 600px; margin: 0 auto; background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                            
                            <div style='background-color: #7e22ce; padding: 15px 20px;'> <!-- Morado para distinguir Anexos/Compliance -->
                                <h2 style='color: white; margin: 0; font-size: 18px;'>GIC: Acción Requerida</h2>
                            </div>

                            <div style='padding: 20px;'>
                                <p style='margin-top: 0;'>Hola <strong>{$userInit['name']}</strong>,</p>
                                
                                $contentHtml

                                <p style='font-size: 0.9em; color: #64748b; margin-top: 20px; text-align: center; border-top: 1px solid #eee; padding-top: 20px;'>
                                    Acción realizada por: <strong>{$_SESSION['name']}</strong>
                                </p>
                            </div>
                            
                            <div style='background-color: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #94a3b8;'>
                                GIC &copy; " . date('Y') . "
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Enviar con adjuntos usando el diseño bonito
                    sendSmtpMail($userInit['email'], "Acción Requerida: Anexos Contrato $id", $fullHtml, true, $userInit['name'], $emailAttachments);
                }
                
                header("Location: contract_detail?id=$id&msg=annex_requested"); exit;
            } else {
                 header("Location: contract_detail?id=$id"); exit;
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            die("Error: " . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // LÓGICA ANEXOS: SUBIR (O REEMPLAZAR) FIRMADO
    // ------------------------------------------------------------------
    if ($action === 'upload_signed_annex') {
        // 1. Candado de seguridad: No permitir cambios si está archivado
        if (in_array($contract['status'], ['ARCHIVED', 'CANCELLED'])) {
            die("<script>alert('No se pueden modificar anexos en contratos cerrados.'); window.history.back();</script>");
        }

        try {
            if (isset($_FILES['signed_file']) && $_FILES['signed_file']['error']===0) {
                $annexId = $_POST['annex_id'];
                
                // 2. LIMPIEZA: Buscar si ya existe un archivo anterior para borrarlo
                $stmtCheck = $pdo->prepare("SELECT signed_path FROM contract_annexes WHERE id = ?");
                $stmtCheck->execute([$annexId]);
                $oldFile = $stmtCheck->fetch();

                // Si existe y es un archivo físico, borrarlo para liberar espacio
                if ($oldFile && $oldFile['signed_path'] && file_exists(__DIR__ . '/' . $oldFile['signed_path'])) {
                    unlink(__DIR__ . '/' . $oldFile['signed_path']);
                }

                // 3. PROCESO NORMAL DE SUBIDA
                $path = __DIR__.'/uploads/'.$id.'_ANN_SIGNED_'.$annexId.'.'.pathinfo($_FILES['signed_file']['name'], PATHINFO_EXTENSION);
                
                if(encryptAndSave($_FILES['signed_file']['tmp_name'], $path)) {
                    $pdo->prepare("UPDATE contract_annexes SET signed_path=?, signed_name=?, status='SIGNED' WHERE id=?")->execute(['uploads/'.basename($path), $_FILES['signed_file']['name'], $annexId]);
                    
                    // Actualizar bandera de contrato
                    if($pdo->query("SELECT COUNT(*) FROM contract_annexes WHERE contract_id='$id' AND status='PENDING'")->fetchColumn() == 0) {
                        $pdo->prepare("UPDATE contracts SET waiting_for_annexes = 0 WHERE id = ?")->execute([$id]);
                    }
                    
                    sendContractNotification($pdo, $id, "Anexo Firmado Cargado/Actualizado", "Se subió: ".$_FILES['signed_file']['name'], $_SESSION['name']);
                    header("Location: contract_detail?id=$id&msg=annex_uploaded"); exit;
                }
            }
        } catch (Exception $e) { die($e->getMessage()); }
    }

    // ------------------------------------------------------------------
    // ACCIONES GENERALES DEL CONTRATO
    // ------------------------------------------------------------------
    
    // X. REVERTIR ESTADO
    if ($action === 'revert_review') {
        if ($contract['status'] == 'IN_REVIEW' && in_array($role, ['LEGAL', 'SUPER_ADMIN'])) {
            $stmt = $pdo->prepare("UPDATE contracts SET status = 'UNREVIEWED' WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: contract_detail?id=$id"); exit;
        }
    }

    // A. APROBAR FIRMA
    if ($action === 'approve_signature') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            try {
                $pdo->beginTransaction();
                $vStmt = $pdo->prepare("SELECT MAX(version_number) FROM contract_versions WHERE contract_id = ?");
                $vStmt->execute([$id]);
                $nextVer = $vStmt->fetchColumn() + 1;

                $uploadDir = __DIR__ . '/uploads/';
                $fileExt = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                $fileName = $id . '_v' . $nextVer . '_SIGNED.' . $fileExt;
                $fileHash = encryptAndSave($_FILES['file']['tmp_name'], $uploadDir . $fileName);

                if ($fileHash !== false) {
                    $stmtV = $pdo->prepare("INSERT INTO contract_versions (contract_id, version_number, file_name, file_path, file_hash, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtV->execute([$id, $nextVer, $_FILES['file']['name'], 'uploads/'.$fileName, $fileHash, $userId]);

                    $stmtUpd = $pdo->prepare("UPDATE contracts SET status = 'SIGNATURE', observation_turn = NULL WHERE id = ?");
                    $stmtUpd->execute([$id]);

                    $finalComment = "Documento final firmado subido. " . $comment;
                    $stmtObs = $pdo->prepare("INSERT INTO observations (contract_id, user_id, comment_text, type) VALUES (?, ?, ?, 'LEGAL')");
                    $stmtObs->execute([$id, $userId, $finalComment]);

                    logAudit($pdo, $id, "Firma Autorizada", "Versión firmada (v$nextVer) subida.");
                    $pdo->commit();
                    
                    sendContractNotification($pdo, $id, "Contrato Firmado y Autorizado", $finalComment);
                    header("Location: contract_detail?id=$id&msg=signed"); exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                die("Error: " . $e->getMessage());
            }
        }
    }

    // B. CAMBIAR ESTADO
    if ($action === 'change_status') {
        // --- VALIDACIÓN DE SEGURIDAD (BACKEND) ---
        if ($_POST['new_status'] === 'ARCHIVED' && ($contract['waiting_for_annexes'] == 1 || $pendingAnnexesCount > 0)) {
             die("Error: No se puede archivar con anexos pendientes.");
        }
        // ------------------------------------------------
        try {
            $pdo->beginTransaction();
            $new_status = $_POST['new_status'];
            
            $newTurn = NULL; 
            if ($new_status == 'OBSERVATIONS') $newTurn = 'USER';
            elseif ($new_status == 'LEGAL_REVIEW_FINAL') $newTurn = 'LEGAL';

            $stmt = $pdo->prepare("UPDATE contracts SET status = ?, observation_turn = ? WHERE id = ?");
            $stmt->execute([$new_status, $newTurn, $id]);

            if (!empty($comment)) {
                $type = ($role == 'LEGAL') ? 'LEGAL' : 'GENERAL';
                $stmtObs = $pdo->prepare("INSERT INTO observations (contract_id, user_id, comment_text, type) VALUES (?, ?, ?, ?)");
                $stmtObs->execute([$id, $userId, $comment, $type]);
            }

            logAudit($pdo, $id, "Cambio de Estado", "Nuevo estado: $new_status");
            $pdo->commit();

            sendContractNotification($pdo, $id, "Cambio de Estado: $new_status", $comment, $_SESSION['name']);
            header("Location: contract_detail?id=$id&msg=status_changed"); exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            die("Error al cambiar estado: " . $e->getMessage());
        }
    }

    // C. ANULAR
    if ($action === 'delete_contract') {
        if (hasRole('SUPER_ADMIN') || ($contract['initiator_id'] == $userId && !in_array($contract['status'], ['SIGNATURE', 'ARCHIVED', 'CANCELLED']))) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE contracts SET status = 'CANCELLED', observation_turn = NULL WHERE id = ?");
                $stmt->execute([$id]);
                logAudit($pdo, $id, "Anulación", "Contrato anulado.");
                $pdo->commit();
                sendContractNotification($pdo, $id, "Contrato Anulado", "El contrato ha sido cancelado.", $_SESSION['name']);
                header("Location: contracts.php?msg=cancelled"); exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                die("Error al anular: " . $e->getMessage());
            }
        }
    }

    // D. SUBIR NUEVA VERSIÓN
    if ($action === 'upload_version') {
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            try {
                $pdo->beginTransaction();
                $vStmt = $pdo->prepare("SELECT MAX(version_number) FROM contract_versions WHERE contract_id = ?");
                $vStmt->execute([$id]);
                $nextVer = $vStmt->fetchColumn() + 1;

                $fileExt = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                $fileName = $id . '_v' . $nextVer . '.' . $fileExt;
                $fileHash = encryptAndSave($_FILES['file']['tmp_name'], 'uploads/'.$fileName);

                if ($fileHash !== false) {
                    $stmt = $pdo->prepare("INSERT INTO contract_versions (contract_id, version_number, file_name, file_path, file_hash, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id, $nextVer, $_FILES['file']['name'], 'uploads/'.$fileName, $fileHash, $userId]);
                    
                    if ($contract['status'] == 'OBSERVATIONS') {
                        $stmtTurn = $pdo->prepare("UPDATE contracts SET observation_turn = 'LEGAL' WHERE id = ?");
                        $stmtTurn->execute([$id]);
                    }

                    if (!empty($comment)) {
                        $stmtObs = $pdo->prepare("INSERT INTO observations (contract_id, user_id, comment_text, type) VALUES (?, ?, ?, 'GENERAL')");
                        $stmtObs->execute([$id, $userId, $comment]);
                    }
                    
                    logAudit($pdo, $id, "Nueva Versión", "v$nextVer subida");
                    $pdo->commit();
                    
                    sendContractNotification($pdo, $id, "Nueva Versión Subida", $comment, $_SESSION['name']);
                    header("Location: contract_detail?id=$id&msg=uploaded"); exit;
                } else {
                    throw new Exception("Error al encriptar archivo");
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                die("Error al subir versión: " . $e->getMessage());
            }
        }
    }

    // E. EDITAR METADATOS
    if ($action === 'edit_metadata') {
        $changes = [];
        if ($contract['title'] != $_POST['title']) $changes[] = "Título";
        if ($contract['supplier'] != $_POST['supplier']) $changes[] = "Proveedor";
        if ($contract['category'] != $_POST['category']) $changes[] = "Categoría";
        if ($contract['summary'] != $_POST['summary']) $changes[] = "Resumen";
        if ($contract['priority'] != $_POST['priority']) $changes[] = "Prioridad a " . $_POST['priority'];

        if (!empty($changes)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE contracts SET title=?, supplier=?, category=?, summary=?, priority=? WHERE id=?");
                $stmt->execute([$_POST['title'], $_POST['supplier'], $_POST['category'], $_POST['summary'], $_POST['priority'], $id]);
                logAudit($pdo, $id, "Edición de Datos", implode(", ", $changes));
                $pdo->commit();
                sendContractNotification($pdo, $id, "Actualización de Contrato", implode(", ", $changes), $_SESSION['name']);
                header("Location: contract_detail?id=$id&msg=updated"); exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                die("Error al editar: " . $e->getMessage());
            }
        }
    }

    // F. COMENTARIO
    if ($action === 'add_observation') {
        // Bloqueo de seguridad para contratos cerrados
        if (in_array($contract['status'], ['ARCHIVED', 'CANCELLED'])) {
            header("Location: contract_detail?id=$id"); exit;
        }

        if (!empty($comment)) {
            $type = ($role == 'LEGAL') ? 'LEGAL' : 'GENERAL';
            
            // 1. Insertar el comentario
            $stmt = $pdo->prepare("INSERT INTO observations (contract_id, user_id, comment_text, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $userId, $comment, $type]);
            
            // =========================================================================
            // NUEVO: AUTOMATIZACIÓN (Sin Revisar/En Revisión -> Observaciones)
            // Solo si comenta LEGAL o ADMIN
            // =========================================================================
            if (hasRole(['LEGAL', 'SUPER_ADMIN']) && in_array($contract['status'], ['UNREVIEWED', 'IN_REVIEW'])) {
                
                // Actualizamos estado a OBSERVACIONES y damos el turno al USUARIO
                $stmtAuto = $pdo->prepare("UPDATE contracts SET status = 'OBSERVATIONS', observation_turn = 'USER' WHERE id = ?");
                $stmtAuto->execute([$id]);
                
                // Actualizamos la variable local $contract para que la notificación refleje el cambio
                $contract['status'] = 'OBSERVATIONS';
                $contract['observation_turn'] = 'USER';

                logAudit($pdo, $id, "Cambio Automático", "Estado pasó a OBSERVACIONES por comentario de Legal.");
            }
            // =========================================================================

            // 2. Lógica de Turnos para cuando YA está en Observaciones
            if ($contract['status'] == 'OBSERVATIONS') {
                $newTurn = $contract['observation_turn'];
                
                // Si comenta Legal -> Turno Usuario
                if (hasRole(['LEGAL', 'SUPER_ADMIN'])) $newTurn = 'USER';
                // Si comenta el Dueño -> Turno Legal
                elseif ($userId == $contract['initiator_id']) $newTurn = 'LEGAL';
                
                // Aplicar cambio de turno si es necesario
                if ($newTurn != $contract['observation_turn']) {
                    $stmtTurn = $pdo->prepare("UPDATE contracts SET observation_turn = ? WHERE id = ?");
                    $stmtTurn->execute([$newTurn, $id]);
                }
            }
            
            sendContractNotification($pdo, $id, "Comentario Agregado", $comment, $_SESSION['name']);
        }
        header("Location: contract_detail?id=$id"); exit;
    }
}

// Carga Datos
$allVersions = $pdo->query("SELECT * FROM contract_versions WHERE contract_id = '$id' ORDER BY version_number DESC")->fetchAll();
$allObs = $pdo->query("SELECT o.*, u.name, u.role FROM observations o JOIN users u ON o.user_id = u.id WHERE contract_id = '$id' ORDER BY created_at ASC")->fetchAll();
$allLogs = $pdo->query("SELECT l.*, u.name FROM audit_logs l JOIN users u ON l.performed_by = u.id WHERE contract_id = '$id' ORDER BY timestamp DESC")->fetchAll();

$categories = ['Servicios', 'Alquiler de oficinas', 'Otro'];
$states = ['UNREVIEWED', 'OBSERVATIONS', 'LEGAL_REVIEW_FINAL', 'SIGNATURE', 'ARCHIVED', 'CANCELLED'];
$stateLabels = ['Sin Revisar', 'Observaciones', 'Rev. Legal', 'Firma', 'Archivado', 'Anulado'];
$currentStateIdx = array_search(($contract['status'] == 'IN_REVIEW' ? 'UNREVIEWED' : $contract['status']), $states);

$pageTitle = "Detalle: " . $id;
require 'includes/header.php';
?>


<div class="animate-entry pb-10">

<!-- BOTÓN ATRÁS (SOLO MÓVIL) -->
    <a href="contracts" class="lg:hidden inline-flex items-center gap-2 text-slate-500 hover:text-blue-600 mb-4 transition-colors font-medium text-sm group">
        <div class="p-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg group-hover:border-blue-300 shadow-sm">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </div>
        <span>Volver a la lista</span>
    </a>
    
<?php if($showReviewToast): ?>
    <div id="reviewToast" class="fixed top-24 left-1/2 transform -translate-x-1/2 bg-slate-900/95 text-white px-6 py-4 rounded-xl shadow-2xl flex items-center gap-4 z-50 animate-fade-in-up border border-yellow-500 shadow-yellow-500/20 backdrop-blur-md">
        <div class="flex items-center gap-3 text-sm">
            <span class="p-1.5 bg-yellow-500 rounded-full animate-pulse"><i data-lucide="eye" class="w-4 h-4 text-white"></i></span>
            <span>Documento marcado como <strong>En Revisión</strong>.</span>
        </div>
        
        <button type="button" onclick="revertReviewState()" class="text-xs bg-slate-700 hover:bg-slate-600 text-white px-3 py-1.5 rounded transition-colors border border-slate-500 font-medium ml-2">
            Deshacer
        </button>
        
        <button onclick="document.getElementById('reviewToast').remove()" class="text-slate-400 hover:text-white ml-2">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
    
    <script>
        setTimeout(() => {
            const toast = document.getElementById('reviewToast');
            if(toast) {
                toast.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                toast.style.opacity = "0";
                toast.style.transform = "translate(-50%, -20px)";
                setTimeout(() => toast.remove(), 500);
            }
        }, 30000);
    </script>
    <?php endif; ?>

    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-6 mb-8 border-b border-gray-200 dark:border-slate-700 pb-6 animate-entry">
        <div class="w-full lg:w-auto">
            <div class="flex flex-wrap items-center gap-3 mb-2">
                <span class="font-mono text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-2 py-1 rounded"><?= $id ?></span>
                
                <?php if($contract['priority'] == 'HIGH'): ?>
                    <span class="text-xs font-bold text-red-500 flex items-center gap-1"><i data-lucide="flame" class="w-3 h-3"></i> Alta Prioridad</span>
                <?php endif; ?>

                <?php //if($contract['status'] == 'OBSERVATIONS'): ?>
                    <?php //if($contract['observation_turn'] == 'USER'): ?>
                        <!-- <span class="bg-orange-100 text-orange-700 border border-orange-200 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-2 shadow-sm animate-pulse"><i data-lucide="user" class="w-4 h-4"></i> Esperando Respuesta del Solicitante</span> -->
                    <?php //elseif($contract['observation_turn'] == 'LEGAL'): ?>
                        <!-- <span class="bg-blue-100 text-blue-700 border border-blue-200 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-2 shadow-sm animate-pulse"><i data-lucide="scale" class="w-4 h-4"></i> Pendiente Revisión Legal</span> -->
                    <?php //endif; ?>
                <?php //endif; ?>

                <!-- BOTÓN EDITAR MEJORADO (Estilo Cápsula) -->
                <?php if(!in_array($contract['status'], ['ARCHIVED', 'CANCELLED']) && (hasRole(['SUPER_ADMIN', 'LEGAL']) || $contract['initiator_id'] == $userId)): ?>
                    <button onclick="document.getElementById('modalEdit').classList.remove('hidden')" 
                            class="group flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition-all duration-200
                                   bg-white border border-slate-200 text-slate-500 shadow-sm
                                   hover:border-blue-400 hover:text-blue-600 hover:shadow-md hover:-translate-y-0.5
                                   dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400 dark:hover:text-blue-400 dark:hover:border-blue-500/50">
                        <i data-lucide="pen-line" class="w-3 h-3 transition-transform group-hover:rotate-12"></i>
                        <span>Editar</span>
                    </button>
                <?php endif; ?>
            </div>
            
            <h1 class="text-3xl font-bold text-slate-800 dark:text-white leading-tight mb-2">
                <?= htmlspecialchars($contract['title']) ?>
                <?php if($contract['status'] == 'CANCELLED'): ?><span class="text-red-500 text-lg">(ANULADO)</span><?php endif; ?>
            </h1>
            
            <div class="flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                <span class="flex items-center gap-1"><i data-lucide="building-2" class="w-4 h-4"></i> <?= htmlspecialchars($contract['supplier']) ?></span>
                <span class="flex items-center gap-1"><i data-lucide="user" class="w-4 h-4"></i> <?= htmlspecialchars($contract['initiator_name']) ?></span>
                <span class="flex items-center gap-1"><i data-lucide="tag" class="w-4 h-4"></i> <?= htmlspecialchars($contract['category']) ?></span>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 w-full lg:w-auto justify-start lg:justify-end">
            <!-- BOTÓN ANULAR (Rojo) -->
            <?php if(!in_array($contract['status'], ['CANCELLED', 'ARCHIVED']) && (hasRole('SUPER_ADMIN') || $contract['initiator_id'] == $userId)): ?>
                <button onclick="document.getElementById('modalDelete').classList.remove('hidden')" 
                        class="bg-white dark:bg-slate-800 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 px-3 py-2.5 rounded-xl font-medium flex items-center gap-2 transition-colors">
                    <i data-lucide="ban" class="w-4 h-4"></i> Anular
                </button>
            <?php endif; ?>

            <!-- BOTÓN GESTIONAR ANEXOS (Morado) -->
            <?php if(hasRole(['LEGAL', 'SUPER_ADMIN']) || count($annexes) > 0): ?>
                <button onclick="document.getElementById('modalAnnexes').classList.remove('hidden')" 
                        class="bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-900/50 text-purple-700 dark:text-purple-300 hover:bg-purple-50 dark:hover:bg-purple-900/20 px-4 py-2.5 rounded-xl font-medium shadow-sm flex items-center gap-2 transition-colors">
                    <!-- ICONO CAMBIADO A 'files' -->
                    <i data-lucide="files" class="w-4 h-4"></i> 
                    <?= (count($annexes) > 0) ? (hasRole(['LEGAL', 'SUPER_ADMIN']) ? "Ver Anexos" : "Cargar Anexos") . " (" . count($annexes) . ")" : "Solicitar Anexos" ?>
                    <?php if($pendingAnnexesCount > 0): ?><span class="bg-red-500 w-2 h-2 rounded-full animate-pulse"></span><?php endif; ?>
                </button>
            <?php endif; ?>

            <!-- BOTÓN SUBIR CORRECCIÓN (Pulse solo si es mi turno) -->
            <?php if($contract['status'] == 'OBSERVATIONS' && $contract['initiator_id'] == $userId): ?>
                <button onclick="document.getElementById('modalUpload').classList.remove('hidden')" 
                        class="bg-orange-600 hover:bg-orange-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg flex items-center gap-2 transition-transform hover:scale-105 <?= ($contract['observation_turn'] == 'USER') ? 'animate-pulse' : '' ?>">
                    <i data-lucide="upload" class="w-4 h-4"></i> Subir Corrección
                </button>
            <?php endif; ?>

            <!-- BOTÓN AUTORIZAR FIRMA (Solo en Revisión Final) -->
            <?php if($contract['status'] == 'LEGAL_REVIEW_FINAL' && hasRole(['LEGAL', 'SUPER_ADMIN'])): ?>
                <button onclick="document.getElementById('modalSign').classList.remove('hidden')" 
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg flex items-center gap-2 transition-transform hover:scale-105">
                    <i data-lucide="pen-tool" class="w-4 h-4"></i> Autorizar Firma
                </button>
            <?php endif; ?>

            <!-- BOTÓN ARCHIVAR  -->
            <?php if($contract['status'] == 'SIGNATURE' && hasRole(['LEGAL', 'SUPER_ADMIN'])): ?>
                <button type="button" 
                        onclick="handleArchiveAttempt()" 
                        class="bg-slate-700 hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg flex items-center gap-2 transition-colors">
                    <i data-lucide="archive" class="w-4 h-4"></i> Archivar
                    <?php if($contract['waiting_for_annexes']): ?>
                        <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <!-- BOTÓN SOLICITAR CAMBIOS (Ahora Azul para coincidir con el ícono de Legal) -->
            <?php if(in_array($contract['status'], ['UNREVIEWED', 'IN_REVIEW', 'OBSERVATIONS']) && hasRole(['LEGAL', 'SUPER_ADMIN'])): ?>
                
                <!-- Nota: Cambiamos 'bg-orange-600' por 'bg-blue-600' en promptAction -->
                <button onclick="promptAction('OBSERVATIONS', 'Solicitar Cambios', true, 'bg-blue-600')" 
                        class="bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50 px-4 py-2.5 rounded-xl font-medium shadow-sm flex items-center gap-2 hover:bg-blue-50 dark:hover:bg-blue-900/20">
                    <i data-lucide="message-square-warning" class="w-4 h-4"></i> Solicitar
                </button>
                
                <!-- El botón Aprobar se queda igual -->
                <button onclick="promptAction('LEGAL_REVIEW_FINAL', 'Aprobar Revisión Legal', false, 'bg-green-600')" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg flex items-center gap-2 transition-transform hover:scale-105">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Aprobar
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- STEPPER RESPONSIVO MEJORADO -->
    <div class="mb-10 px-2 lg:px-10 py-4 animate-entry" style="animation-delay: 100ms;">
        <div class="relative w-full">
            
            <?php 
                $visualStates = array_diff($states, ['CANCELLED']); 
                $visualStates = array_values($visualStates);
                $totalVisualSteps = count($visualStates); 
                
                $mappedStatus = $contract['status'] == 'IN_REVIEW' ? 'UNREVIEWED' : $contract['status'];
                $currentVisualIdx = array_search($mappedStatus, $visualStates);
                
                if ($contract['status'] == 'CANCELLED') $barWidth = 0;
                else $barWidth = ($currentVisualIdx / ($totalVisualSteps - 1)) * 100;
            ?>
            
            <!-- BARRA DE PROGRESO (SOLO ESCRITORIO) -->
            <!-- Línea Base Gris -->
            <div class="hidden lg:block absolute left-0 top-5 w-full h-1 bg-gray-200 dark:bg-slate-700 rounded-full -z-20"></div>
            
            <!-- Línea de Progreso Verde (Animada) -->
            <?php if($contract['status'] != 'CANCELLED'): ?>
                <div id="stepperProgressBar" class="hidden lg:block absolute left-0 top-5 h-1 bg-green-500 rounded-full -z-10 transition-all duration-[1000ms] ease-out shadow-sm" style="width: 0%" data-width="<?= $barWidth ?>%"></div>
            <?php endif; ?>

            <!-- LÍNEA VERTICAL (SOLO MÓVIL) -->
            <!-- Conecta los puntos verticalmente en pantallas pequeñas -->
            <div class="block lg:hidden absolute left-[1.2rem] top-2 bottom-2 w-0.5 bg-gray-200 dark:bg-slate-700 -z-20"></div>

            <!-- CONTENEDOR DE PASOS -->
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 lg:gap-0 h-full">
                <?php foreach($visualStates as $idx => $state): 
                    $isReached = $idx <= $currentVisualIdx && $contract['status'] != 'CANCELLED';
                    $isCurrent = $idx === $currentVisualIdx;
                    $isInReviewMode = ($state == 'UNREVIEWED' && $contract['status'] == 'IN_REVIEW');
                    $isReviewedAndPassed = ($idx == 0 && $currentVisualIdx > 0);

                    // Clases Base
                    $circleClass = "w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 z-10 bg-white dark:bg-slate-800 border-gray-300 dark:border-slate-600 text-gray-400 dark:text-slate-500";
                    $textWrapperClass = "lg:absolute lg:top-12 lg:left-1/2 lg:transform lg:-translate-x-1/2 lg:text-center ml-3 lg:ml-0"; 
                    $textClass = "text-sm lg:text-xs font-medium text-gray-400 dark:text-slate-500 transition-colors duration-300";
                    
                    $icon = '<span class="text-sm font-bold">'.($idx+1).'</span>';
                    $labelText = $stateLabels[$idx];

                    // LÓGICA DEL ÍCONO FLOTANTE (TURNO)
                    $floatingBadge = '';
                    if ($state == 'OBSERVATIONS' && $contract['status'] == 'OBSERVATIONS') {
                        if ($contract['observation_turn'] == 'USER') {
                            $floatingBadge = '
                            <div class="absolute -top-2 -right-1 lg:-right-2 z-30 cursor-help" 
                                 data-tooltip="Esperando respuesta del Solicitante" 
                                 data-color="bg-orange-600">
                                <div class="w-5 h-5 bg-orange-500 rounded-full flex items-center justify-center text-white shadow-sm animate-pulse border border-white dark:border-slate-800">
                                    <i data-lucide="user" class="w-3 h-3"></i>
                                </div>
                            </div>';
                        } elseif ($contract['observation_turn'] == 'LEGAL') {
                            $floatingBadge = '
                            <div class="absolute -top-2 -right-1 lg:-right-2 z-30 cursor-help" 
                                 data-tooltip="Pendiente de revisión Legal" 
                                 data-color="bg-blue-600">
                                <div class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center text-white shadow-sm animate-pulse border border-white dark:border-slate-800">
                                    <i data-lucide="scale" class="w-3 h-3"></i>
                                </div>
                            </div>';
                        }
                    }

                    // Lógica de Estados Visuales
                    if ($isInReviewMode) { 
                        $circleClass = "w-11 h-11 rounded-full flex items-center justify-center border-2 z-10 bg-white dark:bg-slate-800 border-green-500 text-green-500 shadow-lg ring-4 ring-green-50 dark:ring-green-900/40 scale-100 lg:scale-110";
                        $textClass = "text-sm lg:text-xs font-bold text-green-600 dark:text-green-400";
                        $icon = '<div class="flex -space-x-1.5"><i data-lucide="check" class="w-4 h-4"></i><i data-lucide="check" class="w-4 h-4"></i></div>';
                        $labelText = "En Revisión";
                    } 
                    elseif ($isReviewedAndPassed) { 
                        $circleClass = "w-10 h-10 rounded-full flex items-center justify-center border-2 z-10 bg-green-500 border-green-500 text-white shadow-md";
                        $textClass = "text-sm lg:text-xs font-bold text-green-600 dark:text-green-400";
                        $icon = '<div class="flex -space-x-1.5"><i data-lucide="check" class="w-3.5 h-3.5"></i><i data-lucide="check" class="w-3.5 h-3.5"></i></div>';
                        $labelText = "Revisado";
                    }
                    elseif ($isReached) { 
                        $circleClass = "w-10 h-10 rounded-full flex items-center justify-center border-2 z-10 bg-green-500 border-green-500 text-white shadow-md";
                        $textClass = "text-sm lg:text-xs font-bold text-green-600 dark:text-green-400";
                        $icon = '<i data-lucide="check" class="w-5 h-5"></i>'; 
                        
                        if ($isCurrent) { 
                            $circleClass .= " ring-4 ring-green-100 dark:ring-green-900/50 lg:scale-110";
                            $textClass = "text-sm lg:text-xs font-extrabold text-green-700 dark:text-green-300";
                        }
                    }
                ?>
                    <!-- ITEM INDIVIDUAL -->
                    <div class="flex flex-row lg:flex-col items-center relative group w-full lg:w-auto">
                        <!-- Círculo + Badge Flotante -->
                        <div class="relative"> <!-- Contenedor relativo para posicionar el badge -->
                            <div id="stepCircle<?= $idx ?>" class="<?= $circleClass ?> flex-shrink-0">
                                <?= $icon ?>
                            </div>
                            <!-- INYECCIÓN DEL BADGE -->
                            <?= $floatingBadge ?>
                        </div>
                        
                        <!-- Texto -->
                        <div class="<?= $textWrapperClass ?>">
                            <span id="stepText<?= $idx ?>" class="<?= $textClass ?> uppercase tracking-wide whitespace-nowrap block">
                                <?= $labelText ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            setTimeout(() => { const b = document.getElementById('stepperProgressBar'); if(b) b.style.width = b.dataset.width; }, 300);
            if(typeof lucide !== 'undefined') lucide.createIcons();
        });

    // AJAX REVERT REVIEW STATE
    async function revertReviewState() {
        const toast = document.getElementById('reviewToast');
        if(toast) {
            toast.style.opacity = "0.5";
            toast.innerText = "Revirtiendo...";
        }

        try {
            const response = await fetch('api/revert_review.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: '<?= $id ?>' })
            });
            const data = await response.json();
            
            if (data.success) {
                if(toast) toast.remove();
                
                const stepCircle = document.getElementById('stepCircle0');
                const stepText = document.getElementById('stepText0');
                const progressBar = document.getElementById('stepperProgressBar');
                
                if(stepCircle && stepText) {
                    // 1. CÍRCULO: Verde, Activo, Check simple
                    stepCircle.className = "w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-500 z-10 bg-green-500 border-green-500 text-white shadow-md ring-4 ring-green-100 dark:ring-green-900/50 scale-100 lg:scale-110";
                    stepCircle.innerHTML = '<i data-lucide="check" class="w-5 h-5"></i>';
                    
                    // 2. TEXTO
                    stepText.innerText = "Sin Revisar";
                    stepText.className = "text-sm lg:text-xs uppercase tracking-wide whitespace-nowrap block font-extrabold text-green-700 dark:text-green-300 transition-colors duration-300";
                    
                    // 3. BARRA: Reiniciar a 0
                    if(progressBar) progressBar.style.width = '0%';

                    // 4. ICONOS
                    if(typeof lucide !== 'undefined') lucide.createIcons();
                }
            } else {
                console.error("Error backend:", data.error);
                location.reload(); 
            }
        } catch (e) {
            console.error(e);
            location.reload();
        }
    }
    </script>

    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden min-h-[500px] grid grid-cols-1 xl:grid-cols-3 animate-entry" style="animation-delay: 200ms;">
        <div class="xl:col-span-2 flex flex-col border-r border-gray-100 dark:border-slate-700">
            <div class="flex border-b border-gray-100 dark:border-slate-700">
               <button onclick="switchTab('detail')" id="btn-detail" 
                        class="tab-btn flex-1 py-4 text-sm font-bold border-b-2 border-blue-600 text-blue-600 bg-slate-50 dark:bg-slate-900/50 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
                    Detalle & Versiones
                </button>
                <button onclick="switchTab('audit')" id="btn-audit" 
                        class="tab-btn flex-1 py-4 text-sm font-bold border-b-2 border-transparent text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
                    Auditoría
                </button>
            </div>
            
            <div id="tab-detail" class="p-6 overflow-y-auto h-[600px] custom-scrollbar">
                <div class="mb-8">
                    <h3 class="font-bold text-slate-800 dark:text-white mb-2">Resumen</h3>
                    <div class="bg-gray-50 dark:bg-slate-900/50 p-4 rounded-xl text-sm text-slate-600 dark:text-slate-300 border border-gray-100 dark:border-slate-800 leading-relaxed"><?= $contract['summary'] ? nl2br(htmlspecialchars($contract['summary'])) : 'Sin resumen.' ?></div>
                </div>
                
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-slate-800 dark:text-white">Versiones</h3>
                    <?php if(count($allVersions) > 1 && hasRole(['LEGAL', 'SUPER_ADMIN'])): ?>
                        <button onclick="openCompareModal()" class="text-sm bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 px-3 py-1.5 rounded-lg hover:bg-purple-200 transition-colors flex items-center gap-2"><i data-lucide="git-compare" class="w-4 h-4"></i> Comparar</button>
                    <?php endif; ?>
                </div>

                <div class="space-y-3">
                    <?php foreach($allVersions as $idx => $v): ?>
                    <div class="flex justify-between items-center p-4 rounded-xl border border-gray-100 dark:border-slate-700 hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors <?= $idx===0 ? 'bg-blue-50/50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-900' : '' ?>">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-white dark:bg-slate-800 rounded-lg text-blue-500"><i data-lucide="file" class="w-5 h-5"></i></div>
                            <div>
                                <p class="font-bold text-slate-700 dark:text-slate-200 text-sm">Versión <?= $v['version_number'] ?> <?= $idx===0 ? '<span class="text-[10px] bg-green-100 text-green-700 px-2 rounded-full ml-1">ACTUAL</span>' : '' ?></p>
                                <p class="text-xs text-slate-400"><?= $v['file_name'] ?> • <?= date('d/m H:i', strtotime($v['created_at'])) ?></p>
                            </div>
                        </div>
                        <a href="download.php?id=<?= $v['id'] ?>&file=<?= urlencode($v['file_name']) ?>" target="_blank" class="text-sm font-bold text-blue-600 hover:underline flex items-center gap-1"><i data-lucide="download" class="w-4 h-4"></i> Descargar</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-audit" class="hidden p-0 overflow-y-auto h-[600px] custom-scrollbar">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-slate-900/50 sticky top-0"><tr><th class="px-6 py-3">Acción</th><th class="px-6 py-3">Usuario</th><th class="px-6 py-3">Detalle</th><th class="px-6 py-3 text-right">Fecha</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                        <?php foreach($allLogs as $log): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30"><td class="px-6 py-3 font-medium text-slate-700 dark:text-slate-200"><?= htmlspecialchars($log['action']) ?></td><td class="px-6 py-3 text-slate-500 dark:text-slate-400"><?= htmlspecialchars($log['name']) ?></td><td class="px-6 py-3 text-slate-500 dark:text-slate-400 italic"><?= htmlspecialchars($log['details']) ?></td><td class="px-6 py-3 text-right font-mono text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($log['timestamp'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col h-full bg-slate-50/50 dark:bg-slate-900/20">
            <div class="p-4 border-b border-gray-100 dark:border-slate-700 bg-white dark:bg-slate-800"><h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="message-square" class="w-4 h-4 text-blue-500"></i> Comentarios</h3></div>
            <div class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar" id="chatContainer">
                <?php if($contract['has_red_flags']): ?>
                <div class="flex gap-3 animate-fade-in-up">
                    <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center text-purple-600 dark:text-purple-300 shadow-sm flex-shrink-0"><i data-lucide="bot" class="w-4 h-4"></i></div>
                    <div class="max-w-[90%]"><div class="text-[10px] text-purple-600 dark:text-purple-400 font-bold mb-1 ml-1 flex items-center gap-1">IA GIC <span class="font-normal text-slate-400">• <?= date('d/m H:i', strtotime($contract['created_at'])) ?></span></div><div class="p-3.5 rounded-2xl rounded-tl-none text-sm bg-white dark:bg-slate-700 border border-purple-100 dark:border-purple-900 shadow-sm text-slate-700 dark:text-slate-200"><p class="font-bold text-red-500 mb-1 flex items-center gap-1"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Riesgos Detectados (<?= $contract['red_flag_count'] ?>):</p><?= nl2br(htmlspecialchars($contract['red_flag_details'])) ?></div></div>
                </div>
                <?php endif; ?>
                <?php foreach($allObs as $obs): $isMe = $obs['user_id'] == $userId; ?>
                <div class="flex gap-3 <?= $isMe ? 'flex-row-reverse' : '' ?>"><div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0 <?= $obs['role'] == 'LEGAL' ? 'bg-indigo-600' : ($isMe ? 'bg-blue-600' : 'bg-slate-400') ?>"><?= substr($obs['name'], 0, 1) ?></div><div class="max-w-[85%]"><div class="text-[10px] text-slate-400 mb-1 px-1 <?= $isMe ? 'text-right' : '' ?>"><?= htmlspecialchars($obs['name']) ?> • <?= date('d/m H:i', strtotime($obs['created_at'])) ?></div><div class="p-3 rounded-2xl text-sm shadow-sm <?= $isMe ? 'bg-blue-600 text-white rounded-tr-none' : 'bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-tl-none border border-gray-200 dark:border-slate-600' ?>"><?= nl2br(htmlspecialchars($obs['comment_text'])) ?></div></div></div>
                <?php endforeach; ?>
            </div>
            
            <?php if(!in_array($contract['status'], ['CANCELLED', 'ARCHIVED'])): ?>
            <div class="p-3 bg-white dark:bg-slate-800 border-t border-gray-100 dark:border-slate-700"><form method="POST" class="relative" onsubmit="window.showLoading('Enviando', 'Guardando y notificando...')"><input type="hidden" name="action" value="add_observation"><input type="text" name="comment" required placeholder="Escribir..." class="w-full bg-slate-100 dark:bg-slate-900 border-none rounded-xl py-3 pl-4 pr-12 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white"><button type="submit" class="absolute right-2 top-2 p-1.5 bg-blue-600 text-white rounded-lg"><i data-lucide="send" class="w-4 h-4"></i></button></form></div>
            <?php else: ?>
            <div class="p-4 text-center text-slate-400 text-sm bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-700 flex items-center justify-center gap-2">
                <i data-lucide="lock" class="w-4 h-4"></i>
                <span class="font-medium">Flujo finalizado. No se permiten comentarios.</span>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div id="modalAction" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="document.getElementById('modalAction').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl">
        <form method="POST" onsubmit="window.showLoading('Procesando', '...')">
            <input type="hidden" name="action" value="change_status">
            <input type="hidden" name="new_status" id="actionStatus">
            <div class="text-center mb-6">
                <div id="actionIcon" class="w-16 h-16 rounded-full bg-blue-100 mx-auto flex items-center justify-center mb-4 text-blue-600"><i data-lucide="info" class="w-8 h-8"></i></div>
                <h3 id="actionTitle" class="text-xl font-bold text-slate-800 dark:text-white mb-2">Confirmar</h3>
                <p class="text-sm text-slate-500">¿Deseas proceder? Puedes agregar una nota.</p>
            </div>
            <div class="mb-4 text-left">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Observación <span id="commentRequired" class="text-red-500 hidden">*</span></label>
                <textarea name="comment" id="actionComment" rows="3" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-xl p-3 text-sm outline-none dark:text-white mb-2 resize-none" placeholder="Observación opcional..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="document.getElementById('modalAction').classList.add('hidden')" class="flex-1 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl font-medium transition-colors hover:bg-slate-200 dark:hover:bg-slate-700">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-xl font-medium">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalSign" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="document.getElementById('modalSign').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl">
        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2"><i data-lucide="pen-tool" class="text-indigo-600"></i> Autorizar y Subir Firmado</h3>
        <form method="POST" enctype="multipart/form-data" onsubmit="window.showLoading('Firmando', '...')">
            <input type="hidden" name="action" value="approve_signature">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Documento Firmado</label>
                <div class="border-2 border-dashed border-indigo-200 dark:border-indigo-900 rounded-xl p-6 text-center hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors cursor-pointer" onclick="document.getElementById('fileSigned').click()">
                    <i data-lucide="file-check" class="w-8 h-8 text-indigo-500 mx-auto mb-2"></i>
                    <span id="signedFileName" class="text-sm text-slate-500">Subir contrato firmado</span>
                    <input type="file" name="file" id="fileSigned" required class="hidden" onchange="document.getElementById('signedFileName').innerText = this.files[0].name">
                </div>
            </div>
            <textarea name="comment" rows="2" placeholder="Nota final..." class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-xl p-3 text-sm outline-none dark:text-white mb-4 resize-none"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalSign').classList.add('hidden')" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors shadow-lg">Autorizar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalEdit" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="document.getElementById('modalEdit').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-lg p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl">
        <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-4">Editar Contrato</h3>
        <form method="POST" onsubmit="window.showLoading('Guardando', '...')">
            <input type="hidden" name="action" value="edit_metadata">
            <div class="space-y-4 mb-6">
                <div><label class="text-xs font-bold text-slate-500 uppercase">Título</label><input type="text" name="title" value="<?= htmlspecialchars($contract['title']) ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2 dark:text-white"></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="text-xs font-bold text-slate-500 uppercase">Proveedor</label><input type="text" name="supplier" value="<?= htmlspecialchars($contract['supplier']) ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2 dark:text-white"></div>
                    <div>
                        <label class="text-xs font-bold text-slate-500 uppercase">Categoría</label>
                        <select name="category" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2 dark:text-white">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= $contract['category'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase">Prioridad</label>
                    <select name="priority" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2 dark:text-white">
                        <option value="LOW" <?= $contract['priority'] == 'LOW' ? 'selected' : '' ?>>Baja</option>
                        <option value="MEDIUM" <?= $contract['priority'] == 'MEDIUM' ? 'selected' : '' ?>>Media</option>
                        <option value="HIGH" <?= $contract['priority'] == 'HIGH' ? 'selected' : '' ?>>Alta</option>
                    </select>
                </div>
                <div><label class="text-xs font-bold text-slate-500 uppercase">Resumen</label><textarea name="summary" rows="3" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2 dark:text-white"><?= htmlspecialchars($contract['summary']) ?></textarea></div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" 
                        onclick="document.getElementById('modalEdit').classList.add('hidden')" 
                        class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors">
                    Cancelar
                </button>
                
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<div id="modalDelete" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="document.getElementById('modalDelete').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-sm p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border-2 border-red-100 dark:border-red-900">
        <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-full bg-red-100 text-red-600 mx-auto flex items-center justify-center mb-4"><i data-lucide="ban" class="w-8 h-8"></i></div>
            <h3 class="text-xl font-bold text-slate-800 dark:text-white">¿Anular Contrato?</h3>
            <p class="text-sm text-red-500 mt-2">Esta acción es irreversible.</p>
        </div>
        <form method="POST" class="flex gap-3" onsubmit="window.showLoading('Anulando', 'Notificando cancelación...')">
            <input type="hidden" name="action" value="delete_contract">
            <button type="button" 
                    onclick="document.getElementById('modalDelete').classList.add('hidden')" 
                    class="flex-1 px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition-colors">
                Cancelar
            </button>
            <button type="submit" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700">Sí, Anular</button>
        </form>
    </div>
</div>

<div id="modalUpload" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="document.getElementById('modalUpload').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700">
        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-4">Subir Corrección</h3>
        <form method="POST" enctype="multipart/form-data" onsubmit="window.showLoading('Subiendo', '...')">
            <input type="hidden" name="action" value="upload_version">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Archivo</label>
                <div class="border-2 border-dashed border-gray-300 dark:border-slate-700 rounded-xl p-6 text-center hover:bg-gray-50 dark:hover:bg-slate-800 transition-colors cursor-pointer" onclick="document.getElementById('fileNewVersion').click()">
                    <i data-lucide="upload-cloud" class="w-8 h-8 text-blue-500 mx-auto mb-2"></i>
                    <span id="uploadFileName" class="text-sm text-slate-500">Clic para seleccionar</span>
                    <input type="file" name="file" id="fileNewVersion" required class="hidden" onchange="document.getElementById('uploadFileName').innerText = this.files[0].name">
                </div>
            </div>
            <textarea name="comment" rows="2" placeholder="Comentario sobre esta versión..." class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-xl p-3 text-sm outline-none dark:text-white mb-4 resize-none"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modalUpload').classList.add('hidden')" class="px-4 py-2 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Subir</button>
            </div>
        </form>
    </div>
</div>

<div id="modalCompare" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="document.getElementById('modalCompare').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-3xl p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center mb-4 border-b border-gray-100 dark:border-slate-800 pb-4">
            <h3 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="git-compare" class="text-purple-500"></i> Comparar Versiones</h3>
            <button onclick="document.getElementById('modalCompare').classList.add('hidden')" class="text-gray-400 hover:text-white"><i data-lucide="x"></i></button>
        </div>
                <!-- SELECTORES DE VERSIÓN -->
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Versión Anterior (A)</label><select id="verA" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2 text-sm dark:text-white"><?php foreach($allVersions as $v): ?><option value="<?= $v['id'] ?>">Versión <?= $v['version_number'] ?> (<?= date('d/m', strtotime($v['created_at'])) ?>)</option><?php endforeach; ?></select></div>
            <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Versión Nueva (B)</label><select id="verB" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2 text-sm dark:text-white"><?php foreach($allVersions as $v): ?><option value="<?= $v['id'] ?>">Versión <?= $v['version_number'] ?> (<?= date('d/m', strtotime($v['created_at'])) ?>)</option><?php endforeach; ?></select></div>
        </div>

        <!-- SELECTOR DE NIVEL DE ATENCIÓN (NUEVO DISEÑO) -->
        <div class="mb-6 bg-slate-50 dark:bg-slate-800 p-4 rounded-xl border border-gray-200 dark:border-slate-700">
            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">
                Nivel de Atención en Comparación por IA
            </label>
            
            <div class="relative">
                <select id="aiSensitivitySelect" class="w-full appearance-none bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded-lg p-2.5 pr-8 text-sm text-slate-700 dark:text-slate-200 outline-none focus:ring-2 focus:ring-purple-500 transition-shadow cursor-pointer">
                    <option value="3" selected>🔴 Estricto (Atención al detalle minuciosa)</option>
                    <option value="2">🟡 Normal (Ignora formato)</option>
                    <option value="1">🟢 Resumen (Solo cambios críticos)</option>
                </select>
                <!-- Flechita decorativa -->
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                </div>
            </div>

            <p class="text-[10px] text-slate-400 mt-2 flex items-center gap-1">
                <i data-lucide="info" class="w-3 h-3"></i>
                El nivel "Alto" reportará hasta el cambio de una coma.
            </p>
        </div>

        <button onclick="runComparison()" id="btnRunCompare" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 rounded-xl shadow-lg mb-4 flex justify-center items-center gap-2"><i data-lucide="sparkles"></i> Analizar Diferencias</button>
        
        <div id="compareLoading" class="hidden text-center py-10"><i data-lucide="loader" class="animate-spin text-purple-600 w-8 h-8 mx-auto mb-2"></i><p class="text-sm text-slate-500">Procesando documentos...</p></div>
        
        <!-- RESULTADOS (2 PESTAÑAS VISUALES) -->
        <div id="compareResultContainer" class="hidden flex-1 overflow-hidden flex flex-col">
            <!-- TABS SIMPLES -->
            <div class="flex border-b border-gray-200 dark:border-slate-700 mb-2">
                <button onclick="showCompareTab('ai')" id="tab-ai" class="flex-1 pb-2 text-sm font-bold text-purple-600 border-b-2 border-purple-600">Análisis IA</button>
                <button onclick="showCompareTab('visual')" id="tab-visual" class="flex-1 pb-2 text-sm font-bold text-slate-400 hover:text-slate-600 border-b-2 border-transparent">Visualizador de Texto</button>
            </div>

            <!-- CONTENIDO IA -->
            <div id="compareResultAI" class="flex-1 overflow-y-auto bg-slate-50 dark:bg-slate-950/50 p-4 rounded-xl border border-gray-100 dark:border-slate-800 text-sm text-slate-700 dark:text-slate-300 leading-relaxed custom-scrollbar"></div>
            
            <!-- CONTENIDO VISUAL (DIFF) -->
            <div id="compareResultVisual" class="hidden flex-1 overflow-y-auto bg-white dark:bg-black p-4 rounded-xl border border-gray-200 dark:border-slate-700 text-xs font-mono text-slate-600 dark:text-slate-400 leading-relaxed custom-scrollbar"></div>
        </div>
    </div>
</div>

<!-- Librería de Comparación (CDN JSDelivr - Más estable) -->
<script src="https://cdn.jsdelivr.net/gh/google/diff-match-patch/javascript/diff_match_patch.js"></script>

<script>

    function promptAction(status, title, requireComment, colorClass) {
        document.getElementById('modalAction').classList.remove('hidden');
        document.getElementById('actionStatus').value = status;
        document.getElementById('actionTitle').innerText = title;
        document.getElementById('actionComment').value = '';
        const comment = document.getElementById('actionComment');
        const reqLabel = document.getElementById('commentRequired');
        if(requireComment) { comment.setAttribute('required', 'required'); comment.placeholder = "Motivo obligatorio..."; if(reqLabel) reqLabel.classList.remove('hidden'); }
        else { comment.removeAttribute('required'); comment.placeholder = "Nota opcional..."; if(reqLabel) reqLabel.classList.add('hidden'); }
        let color = colorClass.replace('bg-', 'text-');
        document.getElementById('actionIcon').className = `w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 mx-auto flex items-center justify-center mb-4 ${color}`;
    }

    function switchTab(tabName) {
        ['detail', 'audit'].forEach(t => {
            document.getElementById('tab-' + t).classList.add('hidden');
            const btn = document.getElementById('btn-' + t);
            btn.classList.remove('border-blue-600', 'text-blue-600', 'bg-slate-50', 'dark:bg-slate-900/50');
            btn.classList.add('border-transparent', 'text-slate-500');
        });
        document.getElementById('tab-' + tabName).classList.remove('hidden');
        const active = document.getElementById('btn-' + tabName);
        active.classList.remove('border-transparent', 'text-slate-500');
        active.classList.add('border-blue-600', 'text-blue-600', 'bg-slate-50', 'dark:bg-slate-900/50');
    }

    // COMPARACIÓN
    function openCompareModal() { document.getElementById('modalCompare').classList.remove('hidden'); }
    
// 1. MANEJO DEL SLIDER
    function updateSensitivityLabel(val) {
        const labels = {1: 'Resumen (Flexible)', 2: 'Normal (Equilibrado)', 3: 'Estricto (Paranoico)'};
        document.getElementById('sensitivityLabel').innerText = labels[val];
    }

    // 2. MANEJO DE TABS DEL MODAL
    function showCompareTab(tab) {
        document.getElementById('compareResultAI').classList.add('hidden');
        document.getElementById('compareResultVisual').classList.add('hidden');
        document.getElementById('tab-ai').className = "flex-1 pb-2 text-sm font-bold text-slate-400 border-b-2 border-transparent hover:text-slate-600";
        document.getElementById('tab-visual').className = "flex-1 pb-2 text-sm font-bold text-slate-400 border-b-2 border-transparent hover:text-slate-600";
        
        if (tab === 'ai') {
            document.getElementById('compareResultAI').classList.remove('hidden');
            document.getElementById('tab-ai').className = "flex-1 pb-2 text-sm font-bold text-purple-600 border-b-2 border-purple-600";
        } else {
            document.getElementById('compareResultVisual').classList.remove('hidden');
            document.getElementById('tab-visual').className = "flex-1 pb-2 text-sm font-bold text-blue-600 border-b-2 border-blue-600";
        }
    }

    // 3. ACTUALIZAR runComparison
    async function runComparison() {
        const idA = document.getElementById('verA').value; 
        const idB = document.getElementById('verB').value;
        
        const sensitivity = document.getElementById('aiSensitivitySelect').value; //--> Obtiene el valor del nuevo Select de atención al detalle de la IA al comparar documentos
        
        const container = document.getElementById('compareResultContainer');
        const resultAI = document.getElementById('compareResultAI');
        const resultVisual = document.getElementById('compareResultVisual');
        const loading = document.getElementById('compareLoading');
        
        loading.classList.remove('hidden');
        container.classList.add('hidden');
        
        try {
            const [docA, docB] = await Promise.all([ fetchAndExtract(idA), fetchAndExtract(idB) ]);
            
            let consolidatedText = "";
            let changesCount = 0;
            let visualHTML = "<p class='text-center text-gray-400 italic'>No hay visualización de texto disponible para estos formatos.</p>";

            if (docA.type === 'text' && docB.type === 'text') {
                const dmp = new diff_match_patch();
                dmp.Diff_Timeout = 10.0; 
                const diffs = dmp.diff_main(docA.content, docB.content);
                dmp.diff_cleanupEfficiency(diffs); 
                
                // GENERAR VISUALIZADOR HTML (NATIVO DE LA LIBRERÍA)
                // Esto crea el texto con <ins style="background:#e6ffe6;"> y <del style="background:#ffe6e6;">
                visualHTML = dmp.diff_prettyHtml(diffs);
                
                // GENERAR CONSOLIDADO PARA IA
                diffs.forEach((part) => {
                    const type = part[0]; const text = part[1];
                    if (type === 0) consolidatedText += text;
                    else if (type === -1) { // Borrado
                        if(text.trim().length > 0 || !isNaN(text)) {
                            consolidatedText += ` <<<TEXTO ELIMINADO: "${text}">>> `;
                            changesCount++;
                        }
                    } 
                    else if (type === 1) { // Agregado
                        if(text.trim().length > 0 || !isNaN(text)) {
                            consolidatedText += ` <<<TEXTO NUEVO: "${text}">>> `;
                            changesCount++;
                        }
                    }
                });
            } else {
                consolidatedText = "MODO VISUAL";
            }

            // Inyectar el HTML visual generado por Google Diff
            resultVisual.innerHTML = visualHTML;

            const response = await fetch('api/compare_gemini.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({ 
                    doc_a: docA, doc_b: docB,
                    consolidated_text: consolidatedText,
                    changes_count: changesCount,
                    sensitivity: sensitivity // <--- Enviamos el valor del slider
                }) 
            });
            
            const data = await response.json();
            
            loading.classList.add('hidden');
            container.classList.remove('hidden');
            
            // Mostrar IA por defecto
            showCompareTab('ai'); 
            
            if (data.error) resultAI.innerHTML = `<div class='text-red-500'>${data.error}</div>`;
            else resultAI.innerHTML = data.comparison;
            
        } catch (e) { 
            console.error(e);
            loading.classList.add('hidden');
            alert("Error: " + e.message);
        } 
    }
    
    // EXTRACCIÓN UNIVERSAL (TEXTO PURO + FALLBACK VISUAL)
    async function fetchAndExtract(versionId) {
        // 1. Descarga evitando caché
        const noCacheUrl = `api/get_version_content.php?id=${versionId}&t=${new Date().getTime()}`;
        const res = await fetch(noCacheUrl, { cache: "no-store" });
        
        // --- CAMBIO AQUÍ: LEER EL ERROR REAL DEL SERVIDOR ---
        if (!res.ok) {
            let errorMsg = `Error ${res.status}`;
            try {
                const errData = await res.json();
                if (errData.error) errorMsg = errData.error;
            } catch (e) {
                // Si no es JSON, intentar leer texto
                const text = await res.text();
                if (text) errorMsg = `Error Servidor: ${text.substring(0, 100)}...`;
            }
            throw new Error(errorMsg);
        }
        // ----------------------------------------------------
        
        const data = await res.json();
        if (data.error) throw new Error(data.error);

        const mime = data.mime;
        const base64 = data.content;

        // Convertir Base64 a Binary para procesarlo
        const binaryString = window.atob(base64);
        const len = binaryString.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) bytes[i] = binaryString.charCodeAt(i);

        // --- ESTRATEGIA 1: WORD (.docx) ---
        if (mime.includes('wordprocessingml') || mime.includes('msword')) {
            try {
                const result = await mammoth.extractRawText({ arrayBuffer: bytes.buffer });
                if (result.value.trim()) {
                    return { type: 'text', content: result.value, mime: 'text/plain' };
                }
            } catch (e) { console.warn("Mammoth falló, intentando modo visual", e); }
        }

        // --- ESTRATEGIA 2: PDF (Capa de Texto) ---
        if (mime === 'application/pdf') {
            try {
                const loadingTask = pdfjsLib.getDocument({ data: bytes });
                const pdf = await loadingTask.promise;
                let fullText = "";

                // Recorrer todas las páginas y extraer strings
                for (let i = 1; i <= pdf.numPages; i++) {
                    const page = await pdf.getPage(i);
                    const textContent = await page.getTextContent();
                    const pageText = textContent.items.map(item => item.str).join(' ');
                    fullText += pageText + "\n\n";
                }

                // Si encontramos texto real (no es un escaneo vacío)
                if (fullText.trim().length > 50) {
                    return { type: 'text', content: fullText, mime: 'text/plain' };
                } else {
                    console.log(`PDF ${versionId} parece ser una imagen escaneada (sin texto seleccionable).`);
                }
            } catch (e) {
                console.error("Error leyendo PDF text layer", e);
            }
        }

        // --- ESTRATEGIA 3: TEXTO PLANO (.txt) ---
        if (mime === 'text/plain') {
            const textDecoder = new TextDecoder("utf-8");
            return { type: 'text', content: textDecoder.decode(bytes), mime: 'text/plain' };
        }

        // --- ESTRATEGIA 4: FALLBACK VISUAL (Imágenes / Scans) ---
        return { 
            type: 'base64', // Esto desactivará el Diff Engine JS y activará Gemini Vision
            content: base64, 
            mime: mime 
        };
    }

    const chat = document.getElementById('chatContainer');
    if(chat) chat.scrollTop = chat.scrollHeight;
</script>

<!-- MODAL ANEXOS -->
<div id="modalAnnexes" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="document.getElementById('modalAnnexes').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-3xl p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-4">Documentos Anexos</h3>
        
        <!-- LISTA DE ANEXOS -->
        <div class="space-y-3 mb-6">
            <?php foreach($annexes as $ann): ?>
                <div class="p-4 border rounded-xl flex justify-between items-center bg-slate-50 dark:bg-slate-800 dark:border-slate-700 gap-4">
                    
                    <!-- COLUMNA IZQUIERDA: Info y Descargas -->
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-bold text-sm text-slate-700 dark:text-white"><?= htmlspecialchars($ann['doc_label']) ?></p>
                            
                            <!-- 1. BOTÓN ELIMINAR (Solo Legal + Contrato ACTIVO) -->
                            <?php if(hasRole(['LEGAL', 'SUPER_ADMIN']) && !in_array($contract['status'], ['ARCHIVED', 'CANCELLED'])): ?>
                                <form method="POST" onsubmit="return confirm('¿Eliminar este anexo?');" class="inline">
                                    <input type="hidden" name="action" value="delete_annex">
                                    <input type="hidden" name="annex_id" value="<?= $ann['id'] ?>">
                                    <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors" title="Eliminar">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Links de Descarga (SIEMPRE VISIBLES) -->
                        <div class="flex flex-wrap gap-3 mt-1">
                            <a href="download.php?annex_tmpl=<?= $ann['id'] ?>" target="_blank" class="text-xs text-blue-500 hover:underline flex items-center gap-1">
                                <i data-lucide="download" class="w-3 h-3"></i> Descargar anexo
                            </a>

                            <?php if($ann['status'] == 'SIGNED'): ?>
                                <span class="text-slate-300">|</span>
                                <a href="download.php?annex_signed=<?= $ann['id'] ?>" target="_blank" class="text-xs text-green-600 font-bold hover:underline flex items-center gap-1">
                                    <i data-lucide="file-check" class="w-3 h-3"></i> Ver Firmado
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: Acciones o Estado -->
                    <div>
                        <!-- 2. BOTÓN SUBIR/REEMPLAZAR (Solo si Contrato ACTIVO) -->
                        <?php if(!in_array($contract['status'], ['ARCHIVED', 'CANCELLED'])): ?>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_signed_annex">
                                <input type="hidden" name="annex_id" value="<?= $ann['id'] ?>">
                                
                                <label class="cursor-pointer transition-colors shadow-sm text-xs font-bold px-3 py-2 rounded-lg flex items-center gap-2 whitespace-nowrap <?= $ann['status'] == 'PENDING' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-white border border-gray-300 text-slate-600 hover:bg-gray-50 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200' ?>">
                                    <i data-lucide="<?= $ann['status'] == 'PENDING' ? 'upload' : 'refresh-cw' ?>" class="w-3 h-3"></i> 
                                    <?= $ann['status'] == 'PENDING' ? 'Subir Firmado' : 'Reemplazar' ?>
                                    <input type="file" name="signed_file" class="hidden" onchange="window.showLoading(); this.form.submit()">
                                </label>
                            </form>
                        
                        <!-- Si está ARCHIVADO: Solo mostramos estado visual -->
                        <?php else: ?>
                            <?php if($ann['status'] == 'SIGNED'): ?>
                                <span class="text-green-600 font-bold text-[10px] border border-green-200 bg-green-50 px-2 py-1 rounded flex items-center gap-1">
                                    <i data-lucide="check" class="w-3 h-3"></i> COMPLETO
                                </span>
                            <?php else: ?>
                                <span class="text-red-500 font-bold text-[10px] flex items-center gap-1">
                                    <i data-lucide="x" class="w-3 h-3"></i> FALTA FIRMA
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <!-- 3. FORMULARIO SOLICITUD (Solo Legal + Contrato ACTIVO) -->
        <?php if(hasRole(['LEGAL', 'SUPER_ADMIN']) && !in_array($contract['status'], ['ARCHIVED', 'CANCELLED'])): ?>
            <div class="border-t pt-4 dark:border-slate-700">
                <h4 class="font-bold text-sm mb-3 dark:text-white">Solicitar Nuevos</h4>
                <form method="POST" enctype="multipart/form-data" onsubmit="window.showLoading()">
                    <input type="hidden" name="action" value="request_annexes">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <?php foreach(['CODE_CONDUCT'=>'Código Conducta', 'ANTI_CORRUPTION'=>'Anticorrupción', 'NDA'=>'NDA', 'OTHER'=>'Otro'] as $k=>$l): ?>
                            <div class="border p-2 rounded dark:border-slate-700">
                                <label class="font-bold block mb-1 dark:text-slate-300"><?= $l ?></label>
                                <input type="file" name="file_<?= $k ?>" class="text-xs w-full dark:text-slate-400">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="button" 
                                onclick="document.getElementById('modalAnnexes').classList.add('hidden')" 
                                class="flex-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold py-2.5 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-bold py-2.5 rounded-xl shadow-lg transition-transform hover:scale-105 flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i> Enviar Solicitud
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if(in_array($contract['status'], ['ARCHIVED', 'CANCELLED'])): ?>
            <div class="mt-4 p-3 bg-slate-100 dark:bg-slate-800 text-center text-xs text-slate-500 rounded-lg italic">
                El contrato está cerrado. Los anexos están en modo solo lectura.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL DE BLOQUEO DE ARCHIVO (FALTAN ANEXOS) -->
<div id="modalArchiveBlocked" class="fixed inset-0 z-[60] hidden flex items-center justify-center">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="document.getElementById('modalArchiveBlocked').classList.add('hidden')"></div>
    
    <!-- Tarjeta Centrada -->
    <div class="relative w-full max-w-md p-6 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border-2 border-orange-200 dark:border-orange-900 animate-fade-in-up m-4">
        
        <div class="text-center mb-4">
            <div class="w-16 h-16 rounded-full bg-orange-100 text-orange-600 mx-auto flex items-center justify-center mb-4">
                <i data-lucide="files" class="w-8 h-8"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-800 dark:text-white">No se puede archivar</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                Existen <strong><?= $pendingAnnexesCount ?> documentos anexos</strong> pendientes de firma.
            </p>
        </div>

        <!-- LISTA DE PENDIENTES -->
        <div class="bg-orange-50 dark:bg-orange-900/20 rounded-xl p-4 mb-6 border border-orange-100 dark:border-orange-900/50">
            <p class="text-xs font-bold text-orange-800 dark:text-orange-300 uppercase mb-2">Falta por subir:</p>
            <ul class="space-y-1">
                <?php foreach($annexes as $ann): ?>
                    <?php if($ann['status'] == 'PENDING'): ?>
                        <li class="text-sm text-slate-700 dark:text-slate-300 flex items-center gap-2">
                            <i data-lucide="x-circle" class="w-3 h-3 text-red-500"></i>
                            <?= htmlspecialchars($ann['doc_label']) ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- BOTONES DE ACCIÓN (ASÍNCRONOS) -->
        <div class="flex flex-col gap-3">
            <button type="button" onclick="sendReminderAsync()" id="btnSendReminder" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-xl shadow-lg flex items-center justify-center gap-2 transition-transform hover:scale-105">
                <i data-lucide="bell-ring" class="w-5 h-5"></i> Recordar al Solicitante
            </button>
            
            <button type="button" onclick="document.getElementById('modalArchiveBlocked').classList.add('hidden')" class="w-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold py-3 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                Entendido, volver
            </button>
        </div>
    </div>
</div>

<!-- AUTO-APERTURA -->
<?php if(isset($_GET['modal']) && $_GET['modal'] == 'archive_blocked'): ?>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const modal = document.getElementById('modalArchiveBlocked');
        modal.classList.remove('hidden');
        // Aseguramos animacion de entrada
        modal.querySelector('div.relative').classList.add('animate-fade-in-up');
        
        // Limpiamos la URL
        const url = new URL(window.location);
        url.searchParams.delete('modal');
        window.history.replaceState({}, document.title, url);
    });
</script>
<?php endif; ?>

<script>
    // Variables PHP a JS
    const pendingAnnexesCount = <?= (int)$pendingAnnexesCount ?>;
    const contractId = '<?= $id ?>';

    // 1. MANEJADOR DEL CLIC EN ARCHIVAR
    function handleArchiveAttempt() {
        if (pendingAnnexesCount > 0) {
            // Si faltan anexos, abrimos el modal de bloqueo SIN refrescar
            document.getElementById('modalArchiveBlocked').classList.remove('hidden');
        } else {
            // Si todo está bien, procedemos con el flujo normal
            promptAction('ARCHIVED', 'Archivar Contrato', false, 'bg-slate-700');
        }
    }

    // 2. ENVÍO ASÍNCRONO DEL RECORDATORIO
    async function sendReminderAsync() {
        const btn = document.getElementById('btnSendReminder');
        const originalText = btn.innerHTML;
        
        // Estado de carga
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 animate-spin"></i> Enviando...';
        if(typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const response = await fetch('api/send_annex_reminder.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ contract_id: contractId })
            });

            const data = await response.json();

            if (data.success) {
                // Cerrar modal
                document.getElementById('modalArchiveBlocked').classList.add('hidden');
                
                // Mostrar Toast Personalizado
                window.showToast('success', "Recordatorio Enviado", "Se notificó al solicitante por correo.");
            } else {
                alert("Error: " + (data.error || "No se pudo enviar el correo."));
            }
        } catch (e) {
            console.error(e);
            alert("Error de conexión al enviar recordatorio.");
        } finally {
            // Restaurar botón
            btn.disabled = false;
            btn.innerHTML = originalText;
            if(typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

</script>

<?php require 'includes/footer.php'; ?>