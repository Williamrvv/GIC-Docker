</div> <!-- Fin de max-w-7xl -->
    </main> <!-- Fin de Main -->

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

</body>
</html>