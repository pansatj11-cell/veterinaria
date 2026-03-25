<?php
// fix_full_db.php
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = getDB();
    echo "=== Reparación Integral de Base de Datos ===\n\n";

    $queries = [
        "Clientes" => "CREATE TABLE IF NOT EXISTS clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            telefono TEXT,
            telegram_chat_id INTEGER
        )",
        "Veterinarios" => "CREATE TABLE IF NOT EXISTS veterinarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            usuario TEXT,
            password TEXT
        )",
        "Mascotas" => "CREATE TABLE IF NOT EXISTS mascotas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            nombre TEXT NOT NULL,
            especie TEXT,
            raza TEXT,
            edad TEXT,
            vacunas TEXT,
            otros TEXT,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
        )",
        "Citas" => "CREATE TABLE IF NOT EXISTS citas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER,
            veterinario_id INTEGER,
            mascota_id INTEGER,
            fecha DATE NOT NULL,
            hora TEXT NOT NULL,
            estado TEXT DEFAULT 'pendiente',
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
            FOREIGN KEY (veterinario_id) REFERENCES veterinarios(id) ON DELETE CASCADE,
            FOREIGN KEY (mascota_id) REFERENCES mascotas(id) ON DELETE SET NULL
        )",
        "Historial" => "CREATE TABLE IF NOT EXISTS historial_clinico (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mascota_id INTEGER NOT NULL,
            veterinario_id INTEGER NOT NULL,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            diagnostico TEXT,
            tratamiento TEXT,
            costo REAL,
            FOREIGN KEY (mascota_id) REFERENCES mascotas(id) ON DELETE CASCADE,
            FOREIGN KEY (veterinario_id) REFERENCES veterinarios(id) ON DELETE CASCADE
        )",
        "Bot Estado" => "CREATE TABLE IF NOT EXISTS estado_conversacion (
            chat_id INTEGER PRIMARY KEY,
            step TEXT,
            data TEXT
        )"
    ];

    foreach ($queries as $name => $sql) {
        $db->exec($sql);
        echo "✅ Tabla '$name' verificada/creada.\n";
    }

    // MIGRACIÓN ESPECÍFICA: Agregar columnas si la tabla ya existía sin ellas
    echo "\nVerificando columnas adicionales...\n";
    
    $cols = $db->query("PRAGMA table_info(veterinarios)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');

    if (!in_array('usuario', $colNames)) {
        $db->exec("ALTER TABLE veterinarios ADD COLUMN usuario TEXT");
        echo "✅ Columna 'usuario' añadida a veterinarios.\n";
    }
    if (!in_array('password', $colNames)) {
        $db->exec("ALTER TABLE veterinarios ADD COLUMN password TEXT");
        echo "✅ Columna 'password' añadida a veterinarios.\n";
    }
    
    // Crear índice para login rápido
    try {
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_vet_usuario ON veterinarios(usuario)");
        echo "✅ Índice de usuario configurado.\n";
    } catch (Exception $e) {}

    echo "\n=== Proceso finalizado con éxito ===\n";
    echo "Ya puedes probar el bot y el registro de veterinarios nuevamente.";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage();
}
?>
