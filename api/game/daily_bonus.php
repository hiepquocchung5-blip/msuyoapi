<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// 1. Authenticate
$user = authenticate($pdo);
$userId = $user['id'];

// Reward Structure (Day => Amount in MMK)
$rewards = [
    1 => 100, 
    2 => 200, 
    3 => 300, 
    4 => 400, 
    5 => 500, 
    6 => 1000, 
    7 => 5000
];

$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- GET: CHECK STATUS ---
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT * FROM daily_rewards WHERE user_id = ?");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $currentStreak = 1;
        $canClaim = true;
        $status = 'active'; // active, claimed, missed

        if ($streak) {
            if ($streak['last_claim_date'] === $today) {
                $currentStreak = $streak['streak_days'];
                $canClaim = false;
                $status = 'claimed';
            } elseif ($streak['last_claim_date'] === $yesterday) {
                $currentStreak = ($streak['streak_days'] >= 7) ? 1 : $streak['streak_days'] + 1;
            } else {
                // Missed a day, reset pending
                $currentStreak = 1;
                $status = 'missed';
            }
        }

        echo json_encode([
            'status' => 'success',
            'streak_day' => (int)$currentStreak,
            'can_claim' => $canClaim,
            'reward_amount' => $rewards[$currentStreak],
            'next_reward' => $rewards[($currentStreak >= 7 ? 1 : $currentStreak + 1)],
            'rewards_table' => $rewards
        ]);
        exit;
    }

    // --- POST: CLAIM REWARD ---
    if ($method === 'POST') {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM daily_rewards WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $currentStreak = 1;

        if (!$streak) {
            // First time
            $pdo->prepare("INSERT INTO daily_rewards (user_id, streak_days, last_claim_date) VALUES (?, 1, ?)")
                ->execute([$userId, $today]);
        } else {
            if ($streak['last_claim_date'] === $today) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Daily bonus already claimed for today.']);
                exit;
            }

            if ($streak['last_claim_date'] === $yesterday) {
                $currentStreak = $streak['streak_days'] + 1;
                if ($currentStreak > 7) $currentStreak = 1; 
            } else {
                $currentStreak = 1;
            }

            $pdo->prepare("UPDATE daily_rewards SET streak_days = ?, last_claim_date = ? WHERE user_id = ?")
                ->execute([$currentStreak, $today, $userId]);
        }

        $amount = $rewards[$currentStreak];
        
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $userId]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")
            ->execute([$userId, $amount, "Daily Login Bonus: Day $currentStreak"]);

        $pdo->commit();

        // Get new balance
        $stmtBal = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmtBal->execute([$userId]);
        $newBalance = $stmtBal->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'message' => 'Daily bonus claimed!',
            'day' => $currentStreak,
            'reward' => $amount,
            'new_balance' => (float)$newBalance
        ]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>