<?php
// cron/test_nas.php
// Ejecutar con: docker exec -it gic_web_1 php /var/www/html/cron/test_nas.php

echo "\n--- DIAGNÓSTICO PROFUNDO DE CONEXIÓN NAS ---\n";

// 1. DATOS DUROS (HARDCODED)
// Usamos comillas simples para que PHP no interprete el $ como variable
$host = '//172.16.30.2/bkp_desarrollo';
$user = 'ANCCRQNP01\BKP_DE';
$pass = 'DhQiwXbNlYHkZr27'; 

// Verificación de integridad de la contraseña en PHP
echo "Password en memoria PHP: " . $pass . "\n";
echo "Longitud: " . strlen($pass) . " (Debe ser 16)\n\n";

// =================================================================
// ESTRATEGIA 1: CONEXIÓN ESTÁNDAR
// =================================================================
echo ">>> INTENTO 1: Conexión Estándar (-U user%pass)\n";

// escapeshellarg envuelve en comillas simples '...' para Bash. 
// Esto protege el $ del shell.
$auth = "-U " . escapeshellarg($user . "%" . $pass);
$cmd = "smbclient " . escapeshellarg($host) . " $auth -c 'ls'";

echo "Comando a ejecutar: $cmd\n";
system($cmd, $ret);

if ($ret === 0) exit("\n✅ ¡INTENTO 1 EXITOSO!\n");


// =================================================================
// ESTRATEGIA 2: FORZAR WORKGROUP
// =================================================================
echo "\n>>> INTENTO 2: Agregando Workgroup (-W WORKGROUP)\n";
// A veces el NAS rechaza conexiones sin dominio explícito
$cmd = "smbclient " . escapeshellarg($host) . " $auth -W WORKGROUP -c 'ls'";
system($cmd, $ret);

if ($ret === 0) exit("\n✅ ¡INTENTO 2 EXITOSO! (Usa -W WORKGROUP)\n");


// =================================================================
// ESTRATEGIA 3: PROTOCOLO ANTIGUO (NT1)
// =================================================================
echo "\n>>> INTENTO 3: Protocolo Legacy (--option='client min protocol=NT1')\n";
// Algunos NAS viejos requieren esto.
$cmd = "smbclient " . escapeshellarg($host) . " $auth --option='client min protocol=NT1' -c 'ls'";
system($cmd, $ret);

if ($ret === 0) exit("\n✅ ¡INTENTO 3 EXITOSO! (Protocolo NT1)\n");


// =================================================================
// ESTRATEGIA 4: PROTOCOLO SMB3 (MODERNO)
// =================================================================
echo "\n>>> INTENTO 4: Protocolo Moderno (--option='client max protocol=SMB3')\n";
$cmd = "smbclient " . escapeshellarg($host) . " $auth --option='client max protocol=SMB3' -c 'ls'";
system($cmd, $ret);

if ($ret === 0) exit("\n✅ ¡INTENTO 4 EXITOSO! (Protocolo SMB3)\n");


echo "\n❌ TODOS LOS INTENTOS FALLARON.\n";
echo "Posibles causas restantes:\n";
echo "1. El usuario '$user' está bloqueado en el NAS.\n";
echo "2. La IP del contenedor Docker (172.x.x.x) está bloqueada en el firewall del NAS.\n";
echo "3. El NAS requiere un dominio específico (ej: -W MI_EMPRESA).\n";
?>