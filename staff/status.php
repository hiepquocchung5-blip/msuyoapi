<?php
// API for Finance Portal to toggle "Clock In / Clock Out"
// Uses Session Auth (since Finance Portal uses PHP Sessions, not Bearer tokens usually, but we can adapt)
// Assuming Finance Portal shares DB config or connects here.

require_once __DIR__ . '/../config/db.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Check if Request comes from Finance Portal Session
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['finance_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$staffId = $_SESSION['finance_id'];
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->status)) {
    // Just Get Status
    $stmt = $pdo->prepare("SELECT is_online FROM admin_users WHERE id = ?");
    $stmt->execute([$staffId]);
    $status = $stmt->fetchColumn();
    echo json_encode(['status' => 'success', 'is_online' => (bool)$status]);
    exit;
}

// Update Status
$newStatus = $data->status ? 1 : 0;
$stmt = $pdo->prepare("UPDATE admin_users SET is_online = ?, last_login = NOW() WHERE id = ?");
$stmt->execute([$newStatus, $staffId]);

// Log Action
$action = $newStatus ? "Clocked IN" : "Clocked OUT";
$pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'system')")
    ->execute([$staffId, $action]);

echo json_encode(['status' => 'success', 'is_online' => (bool)$newStatus, 'message' => $action]);
?>