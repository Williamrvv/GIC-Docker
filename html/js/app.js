// Variable de control
let isRouterInitialized = false;
let activeFetchController = null;

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

function initApp() {
    if(typeof lucide !== 'undefined') lucide.createIcons();
    
    if (!isRouterInitialized) {
        initRouter();
        isRouterInitialized = true;
    }

    animateElements();
    updateThemeIcon();
    
    // Inicializar gráficos del Dashboard si existen
    if(document.getElementById('statusChart') && typeof window.initDashboardCharts === 'function') {
        window.initDashboardCharts();
    }
}

// === ROUTER SPA ===
function initRouter() {
    document.body.addEventListener('click', e => {
        const link = e.target.closest('a');
        
        if (link && link.href.startsWith(window.location.origin) && 
            !link.getAttribute('download') && 
            !link.href.includes('logout') &&
            !link.getAttribute('target') &&
            !link.href.includes('#')) {

            forceHideTooltip(); 
            
            e.preventDefault();
            toggleSidebar(false);

            const url = link.href;
            const mainContent = document.querySelector('main');
            if(mainContent) mainContent.classList.add('opacity-0', 'translate-y-4');

            setTimeout(() => fetchPage(url), 200); 
        }
    });

    window.onpopstate = () => fetchPage(window.location.href, false);
}

async function fetchPage(url, pushState = true) {

    forceHideTooltip();
    
    // --- SOLUCIÓN UNIVERSAL TOOLTIPS ---
    // Forzamos el cierre de cualquier tooltip abierto al iniciar navegación
    const tooltip = document.getElementById('globalTooltip');
    if (tooltip) {
        tooltip.classList.add('hidden');
        tooltip.classList.add('opacity-0');
        // Movemos el tooltip fuera de pantalla por si acaso
        tooltip.style.top = '-1000px';
        tooltip.style.left = '-1000px';
    }
    // -----------------------------------

    try {
        if (activeFetchController) activeFetchController.abort();
        activeFetchController = new AbortController();

        const response = await fetch(url, { signal: activeFetchController.signal });
        const text = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        
        const newMain = doc.querySelector('main');
        const currentMain = document.querySelector('main');

        if(newMain && currentMain) {
            currentMain.innerHTML = newMain.innerHTML;
            // Re-hidratar scripts (Crucial para gráficos y funciones inline)
            Array.from(newMain.querySelectorAll("script")).forEach(oldScript => {
                const newScript = document.createElement("script");
                Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                currentMain.appendChild(newScript);
            });
        }
        
        const newTitle = doc.querySelector('title');
        if(newTitle) document.title = newTitle.innerText;

        if (pushState) window.history.pushState({}, '', url);

        window.scrollTo(0, 0);
        updateSidebarActiveState(url);
        
        if(currentMain) currentMain.classList.remove('opacity-0', 'translate-y-4');
        
        initApp();

    } catch (error) {
        if (error.name !== 'AbortError') window.location.href = url;
    }
}

// === FUNCIONES MODALES GLOBALES (Contratos & Usuarios) ===
window.openUserModal = function(mode, data = null) {
    const modal = document.getElementById('modalUser');
    if (!modal) return; 
    modal.classList.remove('hidden');

    const title = document.getElementById('modalTitle');
    const action = document.getElementById('formAction');
    const passInput = document.getElementById('inputPass');
    const passHint = document.getElementById('passHint');
    const roleSelect = document.getElementById('inputRole');
    const notifySelect = document.getElementById('inputNotify');
    
    // --- NUEVO: Referencia al checkbox de permisos ---
    const manageChk = document.getElementById('inputCanManage'); 

    // Helper para asignar valor
    const setVal = (id, val) => { 
        const el = document.getElementById(id);
        if(el) el.value = val; 
    };

    // --- LÓGICA DE RESTRICCIÓN DE NOTIFICACIONES ---
    const enforceNotificationRules = () => {
        if (!roleSelect || !notifySelect) return;
        
        const allOption = notifySelect.querySelector('option[value="ALL"]');
        if (!allOption) return;

        if (roleSelect.value === 'DEPT_HEAD') {
            allOption.disabled = true;
            allOption.innerText = "⛔ No disponible para Solicitantes";
            if (notifySelect.value === 'ALL') notifySelect.value = 'INVOLVED';
        } else {
            allOption.disabled = false;
            allOption.innerText = "📢 Todo el Sistema (Legal/Admin)";
        }
    };

    if (roleSelect) roleSelect.onchange = enforceNotificationRules;

    // --- LÓGICA DE APERTURA (EDITAR VS CREAR) ---
    if (mode === 'edit' && data) {
        title.innerText = 'Editar Usuario';
        action.value = 'edit';
        passInput.removeAttribute('required');
        passHint.classList.remove('hidden');

        setVal('userId', data.id);
        setVal('inputName', data.name);
        setVal('inputEmail', data.email);
        setVal('inputUser', data.username);
        setVal('inputRole', data.role);
        setVal('inputDept', data.department);
        setVal('inputNotify', data.notification_preference || 'INVOLVED');
        
        passInput.value = ''; 

        // --- NUEVO: Asignar valor al checkbox (MySQL devuelve 1 o 0) ---
        if(manageChk) manageChk.checked = (data.can_manage_users == 1);

    } else {
        title.innerText = 'Nuevo Usuario';
        action.value = 'create';
        passInput.setAttribute('required', 'required');
        passHint.classList.add('hidden');
        
        ['userId','inputName','inputEmail','inputUser','inputDept','inputPass'].forEach(id => setVal(id, ''));
        
        setVal('inputRole', 'DEPT_HEAD');
        setVal('inputNotify', 'INVOLVED');

        // --- NUEVO: Resetear checkbox ---
        if(manageChk) manageChk.checked = false;
    }

    // Ejecutar la regla una vez al abrir para ajustar el estado inicial
    enforceNotificationRules();
};

