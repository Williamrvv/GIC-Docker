</div> <!-- Fin de max-w-7xl -->
    </main> <!-- Fin de Main -->



    <!-- MODAL DE CARGA -->
    <div id="modalContract" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="window.closeContractModal()"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl p-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
                <div class="p-6 border-b border-gray-100 dark:border-slate-800 flex justify-between items-center">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="sparkles" class="text-purple-500"></i> Nuevo Contrato</h3>
                    <button onclick="window.closeContractModal()" class="text-gray-400 hover:text-white"><i data-lucide="x"></i></button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto p-6 space-y-5" id="contractForm" onsubmit="window.showLoading('Creando Contrato', 'Encriptando archivo y enviando notificaciones iniciales...')">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="red_flag_count" id="aiFlagCount" value="0">
                    <input type="hidden" name="red_flag_details" id="aiFlagDetails" value="">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Documento</label>
                        <div class="flex gap-3">
                            <div class="relative flex-1 border-2 border-dashed border-gray-300 dark:border-slate-700 rounded-xl p-4 hover:bg-gray-50 dark:hover:bg-slate-800 cursor-pointer" onclick="document.getElementById('fileInput').click()">
                                <div class="flex items-center justify-center gap-3"><i data-lucide="file-up" class="w-6 h-6 text-blue-500"></i><span id="fileNameDisplay" class="text-sm text-slate-500">PDF, DOCX o Imagen...</span></div>
                                <input type="file" name="file" id="fileInput" accept=".pdf,.docx,.doc,image/*" required class="hidden" onchange="window.handleFileSelect(this)">
                            </div>
                            <button type="button" id="btnAnalyze" onclick="analyzeDocument()" disabled class="bg-purple-600 disabled:bg-purple-300 hover:bg-purple-700 text-white px-4 rounded-xl flex items-center gap-2 shadow-lg"><i data-lucide="bot"></i> Analizar</button>
                        </div>
                    </div>

                    <div id="aiLoading" class="hidden bg-purple-50 dark:bg-purple-900/20 p-4 rounded-xl border border-purple-100 dark:border-purple-800 flex items-center gap-3"><i data-lucide="loader" class="animate-spin text-purple-600"></i><span class="text-sm text-purple-700 dark:text-purple-300">Analizando documento...</span></div>
                    <div id="aiError" class="hidden bg-orange-50 dark:bg-orange-900/20 p-4 rounded-xl border border-orange-100 dark:border-orange-800 mb-4"><h4 class="font-bold text-orange-700 dark:text-orange-300 text-sm flex items-center gap-2"><i data-lucide="alert-circle" class="w-4 h-4"></i> Error</h4><p class="text-xs text-orange-600 dark:text-orange-400 mt-1">No se pudo analizar. Complete manualmente.</p></div>
                    <div id="aiResults" class="hidden bg-red-50 dark:bg-red-900/20 p-4 rounded-xl border border-red-100 dark:border-red-800"><h4 class="font-bold text-red-700 dark:text-red-300 text-sm flex items-center gap-2 mb-1"><i data-lucide="alert-triangle" class="w-4 h-4"></i> Red Flags: <span id="displayFlagCount">0</span></h4><p id="displayFlagDetails" class="text-xs text-red-600 dark:text-red-400 mt-1"></p></div>

                    <fieldset id="formFields" class="space-y-4">
                        <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Título</label><input type="text" name="title" id="inputTitle" required class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-xl px-4 py-2.5 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Proveedor</label><input type="text" name="supplier" id="inputSupplier" required class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-xl px-4 py-2.5 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Categoría</label>
                                <select name="category" id="inputCategory" required class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-xl px-4 py-2.5 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Seleccionar...</option>
                                    <?php 
                                        if (!isset($categories)) {
                                            $categories = (function_exists('getCategories') && isset($pdo)) 
                                                ? getCategories($pdo) 
                                                : ['Servicios', 'Alquiler de oficinas', 'Otro']; // Fallback de emergencia
                                        }
                                        foreach($categories as $cat): 
                                    ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div><label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Resumen (IA)</label><textarea name="summary" id="inputSummary" rows="2" class="w-full bg-gray-50 dark:bg-slate-950 border border-gray-300 dark:border-slate-700 rounded-xl px-4 py-2 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 text-sm"></textarea></div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Prioridad</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer dark:text-white"><input type="radio" name="priority" value="LOW" class="text-blue-600"> <span class="text-sm">Baja</span></label>
                                <label class="flex items-center gap-2 cursor-pointer dark:text-white"><input type="radio" name="priority" value="MEDIUM" checked class="text-blue-600"> <span class="text-sm">Media</span></label>
                                <label class="flex items-center gap-2 cursor-pointer dark:text-white"><input type="radio" name="priority" value="HIGH" class="text-red-600"> <span class="text-sm font-bold text-red-500">Alta</span></label>
                            </div>
                        </div>
                    </fieldset>
                    <div class="pt-4 flex justify-end gap-3 border-t border-gray-100 dark:border-slate-800">
                        <button type="button" onclick="window.closeContractModal()" class="px-5 py-2.5 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors">Cancelar</button>
                        <button type="submit" id="btnSave" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-lg transition-all font-medium">Guardar Contrato</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- ============================================ -->
    <!-- BLOQUEADOR DE PANTALLA (LOADING OVERLAY)     -->
    <!-- ============================================ -->
    <div id="globalLoader" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[100] hidden flex flex-col items-center justify-center transition-opacity duration-300">
        <!-- Spinner -->
        <div class="relative mb-4">
            <div class="w-16 h-16 border-4 border-blue-200 border-t-blue-500 rounded-full animate-spin"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <i data-lucide="send" class="text-blue-500 w-6 h-6 animate-pulse"></i>
            </div>
        </div>
        
        <!-- Textos -->
        <h2 id="globalLoaderTitle" class="text-2xl font-bold text-white mb-2 tracking-wide">Procesando...</h2>
        <p id="globalLoaderSub" class="text-slate-300 text-sm">Por favor espere, no cierre la ventana.</p>
    </div>

    <!-- ======================================================= -->
    <!-- LIBRERÍAS GLOBALES (CDN JSDelivr)         -->
    <!-- ======================================================= -->
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    <script>
        // Configuración Global del Worker
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js"></script>
    <!-- Script Global de la App -->
    <script src="js/app.js"></script>
    
    <script>
        // Inicializar Iconos
        if(typeof lucide !== 'undefined') lucide.createIcons();

        /**
         * FUNCIÓN GLOBAL PARA BLOQUEAR PANTALLA
         */
        window.showLoading = function(title = "Procesando...", subtitle = "Esto puede tardar unos segundos...") {
            const loader = document.getElementById('globalLoader');
            const titleEl = document.getElementById('globalLoaderTitle');
            const subEl = document.getElementById('globalLoaderSub');

            if(loader) {
                if(titleEl) titleEl.innerText = title;
                if(subEl) subEl.innerText = subtitle;
                
                loader.classList.remove('hidden');
                setTimeout(() => loader.classList.add('opacity-100'), 10);
            }
        };
    </script>
    
    <!-- DEBUGGER DE CORREOS -->
    <?php if (isset($_SESSION['mail_debug']) && !empty($_SESSION['mail_debug'])): ?>
        <script>
            console.group("📧 GIC SMTP Debugger");
            console.table(<?php echo json_encode($_SESSION['mail_debug']); ?>);
            console.groupEnd();
        </script>
        <?php unset($_SESSION['mail_debug']); ?>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- TOOLTIP GLOBAL                             -->
    <!-- ========================================== -->
    <div id="globalTooltip" class="fixed hidden pointer-events-none z-[100] px-3 py-2 rounded-lg shadow-2xl text-[10px] font-bold text-white whitespace-nowrap transition-opacity duration-150 opacity-0 transform -translate-x-1/2 mt-2">
        <span id="globalTooltipText"></span>
        <!-- Flecha -->
        <div id="globalTooltipArrow" class="absolute bottom-full left-1/2 -translate-x-1/2 border-4 border-transparent border-b-current"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tooltip = document.getElementById('globalTooltip');
            const textEl = document.getElementById('globalTooltipText');
            const arrowEl = document.getElementById('globalTooltipArrow');

            // Delegación Global (Funciona incluso con contenido cargado por AJAX/Router)
            document.body.addEventListener('mouseover', (e) => {
                const trigger = e.target.closest('[data-tooltip]');
                if (!trigger) return;

                const text = trigger.getAttribute('data-tooltip');
                // Color por defecto: Slate-800 (Oscuro)
                const colorClass = trigger.getAttribute('data-color') || 'bg-slate-800';
                
                // 1. Contenido y Estilos
                textEl.innerHTML = text;
                tooltip.className = `fixed pointer-events-none z-[100] px-3 py-2 rounded-lg shadow-xl text-[10px] font-bold text-white whitespace-nowrap transition-all duration-200 transform -translate-x-1/2 ${colorClass}`;
                
                // Forzar renderizado de iconos dentro del tooltip si los hay
                if(typeof lucide !== 'undefined') lucide.createIcons({ root: textEl });

                // Color Flecha
                let arrowColor = colorClass.replace('bg-', 'text-');
                arrowEl.className = `absolute bottom-full left-1/2 -translate-x-1/2 border-4 border-transparent border-b-current ${arrowColor}`;

                // 2. Posicionamiento Matemático
                const rect = trigger.getBoundingClientRect();
                let top = rect.bottom + 8; 
                let left = rect.left + (rect.width / 2);

                // Corrección si se sale de la pantalla a la derecha
                if (left + 100 > window.innerWidth) left = window.innerWidth - 60;
                // Corrección si se sale abajo
                if (top + 40 > window.innerHeight) top = rect.top - 40;

                tooltip.style.top = `${top}px`;
                tooltip.style.left = `${left}px`;

                // 3. Mostrar
                tooltip.classList.remove('hidden');
                requestAnimationFrame(() => tooltip.classList.remove('opacity-0'));
            });

            document.body.addEventListener('mouseout', (e) => {
                if (e.target.closest('[data-tooltip]')) {
                    tooltip.classList.add('opacity-0');
                    tooltip.classList.add('hidden');
                }
            });
        });
    </script>
    
    <!-- LÓGICA AUTOMÁTICA DE NOTIFICACIONES PHP -> JS -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            
            // 1. DICCIONARIO DE MENSAJES (Mapeo de 'msg' a textos bonitos)
            const messages = {
                'created':       { type: 'success', title: 'Creación Exitosa', text: 'El registro ha sido creado correctamente.' },
                'updated':       { type: 'success', title: 'Actualización Exitosa', text: 'Los datos se han guardado correctamente.' },
                'deleted':       { type: 'success', title: 'Eliminado', text: 'El registro ha sido eliminado del sistema.' },
                'uploaded':      { type: 'success', title: 'Carga Completa', text: 'El documento se subió y encriptó correctamente.' },
                'signed':        { type: 'success', title: 'Firma Registrada', text: 'El documento firmado ha sido procesado.' },
                'cancelled':     { type: 'warning', title: 'Anulado', text: 'El contrato ha sido marcado como anulado.' },
                'status_changed':{ type: 'info',    title: 'Estado Actualizado', text: 'El flujo ha avanzado a la siguiente etapa.' },
                'reminder_sent': { type: 'success', title: 'Correo Enviado', text: 'Se ha notificado al usuario correctamente.' },
                'annex_requested':{ type: 'info',   title: 'Solicitud Enviada', text: 'Se ha pedido al usuario cargar los anexos.' },
                'annex_uploaded': { type: 'success', title: 'Anexo Recibido', text: 'Documento anexo guardado exitosamente.' },
                'annex_deleted':  { type: 'warning', title: 'Anexo Eliminado', text: 'El documento adjunto ha sido borrado.' }
            };

            // 2. DETECTAR 'msg' (Éxito/Info)
            if (urlParams.has('msg')) {
                const key = urlParams.get('msg');
                if (messages[key]) {
                    const m = messages[key];
                    window.showToast(m.type, m.title, m.text);
                } else if (key === 'activated') {
                    window.showToast('success', 'Usuario Activado', 'Acceso al sistema habilitado.');
                } else if (key === 'deactivated') {
                    window.showToast('warning', 'Usuario Desactivado', 'Acceso revocado temporalmente.');
                }
            }

            // 3. DETECTAR 'error' (Mensajes directos de error)
            if (urlParams.has('error')) {
                window.showToast('error', 'Error del Sistema', decodeURIComponent(urlParams.get('error')));
            }

            // 4. LIMPIEZA DE URL (Para que no salga la alerta al refrescar)
            if (urlParams.has('msg') || urlParams.has('error')) {
                const newUrl = window.location.pathname + window.location.search.replace(/[\?&](msg|error)=[^&]+/, '').replace(/^&/, '?');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
</body>
</html>