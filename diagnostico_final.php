<?php
// diagnostico_final.php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO INTEGRAL DEL SISTEMA ===\n\n";

// 1. Verificar PHP
echo "Versión PHP: " . PHP_VERSION . "\n";

// 2. Verificar Base de Datos
try {
    $db = getDB();
    echo "✅ Conexión a Base de Datos: EXITOSA\n";
    
    $tablas = ['clientes', 'veterinarios', 'mascotas', 'citas', 'historial_clinico', 'estado_conversacion'];
    foreach ($tablas as $t) {
        $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'")->fetch();
        if ($check) {
            echo "✅ Tabla '$t': EXISTE\n";
            // Verificar columnas críticas
            if ($t === 'mascotas') {
                $cols = $db->query("PRAGMA table_info(mascotas)")->fetchAll();
                $especie = false;
                foreach ($cols as $c) if ($c['name'] === 'especie') $especie = true;
                echo ($especie ? "   - Columna 'especie': OK\n" : "   ❌ Columna 'especie': NO EXISTE\n");
            }
            if ($t === 'citas') {
                $cols = $db->query("PRAGMA table_info(citas)")->fetchAll();
                $mid = false;
                foreach ($cols as $c) if ($c['name'] === 'mascota_id') $mid = true;
                echo ($mid ? "   - Columna 'mascota_id': OK\n" : "   ❌ Columna 'mascota_id': NO EXISTE\n");
            }
        } else {
            echo "❌ Tabla '$t': NO EXISTE\n";
        }
    }
    
    // 3. Verificar Datos
    $nCl = $db->query("SELECT count(*) FROM clientes")->fetchColumn();
    $nM = $db->query("SELECT count(*) FROM mascotas")->fetchColumn();
    echo "\nRegistros:\n- Clientes: $nCl\n- Mascotas: $nM\n";
    
} catch (Exception $e) {
    echo "❌ Error de BD: " . $e->getMessage() . "\n";
}

// 4. Verificar Token
if (defined('TELEGRAM_BOT_TOKEN') && strlen(TELEGRAM_BOT_TOKEN) > 10) {
    echo "\n✅ Token de Bot: CARGADO (" . substr(TELEGRAM_BOT_TOKEN, 0, 5) . "...)\n";
} else {
    echo "\n❌ Token de Bot: NO ENCONTRADO O INVÁLIDO\n";
}

// 5. Verificar Logs Recientes
if (file_exists('error_log.txt')) {
    echo "\n--- ÚLTIMOS ERRORES (error_log.txt) ---\n";
    echo file_get_contents('error_log.txt');
} else {
    echo "\n✅ No hay archivo de error_log.txt reciente.\n";
}
?>
