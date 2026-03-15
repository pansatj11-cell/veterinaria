<?php
// diagnostico.php
require_once __DIR__ . '/config.php';
echo "<h1>Diagnóstico de Servidor</h1>";
echo "PHP version: " . PHP_VERSION . "<br>";
echo "DB Path: " . DB_PATH . "<br>";
if (file_exists(DB_PATH)) {
    echo "✅ Base de datos encontrada.<br>";
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        echo "✅ Conexión a BD exitosa.<br>";
    } catch (Exception $e) {
        echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Base de datos NO encontrada en esa ruta.<br>";
}

echo "<h2>Prueba de Bot</h2>";
$url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getMe";
// Intentamos con file_get_contents
$res = @file_get_contents($url);
if ($res) {
    echo "✅ Conexión con Telegram Exitosa vía file_get_contents.<br>";
} else {
    // Intentamos con CURL por si acaso
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    if ($res) {
        echo "✅ Conexión con Telegram Exitosa vía CURL.<br>";
    } else {
        echo "❌ El servidor BLOQUEA la salida a Telegram. Por eso el bot no responde. Error CURL: " . curl_error($ch) . "<br>";
    }
    curl_close($ch);
}

echo "<h2>Prueba de Sesión</h2>";
session_start();
$_SESSION['test_diag'] = "Sesion activa " . date('H:i:s');
echo "ID de sesión: " . session_id() . "<br>";
echo "Variable de prueba guardada: " . $_SESSION['test_diag'] . "<br>";
echo "<br><a href='diagnostico.php'>Recargar para probar permanencia de sesión</a>";
?>
