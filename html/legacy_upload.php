<?php
require 'db.php';
require 'auth.php';
require 'functions.php';
requireLogin();

// SEGURIDAD: Solo Admin o Legal
if (!hasRole(['SUPER_ADMIN', 'LEGAL'])) {
    header("Location: dashboard"); exit;
}

$msg = '';
$error = '';

// === PROCESAMIENTO DEL FORMULARIO ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        // 0. VALIDACIÓN ESTRICTA DE CAMPOS OBLIGATORIOS
        $requiredFields = ['title', 'supplier', 'category', 'issue_date', 'summary', 'initiator_name', 'initiator_email', 'initiator_dept'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Todos los campos son obligatorios. Falta: " . ucfirst(str_replace('_', ' ', $field)));
            }
        }
        
        // Validar archivo principal
        if (!isset($_FILES['file_contract']) || $_FILES['file_contract']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Debes subir el documento del contrato.");
        }

        // 1. GESTIÓN DEL USUARIO SOLICITANTE
        // Buscamos si el correo ya existe para vincularlo
        $emailSolicitante = trim($_POST['initiator_email']);
        $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtUser->execute([$emailSolicitante]);
        $initiatorId = $stmtUser->fetchColumn();

        if (!$initiatorId) {
            // SI NO EXISTE: Lo creamos "Silencioso" (Activo para que pueda entrar por SSO, pero sin password conocido)
            $username = explode('@', $emailSolicitante)[0];
            $dummyPass = password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT);
            
            $stmtNewUser = $pdo->prepare("INSERT INTO users (username, password, name, email, role, department, is_active, notification_preference) VALUES (?, ?, ?, ?, 'DEPT_HEAD', ?, 1, 'INVOLVED')");
            $stmtNewUser->execute([
                $username, 
                $dummyPass, 
                $_POST['initiator_name'], 
                $emailSolicitante, 
                $_POST['initiator_dept']
            ]);
            $initiatorId = $pdo->lastInsertId();
        }

        // 2. CREACIÓN DEL CONTRATO (ID MANUAL O AUTOMÁTICO)
        // Usamos la fecha de emisión real para generar el ID, para mantener orden cronológico
        $issueDate = $_POST['issue_date']; // YYYY-MM-DD
        $year = date('Y', strtotime($issueDate));
        
        // Generar Consecutivo para ESE año histórico
        $stmtSeq = $pdo->prepare("SELECT id FROM contracts WHERE id LIKE ? ORDER BY LENGTH(id) DESC, id DESC LIMIT 1");
        $stmtSeq->execute(["CTR-$year-%"]);
        $lastId = $stmtSeq->fetchColumn();
        
        $nextNum = 1;
        if ($lastId) {
            $parts = explode('-', $lastId);
            $nextNum = (int)end($parts) + 1;
        }
        $contractId = sprintf("CTR-%s-%04d", $year, $nextNum);

        // Insertar Contrato (Estado ARCHIVED directo)
        $stmtC = $pdo->prepare("INSERT INTO contracts (id, title, summary, supplier, category, initiator_id, status, priority, created_at, waiting_for_annexes) VALUES (?, ?, ?, ?, ?, ?, 'ARCHIVED', 'MEDIUM', ?, 0)");
        
        // Nota: created_at se fuerza a la fecha de emisión original
        $stmtC->execute([
            $contractId, 
            $_POST['title'], 
            $_POST['summary'], 
            $_POST['supplier'], 
            $_POST['category'], 
            $initiatorId, 
            $issueDate . ' 12:00:00' // Hora mediodía por defecto
        ]);

        // 3. GUARDAR DOCUMENTO PRINCIPAL
        if (isset($_FILES['file_contract']) && $_FILES['file_contract']['error'] === 0) {
            $ext = pathinfo($_FILES['file_contract']['name'], PATHINFO_EXTENSION);
            $fileName = $contractId . '_FINAL_MIGRADO.' . $ext;
            $path = __DIR__ . '/uploads/' . $fileName;
            
            $hash = encryptAndSave($_FILES['file_contract']['tmp_name'], $path);
            
            if ($hash) {
                // Insertar versión 1 (Final)
                $stmtV = $pdo->prepare("INSERT INTO contract_versions (contract_id, version_number, file_name, file_path, file_hash, uploaded_by, created_at) VALUES (?, 1, ?, ?, ?, ?, ?)");
                $stmtV->execute([$contractId, $_FILES['file_contract']['name'], 'uploads/'.$fileName, $hash, $_SESSION['user_id'], $issueDate . ' 12:00:00']);
            }
        } else {
            throw new Exception("El archivo del contrato es obligatorio.");
        }

        // 4. GUARDAR ANEXOS
        $annexTypes = ['CODE_CONDUCT' => 'Código de Conducta', 'ANTI_CORRUPTION' => 'Pol. Anticorrupción', 'NDA' => 'NDA', 'OTHER' => 'Anexo General'];
        
        foreach ($annexTypes as $key => $label) {
            if (isset($_FILES['annex_'.$key]) && $_FILES['annex_'.$key]['error'] === 0) {
                $f = $_FILES['annex_'.$key];
                $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
                $encName = $contractId . '_ANNEX_HIST_' . $key . '.' . $ext;
                $path = __DIR__ . '/uploads/' . $encName;
                
                if (encryptAndSave($f['tmp_name'], $path)) {
                    // En carga histórica, asumimos que el anexo ya está firmado (status=SIGNED)
                    $stmtAnn = $pdo->prepare("INSERT INTO contract_annexes (contract_id, doc_type, doc_label, signed_path, signed_name, status) VALUES (?, ?, ?, ?, ?, 'SIGNED')");
                    $stmtAnn->execute([$contractId, $key, $label, 'uploads/'.$encName, $f['name']]);
                }
            }
        }

        // 5. LOG DE AUDITORÍA
        logAudit($pdo, $contractId, "Carga Histórica", "Contrato migrado al sistema por " . $_SESSION['name']);

        $pdo->commit();
        $msg = "Contrato histórico $contractId cargado exitosamente.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error en carga: " . $e->getMessage();
    }
}

