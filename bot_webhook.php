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

// REPORTAR ERRORES (Solo para depuración)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sistema de LOGS para depuración
file_put_contents('log_bot.txt', "[" . date('Y-m-d H:i:s') . "] Payload recibido: " . $content . "\n", FILE_APPEND);

if (!$update) {
    file_put_contents('log_bot.txt', "[" . date('Y-m-d H:i:s') . "] Error: No se recibió un JSON válido o petición vacía.\n", FILE_APPEND);
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

function answerCallbackQuery($callbackQueryId) {
    global $apiUrl;
    $data = ['callback_query_id' => $callbackQueryId];
    $ch = curl_init("$apiUrl/answerCallbackQuery");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
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

function setStep($chatId, $step, $data = []) {
    $db = getDB();
    $dataJson = json_encode($data);
    $stmt = $db->prepare("INSERT INTO estado_conversacion (chat_id, step, data) VALUES (:chat_id, :step, :data) ON CONFLICT(chat_id) DO UPDATE SET step = :step, data = :data");
    $stmt->execute([':chat_id' => $chatId, ':step' => $step, ':data' => $dataJson]);
}

function getStep($chatId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM estado_conversacion WHERE chat_id = :chat_id");
    $stmt->execute([':chat_id' => $chatId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $res['data'] = json_decode($res['data'], true);
        return $res;
    }
    return null;
}

function clearStep($chatId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM estado_conversacion WHERE chat_id = :chat_id");
    $stmt->execute([':chat_id' => $chatId]);
}

function obtenerMascotasPorCliente($clienteId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM mascotas WHERE cliente_id = :id ORDER BY nombre ASC");
    $stmt->execute([':id' => $clienteId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mostrarMenuPrincipal($chatId, $nombreCliente) {
    $keyboard = ['inline_keyboard' => [[['text' => '📅 Agendar Cita', 'callback_data' => 'agendar']], [['text' => '📋 Ver Mis Citas', 'callback_data' => 'ver_citas']]]];
    sendMessage($chatId, "¡Hola <b>$nombreCliente</b>! 🐾\n¿Qué deseas hacer?", $keyboard);
}

try {
    $chatId = null;
    if (isset($update['callback_query'])) {
        $chatId = $update['callback_query']['message']['chat']['id'];
    } elseif (isset($update['message'])) {
        $chatId = $update['message']['chat']['id'];
    }

    if (!$chatId) exit;

    $cliente = buscarClientePorChatId($chatId);
    $estado = getStep($chatId);

    if (isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        answerCallbackQuery($cb['id']);
        $data = $cb['data'];
        
        if (!$cliente && !in_array($data, ['registrar_nueva', 'check_contact'])) {
            sendMessage($chatId, "⚠️ No vinculado. Pulsa /start.");
            exit;
        }

        if ($data === 'agendar') {
            $pets = obtenerMascotasPorCliente($cliente['id']);
            if (!empty($pets)) {
                $btns = array_map(function($p) {
                    return [['text' => '🐾 ' . $p['nombre'], 'callback_data' => "selpet_start_{$p['id']}"]];
                }, $pets);
                $btns[] = [['text' => '➕ Agregar nueva mascota', 'callback_data' => "newpet_start"]];
                sendMessage($chatId, "¿Para cuál de tus <b>mascotas</b> es la cita?", ['inline_keyboard' => $btns]);
            } else {
                setStep($chatId, 'preg_nombre');
                sendMessage($chatId, "¡Entendido! Vamos a registrar a tu mascota. 😊\n\n¿Cuál es su <b>nombre</b>?");
            }
        } elseif (substr($data, 0, 13) === 'selpet_start_') {
            $pid = substr($data, 13);
            setStep($chatId, 'sel_vet', ['mid' => $pid]);
            $vets = obtenerVeterinarios();
            $btns = array_map(function($v) {
                return [['text' => '🩺 '.$v['nombre'], 'callback_data' => 'vet_'.$v['id']]];
            }, $vets);
            sendMessage($chatId, "Selecciona un <b>veterinario</b>:", ['inline_keyboard' => $btns]);
        } elseif ($data === 'newpet_start') {
            setStep($chatId, 'preg_nombre');
            sendMessage($chatId, "¿Cuál es el <b>nombre</b> de la nueva mascota? 🐾");
        } elseif (substr($data, 0, 4) === 'vet_') {
            if (!$estado) { sendMessage($chatId, "⚠️ Sesión expirada. Pulsa /start."); exit; }
            $vid = substr($data, 4);
            $sdata = $estado['data'] ?? [];
            $sdata['vid'] = $vid;
            setStep($chatId, 'sel_fecha', $sdata);
            $btns = [];
            for ($i=1; $i<=7; $i++) {
                $f = date('Y-m-d', strtotime("+$i days"));
                if (date('N', strtotime($f)) <= 5) {
                    $btns[] = [['text' => date('D d/m', strtotime($f)), 'callback_data' => "f_{$f}"]];
                }
            }
            sendMessage($chatId, "Selecciona una <b>fecha</b>:", ['inline_keyboard' => $btns]);
        } elseif (substr($data, 0, 2) === 'f_') {
            if (!$estado || !isset($estado['data']['vid'])) { sendMessage($chatId, "⚠️ Sesión expirada. Pulsa /start."); exit; }
            $fecha = substr($data, 2);
            $sdata = $estado['data'];
            $sdata['f'] = $fecha;
            setStep($chatId, 'sel_hora', $sdata);
            $hdis = obtenerHorariosDisponibles($fecha, $sdata['vid']);
            $btns = []; $row = [];
            foreach ($hdis as $h) {
                $row[] = ['text' => $h, 'callback_data' => "h_$h"];
                if (count($row) == 3) { $btns[] = $row; $row = []; }
            }
            if ($row) $btns[] = $row;
            sendMessage($chatId, "Elegir hora para $fecha:", ['inline_keyboard' => $btns]);
        } elseif (substr($data, 0, 2) === 'h_') {
            if (!$estado || !isset($estado['data']['f'])) { sendMessage($chatId, "⚠️ Sesión expirada. Pulsa /start."); exit; }
            $hora = substr($data, 2);
            $sdata = $estado['data'];
            $db = getDB();
            $stmtC = $db->prepare("INSERT INTO citas (cliente_id, veterinario_id, mascota_id, fecha, hora) VALUES (?, ?, ?, ?, ?)");
            if ($stmtC->execute([$cliente['id'], $sdata['vid'], $sdata['mid'], $sdata['f'], $hora])) {
                $stP = $db->prepare("SELECT nombre FROM mascotas WHERE id = ?");
                $stP->execute([$sdata['mid']]);
                $pname = $stP->fetchColumn();
                clearStep($chatId);
                sendMessage($chatId, "✅ <b>¡Cita agendada con éxito!</b>\n\n📅 <b>Fecha:</b> {$sdata['f']}\n⏰ <b>Hora:</b> $hora\n🐾 <b>Mascota:</b> $pname\n\n⚠️ <i>Nota: El precio final depende de los servicios realizados, medicamentos o terapias necesarias durante la consulta.</i>\n\n¡Te esperamos! 🐾");
            } else {
                sendMessage($chatId, "❌ Error al guardar la cita.");
            }
        } elseif ($data === 'ver_citas') {
            $db = getDB(); $hoy = date('Y-m-d');
            $s = $db->prepare("SELECT c.fecha, c.hora, v.nombre as vname, m.nombre as mname FROM citas c JOIN veterinarios v ON c.veterinario_id = v.id LEFT JOIN mascotas m ON c.mascota_id = m.id WHERE c.cliente_id = ? AND c.fecha >= ? ORDER BY c.fecha, c.hora");
            $s->execute([$cliente['id'], $hoy]);
            $cs = $s->fetchAll();
            $txt = "📋 <b>Tus próximas citas:</b>\n\n";
            foreach ($cs as $c) {
                $pet = $c['mname'] ? " (Mascota: {$c['mname']})" : "";
                $txt .= "📅 {$c['fecha']} a las {$c['hora']}\n🩺 Vet: {$c['vname']}$pet\n\n";
            }
            sendMessage($chatId, empty($cs) ? "No tienes citas agendadas." : $txt);
        } elseif ($data === 'registrar_nueva') {
            setStep($chatId, 'reg_nombre');
            sendMessage($chatId, "¡Genial! Vamos a registrarte. 😊\n\n¿Cuál es tu <b>nombre completo</b>?");
        } elseif ($data === 'check_contact') {
            sendContact($chatId, "📱 Por favor, presiona el botón de abajo para compartir tu número:");
        }
    } elseif (isset($update['message'])) {
        $msg = $update['message'];
        $text = trim($msg['text'] ?? '');

        if ($text === '/start') {
            if ($cliente) {
                mostrarMenuPrincipal($chatId, $cliente['nombre']);
            } else {
                $kb = ['inline_keyboard' => [
                    [['text' => '📱 Compartir Número (Si ya eres cliente)', 'callback_data' => 'check_contact']],
                    [['text' => '🆕 Registrarme como nuevo cliente', 'callback_data' => 'registrar_nueva']]
                ]];
                sendMessage($chatId, "🐾 ¡Bienvenido a la Clínica Veterinaria! 🐾\n\nVeo que aún no estás registrado. ¿Cómo deseas continuar?", $kb);
            }
            clearStep($chatId);
            exit;
        } elseif ($text === '/cancel' || strtolower($text) === 'cancelar') {
            clearStep($chatId);
            sendMessage($chatId, "Acción cancelada.");
            if ($cliente) mostrarMenuPrincipal($chatId, $cliente['nombre']);
            exit;
        }

        if (isset($msg['contact'])) {
            $num = preg_replace('/\D/', '', $msg['contact']['phone_number']);
            $cl = buscarClientePorTelefono($num);
            if ($cl) {
                vincularTelegram($cl['id'], $chatId);
                sendMessage($chatId, "✅ Vinculado como <b>{$cl['nombre']}</b>. Escribe /start.");
                clearStep($chatId);
            } else {
                sendMessage($chatId, "❌ Número no registrado en nuestro sistema.");
            }
            exit;
        }

        if ($estado) {
            $step = $estado['step'];
            $sdata = $estado['data'];
            if ($step === 'reg_nombre') {
                $sdata['nombre'] = $text;
                setStep($chatId, 'reg_telefono', $sdata);
                sendMessage($chatId, "Perfecto, <b>$text</b>. Ahora, ¿cuál es tu <b>número de teléfono</b>?");
            } elseif ($step === 'reg_telefono') {
                $db = getDB();
                $stmt = $db->prepare("INSERT INTO clientes (nombre, telefono, telegram_chat_id) VALUES (?, ?, ?)");
                if ($stmt->execute([$sdata['nombre'], $text, $chatId])) {
                    clearStep($chatId);
                    sendMessage($chatId, "✅ ¡Registro completado con éxito!");
                    mostrarMenuPrincipal($chatId, $sdata['nombre']);
                } else {
                    sendMessage($chatId, "❌ Error al registrarte. Intenta de nuevo.");
                }
            } elseif ($step === 'preg_nombre') {
                $sdata['m_nombre'] = $text;
                setStep($chatId, 'preg_especie', $sdata);
                sendMessage($chatId, "Entendido. ¿Qué <b>animal</b> es $text? (Perro, gato, conejo, etc.)");
            } elseif ($step === 'preg_especie') {
                $sdata['m_especie'] = $text;
                setStep($chatId, 'preg_raza', $sdata);
                sendMessage($chatId, "¿De qué <b>raza</b> es?");
            } elseif ($step === 'preg_raza') {
                $sdata['m_raza'] = $text;
                setStep($chatId, 'preg_edad', $sdata);
                sendMessage($chatId, "¿Qué <b>edad</b> tiene?");
            } elseif ($step === 'preg_edad') {
                $sdata['m_edad'] = $text;
                setStep($chatId, 'preg_vacunas', $sdata);
                sendMessage($chatId, "¿Tiene sus <b>vacunas</b> al día? (Sí/No, cuáles)");
            } elseif ($step === 'preg_vacunas') {
                $sdata['m_vacunas'] = $text;
                $db = getDB();
                $stmtM = $db->prepare("INSERT INTO mascotas (cliente_id, nombre, especie, raza, edad, vacunas) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtM->execute([$cliente['id'], $sdata['m_nombre'], $sdata['m_especie'], $sdata['m_raza'], $sdata['m_edad'], $sdata['m_vacunas']]);
                $mascotaId = $db->lastInsertId();
                setStep($chatId, 'sel_vet', ['mid' => $mascotaId]);
                $vets = obtenerVeterinarios();
                $btns = array_map(function($v) {
                    return [['text' => '🩺 '.$v['nombre'], 'callback_data' => 'vet_'.$v['id']]];
                }, $vets);
                sendMessage($chatId, "✅ Mascota registrada.\n\nAhora, selecciona un <b>veterinario</b>:", ['inline_keyboard' => $btns]);
            }
            exit;
        }
    }
} catch (Exception $e) {
    file_put_contents('error_log.txt', "[" . date('Y-m-d H:i:s') . "] Error fatal: " . $e->getMessage() . "\n", FILE_APPEND);
    if (isset($chatId)) {
        sendMessage($chatId, "⚠️ Error Interno: " . $e->getMessage() . "\n\nPor favor, ejecuta setup.php de nuevo para asegurar que la base de datos esté bien.");
    }
}
?>
