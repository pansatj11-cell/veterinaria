<?php
// setup.php
// Script para inicializar la estructura de la base de datos

require_once __DIR__ . '/db.php';

echo "Inicializando la base de datos...\n";

try {
    $db = getDB();

    $sql = "
    -- Tabla para Veterinarios
    CREATE TABLE IF NOT EXISTS veterinarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL
    );

    -- Tabla para Clientes
    CREATE TABLE IF NOT EXISTS clientes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        telefono TEXT,
        telegram_chat_id TEXT
    );

    -- Tabla para Citas
    -- Se relacionan con cliente y veterinario.
    CREATE TABLE IF NOT EXISTS citas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER,
        veterinario_id INTEGER,
        fecha DATE NOT NULL,
        hora TEXT NOT NULL,
        estado TEXT DEFAULT 'pendiente',
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
        FOREIGN KEY (veterinario_id) REFERENCES veterinarios(id) ON DELETE CASCADE
    );
    ";

    $db->exec($sql);
    echo "Tablas creadas con éxito.\n";
    
} catch (PDOException $e) {
    echo "Hubo un error al crear las tablas: " . $e->getMessage() . "\n";
}
?>
