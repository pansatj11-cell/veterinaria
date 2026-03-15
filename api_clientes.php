<?php
// api_clientes.php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    // Listar todos los clientes
    $stmt = $db->query('SELECT * FROM clientes ORDER BY nombre ASC');
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $clientes]);
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // CASO: ELIMINAR CLIENTE
    if (isset($input['action']) && $input['action'] === 'delete') {
        $id = $input['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare('DELETE FROM clientes WHERE id = :id');
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cliente eliminado']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID requerido para eliminar']);
        }
        exit;
    }

    // CASO: AGREGAR CLIENTE
    if (isset($input['nombre']) && !empty(trim($input['nombre']))) {
        $nombre = trim($input['nombre']);
        $telefono = isset($input['telefono']) ? trim($input['telefono']) : null;
        $telegram_chat_id = isset($input['telegram_chat_id']) ? trim($input['telegram_chat_id']) : null;
        
        $stmt = $db->prepare('INSERT INTO clientes (nombre, telefono, telegram_chat_id) VALUES (:nombre, :telefono, :telegram_chat_id)');
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':telefono', $telefono);
        $stmt->bindParam(':telegram_chat_id', $telegram_chat_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cliente agregado con éxito', 'id' => $db->lastInsertId()]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
