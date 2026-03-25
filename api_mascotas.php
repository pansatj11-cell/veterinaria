<?php
// api_mascotas.php
// Endpoint para obtener mascotas de un cliente específico

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $clienteId = $_GET['cliente_id'] ?? null;
    
    if ($clienteId) {
        // Mascotas de un cliente específico
        $stmt = $db->prepare('SELECT id, nombre, especie, raza, edad FROM mascotas WHERE cliente_id = :cid ORDER BY nombre ASC');
        $stmt->bindParam(':cid', $clienteId);
        $stmt->execute();
        $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Todas las mascotas (con nombre del dueño)
        $stmt = $db->query('SELECT m.id, m.nombre, m.especie, m.raza, m.edad, c.nombre AS cliente_nombre FROM mascotas m JOIN clientes c ON m.cliente_id = c.id ORDER BY m.nombre ASC');
        $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'data' => $mascotas]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
