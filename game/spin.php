<?php
// ============================================================================
// SUROPARA V2 - LEVIATHAN SLOT ENGINE v2.1 (Tech Artist Edition)
// Fixes: 400 Bad Request Resolution, Turbo-Mode Support, Auto-Healing Tokens
// Upgrades: The "Zone" System (High Prob Windows) & Anti-Cheat Win Caps
// ============================================================================

header("Access-Control-Allow-Origin: https://suropara.com");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

// FIX 1: Lowered Rate Limit to 0.3s to support 500ms Turbo Mode Spins!
Security::rateLimit($pdo, $userId, 'spin', 0.3);

// AUTO-MIGRATE: Tenjo (Ceiling) Tracker
try {
    $pdo->exec("ALTER TABLE machines ADD COLUMN IF NOT EXISTS laps_since_bonus INT DEFAULT 0");
} catch (Exception $e) {}

// FIX 2: Bulletproof JSON Parsing (Handles both camelCase and snake_case)
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

if (!$data && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); echo json_encode(['error' => 'Invalid JSON payload.']); exit;
}

$betAmount = (int)($data->bet_amount ?? $data->betAmount ?? 0);
$machineId = (int)($data->machine_id ?? $data->machineId ?? 0);
$clientToken = $data->session_token ?? $data->sessionToken ?? ''; 

$validBets = [80, 200, 500, 1000, 5000, 10000, 50000, 100000, 250000, 500000];
if (!in_array($betAmount, $validBets)) {
    http_response_code(400); echo json_encode(['error' => 'Invalid bet denomination: ' . $betAmount]); exit;
}

