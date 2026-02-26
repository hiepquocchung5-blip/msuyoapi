<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

// Decode JSON fields
$ownedIslands = json_decode($user['owned_islands']) ?? [];

// Calculate Level Progress
$nextLevelXp = $user['level'] * 100;
$progressPercent = ($user['xp'] / $nextLevelXp) * 100;

// NEW: Calculate Lifetime Deposits for Withdrawal Tiers
$stmtDep = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'deposit' AND status = 'approved'");
$stmtDep->execute([$userId]);
$totalDeposited = (float)$stmtDep->fetchColumn();

// Fetch Current Withdrawal Limit based on Deposit
$stmtLimit = $pdo->prepare("SELECT max_withdraw FROM withdrawal_limits WHERE deposit_amount <= ? ORDER BY deposit_amount DESC LIMIT 1");
$stmtLimit->execute([$totalDeposited]);
$currentLimit = $stmtLimit->fetchColumn() ?: 0; // Default 0 if no deposits

$response = [
    'status' => 'success',
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'phone' => $user['phone'],
        'balance' => (float)$user['balance'],
        'level' => (int)$user['level'],
        'xp' => (int)$user['xp'],
        'next_level_xp' => $nextLevelXp,
        'progress_percent' => min(100, $progressPercent),
        'active_pet_id' => $user['active_pet_id'],
        'owned_islands' => $ownedIslands,
        'referral_code' => $user['referral_code'],
        'gacha_pity' => (int)($user['gacha_pity'] ?? 0),
        
        // Financial Stats
        'total_deposited' => $totalDeposited,
        'current_withdraw_limit' => (float)$currentLimit
    ]
];

echo json_encode($response);
?>