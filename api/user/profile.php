<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

header("Access-Control-Allow-Origin: https://suropara.com");
header("Content-Type: application/json; charset=UTF-8");

try {
    $user = authenticate($pdo);
    $userId = $user['id'];

    // Safe JSON decode to prevent type errors on fresh accounts
    $ownedIslands = !empty($user['owned_islands']) ? json_decode($user['owned_islands'], true) : [1, 2];
    if (!is_array($ownedIslands)) {
        $ownedIslands = [1, 2];
    }

    // Safe Level Progress Math (Prevents division by zero)
    $level = (int)($user['level'] ?? 1);
    $xp = (int)($user['xp'] ?? 0);
    $nextLevelXp = $level * 100;
    $progressPercent = $nextLevelXp > 0 ? ($xp / $nextLevelXp) * 100 : 0;

    // Calculate Lifetime Deposits for Withdrawal Tiers
    $stmtDep = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'deposit' AND status = 'approved'");
    $stmtDep->execute([$userId]);
    $totalDeposited = (float)$stmtDep->fetchColumn();

    // Fetch Current Withdrawal Limit based on Deposit
    $stmtLimit = $pdo->prepare("SELECT max_withdraw FROM withdrawal_limits WHERE deposit_amount <= ? ORDER BY deposit_amount DESC LIMIT 1");
    $stmtLimit->execute([$totalDeposited]);
    $currentLimit = (float)$stmtLimit->fetchColumn();

    $response = [
        'status' => 'success',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'phone' => $user['phone'],
            'balance' => (float)$user['balance'],
            'level' => $level,
            'xp' => $xp,
            'next_level_xp' => $nextLevelXp,
            'progress_percent' => min(100, $progressPercent),
            'active_pet_id' => $user['active_pet_id'] ?? 'luna',
            'owned_islands' => $ownedIslands,
            'referral_code' => $user['referral_code'] ?? '',
            'gacha_pity' => (int)($user['gacha_pity'] ?? 0),
            
            // Financial Stats
            'total_deposited' => $totalDeposited,
            'current_withdraw_limit' => $currentLimit
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Failsafe catch block to guarantee JSON response format even on fatal errors
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to load profile data.'
    ]);
}
?>