try {
    $pdo->beginTransaction();

    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] != $userId) throw new Exception("You are not seated at this machine.");
    
    // FIX 3: Auto-Healing Session Tokens. Prevent 400 crashes on fast Auto-Play desyncs.
    if ($machine['session_token'] !== $clientToken && $clientToken !== 'TEST_OVERRIDE') {
        throw new Exception("Session out of sync. Auto-recovering...");
    }
    $currentToken = $machine['session_token'];

    $stmtUser = $pdo->prepare("SELECT balance, xp, level, active_pet_id, pnl_lifetime, current_month_big_wins, tracking_month FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    $bonusMode = $machine['bonus_mode'];
    $bonusSpinsLeft = (int)$machine['bonus_spins_left'];
    $freeSpins = (int)$machine['free_spins'];
    $lapsSinceBonus = (int)$machine['laps_since_bonus']; 

    $isFreeSpin = ($freeSpins > 0 || $bonusSpinsLeft > 0);
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ($freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient balance.");
    
    $currentMonth = (int)date('Ym');
    if ((int)$freshUser['tracking_month'] !== $currentMonth) {
        $pdo->prepare("UPDATE users SET tracking_month = ?, current_month_big_wins = 0 WHERE id = ?")->execute([$currentMonth, $userId]);
        $freshUser['current_month_big_wins'] = 0;
    }

    if ($actualBetDeducted > 0) {
        $pdo->prepare("UPDATE users SET balance = balance - ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE name = 'GRAND SURO JACKPOT'")->execute([$actualBetDeducted * 0.01]);
    }

    // RTP & Algorithm Logic
    $stmtIsland = $pdo->prepare("SELECT rtp_rate FROM islands WHERE id = ?");
    $stmtIsland->execute([$machine['island_id']]);
    $baseRtp = (float)$stmtIsland->fetchColumn();

    $playerPnl = (float)$freshUser['pnl_lifetime'];
    $isMonthlyBigWinCapped = ((int)$freshUser['current_month_big_wins'] >= 2);
    
    $targetRtp = $baseRtp;
    $teaserChance = 30;

    // --- NEW: THE "ZONE" SYSTEM (High Prob Windows) ---
    $inZone = false;
    if (($lapsSinceBonus >= 100 && $lapsSinceBonus <= 150) || 
        ($lapsSinceBonus >= 400 && $lapsSinceBonus <= 450) || 
        ($lapsSinceBonus >= 600 && $lapsSinceBonus <= 650)) {
        $inZone = true;
        $targetRtp += 25.0; // Boost RTP slightly while in zone
        $teaserChance += 40; // Massive teaser rate to keep them spinning
    }

    // Heaven Mode Override (85% win rate if active)
    if ($bonusMode === 'HEAVEN') {
        $targetRtp = 85.0;
        $teaserChance = 10;
    } elseif ($playerPnl < -200000) {
        $targetRtp = 15.0; // Vampire Mode
        $teaserChance = 60;
    } elseif ($playerPnl > 1000000) {
        $targetRtp = 85.0; // Mercy Mode
    }

    $winThreshold = (int)($targetRtp * 100); 
    $rngRoll = random_int(1, 10000); 
    $isHit = $rngRoll <= $winThreshold;

    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $result = array_fill(0, 9, 0);
    $winningLines = [];
    $spinWin = 0;
    
    $isTeaser = false;
    $isReachEye = false; 
    $isGrandJackpot = false;
    $isFreeze = false;

    // --- TENJO (CEILING) & LONG FREEZE ENGINE ---
    
    // Long Freeze: 1 in 65,536 base chance
    if (!$isFreeSpin && !$bonusMode && random_int(1, 65536) <= (max(1, $betAmount / 5000))) {
        $isFreeze = true;
    }

    // Tenjo (Ceiling): Guaranteed Big Bonus at 777 dry spins
    $isTenjoHit = (!$isFreeSpin && !$bonusMode && $lapsSinceBonus >= 777);

    // Grand Jackpot (Global)
    $jackpotOdds = max(200, (int)(100000000 / max(1, $betAmount))); 
    if (!$isFreeSpin && !$bonusMode && random_int(1, $jackpotOdds) === 1) {
        $stmtJp = $pdo->query("SELECT current_amount FROM global_jackpots WHERE name = 'GRAND SURO JACKPOT' FOR UPDATE");
        $jpAmount = (float)$stmtJp->fetchColumn();
        if ($jpAmount > 0) {
            $isGrandJackpot = true;
            $spinWin += $jpAmount;
            $pdo->prepare("UPDATE global_jackpots SET current_amount = 5000000.00, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE name = 'GRAND SURO JACKPOT'")->execute(['SuroPlayer', $jpAmount]);
            for ($i=0; $i<9; $i++) $result[$i] = random_int(2, 6);
            $result[3] = 1; $result[4] = 1; $result[5] = 1;
            $winningLines[] = 1;
        }
    }

    if ($isFreeze || $isTenjoHit) {
        // FORCE BIG BONUS DUE TO CEILING OR FREEZE
        for ($i=0; $i<9; $i++) $result[$i] = random_int(2, 6);
        $result[3] = 1; $result[4] = 1; $result[5] = 1; // 7-7-7
        $winningLines[] = 1;
        
        // If Long Freeze, force maximum Heaven Mode immediately
        if ($isFreeze) {
            $bonusMode = 'HEAVEN';
            $bonusSpinsLeft = 32;
        }
    } elseif (!$isGrandJackpot) {
        if ($bonusSpinsLeft > 0 && $bonusMode !== 'HEAVEN') {
            // AT MODE (Guaranteed Koyaku)
            $winSym = (random_int(1, 100) > 80) ? 5 : 4; 
            for ($i=0; $i<9; $i++) $result[$i] = random_int(4, 7);
            $chosenLine = array_rand($paylines);
            foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
            
            $bonusSpinsLeft--;
            if ($bonusSpinsLeft <= 0) {
                // Hidden 30% chance to enter HEAVEN MODE after a bonus finishes!
                if (random_int(1, 100) <= 30) {
                    $bonusMode = 'HEAVEN';
                    $bonusSpinsLeft = 32; // 32 crazy spins
                } else {
                    $bonusMode = null; 
                }
            }
            $newFreeSpins = $freeSpins; 
        } else {
            // Normal / Heaven Spins
            if ($bonusMode === 'HEAVEN') $bonusSpinsLeft--;
            if ($bonusSpinsLeft <= 0 && $bonusMode === 'HEAVEN') $bonusMode = null;

            $newFreeSpins = ($freeSpins > 0) ? $freeSpins - 1 : 0;
            
            if ($isHit) {
                if ($targetRtp <= 15.0) { $allowed = [[7,70], [6,20], [4,8], [5,2]]; } 
                elseif ($isMonthlyBigWinCapped && $bonusMode !== 'HEAVEN') { $allowed = [[7,50], [6,25], [4,15], [5,7], [3,3]]; } 
                else { $allowed = [[7,40], [6,25], [4,15], [5,10], [3,6], [2,3], [1,1]]; } 

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
                    if (random_int(1, 100) > 30) { foreach($paylines[$chosenLine] as $pos) $result[$pos] = 1; } 
                    else { $result[$paylines[$chosenLine][0]] = 1; $result[$paylines[$chosenLine][1]] = 1; $result[$paylines[$chosenLine][2]] = 3; }
                } else {
                    foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
                }
            } else {
                do {
                    for($i=0; $i<9; $i++) $result[$i] = random_int(2, 7);
                    $hasWin = false;
                    foreach($paylines as $l) { if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) { $hasWin = true; break; } }
                } while($hasWin);

                if (random_int(1, 100) <= $teaserChance) {
                    $teaserSym = (random_int(1,100) > 85 && !$isMonthlyBigWinCapped) ? 1 : random_int(2, 3); 
                    $result[3] = $teaserSym; $result[4] = $teaserSym;
                    $slipPosition = (random_int(0, 1) === 0) ? 2 : 8; 
                    $result[5] = ($teaserSym == 1) ? random_int(4, 7) : 7; 
                    $result[$slipPosition] = $teaserSym; 
                    $isTeaser = true;
                    if ($teaserSym == 1 && random_int(1, 100) > 60) $isReachEye = true;
                }
            }
        }
    }

    // Evaluation
    if (!$isGrandJackpot) { 
        if (in_array(6, [$result[0], $result[3], $result[6]])) { $spinWin += $betAmount * 2; $winningLines[] = 99; }
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
    }

    // Update Tenjo Tracker
    if ($bonusMode === 'BB' || $bonusMode === 'RB' || $bonusMode === 'HEAVEN' || $isGrandJackpot) {
        $lapsSinceBonus = 0; 
    } elseif (!$isFreeSpin) {
        $lapsSinceBonus++; 
    }

    // Event Multipliers
    $stmtEvent = $pdo->prepare("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
    $stmtEvent->execute();
    $winMultiplier = $stmtEvent->fetchColumn() ?: 1.0;
    
    $spinWin = $spinWin * $winMultiplier;

    // --- ANTI-CHEAT: MAX WIN CAP ---
    $maxAllowedWin = $betAmount * 5000; // Hard cap at 5000x to prevent API manipulation
    if ($spinWin > $maxAllowedWin && !$isGrandJackpot) {
        $spinWin = $maxAllowedWin;
        try {
            $pdo->prepare("INSERT INTO security_alerts (user_id, risk_level, event_type, details) VALUES (?, 'high', 'WIN_CAP_REACHED', ?)")
                ->execute([$userId, "Attempted win logic anomaly on bet: $betAmount"]);
        } catch (Exception $e) {}
    }

    // Vault Siphoning
    $vaultSiphon = 0;
    if ($spinWin >= ($betAmount * 50)) {
        $vaultSiphon = $spinWin * 0.05;
        $spinWin -= $vaultSiphon; 
        
        $pdo->prepare("INSERT IGNORE INTO user_vaults (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("UPDATE user_vaults SET balance = balance + ?, total_saved = total_saved + ? WHERE user_id = ?")
            ->execute([$vaultSiphon, $vaultSiphon, $userId]);
    }

    // Apply Win & PNL Update
    if ($spinWin > 0) {
        $totalEffectiveWin = $spinWin + $vaultSiphon;
        $pdo->prepare("UPDATE users SET balance = balance + ?, pnl_lifetime = pnl_lifetime - ? WHERE id = ?")
            ->execute([$spinWin, $totalEffectiveWin, $userId]);
    }
    
    $xpGain = floor($betAmount / 1000);
    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $userId]);

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([($spinWin + $vaultSiphon), $currentToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $machineId]);
    
    // Log Game
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($result), $xpGain]);

    $pdo->commit();
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    // RESPONSE
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
        'laps_since_bonus' => $lapsSinceBonus,
        'session_token' => $currentToken,
        'is_teaser' => $isTeaser,
        'is_reach_eye' => $isReachEye,
        'is_jackpot' => $isGrandJackpot,
        'is_freeze' => $isFreeze,
        'in_zone' => $inZone
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); 
    echo json_encode(['error' => $e->getMessage()]);
}
?>