<?php
// api_historial.php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = getDB();

    if ($method === 'GET') {
        if ($action === 'list_pets') {
            $stmt = $db->query("SELECT m.*, c.nombre as cliente_nombre FROM mascotas m JOIN clientes c ON m.cliente_id = c.id ORDER BY m.nombre ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($action === 'get_pet_history') {
            $petId = $_GET['pet_id'] ?? 0;
            $stmt = $db->prepare("SELECT h.*, v.nombre as vet_nombre FROM historial_clinico h JOIN veterinarios v ON h.veterinario_id = v.id WHERE h.mascota_id = ? ORDER BY h.fecha DESC");
            $stmt->execute([$petId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($action === 'add_entry') {
            $stmt = $db->prepare("INSERT INTO historial_clinico (mascota_id, veterinario_id, diagnostico, tratamiento, costo) VALUES (?, ?, ?, ?, ?)");
            $res = $stmt->execute([
                $data['mascota_id'],
                $data['veterinario_id'],
                $data['diagnostico'],
                $data['tratamiento'],
                $data['costo']
            ]);
            echo json_encode(['success' => $res]);
        }
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
