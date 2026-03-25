<?php
// api_historial.php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

function enviarNotificacionTelegram($chatId, $mensaje) {
    $token = TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $mensaje,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
}

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

            if ($res) {
                // Notificar al cliente
                $stmtInfo = $db->prepare("SELECT m.nombre as m_nombre, c.telegram_chat_id, c.nombre as c_nombre 
                                         FROM mascotas m JOIN clientes c ON m.cliente_id = c.id 
                                         WHERE m.id = ?");
                $stmtInfo->execute([$data['mascota_id']]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

                if ($info && !empty($info['telegram_chat_id'])) {
                    $msg = "🩺 <b>Nuevo Historial Clínico</b> 🐾\n\n";
                    $msg .= "Hola <b>{$info['c_nombre']}</b>, se ha registrado una consulta para <b>{$info['m_nombre']}</b>.\n\n";
                    $msg .= "📝 <b>Diagnóstico:</b> {$data['diagnostico']}\n";
                    $msg .= "💊 <b>Tratamiento:</b> {$data['tratamiento']}\n";
                    $msg .= "💰 <b>Valor de la consulta:</b> $" . number_format($data['costo'], 0, ',', '.') . " COP\n\n";
                    $msg .= "¡Gracias por confiar en nosotros!";
                    enviarNotificacionTelegram($info['telegram_chat_id'], $msg);
                }
            }

            echo json_encode(['success' => $res]);
        }
    }

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