$pageTitle = "Carga Histórica";
require 'includes/header.php';
?>

<!-- LIBRERÍAS IA (Globales en Footer, pero aseguramos acceso al script) -->

<div class="max-w-4xl mx-auto animate-entry">
    
    <div class="flex items-center gap-4 mb-6">
        <div class="p-3 bg-purple-100 text-purple-600 rounded-xl">
            <i data-lucide="archive-restore" class="w-8 h-8"></i>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Carga Histórica de Contratos</h2>
            <p class="text-slate-500 text-sm">Migración de documentos antiguos al repositorio digital seguro.</p>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex justify-between items-center">
            <span><?= $msg ?></span>
            <a href="contracts?filter=ARCHIVED" class="text-sm font-bold underline">Ver en Archivo</a>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?= $error ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" 
          class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden" 
          id="legacyForm"
          onsubmit="window.showLoading('Guardando Histórico', 'Encriptando y archivando...')">
        
        <!-- SECCIÓN 1: DOCUMENTO Y ANÁLISIS -->
        <div class="p-6 border-b border-gray-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
            <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">1. Documento Principal (Contrato Firmado)</label>
            <div class="flex gap-4 items-start">
                <div class="flex-1">
                    <input type="file" name="file_contract" id="fileInput" accept=".pdf,.doc,.docx" required 
                           class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all cursor-pointer"
                           onchange="handleFileSelect(this)">
                </div>
                <button type="button" id="btnAnalyze" onclick="analyzeLegacyDocument()" disabled 
                        class="bg-purple-600 disabled:bg-slate-300 disabled:cursor-not-allowed hover:bg-purple-700 text-white px-4 py-2.5 rounded-xl font-bold shadow-lg flex items-center gap-2 transition-all">
                    <i data-lucide="sparkles" class="w-4 h-4"></i> Autocompletar con IA
                </button>
            </div>
            
            <!-- Feedback IA -->
            <div id="aiLoading" class="hidden mt-3 text-purple-600 text-sm flex items-center gap-2 font-medium">
                <i data-lucide="loader" class="animate-spin w-4 h-4"></i> Analizando documento...
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- SECCIÓN 2: METADATOS DEL CONTRATO -->
            <div class="space-y-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Datos del Contrato</h3>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Título</label>
                    <input type="text" name="title" id="inputTitle" required class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Proveedor</label>
                        <input type="text" name="supplier" id="inputSupplier" required class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Categoría</label>
                        <select name="category" id="inputCategory" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white">
                            <option value="" disabled selected>Seleccionar...</option>
                            <option value="Servicios">Servicios</option>
                            <option value="Alquiler de oficinas">Alquiler de oficinas</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>

                <div class="relative">
                    <!-- El ícono de calendario lo pone el navegador, pero agregamos cursor-pointer para UX -->
                     <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha de Emisión</label>
                    <input type="date" name="issue_date" id="inputDate" required 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white cursor-pointer"
                        onclick="this.showPicker()"> <!-- Truco: Abre el calendario al hacer clic en cualquier parte del input -->
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Descripción / Resumen</label>
                    <textarea name="summary" id="inputSummary" rows="3" class="w-full bg-slate-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-700 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:text-white"></textarea>
                </div>
            </div>

            <!-- SECCIÓN 3: SOLICITANTE Y ANEXOS -->
            <div class="space-y-6">
                
                <!-- SOLICITANTE -->
                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-xl border border-blue-100 dark:border-blue-900">
                    <h3 class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-3">Dueño Original (Solicitante)</h3>
                    <div class="space-y-3">
                        
                        <!-- INPUT NOMBRE CON AUTOCOMPLETADO -->
                        <div class="relative">
                            <input type="text" 
                                   name="initiator_name" 
                                   id="initiator_name" 
                                   placeholder="Nombre Completo (Escribe para buscar...)" 
                                   required 
                                   autocomplete="off"
                                   oninput="searchUser(this.value)"
                                   class="w-full bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800 rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                            
                            <!-- Lista de Sugerencias (Oculta por defecto) -->
                            <div id="userSuggestions" class="hidden absolute top-full left-0 w-full bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg shadow-xl z-50 mt-1 overflow-hidden">
                                <!-- Aquí se inyectan los resultados -->
                            </div>
                        </div>

                        <input type="email" name="initiator_email" id="initiator_email" placeholder="Correo Corporativo (@grupoanc.com)" required class="w-full bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800 rounded-lg p-2 text-sm outline-none">
                        <input type="text" name="initiator_dept" id="initiator_dept" placeholder="Departamento" required class="w-full bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800 rounded-lg p-2 text-sm outline-none">
                    </div>
                    <p class="text-[10px] text-blue-500 mt-2">
                        * Si el correo coincide con un usuario existente, se vinculará automáticamente. Si no, se creará un usuario nuevo.
                    </p>
                </div>

                <!-- ANEXOS -->
                <div>
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Anexos (Opcional)</h3>
                    <div class="space-y-2 max-h-48 overflow-y-auto custom-scrollbar pr-2">
                        <?php foreach(['CODE_CONDUCT'=>'Código Conducta', 'ANTI_CORRUPTION'=>'Pol. Anticorrupción', 'NDA'=>'NDA', 'OTHER'=>'Otro'] as $k=>$l): ?>
                            <div class="flex items-center gap-3 p-2 border border-dashed border-gray-300 dark:border-slate-700 rounded-lg">
                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400 w-1/3"><?= $l ?></span>
                                <input type="file" name="annex_<?= $k ?>" class="text-xs w-full text-slate-500">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- FOOTER -->
        <div class="p-6 border-t border-gray-100 dark:border-slate-700 flex justify-end gap-3 bg-slate-50 dark:bg-slate-900">
            <button type="reset" class="px-5 py-2.5 text-slate-500 hover:text-slate-700 font-bold">Limpiar</button>
            <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-2.5 rounded-xl font-bold shadow-lg flex items-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Guardar en Archivo
            </button>
        </div>
    </form>
