<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// 1. Authenticate
$user = authenticate($pdo);
$userId = $user['id'];

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    // 2. Fetch Downline (Users who used my code)
    // Limit to last 50 for performance
    $stmt = $pdo->prepare("
        SELECT username, created_at, 
               (SELECT SUM(amount) FROM transactions WHERE user_id = users.id AND type = 'deposit' AND status = 'approved') as total_deposited
        FROM users 
        WHERE referrer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get Totals
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referrer_id = ?");
    $countStmt->execute([$userId]);
    $totalCount = $countStmt->fetchColumn();

    // 4. Get Lifetime Commission Earned
    $lifeStmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'commission'");
    $lifeStmt->execute([$userId]);
    $lifetimeEarnings = $lifeStmt->fetchColumn() ?: 0;

    echo json_encode([
        'status' => 'success',
        'referral_code' => $user['referral_code'],
        'current_commission' => (float)$user['commission_balance'],
        'lifetime_commission' => (float)$lifetimeEarnings,
        'total_invites' => (int)$totalCount,
        'recent_referrals' => $referrals
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>