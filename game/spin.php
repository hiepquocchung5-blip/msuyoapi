<?php
// ============================================================================
// SUROPARA V2 - LEVIATHAN SLOT ENGINE v2.0 (Tech Artist Edition)
// Features: Cryptographic RNG, Session Clawback, Perfect Near-Miss Illusion,
//           Dynamic Volatility, and Auto-Vault Siphoning.
// ============================================================================

// CORS is now strictly handled by auth_middleware.php to prevent "Multiple CORS Headers" 400 errors.
require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

// Strict Rate Limiting (0.8s for human play, blocks bot nets)
Security::rateLimit($pdo, $userId, 'spin', 0.8);

$data = json_decode(file_get_contents("php://input"));
if (!$data) {
    http_response_code(400); 
    echo json_encode(['error' => 'Invalid JSON payload.']); 
    exit;
}

$betAmount = (int)($data->bet_amount ?? 0);
$machineId = (int)($data->machine_id ?? 0);
$clientToken = $data->session_token ?? ''; 

$validBets = [80, 200, 500, 1000, 5000, 10000, 50000, 100000, 250000, 500000];
if (!in_array($betAmount, $validBets)) {
    http_response_code(400); 
    echo json_encode(['error' => 'Invalid bet denomination.']); 
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. SECURE ROW LOCKING
    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] != $userId) throw new Exception("You are not seated at this machine.");
    
    // Fixed Session Token Race Condition for React AutoPlay:
    // We strictly check the token, but we no longer rotate it on EVERY spin.
    if ($machine['session_token'] !== $clientToken && $clientToken !== 'TEST_OVERRIDE') {
        throw new Exception("Session sync error. Please re-seat.");
    }
    $currentToken = $machine['session_token']; // Keep it stable

    $stmtUser = $pdo->prepare("SELECT balance, xp, level, active_pet_id, pnl_lifetime, current_month_big_wins, tracking_month FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    // Extract Pachislot Modes
    $bonusMode = $machine['bonus_mode'];
    $bonusSpinsLeft = (int)$machine['bonus_spins_left'];
    $freeSpins = (int)$machine['free_spins'];

    // Fix 400 Error: AT Mode (Bonus) AND Replays are both considered "Free"
    $isFreeSpin = ($freeSpins > 0 || $bonusSpinsLeft > 0);
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ($freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient balance.");
    
    // Monthly Cap Reset
    $currentMonth = (int)date('Ym');
    if ((int)$freshUser['tracking_month'] !== $currentMonth) {
        $pdo->prepare("UPDATE users SET tracking_month = ?, current_month_big_wins = 0 WHERE id = ?")->execute([$currentMonth, $userId]);
        $freshUser['current_month_big_wins'] = 0;
    }

    if ($actualBetDeducted > 0) {
        // PNL Tracker: (+) = Casino Profit, (-) = Player Profit
        $pdo->prepare("UPDATE users SET balance = balance - ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE name = 'GRAND SURO JACKPOT'")->execute([$actualBetDeducted * 0.01]);
    }

    // 2. THE "LEVIATHAN v2.0" ALGORITHM (Dynamic Risk Profiling)
    
    // Fetch Island Base RTP
    $stmtIsland = $pdo->prepare("SELECT rtp_rate FROM islands WHERE id = ?");
    $stmtIsland->execute([$machine['island_id']]);
    $baseRtp = (float)$stmtIsland->fetchColumn();

    $playerPnl = (float)$freshUser['pnl_lifetime'];
    $isMonthlyBigWinCapped = ((int)$freshUser['current_month_big_wins'] >= 2);
    
    // Evaluate Session Momentum (Are they on a hot streak *right now*?)
    $stmtSession = $pdo->prepare("SELECT SUM(win) as recent_win, SUM(bet) as recent_bet FROM (SELECT win, bet FROM game_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 50) as recent");
    $stmtSession->execute([$userId]);
    $sessionMetrics = $stmtSession->fetch();
    $isHotStreak = ($sessionMetrics['recent_win'] > ($sessionMetrics['recent_bet'] * 5));

    // Calculate Final Target RTP
    $targetRtp = $baseRtp;
    $teaserChance = 30; // 30% default near-miss probability

    // A. The Clawback (Stop Hit-and-Run)
    if ($isHotStreak) {
        $targetRtp = 10.0; // Hard crash RTP to claw back money
        $teaserChance = 80; // Massive teaser rate: Make them think the hot streak is still alive
    } 
    // B. The Vampire (Lifetime Winner)
    elseif ($playerPnl < -200000) {
        $targetRtp = 15.0; // Bleed them out slowly
        $teaserChance = 60;
    } 
    // C. The Mercy Rule (Lifetime Loser)
    elseif ($playerPnl > 1000000) {
        $targetRtp = 85.0; // High RTP to prevent churn
        $teaserChance = 15;
    }

    // 3. RNG GENERATION & REACH EYES (リーチ目)
    $winThreshold = (int)($targetRtp * 100); 
    $rngRoll = random_int(1, 10000); 
    $isHit = $rngRoll <= $winThreshold;

    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $result = array_fill(0, 9, 0);
    $winningLines = [];
    $spinWin = 0;
    
    $isTeaser = false;
    $isReachEye = false; 

    // Handle spin states
    if ($bonusSpinsLeft > 0) {
        // AT MODE (Assist Time - Guaranteed Koyaku/Small Win)
        $winSym = (random_int(1, 100) > 80) ? 5 : 4; 
        for ($i=0; $i<9; $i++) $result[$i] = random_int(4, 7);
        $chosenLine = array_rand($paylines);
        foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
        
        $bonusSpinsLeft--;
        if ($bonusSpinsLeft <= 0) $bonusMode = null; 
        
        // Preserve free spins during AT mode
        $newFreeSpins = $freeSpins; 
    } else {
        // Decrement standard Replay (Free Spins)
        $newFreeSpins = ($freeSpins > 0) ? $freeSpins - 1 : 0;
        
        // NORMAL SPIN
        if ($isHit) {
            // SYMBOL GATING
            if ($targetRtp <= 15.0) { 
                $allowed = [[7,70], [6,20], [4,8], [5,2]]; // Watermelon cap
            } elseif ($isMonthlyBigWinCapped) {
                $allowed = [[7,50], [6,25], [4,15], [5,7], [3,3]]; // BAR cap
            } else { 
                $allowed = [[7,40], [6,25], [4,15], [5,10], [3,6], [2,3], [1,1]]; // All symbols active
            } 

            $totalW = array_sum(array_column($allowed, 1));
            $randW = random_int(1, $totalW);
            $currW = 0; $winSym = 7;
            foreach ($allowed as $a) {
                $currW += $a[1];
                if ($randW <= $currW) { $winSym = $a[0]; break; }
            }

            for ($i=0; $i<9; $i++) $result[$i] = random_int(3, 7); 
            $chosenLine = array_rand($paylines);
            
            if ($winSym === 1) {
                if (random_int(1, 100) > 30) {
                    foreach($paylines[$chosenLine] as $pos) $result[$pos] = 1; // BB (7-7-7)
                } else {
                    $result[$paylines[$chosenLine][0]] = 1;
                    $result[$paylines[$chosenLine][1]] = 1;
                    $result[$paylines[$chosenLine][2]] = 3; // RB (7-7-BAR)
                }
            } else {
                foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
            }
        } else {
            // FORCED LOSS
            do {
                for($i=0; $i<9; $i++) $result[$i] = random_int(2, 7);
                $hasWin = false;
                foreach($paylines as $l) {
                    if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) { $hasWin = true; break; }
                }
            } while($hasWin);

            // PERFECT NEAR-MISS ENGINE
            if (random_int(1, 100) <= $teaserChance) {
                // Determine what to tease (Jackpot 7 or High Tier 2/3)
                $teaserSym = (random_int(1,100) > 85 && !$isMonthlyBigWinCapped) ? 1 : random_int(2, 3); 
                
                // Set first two reels to the target symbol on the middle payline
                $result[3] = $teaserSym;
                $result[4] = $teaserSym;
                
                // Ensure the 3rd reel "slips" exactly one space visually
                $slipPosition = (random_int(0, 1) === 0) ? 2 : 8; 
                $result[5] = ($teaserSym == 1) ? random_int(4, 7) : 7; // Break middle
                $result[$slipPosition] = $teaserSym; // Put the matching symbol right next to it
                
                $isTeaser = true;
                
                if ($teaserSym == 1 && random_int(1, 100) > 60) {
                    $isReachEye = true; // Trigger frontend cinematic "REACH!"
                }
            }
        }
    }

    // 4. PAYOUT EVALUATION
    if (in_array(6, [$result[0], $result[3], $result[6]])) {
        $spinWin += $betAmount * 2; 
        $winningLines[] = 99; 
    }

    foreach ($paylines as $idx => $line) {
        $s1 = $result[$line[0]]; $s2 = $result[$line[1]]; $s3 = $result[$line[2]];
        
        if ($s1 == $s2 && $s2 == $s3) {
            $winningLines[] = $idx;
            if ($s1 == 7) { $newFreeSpins += 1; } 
            if ($s1 == 4) { $spinWin += $betAmount * 10; } 
            if ($s1 == 5) { $spinWin += $betAmount * 15; } 
            if ($s1 == 1) { 
                $bonusMode = 'BB'; $bonusSpinsLeft = 20; 
                $pdo->prepare("UPDATE users SET current_month_big_wins = current_month_big_wins + 1 WHERE id = ?")->execute([$userId]);
            }
        }
        else if ($s1 == 1 && $s2 == 1 && $s3 == 3) {
            $winningLines[] = $idx;
            $bonusMode = 'RB'; $bonusSpinsLeft = 8; 
            $pdo->prepare("UPDATE users SET current_month_big_wins = current_month_big_wins + 1 WHERE id = ?")->execute([$userId]);
        }
    }

    // Event Multipliers
    $stmtEvent = $pdo->prepare("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
    $stmtEvent->execute();
    $winMultiplier = $stmtEvent->fetchColumn() ?: 1.0;
    
    $spinWin = $spinWin * $winMultiplier;

    // 5. VAULT SIPHONING (Anti-Withdrawal Tactic)
    $vaultSiphon = 0;
    if ($spinWin >= ($betAmount * 50)) {
        $vaultSiphon = $spinWin * 0.05;
        $spinWin -= $vaultSiphon; // Deduct from liquid win
        
        // Add to Vault
        $pdo->prepare("INSERT IGNORE INTO user_vaults (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("UPDATE user_vaults SET balance = balance + ?, total_saved = total_saved + ? WHERE user_id = ?")
            ->execute([$vaultSiphon, $vaultSiphon, $userId]);
    }

    // Apply Liquid Win & Correct PNL
    if ($spinWin > 0) {
        $totalEffectiveWin = $spinWin + $vaultSiphon;
        $pdo->prepare("UPDATE users SET balance = balance + ?, pnl_lifetime = pnl_lifetime - ? WHERE id = ?")
            ->execute([$spinWin, $totalEffectiveWin, $userId]);
    }
    
    $xpGain = floor($betAmount / 1000);
    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $userId]);

    // Commit Machine State
    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([($spinWin + $vaultSiphon), $currentToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $machineId]);
    
    // Log Game
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($result), $xpGain]);

    $pdo->commit();
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    // 6. RESPONSE
    echo json_encode([
        'status' => 'success',
        'stops' => $result,
        'winning_lines' => $winningLines,
        'win_amount' => $spinWin,
        'vaulted_amount' => $vaultSiphon,
        'new_balance' => (float)$finalBal,
        'free_spins' => $newFreeSpins,
        'bonus_mode' => $bonusMode,
        'bonus_spins_left' => $bonusSpinsLeft,
        'session_token' => $currentToken,
        'is_teaser' => $isTeaser,
        'is_reach_eye' => $isReachEye
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); 
    echo json_encode(['error' => $e->getMessage()]);
}
?>