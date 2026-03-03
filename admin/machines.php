<?php
// api/admin/machines.php
require_once __DIR__ . '/../../utils/admin_middleware.php';

$admin = authenticateAdmin($pdo);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Fetch all machines with user info
    $stmt = $pdo->query("
        SELECT m.*, i.name as island_name, u.username as current_user_name
        FROM machines m
        LEFT JOIN islands i ON m.island_id = i.id
        LEFT JOIN users u ON m.current_user_id = u.id
        ORDER BY m.id ASC
    ");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
} 
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $action = $data->action ?? '';
    $machineId = $data->machine_id ?? 0;

    if (!$machineId) {
        http_response_code(400); echo json_encode(['error' => 'Machine ID required']); exit;
    }

    if ($action === 'kick') {
        // Force kick user from machine
        $pdo->prepare("UPDATE machines SET status = 'free', current_user_id = NULL, session_token = NULL, last_ping_at = NULL WHERE id = ?")->execute([$machineId]);
        echo json_encode(['status' => 'success', 'message' => 'User kicked from machine']);
    } 
    elseif ($action === 'toggle_maintenance') {
        $stmt = $pdo->prepare("SELECT status FROM machines WHERE id = ?");
        $stmt->execute([$machineId]);
        $currentStatus = $stmt->fetchColumn();

        $newStatus = ($currentStatus === 'maintenance') ? 'free' : 'maintenance';
        
        // If putting into maintenance, also kick any current user
        if ($newStatus === 'maintenance') {
             $pdo->prepare("UPDATE machines SET status = ?, current_user_id = NULL, session_token = NULL WHERE id = ?")->execute([$newStatus, $machineId]);
        } else {
             $pdo->prepare("UPDATE machines SET status = ? WHERE id = ?")->execute([$newStatus, $machineId]);
        }
        
        echo json_encode(['status' => 'success', 'message' => "Machine status updated to $newStatus"]);
    }
}
?>