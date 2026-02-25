<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 
require_once __DIR__ . '/../../utils/security.php'; 

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

    // 8. 3x3 SLOT RNG ENGINE
    // Paytable with micro-wins
    $payTable = [
        1 => 50,    // Big Bonus (7)
        2 => 20,    // Reg Bonus
        3 => 10,    // Bar
        4 => 5,     // Bell
        5 => 1,     // Watermelon
        6 => 0.1,   // Cherry
        7 => 0.01   // Replay / Junk (Micro Win)
    ];

    // Defined Paylines (Indices of 9-item array 0-8)
    $paylines = [
        0 => [0, 1, 2], // Top Row
        1 => [3, 4, 5], // Middle Row
        2 => [6, 7, 8], // Bottom Row
        3 => [0, 4, 8], // Diagonal \
        4 => [6, 4, 2]  // Diagonal /
    ];

    // Adjust win frequency for multiple lines
    $winChance = $rtp * 4.5; // ~40-45% hit rate due to micro wins
    $rng = rand(1, 10000);
    $isHit = $rng <= $winChance;

    $result = array_fill(0, 9, 0);
    $winningLines = [];
    $spinWin = 0;
    $isTeaser = false;

    if ($isHit) {
        // Populate random grid first
        for ($i=0; $i<9; $i++) $result[$i] = rand(1, 7);
        
        // Force a win on 1 or 2 lines
        $numLinesToWin = (rand(1, 100) > 85) ? 2 : 1;
        $chosenLines = array_rand($paylines, $numLinesToWin);
        if(!is_array($chosenLines)) $chosenLines = [$chosenLines];

        foreach($chosenLines as $lineIdx) {
            // Determine symbol for this line based on weights
            $symRng = rand(1, 100);
            if ($symRng <= 1) $sym = 1;         // 1%
            elseif ($symRng <= 4) $sym = 2;     // 3%
            elseif ($symRng <= 10) $sym = 3;    // 6%
            elseif ($symRng <= 25) $sym = 4;    // 15%
            elseif ($symRng <= 50) $sym = 5;    // 25%
            elseif ($symRng <= 80) $sym = 6;    // 30%
            else $sym = 7;                      // 20%

            // Apply to grid
            foreach($paylines[$lineIdx] as $pos) {
                $result[$pos] = $sym;
            }
        }

        // Recalculate exact payouts safely by scanning the final generated grid
        $spinWin = 0;
        foreach ($paylines as $idx => $line) {
            if ($result[$line[0]] == $result[$line[1]] && $result[$line[1]] == $result[$line[2]]) {
                $winningLines[] = $idx;
                $spinWin += $betAmount * $payTable[$result[$line[0]]];
            }
        }
        
        // Security Alert for massive wins
        if ($spinWin > 500000) {
            $pdo->prepare("INSERT INTO security_alerts (user_id, risk_level, event_type, details) VALUES (?, 'medium', 'HIGH_WIN', ?)")
                ->execute([$userId, "Won " . number_format($spinWin)]);
        }

    } else {
        // Force non-winning grid
        do {
            for($i=0; $i<9; $i++) $result[$i] = rand(1, 7);
            $hasWin = false;
            foreach($paylines as $l) {
                if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) {
                    $hasWin = true; break;
                }
            }
        } while($hasWin);
        
        // Teaser Detection (e.g. 2 out of 3 matching on middle row)
        if ($result[3] == $result[4] && rand(1, 10) > 5) {
            $isTeaser = true;
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

    // 13. Logging
    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, last_played_at = NOW() WHERE id = ?")->execute([$totalWin, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned, is_gamble_win) VALUES (?, ?, ?, ?, ?, ?, 0)")->execute([$userId, $machineId, $betAmount, $totalWin, json_encode($result), $xpGain]);

    $pdo->commit();

    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'stops' => $result,
        'winning_lines' => $winningLines,
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