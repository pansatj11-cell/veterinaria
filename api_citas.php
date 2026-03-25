<?php
// api_citas.php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    // Listar citas próximas
    // Podemos filtrar por veterinario_id o cliente_id a futuro
    // Para simplificar, obtenemos todas uniéndo las tablas
    $sql = '
        SELECT c.id, c.fecha, c.hora, c.estado, 
               cli.nombre AS cliente_nombre, cli.telegram_chat_id,
               vet.nombre AS veterinario_nombre,
               m.nombre AS mascota_nombre
        FROM citas c
        JOIN clientes cli ON c.cliente_id = cli.id
        JOIN veterinarios vet ON c.veterinario_id = vet.id
        LEFT JOIN mascotas m ON c.mascota_id = m.id
        ORDER BY c.fecha ASC, c.hora ASC
    ';
    $stmt = $db->query($sql);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $citas]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // CASO: CANCELAR CITA
    if (isset($input['action']) && $input['action'] === 'delete') {
        $id = $input['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare('DELETE FROM citas WHERE id = :id');
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cita cancelada']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al cancelar']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID requerido para cancelar']);
        }
        exit;
    }

    $cliente_id = $input['cliente_id'] ?? null;
    $veterinario_id = $input['veterinario_id'] ?? null;
    $fecha = $input['fecha'] ?? null; // Formato YYYY-MM-DD
    $hora = $input['hora'] ?? null;   // Formato HH:MM
    // ... resto del código post ...
    $mascota_id = $input['mascota_id'] ?? null;

    if (!$cliente_id || !$veterinario_id || !$fecha || !$hora) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
        exit;
    }
    // ... validaciones y carga ...
    $diaSemana = date('N', strtotime($fecha));
    if ($diaSemana > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Las citas solo pueden programarse de lunes a viernes.']);
        exit;
    }
    $horariosPermitidos = [
        '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00',
        '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'
    ];
    $horaLimpia = date('H:i', strtotime($hora));
    if (!in_array($horaLimpia, $horariosPermitidos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Horario no válido.']);
        exit;
    }
    $stmtCheck = $db->prepare('SELECT COUNT(*) FROM citas WHERE veterinario_id = :vet_id AND fecha = :fecha AND hora = :hora');
    $stmtCheck->bindParam(':vet_id', $veterinario_id);
    $stmtCheck->bindParam(':fecha', $fecha);
    $stmtCheck->bindParam(':hora', $horaLimpia);
    $stmtCheck->execute();
    if ($stmtCheck->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Horario ocupado.']);
        exit;
    }
    $stmtInsert = $db->prepare('INSERT INTO citas (cliente_id, veterinario_id, mascota_id, fecha, hora, estado) VALUES (:cliente_id, :veterinario_id, :mascota_id, :fecha, :hora, :estado)');
    $estado = (isset($input['tipo']) && $input['tipo'] === 'control') ? 'pendiente_confirmacion' : 'pendiente';
    $stmtInsert->bindParam(':cliente_id', $cliente_id);
    $stmtInsert->bindParam(':veterinario_id', $veterinario_id);
    $stmtInsert->bindParam(':mascota_id', $mascota_id);
    $stmtInsert->bindParam(':fecha', $fecha);
    $stmtInsert->bindParam(':hora', $horaLimpia);
    $stmtInsert->bindParam(':estado', $estado);
    if ($stmtInsert->execute()) {
        $citaId = $db->lastInsertId();
        $notificado = false;

        // Si es cita de control, enviar notificación por Telegram
        if (isset($input['tipo']) && $input['tipo'] === 'control') {
            // Buscar chat_id del cliente
            $stmtCli = $db->prepare('SELECT telegram_chat_id, nombre FROM clientes WHERE id = :id');
            $stmtCli->bindParam(':id', $cliente_id);
            $stmtCli->execute();
            $clienteData = $stmtCli->fetch(PDO::FETCH_ASSOC);

            // Buscar nombre del veterinario
            $stmtVet = $db->prepare('SELECT nombre FROM veterinarios WHERE id = :id');
            $stmtVet->bindParam(':id', $veterinario_id);
            $stmtVet->execute();
            $vetNombre = $stmtVet->fetchColumn();

            // Buscar nombre de la mascota
            $petNombre = 'Tu mascota';
            if ($mascota_id) {
                $stmtPet = $db->prepare('SELECT nombre FROM mascotas WHERE id = :id');
                $stmtPet->bindParam(':id', $mascota_id);
                $stmtPet->execute();
                $petNombre = $stmtPet->fetchColumn() ?: 'Tu mascota';
            }

            if ($clienteData && !empty($clienteData['telegram_chat_id'])) {
                $chatId = $clienteData['telegram_chat_id'];
                $token = TELEGRAM_BOT_TOKEN;
                $apiUrl = "https://api.telegram.org/bot$token/sendMessage";

                $texto = "🩺 <b>Cita de Control Programada</b>\n\n";
                $texto .= "Hola <b>{$clienteData['nombre']}</b>, tu veterinario ha agendado una cita de control:\n\n";
                $texto .= "🐾 <b>Mascota:</b> $petNombre\n";
                $texto .= "📅 <b>Fecha:</b> $fecha\n";
                $texto .= "⏰ <b>Hora:</b> $horaLimpia\n";
                $texto .= "🩺 <b>Veterinario:</b> $vetNombre\n\n";
                $texto .= "¿Puedes asistir a esta cita?";

                $keyboard = json_encode([
                    'inline_keyboard' => [
                        [['text' => '✅ Aceptar Cita', 'callback_data' => "confirm_cita_$citaId"]],
                        [['text' => '📅 Aplazar / Cambiar fecha', 'callback_data' => "postpone_cita_$citaId"]]
                    ]
                ]);

                $postData = [
                    'chat_id' => $chatId,
                    'text' => $texto,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $keyboard
                ];

                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                $result = curl_exec($ch);
                curl_close($ch);

                $notificado = true;
            }
        }

        $msg = $notificado ? 'Cita reservada y notificación enviada al cliente.' : 'Cita reservada.';
        if (isset($input['tipo']) && $input['tipo'] === 'control' && !$notificado) {
            $msg = 'Cita reservada, pero el cliente no tiene Telegram vinculado. No se pudo notificar.';
        }
        echo json_encode(['success' => true, 'message' => $msg, 'id' => $citaId]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al guardar.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>

