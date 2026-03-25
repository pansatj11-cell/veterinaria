<?php
// setup.php
// Script para inicializar la estructura de la base de datos

require_once __DIR__ . '/db.php';

echo "Inicializando la base de datos...\n";

try {
    $db = getDB();

    $sql = "
    -- Tabla para Mascotas
    CREATE TABLE IF NOT EXISTS mascotas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_id INTEGER NOT NULL,
        nombre TEXT NOT NULL,
        especie TEXT,
        raza TEXT,
        edad TEXT,
        vacunas TEXT,
        otros TEXT,
        FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    );

    -- Tabla para Historial Clínico
    CREATE TABLE IF NOT EXISTS historial_clinico (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        mascota_id INTEGER NOT NULL,
        veterinario_id INTEGER NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        diagnostico TEXT,
        tratamiento TEXT,
        costo REAL,
        FOREIGN KEY (mascota_id) REFERENCES mascotas(id) ON DELETE CASCADE,
        FOREIGN KEY (veterinario_id) REFERENCES veterinarios(id) ON DELETE CASCADE
    );

    -- Tabla para Citas
    -- Se relacionan con cliente, veterinario y opcionalmente mascota.
    CREATE TABLE IF NOT EXISTS citas (
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
    );

    -- Tabla para Estado de Conversación (Telegram)
    CREATE TABLE IF NOT EXISTS estado_conversacion (
        chat_id TEXT PRIMARY KEY,
        step TEXT NOT NULL,
        data TEXT -- JSON con datos temporales
    );

    ";

    $db->exec($sql);
    
    // Migración: Asegurar que 'citas' tenga 'mascota_id'
    $res = $db->query("PRAGMA table_info(citas)")->fetchAll(PDO::FETCH_ASSOC);
    $hasMascotaId = false;
    foreach ($res as $col) {
        if ($col['name'] === 'mascota_id') {
            $hasMascotaId = true;
            break;
        }
    }
    
    if (!$hasMascotaId) {
        $db->exec("ALTER TABLE citas ADD COLUMN mascota_id INTEGER REFERENCES mascotas(id) ON DELETE SET NULL");
        echo "Columna 'mascota_id' añadida a 'citas'.\n";
    }

    // Migración: Asegurar que 'mascotas' tenga 'especie'
    $resM = $db->query("PRAGMA table_info(mascotas)")->fetchAll(PDO::FETCH_ASSOC);
    $hasEspecie = false;
    foreach ($resM as $col) {
        if ($col['name'] === 'especie') {
            $hasEspecie = true;
            break;
        }
    }
    if (!$hasEspecie) {
        $db->exec("ALTER TABLE mascotas ADD COLUMN especie TEXT");
        echo "Columna 'especie' añadida a 'mascotas'.\n";
    }

    // Migración: Agregar mascota_id a citas si no existe
    try {
        $db->query("SELECT mascota_id FROM citas LIMIT 1");
    } catch (Exception $e) {
        $db->exec("ALTER TABLE citas ADD COLUMN mascota_id INTEGER");
        echo "Columna 'mascota_id' añadida a 'citas'.\n";
    }

    // Migración: Agregar telegram_chat_id a clientes si no existe
    try {
        $db->query("SELECT telegram_chat_id FROM clientes LIMIT 1");
    } catch (Exception $e) {
        $db->exec("ALTER TABLE clientes ADD COLUMN telegram_chat_id INTEGER");
        echo "Columna 'telegram_chat_id' añadida a 'clientes'.\n";
    }

    // Asegurar tabla de estados
    $db->exec("CREATE TABLE IF NOT EXISTS estado_conversacion (
        chat_id INTEGER PRIMARY KEY,
        step TEXT,
        data TEXT
    )");

    echo "¡Base de datos lista y actualizada!\n";
    
} catch (PDOException $e) {
    echo "Hubo un error al procesar la base de datos: " . $e->getMessage() . "\n";
}
?>
