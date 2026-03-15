<?php
// telegram_bot.php
// Bot interactivo de Telegram para agendar citas veterinarias
// Funciona con Long Polling (ejecutar en consola: php telegram_bot.php)

require_once __DIR__ . '/db.php';

$token = TELEGRAM_BOT_TOKEN;
$apiUrl = "https://api.telegram.org/bot$token";

// Estado de conversación almacenado en memoria (se reinicia si se detiene el script)
// Para producción se puede usar una tabla en la BD
$conversationState = [];

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
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendContact($chatId, $text) {
    // Enviar mensaje con botón para compartir contacto
    $keyboard = [
        'keyboard' => [
            [['text' => '📱 Compartir mi número de teléfono', 'request_contact' => true]]
        ],
        'one_time_keyboard' => true,
        'resize_keyboard' => true
    ];
    sendMessage($chatId, $text, $keyboard);
}

function removeKeyboard($chatId, $text) {
    $remove = ['remove_keyboard' => true];
    sendMessage($chatId, $text, $remove);
}

function getUpdates($offset = 0) {
    global $apiUrl;
    $url = "$apiUrl/getUpdates?offset=$offset&timeout=30";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 35);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Buscar cliente por teléfono en la base de datos
// Compara los últimos 10 dígitos para ignorar código de país
function buscarClientePorTelefono($telefono) {
    $db = getDB();
    // Quitar todo excepto dígitos
    $soloDigitos = preg_replace('/\D/', '', $telefono);
    // Tomar los últimos 10 dígitos (número local sin código de país)
    $ultimos10 = substr($soloDigitos, -10);

    echo "  [DEBUG] Buscando teléfono: $telefono -> últimos 10: $ultimos10\n";

    // Buscar todos los clientes y comparar los últimos 10 dígitos
    $stmt = $db->query("SELECT * FROM clientes");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($clientes as $c) {
        $telCliente = preg_replace('/\D/', '', $c['telefono']);
        $ultimos10Cliente = substr($telCliente, -10);
        echo "  [DEBUG] Comparando con: {$c['nombre']} -> [{$c['telefono']}] -> últimos 10: $ultimos10Cliente\n";
        if ($ultimos10 === $ultimos10Cliente && strlen($ultimos10) >= 7) {
            return $c;
        }
    }
    return false;
}

// Buscar cliente por chat_id (ya vinculado)
function buscarClientePorChatId($chatId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM clientes WHERE telegram_chat_id = :chat_id");
    $stmt->bindParam(':chat_id', $chatId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Vincular chat_id al cliente
function vincularTelegram($clienteId, $chatId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE clientes SET telegram_chat_id = :chat_id WHERE id = :id");
    $stmt->bindParam(':chat_id', $chatId);
    $stmt->bindParam(':id', $clienteId);
    return $stmt->execute();
}

// Obtener veterinarios
function obtenerVeterinarios() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM veterinarios ORDER BY nombre ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener horarios disponibles para una fecha y veterinario
function obtenerHorariosDisponibles($fecha, $vetId) {
    $db = getDB();
    $todosHorarios = [
        '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00',
        '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'
    ];

    $stmt = $db->prepare("SELECT hora FROM citas WHERE fecha = :fecha AND veterinario_id = :vet_id");
    $stmt->bindParam(':fecha', $fecha);
    $stmt->bindParam(':vet_id', $vetId);
    $stmt->execute();
    $ocupados = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return array_diff($todosHorarios, $ocupados);
}

// Crear cita
function crearCita($clienteId, $vetId, $fecha, $hora) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO citas (cliente_id, veterinario_id, fecha, hora) VALUES (:cliente_id, :vet_id, :fecha, :hora)");
    $stmt->bindParam(':cliente_id', $clienteId);
    $stmt->bindParam(':vet_id', $vetId);
    $stmt->bindParam(':fecha', $fecha);
    $stmt->bindParam(':hora', $hora);
    return $stmt->execute();
}

// Obtener citas futuras de un cliente
function obtenerCitasCliente($clienteId) {
    $db = getDB();
    $hoy = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT c.fecha, c.hora, c.estado, v.nombre AS vet_nombre 
        FROM citas c 
        JOIN veterinarios v ON c.veterinario_id = v.id 
        WHERE c.cliente_id = :id AND c.fecha >= :hoy 
        ORDER BY c.fecha ASC, c.hora ASC
    ");
    $stmt->bindParam(':id', $clienteId);
    $stmt->bindParam(':hoy', $hoy);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function mostrarMenuPrincipal($chatId, $nombreCliente) {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📅 Agendar Cita', 'callback_data' => 'agendar']],
            [['text' => '📋 Ver Mis Citas', 'callback_data' => 'ver_citas']],
        ]
    ];
    sendMessage($chatId, "¡Hola <b>$nombreCliente</b>! 🐾\n\n¿Qué deseas hacer?", $keyboard);
}

