<?php
// api_veterinarios.php
// Endpoint para gestionar veterinarios

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    // Listar todos los veterinarios
    $stmt = $db->query('SELECT * FROM veterinarios ORDER BY nombre ASC');
    $veterinarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $veterinarios]);
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // CASO: ELIMINAR VETERINARIO
    if (isset($input['action']) && $input['action'] === 'delete') {
        $id = $input['id'] ?? null;
        if ($id) {
            $stmt = $db->prepare('DELETE FROM veterinarios WHERE id = :id');
            $stmt->bindParam(':id', $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Veterinario eliminado']);
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

    if (isset($input['nombre']) && !empty(trim($input['nombre']))) {
        $nombre = trim($input['nombre']);
        $usuario = trim($input['usuario'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($usuario) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son obligatorios']);
            exit;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $db->prepare('INSERT INTO veterinarios (nombre, usuario, password) VALUES (:nombre, :usuario, :password)');
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->bindParam(':password', $passwordHash);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Veterinario agregado con éxito', 'id' => $db->lastInsertId()]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
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
