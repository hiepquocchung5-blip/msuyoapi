<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

// 1. Authenticate
$user = authenticate($pdo);
$userId = $user['id'];

// 2. Verify Agent Status
if ($user['is_agent'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied. Agent status required.']);
    exit;
}

try {
    // 3. Fetch Downline (Users referred by this agent)
    $stmtDownline = $pdo->prepare("
        SELECT id, username, phone, created_at, 
               (SELECT SUM(amount) FROM transactions WHERE user_id = users.id AND type = 'deposit' AND status = 'approved') as total_deposited
        FROM users 
        WHERE referrer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmtDownline->execute([$userId]);
    $downline = $stmtDownline->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Recent Transfers (Outbound)
    $stmtTransfers = $pdo->prepare("
        SELECT t.*, u.username as target_user 
        FROM transactions t
        LEFT JOIN users u ON t.admin_note LIKE CONCAT('%User #', u.id, '%') -- Simple linkage via note parsing or specialized query
        WHERE t.user_id = ? AND t.type = 'withdraw' AND t.payment_method_id IS NULL -- Internal transfers
        ORDER BY t.created_at DESC 
        LIMIT 20
    ");
    // Note: A more robust schema would link transfer_target_id, but we use existing structure
    $stmtTransfers->execute([$userId]);
    $transfers = $stmtTransfers->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'agent_profile' => [
            'commission' => (float)$user['commission_balance'],
            'referral_code' => $user['referral_code'],
            'total_referrals' => count($downline)
        ],
        'downline' => $downline,
        'history' => $transfers
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load agent data']);
}
?>