// ======= LOOP PRINCIPAL DEL BOT =======
echo "🐾 Bot de la Clínica Veterinaria iniciado...\n";
echo "Escuchando mensajes de Telegram...\n\n";

$offset = 0;

while (true) {
    $updates = getUpdates($offset);

    if (!$updates || !$updates['ok'] || empty($updates['result'])) {
        continue;
    }

    foreach ($updates['result'] as $update) {
        $offset = $update['update_id'] + 1;

        // ---- CALLBACK (botones inline) ----
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $chatId = $callback['message']['chat']['id'];
            $data = $callback['data'];

            $cliente = buscarClientePorChatId($chatId);
            if (!$cliente) {
                sendMessage($chatId, "⚠️ No estás vinculado aún. Envía /start para comenzar.");
                continue;
            }

            // AGENDAR CITA - Paso 1: Elegir veterinario
            if ($data === 'agendar') {
                $vets = obtenerVeterinarios();
                if (empty($vets)) {
                    sendMessage($chatId, "❌ No hay veterinarios disponibles en este momento.");
                    continue;
                }
                $buttons = [];
                foreach ($vets as $v) {
                    $buttons[] = [['text' => '🩺 ' . $v['nombre'], 'callback_data' => 'vet_' . $v['id']]];
                }
                $keyboard = ['inline_keyboard' => $buttons];
                sendMessage($chatId, "Selecciona un <b>veterinario</b>:", $keyboard);
            }

            // AGENDAR - Paso 2: Elegir fecha (se pide como texto)
            elseif (strpos($data, 'vet_') === 0) {
                $vetId = str_replace('vet_', '', $data);
                $conversationState[$chatId] = [
                    'step' => 'esperando_fecha',
                    'vet_id' => $vetId,
                    'cliente_id' => $cliente['id']
                ];

                // Generar botones con los próximos 5 días hábiles
                $buttons = [];
                $dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
                $count = 0;
                $day = 1;
                while ($count < 5) {
                    $fecha = date('Y-m-d', strtotime("+$day days"));
                    $diaSemana = date('N', strtotime($fecha));
                    if ($diaSemana <= 5) { // Lunes a Viernes
                        $nombreDia = $dias[$diaSemana - 1];
                        $fechaDisplay = date('d/m', strtotime($fecha));
                        $buttons[] = [['text' => "$nombreDia $fechaDisplay", 'callback_data' => "fecha_$fecha"]];
                        $count++;
                    }
                    $day++;
                }
                $keyboard = ['inline_keyboard' => $buttons];
                sendMessage($chatId, "📅 Selecciona una <b>fecha</b> disponible:", $keyboard);
            }

            // AGENDAR - Paso 3: Elegir hora
            elseif (strpos($data, 'fecha_') === 0) {
                $fecha = str_replace('fecha_', '', $data);

                if (isset($conversationState[$chatId])) {
                    $conversationState[$chatId]['fecha'] = $fecha;
                    $conversationState[$chatId]['step'] = 'esperando_hora';
                    $vetId = $conversationState[$chatId]['vet_id'];

                    $disponibles = obtenerHorariosDisponibles($fecha, $vetId);

                    if (empty($disponibles)) {
                        sendMessage($chatId, "❌ No hay horarios disponibles para esa fecha con ese veterinario. Intenta otra fecha.");
                        mostrarMenuPrincipal($chatId, $cliente['nombre']);
                        unset($conversationState[$chatId]);
                        continue;
                    }

                    $buttons = [];
                    $row = [];
                    $i = 0;
                    foreach ($disponibles as $h) {
                        $display = date('h:i A', strtotime($h));
                        $row[] = ['text' => $display, 'callback_data' => "hora_$h"];
                        $i++;
                        if ($i % 3 == 0) {
                            $buttons[] = $row;
                            $row = [];
                        }
                    }
                    if (!empty($row)) $buttons[] = $row;

                    $keyboard = ['inline_keyboard' => $buttons];
                    $fechaDisplay = date('d/m/Y', strtotime($fecha));
                    sendMessage($chatId, "⏰ Horarios disponibles para el <b>$fechaDisplay</b>:", $keyboard);
                }
            }

            // AGENDAR - Paso 4: Confirmar y guardar
            elseif (strpos($data, 'hora_') === 0) {
                $hora = str_replace('hora_', '', $data);

                if (isset($conversationState[$chatId])) {
                    $state = $conversationState[$chatId];
                    $resultado = crearCita($state['cliente_id'], $state['vet_id'], $state['fecha'], $hora);

                    if ($resultado) {
                        $db = getDB();
                        $stmtVet = $db->prepare("SELECT nombre FROM veterinarios WHERE id = :id");
                        $stmtVet->bindParam(':id', $state['vet_id']);
                        $stmtVet->execute();
                        $vetNombre = $stmtVet->fetchColumn();

                        $fechaDisplay = date('d/m/Y', strtotime($state['fecha']));
                        $horaDisplay = date('h:i A', strtotime($hora));

                        sendMessage($chatId, "✅ <b>¡Cita agendada con éxito!</b>\n\n📅 Fecha: <b>$fechaDisplay</b>\n⏰ Hora: <b>$horaDisplay</b>\n🩺 Veterinario: <b>$vetNombre</b>\n\n¡Te esperamos! 🐾");
                    } else {
                        sendMessage($chatId, "❌ Hubo un error al agendar tu cita. Intenta de nuevo.");
                    }
                    unset($conversationState[$chatId]);
                }
            }

            // VER MIS CITAS
            elseif ($data === 'ver_citas') {
                $citas = obtenerCitasCliente($cliente['id']);
                if (empty($citas)) {
                    sendMessage($chatId, "📋 No tienes citas próximas agendadas.");
                } else {
                    $msg = "📋 <b>Tus próximas citas:</b>\n\n";
                    foreach ($citas as $c) {
                        $fechaDisplay = date('d/m/Y', strtotime($c['fecha']));
                        $horaDisplay = date('h:i A', strtotime($c['hora']));
                        $msg .= "📅 $fechaDisplay a las $horaDisplay\n";
                        $msg .= "🩺 Vet: {$c['vet_nombre']}\n";
                        $msg .= "Estado: {$c['estado']}\n\n";
                    }
                    sendMessage($chatId, $msg);
                }
            }

            continue;
        }

        // ---- MENSAJES DE TEXTO / CONTACTO ----
        if (!isset($update['message'])) continue;

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = isset($message['text']) ? trim($message['text']) : '';

        // El usuario compartió su contacto (botón de compartir teléfono)
        if (isset($message['contact'])) {
            $phone = $message['contact']['phone_number'];
            // Normalizar
            $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
            if (strpos($phone, '+') !== 0) {
                $phone = '+' . $phone;
            }

            $cliente = buscarClientePorTelefono($phone);
            if ($cliente) {
                vincularTelegram($cliente['id'], $chatId);
                removeKeyboard($chatId, "✅ ¡Cuenta vinculada exitosamente!\n\nBienvenido/a <b>{$cliente['nombre']}</b>. Ahora puedes agendar tus citas desde aquí. 🐾\n\nEscribe /start cuando quieras agendar una cita.");
            } else {
                removeKeyboard($chatId, "❌ No encontramos tu número <b>$phone</b> en nuestro sistema.\n\nPor favor, pide a la clínica que te registre primero con tu número de teléfono y luego vuelve a intentar con /start.");
            }
            continue;
        }

        // Comando /start
        if ($text === '/start') {
            // Verificar si ya está vinculado
            $cliente = buscarClientePorChatId($chatId);
            if ($cliente) {
                mostrarMenuPrincipal($chatId, $cliente['nombre']);
            } else {
                sendMessage($chatId, "🐾 <b>¡Bienvenido a la Clínica Veterinaria!</b>\n\nPara comenzar, necesito verificar tu identidad.\nPor favor, comparte tu número de teléfono presionando el botón de abajo. 👇");
                sleep(1);
                sendContact($chatId, "📱 Toca el botón para compartir tu número:");
            }
            continue;
        }

        // Si el usuario escribe su teléfono manualmente
        if (preg_match('/^\+?\d{7,15}$/', preg_replace('/[\s\-]/', '', $text))) {
            $phone = preg_replace('/[\s\-]/', '', $text);
            if (strpos($phone, '+') !== 0) {
                $phone = '+' . $phone;
            }
            $cliente = buscarClientePorTelefono($phone);
            if ($cliente) {
                vincularTelegram($cliente['id'], $chatId);
                sendMessage($chatId, "✅ ¡Cuenta vinculada exitosamente!\n\nBienvenido/a <b>{$cliente['nombre']}</b>. 🐾\n\nEscribe /start cuando quieras agendar una cita.");
            } else {
                sendMessage($chatId, "❌ No encontramos ese número en nuestro sistema.\n\nAsegúrate de que la clínica te haya registrado con este número, o intenta compartir tu contacto con el botón de /start.");
            }
            continue;
        }

        // Cualquier otro mensaje
        $cliente = buscarClientePorChatId($chatId);
        if ($cliente) {
            mostrarMenuPrincipal($chatId, $cliente['nombre']);
        } else {
            sendMessage($chatId, "👋 Hola, soy el bot de la Clínica Veterinaria.\nEscribe /start para comenzar.");
        }
    }
}
?>
