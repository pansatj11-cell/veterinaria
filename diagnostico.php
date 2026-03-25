<?php
// diagnostico.php
require_once __DIR__ . '/config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico de Servidor</h1>";
echo "PHP version: " . PHP_VERSION . "<br>";
echo "DB Path: " . DB_PATH . "<br>";
echo "Ruta actual (__DIR__): " . __DIR__ . "<br>";

if (file_exists(DB_PATH)) {
    echo "✅ Base de datos encontrada.<br>";
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Conexión a BD exitosa.<br>";
        
        // Prueba de escritura
        $db->exec("CREATE TABLE IF NOT EXISTS test_write (id INTEGER PRIMARY KEY, txt TEXT)");
        $db->exec("INSERT INTO test_write (txt) VALUES ('test " . date('H:i:s') . "')");
        echo "✅ Prueba de ESCRITURA en BD exitosa.<br>";
    } catch (Exception $e) {
        echo "❌ Error de BD/Escritura: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Base de datos NO encontrada.<br>";
}

echo "<h2>Prueba de Bot</h2>";
if (!extension_loaded('curl')) {
    echo "❌ LIBRERÍA CURL NO ESTÁ INSTALADA. El bot no podrá enviar mensajes.<br>";
} else {
    echo "✅ Librería CURL detectada.<br>";
}

$url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getMe";
$res = @file_get_contents($url);
if ($res) {
    echo "✅ Conexión con Telegram vía file_get_contents.<br>";
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
if ($res) {
    echo "✅ Conexión con Telegram vía CURL.<br>";
} else {
    echo "❌ Error CURL: " . curl_error($ch) . "<br>";
}
curl_close($ch);

echo "<h2>Prueba de Sesión</h2>";
session_start();
$_SESSION['test_diag'] = "Sesion activa " . date('H:i:s');
echo "ID de sesión: " . session_id() . "<br>";
echo "Variable de prueba: " . $_SESSION['test_diag'] . "<br>";
?>
