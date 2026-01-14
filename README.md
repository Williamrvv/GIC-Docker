GIC - Gestión Inteligente de Contratos
![alt text](assets/img/logo.png)
GIC es una plataforma empresarial de Gestión del Ciclo de Vida de Contratos (CLM) diseñada para alta seguridad, eficiencia operativa y auditoría forense automatizada mediante Inteligencia Artificial.
El sistema permite a departamentos legales y solicitantes gestionar la creación, revisión, negociación, firma y archivado de documentos legales, integrando un motor de IA para detección de riesgos y comparación de versiones.
🚀 Características Principales
🧠 Inteligencia Artificial (Google Gemini 2.5)
Auditoría de Riesgos: Análisis automático de contratos ("Red Flags") verificando cláusulas críticas (Fuerza Mayor, Terminación, Jurisdicción) contra estándares corporativos.
Comparación Híbrida (AI + Algorítmica): Sistema de "Redlining" inteligente que combina diff_match_patch (precisión matemática carácter por carácter) con el razonamiento de Gemini para explicar el impacto legal de los cambios.
Soporte Multimodal: Capacidad para analizar texto plano, Word (.docx) e imágenes/PDFs escaneados (OCR nativo).
🔒 Seguridad de Grado Militar
Encriptación en Reposo: Todos los archivos se cifran con AES-256-CBC antes de tocar el disco duro.
Derivación de Claves: Uso de SHA-256 para derivar llaves criptográficas robustas a partir de frases de paso complejas.
Proxy de Descarga: Los archivos nunca son accesibles por URL directa. Se desencriptan en memoria RAM ("On-the-fly") solo bajo demanda y verificación de sesión.
Deep Linking Seguro: Redirección inteligente post-login preservando el contexto del usuario.
⚡ Flujo de Trabajo y UX
Arquitectura SPA (Single Page Application): Router en Vanilla JS que permite navegación fluida sin recargas de página.
Máquina de Estados: Flujo estricto (Sin Revisar -> En Revisión -> Observaciones -> Firma -> Archivado).
Smart Routing de Notificaciones: Motor SMTP nativo que decide inteligentemente a quién notificar basándose en roles e historial de interacción.
Gestión de Anexos: Módulo dedicado para solicitar y validar documentos de cumplimiento (NDA, Código de Conducta) antes del cierre.
☁️ Geo-Redundancia
Cloud Sync: Script automatizado (Cron) para respaldar contratos finalizados (desencriptados) hacia Microsoft OneDrive/SharePoint para redundancia geográfica.
🛠️ Tech Stack
Infraestructura: Docker & Docker Compose.
Backend: PHP 8.2 (Nativo, sin frameworks pesados para máximo rendimiento).
Base de Datos: MySQL 8.0 (Transaccional con motor InnoDB).
Frontend: Vanilla Javascript (ES6+) + TailwindCSS (CDN).
Librerías JS: pdf.js, mammoth.js, diff_match_patch, Chart.js.
Autenticación: Híbrida (Local + Microsoft 365 OAuth2).
⚙️ Instalación y Despliegue
1. Prerrequisitos
Docker y Docker Compose instalados.
Clave de API de Google Gemini.
Credenciales de Azure AD (para SSO y Sync).
2. Clonar Repositorio
code
Bash
git clone https://github.com/tu-usuario/GIC-Docker.git
cd GIC-Docker
3. Configuración de Entorno (.env)
Crea un archivo .env en la raíz basado en el siguiente template. Es vital usar comillas dobles para la frase de encriptación.
code
Env
# --- DATABASE ---
DB_HOST=db
DB_NAME=bd_aprobador
DB_USER=root
DB_PASS=tu_password_seguro

# --- SECURITY (CRÍTICO: NO PERDER ESTA FRASE) ---
ENCRYPTION_PASSPHRASE="Tu_Frase_Secreta_Extremadamente_Larga_Y_Compleja_2026!"

# --- AI SERVICES ---
GEMINI_API_KEY=AIzaSy...Tu_Key_De_Google

# --- MICROSOFT 365 SSO ---
O365_CLIENT_ID=tu-client-id
O365_TENANT_ID=tu-tenant-id
O365_CLIENT_SECRET=tu-secret
O365_REDIRECT_URI=https://tu-dominio.com/callback.php

# --- SMTP ---
SMTP_HOST=smtp.office365.com
SMTP_PORT=587
SMTP_USER=notificaciones@tu-dominio.com
SMTP_PASS=tu-password-correo
4. Levantar Contenedores
code
Bash
docker-compose up -d --build
5. Inicializar Base de Datos
Importa el archivo bd_aprobador.sql en tu contenedor MySQL:
code
Bash
docker exec -i gic_db mysql -u root -p bd_aprobador < bd_aprobador.sql
📂 Estructura del Proyecto
code
Text
/
├── api/                 # Endpoints AJAX (Análisis IA, Comparación, Preferencias)
├── cron/                # Scripts de automatización (Cloud Sync)
├── includes/            # Header, Footer y layouts comunes
├── js/                  # Lógica Frontend (Router SPA, Modales)
├── uploads/             # Almacenamiento local (Archivos encriptados .bin)
├── .htaccess            # Reglas de ruteo Apache
├── auth.php             # Middleware de sesión y RBAC
├── db.php               # Conexión PDO y carga de entorno
├── functions.php        # Core: Encriptación y SMTP
└── contract_detail.php  # Controlador principal del flujo
🤖 Automatización (Cron Jobs)
Para activar la sincronización con la nube (Geo-Redundancia), agrega la siguiente tarea al crontab del servidor host o del contenedor:
code
Bash
# Ejecutar cada noche a las 23:00
0 23 * * * /usr/local/bin/php /var/www/html/cron/cloud_sync.php >> /var/logs/gic_sync.log 2>&1
🛡️ Roles y Permisos
SUPER_ADMIN: Acceso total, gestión de usuarios, anulación forzada.
LEGAL: Revisión de contratos, solicitud de cambios, aprobación legal, gestión de anexos.
DEPT_HEAD (Solicitante): Carga de contratos, respuesta a observaciones, subida de anexos firmados.
MANAGER: Visto bueno gerencial (si aplica en el flujo).
Nota: Existe un permiso especial can_manage_users que permite a usuarios no-admin gestionar el módulo de usuarios.
📄 Licencia
Propiedad de Grupo ANC. Uso interno exclusivo.
Desarrollado con estándares de seguridad OWASP.
