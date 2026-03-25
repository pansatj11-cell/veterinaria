<?php
// migrate_vets.php
require_once __DIR__ . '/db.php';

try {
    $db = getDB();
    echo "Iniciando migración de la tabla veterinarios...\n";

    // 1. Agregar columna 'usuario'
    try {
        $db->exec("ALTER TABLE veterinarios ADD COLUMN usuario TEXT");
        echo "Columna 'usuario' añadida.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column name') !== false) {
            echo "La columna 'usuario' ya existe.\n";
        } else {
            throw $e;
        }
    }

    // 2. Agregar columna 'password'
    try {
        $db->exec("ALTER TABLE veterinarios ADD COLUMN password TEXT");
        echo "Columna 'password' añadida.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column name') !== false) {
            echo "La columna 'password' ya existe.\n";
        } else {
            throw $e;
        }
    }

    // 3. Crear índice único para usuario para evitar duplicidades
    try {
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_vet_usuario ON veterinarios(usuario)");
        echo "Índice único para 'usuario' creado.\n";
    } catch (PDOException $e) {
        echo "No se pudo crear el índice único: " . $e->getMessage() . "\n";
    }

    echo "Migración completada con éxito.\n";

} catch (Exception $e) {
    echo "ERROR en la migración: " . $e->getMessage() . "\n";
}
