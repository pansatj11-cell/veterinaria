<?php
// bot_notificaciones.php
// Este script está diseñado para ejecutarse diariamente (ej. vía Cron o manualmente)
// Busca citas para el día de mañana y envía recordatorios al Telegram de los clientes.

require_once __DIR__ . '/db.php';

function enviarMensajeTelegram($chat_id, $mensaje) {
    if (empty($chat_id) || empty(TELEGRAM_BOT_TOKEN)) {
        return false;
    }

    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $mensaje,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ]
    ];

    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return false;
    }
    return true;
}

$db = getDB();

$is_test = isset($_GET['test']) && $_GET['test'] == '1';
$manana = date('Y-m-d', strtotime('+1 day'));

if ($is_test) {
    echo "=== MODO DE PRUEBA ACTIVADO ===<br>\nBuscando todas las citas pendientes de mañana ($manana) para enviar notificaciones de prueba...<br><br>\n";
    $sql = "
        SELECT c.id, c.hora, c.estado, 
               cli.nombre AS cliente_nombre, cli.telegram_chat_id,
               vet.nombre AS veterinario_nombre,
               m.nombre AS mascota_nombre
        FROM citas c
        JOIN clientes cli ON c.cliente_id = cli.id
        JOIN veterinarios vet ON c.veterinario_id = vet.id
        LEFT JOIN mascotas m ON c.mascota_id = m.id
        WHERE c.fecha = :fecha AND c.estado = 'pendiente'
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':fecha', $manana);
} else {
    // Modo normal cron (busca las de mañana)
    date_default_timezone_set('America/Bogota');
    
    echo "Buscando citas programadas para el día de mañana ($manana)...\n";

    $sql = "
        SELECT c.id, c.hora, c.estado, 
               cli.nombre AS cliente_nombre, cli.telegram_chat_id,
               vet.nombre AS veterinario_nombre,
               m.nombre AS mascota_nombre
        FROM citas c
        JOIN clientes cli ON c.cliente_id = cli.id
        JOIN veterinarios vet ON c.veterinario_id = vet.id
        LEFT JOIN mascotas m ON c.mascota_id = m.id
        WHERE c.fecha = :fecha AND c.estado = 'pendiente'
    ";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':fecha', $manana);
}

$stmt->execute();
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$enviados = 0;
$fallidos = 0;

foreach ($citas as $cita) {
    if (!empty($cita['telegram_chat_id'])) {
        $mensaje = "🐾 <b>Recordatorio de Cita Veterinaria</b> 🐾\n\n";
        $mensaje .= "Hola <b>" . htmlspecialchars($cita['cliente_nombre']) . "</b>,\n";
        if ($is_test) {
            $mensaje .= "🚨 <i>MENSAJE DE PRUEBA DEL SISTEMA</i> 🚨\n";
        }
        $mensaje .= "Te recordamos que tienes una cita para <b>" . htmlspecialchars($cita['mascota_nombre'] ?? 'tu mascota') . "</b> el día de **mañana**.\n\n";
        $mensaje .= "🩺 <b>Veterinario:</b> " . htmlspecialchars($cita['veterinario_nombre']) . "\n";
        $mensaje .= "⏰ <b>Hora:</b> " . $cita['hora'] . "\n\n";
        $mensaje .= "¡Por favor, sé puntual!\nSi no puedes asistir, comunícate con nosotros.\nGracias.";

        $exito = enviarMensajeTelegram($cita['telegram_chat_id'], $mensaje);
        
        if ($exito) {
            echo "✅ Recordatorio enviado a " . $cita['cliente_nombre'] . " (Chat ID: " . $cita['telegram_chat_id'] . ")\n";
            $enviados++;
        } else {
            echo "❌ Falló el envío a " . $cita['cliente_nombre'] . " (Chat ID: " . $cita['telegram_chat_id'] . ")\n";
            $fallidos++;
        }
    } else {
        echo "⚠️ " . $cita['cliente_nombre'] . " no tiene configurado un ID de Telegram.\n";
        $fallidos++;
    }
}

echo "\nProceso terminado.\n";
echo "Enviados: $enviados\n";
echo "Fallidos/Sin Telegram: $fallidos\n";
?>
