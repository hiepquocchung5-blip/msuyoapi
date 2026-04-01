<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"));
$type = $data->type ?? 'standard'; // 'standard' (1000) or 'premium' (5000)

$cost = ($type === 'premium') ? 5000 : 1000;

if ($user['balance'] < $cost) {
    http_response_code(400); echo json_encode(['error' => 'Insufficient funds']); exit;
}

try {
    $pdo->beginTransaction();

    // 1. Deduct Cost & Increment Pity
    $pdo->prepare("UPDATE users SET balance = balance - ?, gacha_pity = gacha_pity + 1 WHERE id = ?")->execute([$cost, $userId]);
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'withdraw', ?, 'approved', 'Gacha Summon')")
        ->execute([$userId, $cost]);

    // Fetch updated pity
    $stmtPity = $pdo->prepare("SELECT gacha_pity FROM users WHERE id = ?");
    $stmtPity->execute([$userId]);
    $currentPity = (int)$stmtPity->fetchColumn();

    // 2. Fetch Pool (All Characters)
    // In a real app, you'd filter by 'is_in_pool' column
    $stmtChars = $pdo->query("SELECT * FROM characters");
    $allChars = $stmtChars->fetchAll(PDO::FETCH_ASSOC);

    // 3. RNG Logic & Pity Trigger
    $rand = rand(1, 100);
    $rarity = 'R';
    
    // THE SINGULARITY DRIVE (100 Pity UR Guarantee)
    if ($currentPity >= 100) {
        $rarity = 'UR';
        $pdo->prepare("UPDATE users SET gacha_pity = 0 WHERE id = ?")->execute([$userId]);
        $currentPity = 0;
    } else {
        if ($type === 'premium') {
            // Better odds for premium
            if ($rand <= 5) $rarity = 'UR';
            elseif ($rand <= 25) $rarity = 'SSR';
            elseif ($rand <= 60) $rarity = 'SR';
        } else {
            if ($rand <= 1) $rarity = 'UR';
            elseif ($rand <= 10) $rarity = 'SSR';
            elseif ($rand <= 40) $rarity = 'SR';
        }
        
        // Reset pity if they naturally pull a UR
        if ($rarity === 'UR') {
            $pdo->prepare("UPDATE users SET gacha_pity = 0 WHERE id = ?")->execute([$userId]);
            $currentPity = 0;
        }
    }

    // Filter pool by rolled rarity
    $pool = array_filter($allChars, function($c) use ($rarity) {
        $meta = json_decode($c['svg_data'], true);
        return ($meta['rarity'] ?? 'R') === $rarity;
    });

    // Fallback if pool empty (shouldn't happen with full data)
    if (empty($pool)) $pool = $allChars;

    // Pick Winner
    $wonChar = $pool[array_rand($pool)];
    $charKey = $wonChar['char_key'];

    // 4. Record Win
    // Check if duplicate
    $check = $pdo->prepare("SELECT id FROM user_characters WHERE user_id = ? AND char_key = ?");
    $check->execute([$userId, $charKey]);
    
    $isDuplicate = $check->rowCount() > 0;
    
    if (!$isDuplicate) {
        $pdo->prepare("INSERT INTO user_characters (user_id, char_key) VALUES (?, ?)")->execute([$userId, $charKey]);
        $msg = "NEW CHARACTER UNLOCKED!";
    } else {
        // Duplicate Logic: Give Fragments or XP (Simulated here with small cashback)
        $refund = $cost * 0.2; // 20% cashback
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$refund, $userId]);
        $msg = "Duplicate! Converted to " . number_format($refund) . " MMK.";
    }

    $pdo->commit();

    // Get updated balance
    $newBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'character' => $wonChar,
        'is_new' => !$isDuplicate,
        'rarity' => $rarity,
        'message' => $msg,
        'new_balance' => (float)$newBal,
        'pity_count' => $currentPity
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>