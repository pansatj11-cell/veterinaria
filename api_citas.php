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
    $stmtInsert = $db->prepare('INSERT INTO citas (cliente_id, veterinario_id, fecha, hora) VALUES (:cliente_id, :veterinario_id, :fecha, :hora)');
    $stmtInsert->bindParam(':cliente_id', $cliente_id);
    $stmtInsert->bindParam(':veterinario_id', $veterinario_id);
    $stmtInsert->bindParam(':fecha', $fecha);
    $stmtInsert->bindParam(':hora', $horaLimpia);
    if ($stmtInsert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cita reservada.', 'id' => $db->lastInsertId()]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al guardar.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
