<?php
// bot_webhook.php
// Versión optimizada del bot para hosting gratuito (vía Webhooks)
// No necesita ejecutarse en consola, se dispara cuando Telegram envía un mensaje.

require_once __DIR__ . '/db.php';

$token = TELEGRAM_BOT_TOKEN;
$apiUrl = "https://api.telegram.org/bot$token";

// Recibir datos de Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

function sendMessage($chatId, $text, $replyMarkup = null) {
    global $apiUrl;
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }

    $ch = curl_init("$apiUrl/sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
}

function sendContact($chatId, $text) {
    $keyboard = [
        'keyboard' => [[['text' => '📱 Compartir mi número de teléfono', 'request_contact' => true]]],
        'one_time_keyboard' => true,
        'resize_keyboard' => true
    ];
    sendMessage($chatId, $text, $keyboard);
}

function removeKeyboard($chatId, $text) {
    sendMessage($chatId, $text, ['remove_keyboard' => true]);
}

function buscarClientePorTelefono($telefono) {
    $db = getDB();
    $soloDigitos = preg_replace('/\D/', '', $telefono);
    $ultimos10 = substr($soloDigitos, -10);

    $stmt = $db->query("SELECT * FROM clientes");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($clientes as $c) {
        $telCliente = preg_replace('/\D/', '', $c['telefono']);
        if (substr($telCliente, -10) === $ultimos10 && strlen($ultimos10) >= 7) {
            return $c;
        }
    }
    return false;
}

function buscarClientePorChatId($chatId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM clientes WHERE telegram_chat_id = :chat_id");
    $stmt->bindParam(':chat_id', $chatId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function vincularTelegram($clienteId, $chatId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE clientes SET telegram_chat_id = :chat_id WHERE id = :id");
    $stmt->bindParam(':chat_id', $chatId);
    $stmt->bindParam(':id', $clienteId);
    return $stmt->execute();
}

function obtenerVeterinarios() {
    $db = getDB();
    return $db->query("SELECT * FROM veterinarios ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerHorariosDisponibles($fecha, $vetId) {
    $db = getDB();
    $todos = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'];
    $stmt = $db->prepare("SELECT hora FROM citas WHERE fecha = :fecha AND veterinario_id = :vet_id");
    $stmt->execute([':fecha' => $fecha, ':vet_id' => $vetId]);
    $ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_diff($todos, $ocupados);
}

function mostrarMenuPrincipal($chatId, $nombreCliente) {
    $keyboard = ['inline_keyboard' => [[['text' => '📅 Agendar Cita', 'callback_data' => 'agendar']], [['text' => '📋 Ver Mis Citas', 'callback_data' => 'ver_citas']]]];
    sendMessage($chatId, "¡Hola <b>$nombreCliente</b>! 🐾\n¿Qué deseas hacer?", $keyboard);
}

// Lógica de procesamiento según el tipo de update
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $chatId = $cb['message']['chat']['id'];
    $data = $cb['data'];
    $cliente = buscarClientePorChatId($chatId);
    
    if (!$cliente) {
        sendMessage($chatId, "⚠️ No vinculado. Pulsa /start.");
        exit;
    }

    if ($data === 'agendar') {
        $vets = obtenerVeterinarios();
        $btns = array_map(fn($v) => [['text' => '🩺 '.$v['nombre'], 'callback_data' => 'vet_'.$v['id']]], $vets);
        sendMessage($chatId, "Selecciona un <b>veterinario</b>:", ['inline_keyboard' => $btns]);
    } elseif (str_starts_with($data, 'vet_')) {
        $vid = substr($data, 4);
        $btns = [];
        for ($i=1; $i<=7; $i++) {
            $f = date('Y-m-d', strtotime("+$i days"));
            if (date('N', strtotime($f)) <= 5) {
                $btns[] = [['text' => date('D d/m', strtotime($f)), 'callback_data' => "f_{$vid}_{$f}"]];
            }
        }
        sendMessage($chatId, "Selecciona una <b>fecha</b>:", ['inline_keyboard' => $btns]);
    } elseif (str_starts_with($data, 'f_')) {
        [, $vid, $fecha] = explode('_', $data);
        $hdis = obtenerHorariosDisponibles($fecha, $vid);
        $btns = []; $row = [];
        foreach ($hdis as $h) {
            $row[] = ['text' => $h, 'callback_data' => "h_{$vid}_{$fecha}_{$h}"];
            if (count($row) == 3) { $btns[] = $row; $row = []; }
        }
        if ($row) $btns[] = $row;
        sendMessage($chatId, "Elegir hora para $fecha:", ['inline_keyboard' => $btns]);
    } elseif (str_starts_with($data, 'h_')) {
        [, $vid, $f, $h] = explode('_', $data);
        $db = getDB();
        $s = $db->prepare("INSERT INTO citas (cliente_id, veterinario_id, fecha, hora) VALUES (?, ?, ?, ?)");
        if ($s->execute([$cliente['id'], $vid, $f, $h])) {
            sendMessage($chatId, "✅ <b>Cita agendada!</b>\n📅 $f a las $h. 🐾");
        }
    } elseif ($data === 'ver_citas') {
        $db = getDB(); $hoy = date('Y-m-d');
        $s = $db->prepare("SELECT c.fecha, c.hora, v.nombre as vname FROM citas c JOIN veterinarios v ON c.veterinario_id = v.id WHERE c.cliente_id = ? AND c.fecha >= ? ORDER BY c.fecha, c.hora");
        $s->execute([$cliente['id'], $hoy]);
        $cs = $s->fetchAll();
        $txt = "📋 <b>Tus citas:</b>\n\n";
        foreach ($cs as $c) $txt .= "📅 {$c['fecha']} {$c['hora']} (Vet: {$c['vname']})\n";
        sendMessage($chatId, empty($cs) ? "No tienes citas." : $txt);
    }
} elseif (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = $msg['chat']['id'];
    if (isset($msg['contact'])) {
        $num = preg_replace('/\D/', '', $msg['contact']['phone_number']);
        $cl = buscarClientePorTelefono($num);
        if ($cl) {
            vincularTelegram($cl['id'], $chatId);
            sendMessage($chatId, "✅ Vinculado como <b>{$cl['nombre']}</b>. Escribe /start.");
        } else sendMessage($chatId, "❌ Número no registrado.");
    } elseif (($msg['text'] ?? '') === '/start') {
        $cl = buscarClientePorChatId($chatId);
        if ($cl) mostrarMenuPrincipal($chatId, $cl['nombre']);
        else sendContact($chatId, "🐾 ¡Hola! Comparte tu número para comenzar:");
    }
}
?>
