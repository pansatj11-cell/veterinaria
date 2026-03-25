<?php
// debug_bot.php
// Ejecuta este archivo en tu navegador para diagnosticar el estado del bot en el servidor.

require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DEL SISTEMA VETERINARIO ===\n\n";

// 1. Verificar PHP
echo "1. Versión de PHP: " . PHP_VERSION . "\n";
echo "   S.O. del servidor: " . PHP_OS . "\n\n";

// 2. Verificar Base de Datos
echo "2. Probando conexión a la base de datos...\n";
try {
    $db = getDB();
    echo "   ✅ Conexión exitosa.\n";
    
    $tables = ['clientes', 'veterinarios', 'citas', 'mascotas', 'historial_clinico', 'estado_conversacion'];
    foreach ($tables as $t) {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'");
        if ($stmt->fetch()) {
            echo "   ✅ Tabla '$t' existe.\n";
        } else {
            echo "   ❌ Tabla '$t' NO EXISTE. ¡Ejecuta setup.php!\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ ERROR de base de datos: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Verificar Token de Telegram
echo "3. Verificando Token de Telegram con la API...\n";
$token = TELEGRAM_BOT_TOKEN;
if (!$token) {
    echo "   ❌ TOKEN no definido en config.php\n";
} else {
    $url = "https://api.telegram.org/bot$token/getMe";
    $res = @file_get_contents($url);
    if ($res) {
        $data = json_decode($res, true);
        if ($data['ok']) {
            echo "   ✅ Token válido. Bot: @" . $data['result']['username'] . "\n";
        } else {
            echo "   ❌ Token inválido o API de Telegram inaccesible.\n";
        }
    } else {
        echo "   ❌ No se pudo conectar a la API de Telegram. (¿curl o allow_url_fopen desactivado?)\n";
    }
}
echo "\n";

// 4. Verificar Permisos de Escritura
echo "4. Verificando permisos de escritura para LOGS...\n";
$testFile = 'test_write.txt';
if (@file_put_contents($testFile, "test " . date('Y-m-d H:i:s'))) {
    echo "   ✅ Permisos de escritura correctos.\n";
    unlink($testFile);
} else {
    echo "   ❌ No se puede escribir en el directorio. El bot no podrá registrar logs ni crear la base de datos.\n";
}

// 5. Verificar Citas Recientes
echo "5. Verificando las últimas 5 citas...\n";
try {
    $sql = "SELECT c.id, c.fecha, c.hora, c.mascota_id, m.nombre as pet_name 
            FROM citas c 
            LEFT JOIN mascotas m ON c.mascota_id = m.id 
            ORDER BY c.id DESC LIMIT 5";
    $stmt = $db->query($sql);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($citas as $c) {
        $mid = $c['mascota_id'] ?? 'NULL';
        $pname = $c['pet_name'] ?? 'N/A';
        echo "   - Cita ID: {$c['id']} | Fecha: {$c['fecha']} | Mascota ID: $mid | Nombre Pet: $pname\n";
    }
} catch (Exception $e) {
    echo "   ❌ ERROR al leer citas: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
?>
