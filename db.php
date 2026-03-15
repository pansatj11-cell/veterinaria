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
        
        // Habilitar claves foráneas (importante en SQLite)
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
?>
