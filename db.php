<?php
// db.php
// Conexión a la base de datos SQLite

require_once __DIR__ . '/config.php';

function getDBStatus() {
    $dbExists = file_exists(DB_PATH);
    return $dbExists;
}

function getDB() {
    try {
        // Conexión PDO para SQLite
        $pdo = new PDO('sqlite:' . DB_PATH);
        
        // Habilitar excepciones en caso de error
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Optimización de concurrencia para SQLite
        $pdo->exec("PRAGMA journal_mode=WAL;");
        $pdo->exec("PRAGMA busy_timeout=5000;");
        $pdo->exec('PRAGMA foreign_keys = ON;');

        // SELF-HEALING: Recrear tablas si no existen (Especial para Railway)
        $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='clientes'")->fetch();
        if (!$tableExists) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                telefono TEXT,
                telegram_chat_id INTEGER
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS veterinarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                usuario TEXT,
                password TEXT
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS mascotas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cliente_id INTEGER NOT NULL,
                nombre TEXT NOT NULL,
                especie TEXT,
                raza TEXT,
                edad TEXT,
                vacunas TEXT,
                FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS citas (
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
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS historial_clinico (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                mascota_id INTEGER NOT NULL,
                veterinario_id INTEGER NOT NULL,
                fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
                diagnostico TEXT,
                tratamiento TEXT,
                costo REAL,
                FOREIGN KEY (mascota_id) REFERENCES mascotas(id) ON DELETE CASCADE,
                FOREIGN KEY (veterinario_id) REFERENCES veterinarios(id) ON DELETE CASCADE
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS estado_conversacion (
                chat_id INTEGER PRIMARY KEY,
                step TEXT,
                data TEXT
            )");
        }

        // Asegurar que veterinarios tenga las columnas nuevas (Migración silenciosa)
        $colsVet = $pdo->query("PRAGMA table_info(veterinarios)")->fetchAll(PDO::FETCH_ASSOC);
        $colNamesVet = array_column($colsVet, 'name');
        if (!in_array('usuario', $colNamesVet)) {
            $pdo->exec("ALTER TABLE veterinarios ADD COLUMN usuario TEXT");
        }
        if (!in_array('password', $colNamesVet)) {
            $pdo->exec("ALTER TABLE veterinarios ADD COLUMN password TEXT");
        }

        // Asegurar que citas tenga la columna mascota_id
        $colsCitas = $pdo->query("PRAGMA table_info(citas)")->fetchAll(PDO::FETCH_ASSOC);
        $colNamesCitas = array_column($colsCitas, 'name');
        if (!in_array('mascota_id', $colNamesCitas)) {
            $pdo->exec("ALTER TABLE citas ADD COLUMN mascota_id INTEGER");
        }
        
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
?>
