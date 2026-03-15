<?php
require_once __DIR__ . '/db.php';
$db = getDB();
$stmt = $db->query('SELECT id, nombre, telefono, telegram_chat_id FROM clientes');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['id'] . " | " . $r['nombre'] . " | [" . $r['telefono'] . "] | tg:" . $r['telegram_chat_id'] . "\n";
}
?>
