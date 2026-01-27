<?php
require_once __DIR__ . '/../../utils/admin_middleware.php'; 

// 1. Authenticate (GOD role required for RTP changes)
$admin = authenticateAdmin($pdo, 'GOD');

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->island_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Island ID required']);
    exit;
}

$id = (int)$data->island_id;
$allowedFields = ['name', 'desc', 'unlock_price', 'rtp_rate', 'hostess_char_id', 'atmosphere_type', 'is_active'];
$updates = [];
$params = [];

// Build dynamic query
foreach ($allowedFields as $field) {
    if (isset($data->$field)) {
        $updates[] = "$field = ?";
        $params[] = $data->$field;
    }
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid fields to update']);
    exit;
}

// Add ID to params for WHERE clause
$params[] = $id;

try {
    $sql = "UPDATE islands SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Audit Log
    $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'islands')");
    $stmtAudit->execute([$admin['id'], "Updated Config for Island #$id"]);

    echo json_encode(['status' => 'success', 'message' => 'Island configuration updated']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
}
?>