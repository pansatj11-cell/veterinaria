<?php
// web_migrate.php
// Script para ejecutar la migración de la base de datos desde el navegador (especial para Railway)

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = getDB();
    echo "=== Iniciando migración de la base de datos en Railway ===\n\n";

    // 1. Agregar columna 'usuario' a la tabla veterinarios
    try {
        @$db->exec("ALTER TABLE veterinarios ADD COLUMN usuario TEXT");
        echo "✅ Columna 'usuario' añadida o ya existía.\n";
    } catch (PDOException $e) {
        echo "ℹ️ Aviso: La columna 'usuario' no se pudo añadir (quizás ya existe).\n";
    }

    // 2. Agregar columna 'password' a la tabla veterinarios
    try {
        @$db->exec("ALTER TABLE veterinarios ADD COLUMN password TEXT");
        echo "✅ Columna 'password' añadida o ya existía.\n";
    } catch (PDOException $e) {
        echo "ℹ️ Aviso: La columna 'password' no se pudo añadir (quizás ya existe).\n";
    }

    // 3. Crear índice único para el usuario
    try {
        @$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_vet_usuario ON veterinarios(usuario)");
        echo "✅ Índice único para 'usuario' configurado correctamente.\n";
    } catch (PDOException $e) {
        echo "ℹ️ Aviso: No se pudo crear el índice único.\n";
    }

    echo "\n¡MIGRACIÓN FINALIZADA!\n";
    echo "Ya puedes volver a intentar el registro en tu panel de administración.\n";
    echo "RECUERDA: Borra este archivo de GitHub por seguridad una vez que veas este mensaje.";

} catch (Exception $e) {
    echo "❌ ERROR CRÍTICO: " . $e->getMessage();
}
?>
