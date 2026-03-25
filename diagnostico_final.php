<?php
// diagnostico_final.php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO INTEGRAL DEL SISTEMA ===\n\n";

// 1. Verificar PHP y Entorno
echo "Versión PHP: " . PHP_VERSION . "\n";
echo "URL Detectada: " . ($_SERVER['HTTP_HOST'] ?? 'Localhost') . "\n";
echo "Ruta actual: " . __DIR__ . "\n\n";

// 2. Verificar Base de Datos
try {
    $db = getDB();
    echo "✅ Conexión a Base de Datos: EXITOSA\n";
    
    $tablas = [
        'clientes' => ['telegram_chat_id'],
        'veterinarios' => [],
        'mascotas' => ['especie'],
        'citas' => ['mascota_id'],
        'historial_clinico' => [],
        'estado_conversacion' => []
    ];
    
    foreach ($tablas as $t => $colsToCheck) {
        $check = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'")->fetch();
        if ($check) {
            echo "✅ Tabla '$t': EXISTE\n";
            $cols = $db->query("PRAGMA table_info($t)")->fetchAll();
            $existentes = array_column($cols, 'name');
            foreach ($colsToCheck as $c) {
                if (in_array($c, $existentes)) {
                    echo "   - Columna '$c': OK\n";
                } else {
                    echo "   ❌ Columna '$c': NO ENCONTRADA\n";
                }
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
