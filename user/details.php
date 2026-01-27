<?php
require_once __DIR__ . '/../../utils/admin_middleware.php'; 

$admin = authenticateAdmin($pdo, 'STAFF');

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

$userId = (int)$_GET['user_id'];

try {
    // 1. Basic Info
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // 2. Recent Transactions (Last 10)
    $stmtTx = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmtTx->execute([$userId]);
    $transactions = $stmtTx->fetchAll();

    // 3. Recent Game Logs (Last 20)
    $stmtLogs = $pdo->prepare("SELECT * FROM game_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmtLogs->execute([$userId]);
    $gameLogs = $stmtLogs->fetchAll();

    echo json_encode([
        'status' => 'success',
        'user' => $user,
        'recent_transactions' => $transactions,
        'recent_games' => $gameLogs
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch user details']);
}
?>