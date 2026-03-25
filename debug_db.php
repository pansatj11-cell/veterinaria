<?php
// debug_db.php
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    echo "=== Sistema de Diagnóstico de Base de Datos ===\n\n";
    
    // 1. Verificar Constantes
    echo "DB_PATH configurada como: " . DB_PATH . "\n";
    if (file_exists(DB_PATH)) {
        echo "✅ El archivo de base de datos EXISTE.\n";
        echo "Tamaño del archivo: " . filesize(DB_PATH) . " bytes\n";
    } else {
        echo "❌ El archivo de base de datos NO EXISTE en esa ruta.\n";
    }

    // 2. Intentar Conexión
    $db = getDB();
    echo "✅ Conexión PDO exitosa.\n\n";

    // 3. Revisar Tablas
    echo "=== Listado de Tablas ===\n";
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    
    $required = ['clientes', 'veterinarios', 'mascotas', 'citas', 'estado_conversacion', 'historial_clinico'];
    foreach ($required as $table) {
        if (in_array($table, $tables)) {
            echo "✅ Tabla '$table': EXISTE\n";
            // Contar registros
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "   -> Registros: $count\n";
            
            // Ver columnas de veterinarios si es el caso
            if ($table === 'veterinarios') {
                $cols = $db->query("PRAGMA table_info(veterinarios)")->fetchAll(PDO::FETCH_ASSOC);
                $colNames = array_column($cols, 'name');
                echo "   -> Columnas: " . implode(', ', $colNames) . "\n";
            }
        } else {
            echo "❌ Tabla '$table': NO ENCONTRADA\n";
        }
    }

    echo "\n=== Diagnóstico Finalizado ===\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage();
}
?>
