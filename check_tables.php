<?php
try {
    $db = new PDO('sqlite:bdatos.sqlite');
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        echo $table . PHP_EOL;
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
