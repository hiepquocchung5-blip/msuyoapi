<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Authenticate and get fresh user data from DB
$user = authenticate($pdo);

// Decode JSON fields for frontend consumption
$ownedIslands = json_decode($user['owned_islands']) ?? [];

// Calculate progress to next level
// Example formula: Next Level XP = Current Level * 100
$nextLevelXp = $user['level'] * 100;
$progressPercent = ($user['xp'] / $nextLevelXp) * 100;

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
    ]
];

echo json_encode($response);
?>