</div>

<script>
    // Reutilizamos la lógica de IA existente, pero adaptada
    async function analyzeLegacyDocument() {
        const fileInput = document.getElementById('fileInput');
        const file = fileInput.files[0];
        if (!file) return;

        // UI Loading
        document.getElementById('aiLoading').classList.remove('hidden');
        document.getElementById('btnAnalyze').disabled = true;

        try {
            // 1. Preparar Payload (Reusamos la lógica de Mammoth/PDF.js que ya tienes en app.js o contracts.php)
            // Como este es un archivo nuevo, implementamos una versión simplificada inline o llamamos a una global si existiera.
            // Para asegurar consistencia, usamos la misma lógica de "Extraer Texto" del cliente.
            
            let payload = {};
            
            // Helper Base64
            const toBase64 = f => new Promise((r, j) => { const x=new FileReader(); x.onload=()=>r(x.result); x.onerror=e=>j(e); x.readAsDataURL(f); });

            // Detectar tipo
            if (file.name.endsWith('.docx')) {
                const arrayBuffer = await file.arrayBuffer();
                const result = await mammoth.extractRawText({arrayBuffer: arrayBuffer});
                payload = { type: 'text', content: result.value };
            } 
            else if (file.type === 'application/pdf') {
                // Intento rápido de texto con PDF.js
                const ab = await file.arrayBuffer();
                const pdf = await pdfjsLib.getDocument({ data: ab }).promise;
                let txt = "";
                for(let i=1; i<=Math.min(pdf.numPages, 5); i++) { // Leer primeras 5 págs
                    const page = await pdf.getPage(i);
                    const content = await page.getTextContent();
                    txt += content.items.map(s=>s.str).join(' ') + "\n";
                }
                
                if (txt.trim().length > 50) {
                    payload = { type: 'text', content: txt };
                } else {
                    // Fallback visual
                    const b64 = await toBase64(file);
                    payload = { type: 'base64', mime: file.type, content: b64.split(',')[1] };
                }
            }
            else {
                // Imágenes
                const b64 = await toBase64(file);
                payload = { type: 'base64', mime: file.type, content: b64.split(',')[1] };
            }

            // 2. Enviar a la API
            const res = await fetch('api/analyze_gemini.php', { 
                method: 'POST', 
                headers: {'Content-Type':'application/json'}, 
                body: JSON.stringify(payload) 
            });
            
            const data = await res.json();

            if (data.error) throw new Error(data.error);

            // 3. Llenar campos
            if (data.title) document.getElementById('inputTitle').value = data.title;
            if (data.supplier) document.getElementById('inputSupplier').value = data.supplier;
            if (data.summary) document.getElementById('inputSummary').value = data.summary;
             if (data.date) {
                // El input type="date" requiere formato YYYY-MM-DD, que es justo lo que pedimos a la IA
                document.getElementById('inputDate').value = data.date;
            }
            
            // Seleccionar categoría
            const catSelect = document.getElementById('inputCategory');
            for(let i=0; i<catSelect.options.length; i++) {
                if (data.category && catSelect.options[i].text.includes(data.category)) {
                    catSelect.selectedIndex = i;
                    break;
                }
            }

        } catch (e) {
            console.error(e);
            alert("Error IA: " + e.message);
        } finally {
            document.getElementById('aiLoading').classList.add('hidden');
            document.getElementById('btnAnalyze').disabled = false;
        }
    }

    function handleFileSelect(input) {
        document.getElementById('btnAnalyze').disabled = !input.files[0];
    }

    // --- LÓGICA DE AUTOCOMPLETADO DE USUARIOS ---
    let debounceTimer;

    async function searchUser(query) {
        const list = document.getElementById('userSuggestions');
        
        // Limpiar timer anterior
        clearTimeout(debounceTimer);
        
        if (query.length < 2) {
            list.classList.add('hidden');
            list.innerHTML = '';
            return;
        }

        // Esperar 300ms antes de llamar a la API (Optimización)
        debounceTimer = setTimeout(async () => {
            try {
                const res = await fetch(`api/search_users.php?q=${encodeURIComponent(query)}`);
                const users = await res.json();

                list.innerHTML = '';
                
                if (users.length > 0) {
                    users.forEach(u => {
                        const item = document.createElement('div');
                        item.className = "p-3 hover:bg-blue-50 dark:hover:bg-slate-700 cursor-pointer border-b border-gray-100 dark:border-slate-700 last:border-0 transition-colors";
                        item.innerHTML = `
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-200">${u.name}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">${u.email} • ${u.department}</p>
                        `;
                        // Al hacer clic, rellenamos todo
                        item.onclick = () => selectUser(u);
                        list.appendChild(item);
                    });
                    list.classList.remove('hidden');
                } else {
                    // Opcional: Mostrar "Sin resultados" o simplemente ocultar
                    list.classList.add('hidden');
                }
            } catch (e) {
                console.error("Error buscando usuarios", e);
            }
        }, 300);
    }

    function selectUser(user) {
        document.getElementById('initiator_name').value = user.name;
        document.getElementById('initiator_email').value = user.email;
        document.getElementById('initiator_dept').value = user.department; // Asume que la columna en BD se llama 'department'
        
        // Ocultar lista
        document.getElementById('userSuggestions').classList.add('hidden');
    }

    // Cerrar lista si hago clic fuera
    document.addEventListener('click', function(e) {
        const input = document.getElementById('initiator_name');
        const list = document.getElementById('userSuggestions');
        if (!input.contains(e.target) && !list.contains(e.target)) {
            list.classList.add('hidden');
        }
    });
</script>

<?php require 'includes/footer.php'; ?>