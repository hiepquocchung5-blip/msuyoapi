<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

// 1. Authenticate User
$user = authenticate($pdo);
$userId = $user['id'];

// 2. Rate Limiting (Anti-Bot)
Security::rateLimit($pdo, $userId, 'spin', 0.5);

$data = json_decode(file_get_contents("php://input"));
$betAmount = (int)($data->bet_amount ?? 0);
$machineId = (int)($data->machine_id ?? 0);
$clientToken = $data->session_token ?? ''; 

// 3. Bet Validation
Security::validateBet($betAmount);

if ($user['balance'] < $betAmount) {
    http_response_code(400); echo json_encode(['error' => 'Insufficient funds']); exit;
}

try {
    $pdo->beginTransaction();

    // 4. Session/Machine Checks (Anti-Replay)
    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] !== $userId) throw new Exception("You are not seated at this machine.");
    if ($machine['session_token'] !== $clientToken) throw new Exception("Session Expired.");

    $nextToken = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE machines SET session_token = ? WHERE id = ?")->execute([$nextToken, $machineId]);

    // 5. Config Fetch
    $stmtIsland = $pdo->prepare("SELECT rtp_rate, hostess_char_id FROM islands WHERE id = ?");
    $stmtIsland->execute([$machine['island_id']]);
    $island = $stmtIsland->fetch();
    $rtp = $island['rtp_rate'];

    // 6. Deduct Balance
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$betAmount, $userId]);
    $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE name = 'GRAND SURO JACKPOT'")->execute([$betAmount * 0.01]);

    // 7. Jackpot Check
    $jackpotWin = 0; $jackpotWon = false;
    if (rand(1, 1000000) === 777777) {
        $stmtJ = $pdo->query("SELECT current_amount FROM global_jackpots WHERE name = 'GRAND SURO JACKPOT' FOR UPDATE");
        $jpAmount = $stmtJ->fetchColumn();
        if ($jpAmount > 0) {
            $jackpotWin = $jpAmount;
            $jackpotWon = true;
            $pdo->prepare("UPDATE global_jackpots SET current_amount = 5000000, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE name = 'GRAND SURO JACKPOT'")->execute([$user['username'], $jackpotWin]);
        }
    }

    // 8. Slot RNG
    $payTable = [1 => 100, 2 => 50, 3 => 20, 4 => 10, 5 => 5, 6 => 2, 7 => 0.5];
    $winChance = $rtp * 20; 
    $rng = rand(1, 10000);
    $isLineWin = $rng <= $winChance;
    $result = []; $spinWin = 0; $isTeaser = false;

    if ($isLineWin) {
        $rand = rand(1, 100);
        if ($rand <= 1) $sym = 1; elseif ($rand <= 3) $sym = 2; elseif ($rand <= 8) $sym = 3; 
        elseif ($rand <= 15) $sym = 4; elseif ($rand <= 30) $sym = 5; elseif ($rand <= 55) $sym = 6; else $sym = 7;
        $result = [$sym, $sym, $sym];
        $spinWin = $betAmount * $payTable[$sym];
        
        if ($spinWin > 500000) {
            $pdo->prepare("INSERT INTO security_alerts (user_id, risk_level, event_type, details) VALUES (?, 'medium', 'HIGH_WIN', ?)")
                ->execute([$userId, "Won " . number_format($spinWin)]);
        }
    } else {
        if (rand(1, 100) <= 30) {
            $isTeaser = true;
            $teaserSym = rand(1, 4); 
            $missSym = ($teaserSym % 7) + 1; 
            $result = [$teaserSym, $teaserSym, $missSym];
        } else {
            $r1 = rand(1, 7); $r2 = rand(1, 7);
            do { $r3 = rand(1, 7); } while ($r3 === $r1);
            $result = [$r1, $r2, $r3];
        }
    }

    $totalWin = $spinWin + $jackpotWin;

    // 9. Mystery Bonus
    $mysteryItem = null;
    if (rand(1, 100) === 50) {
        $bonusType = ['freespin', 'multiplier_x2', 'cashback_10'][rand(0, 2)];
        $pdo->prepare("INSERT INTO user_items (user_id, type, amount, expires_at) VALUES (?, ?, 5, DATE_ADD(NOW(), INTERVAL 24 HOUR))")->execute([$userId, $bonusType]);
        $mysteryItem = ['type' => $bonusType, 'name' => strtoupper($bonusType), 'message' => "Mystery Bonus!"];
    }

    // 10. Process Win
    if ($totalWin > 0) {
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$totalWin, $userId]);
    }

    // 11. XP & Leveling
    $xpGain = floor($betAmount / 1000);
    if ($user['active_pet_id'] === $island['hostess_char_id']) $xpGain = ceil($xpGain * 1.1);
    
    $newXp = $user['xp'] + $xpGain;
    $newLevel = $user['level'];
    $levelUpData = null;
    $reqXp = $newLevel * 100;
    
    if ($newXp >= $reqXp) {
        $newLevel++;
        $reward = $newLevel * 1000;
        $pdo->prepare("UPDATE users SET balance = balance + ?, level = ?, xp = ? WHERE id = ?")->execute([$reward, $newLevel, $newXp, $userId]);
        $levelUpData = ['new_level' => $newLevel, 'reward' => $reward];
    } else {
        $pdo->prepare("UPDATE users SET xp = ? WHERE id = ?")->execute([$newXp, $userId]);
    }

    // 12. TOURNAMENT LOGIC
    // Check if user is in any active tournament
    $stmtTourney = $pdo->prepare("
        SELECT t.id, t.spin_limit, e.spins_used 
        FROM tournaments t
        JOIN tournament_entries e ON t.id = e.tournament_id
        WHERE e.user_id = ? AND t.status = 'active' AND t.end_time > NOW()
    ");
    $stmtTourney->execute([$userId]);
    $activeTourneys = $stmtTourney->fetchAll();

    foreach ($activeTourneys as $t) {
        if ($t['spins_used'] < $t['spin_limit']) {
            // Update Score (Total Win) and Spin Count
            $pdo->prepare("UPDATE tournament_entries SET current_score = current_score + ?, spins_used = spins_used + 1 WHERE tournament_id = ? AND user_id = ?")
                ->execute([$totalWin, $t['id'], $userId]);
        }
    }

    // 13. Logging
    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, last_played_at = NOW() WHERE id = ?")->execute([$totalWin, $machineId]);
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned, is_gamble_win) VALUES (?, ?, ?, ?, ?, ?, 0)")->execute([$userId, $machineId, $betAmount, $totalWin, json_encode($result), $xpGain]);

    $pdo->commit();

    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'stops' => $result,
        'win_amount' => $totalWin,
        'new_balance' => (float)$finalBal,
        'xp_gained' => $xpGain,
        'level_up' => $levelUpData,
        'mystery_item' => $mysteryItem,
        'is_jackpot' => $jackpotWon,
        'is_teaser' => $isTeaser,
        'session_token' => $nextToken
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>