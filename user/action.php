<?php
require_once __DIR__ . '/../../utils/admin_middleware.php'; 

// Only FINANCE or GOD can ban/reset
$admin = authenticateAdmin($pdo, 'FINANCE');

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) || !isset($data->action)) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID and Action required']);
    exit;
}

$userId = (int)$data->user_id;
$action = $data->action; // 'ban', 'unban', 'reset_password'

try {
    $pdo->beginTransaction();

    if ($action === 'ban') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
        $stmt->execute([$userId]);
        $auditMsg = "Banned User #$userId";
    } 
    elseif ($action === 'unban') {
        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$userId]);
        $auditMsg = "Unbanned User #$userId";
    } 
    elseif ($action === 'reset_password') {
        // Reset to default: '123456'
        $defaultHash = password_hash('123456', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$defaultHash, $userId]);
        $auditMsg = "Reset Password for User #$userId";
    } 
    else {
        throw new Exception("Invalid action");
    }

    // Audit Log
    $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'users')")
        ->execute([$admin['id'], $auditMsg]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => $auditMsg]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Action failed: ' . $e->getMessage()]);
}
?>