window.closeUserModal = function() {
    const modal = document.getElementById('modalUser');
    if(modal) modal.classList.add('hidden');
};

// 2. Contratos (CORREGIDO)
window.openContractModal = function() {
    const modal = document.getElementById('modalContract');
    if (modal) {
        modal.classList.remove('hidden');
        // Reset UI si la función existe
        if(typeof window.resetAnalysisUI === 'function') window.resetAnalysisUI();
    } else {
        console.error("No se encontró el modalContract en el DOM");
    }
};

window.closeContractModal = function() {
    const modal = document.getElementById('modalContract');
    if(modal) modal.classList.add('hidden');
};

// Función auxiliar para resetear el formulario de IA
window.resetAnalysisUI = function() {
    ['aiResults', 'aiError', 'aiLoading'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.classList.add('hidden');
    });
    
    if(document.getElementById('fileNameDisplay')) document.getElementById('fileNameDisplay').innerText = "Seleccionar PDF o Imagen...";
    if(document.getElementById('fileInput')) document.getElementById('fileInput').value = "";
    if(document.getElementById('btnAnalyze')) document.getElementById('btnAnalyze').disabled = true;

    // Limpiar inputs visuales
    ['inputTitle', 'inputSupplier', 'inputSummary'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.value = "";
    });
    // Limpiar inputs ocultos
    ['aiFlagCount', 'aiFlagDetails'].forEach(id => {
        const el = document.getElementById(id);
        if(el) el.value = "0"; // o string vacío
    });
    
    const cat = document.getElementById('inputCategory');
    if(cat) cat.selectedIndex = 0;
};

// Manejador de archivo
window.handleFileSelect = function(input) {
    if (input.files && input.files[0]) {
        if(document.getElementById('fileNameDisplay')) {
            document.getElementById('fileNameDisplay').innerText = input.files[0].name;
        }
        if(document.getElementById('btnAnalyze')) {
            document.getElementById('btnAnalyze').disabled = false;
        }
        if(document.getElementById('aiError')) {
            document.getElementById('aiError').classList.add('hidden');
        }
    }
};

// Helpers UI Genéricos
function updateSidebarActiveState(url) {
    let path = new URL(url).pathname.split('/').pop().replace('.php', '');
    const activeClasses = ['bg-blue-600', 'text-white', 'shadow-lg', 'shadow-blue-500/30'];
    const inactiveClasses = ['text-slate-400', 'hover:bg-slate-800', 'hover:text-white', 'transition-colors'];

    document.querySelectorAll('#sidebar-nav a').forEach(link => {
        link.className = `group flex items-center px-4 py-3 text-sm font-medium rounded-xl ${inactiveClasses.join(' ')}`;
        if (link.href.includes(path) || (path === '' && link.href.includes('dashboard'))) {
             link.className = `group flex items-center px-4 py-3 text-sm font-medium rounded-xl ${activeClasses.join(' ')}`;
        }
    });
}

function animateElements() {
    document.querySelectorAll('.animate-entry').forEach((el, index) => {
        el.style.animationDelay = `${index * 0.05}s`;
        el.classList.add('animate-fade-in-up');
    });
}

function updateThemeIcon() {
    const icon = document.getElementById('theme-icon');
    if(!icon) return;
    icon.setAttribute('data-lucide', document.documentElement.classList.contains('dark') ? 'sun' : 'moon');
    if(typeof lucide !== 'undefined') lucide.createIcons();
}

function toggleTheme() {
    const html = document.documentElement;
    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.theme = 'light';
    } else {
        html.classList.add('dark');
        localStorage.theme = 'dark';
    }
    updateThemeIcon();
    // Redibujar gráficos del dashboard al cambiar tema para que se ajuste el color del texto
    if(typeof window.initDashboardCharts === 'function') window.initDashboardCharts();
}

function toggleSidebar(forceOpen = null) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    if(!sidebar || !overlay) return;

    const isClosed = sidebar.classList.contains('-translate-x-full');
    const shouldOpen = forceOpen !== null ? forceOpen : isClosed;

    if (shouldOpen) {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }
}

// --- UTILIDAD: CIERRE FORZADO DE TOOLTIPS ---
function forceHideTooltip() {
    const tooltip = document.getElementById('globalTooltip');
    if (tooltip) {
        tooltip.classList.add('hidden');
        tooltip.classList.add('opacity-0');
        // Lo movemos lejos para asegurar que no estorbe
        tooltip.style.top = '-5000px';
        tooltip.style.left = '-5000px';
    }
}