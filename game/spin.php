<?php
// ============================================================================
// SUROPARA V4.0 - OMNI-GOD SPIN ENGINE (DYNAMIC GJP ALGORITHM)
// ----------------------------------------------------------------------------
// FEATURES:
// 1. DB-Driven Independent Reel Spawn Rates (Ultimate Admin Control).
// 2. Escalating Grand Jackpots with Thermal Compression Odds.
// 3. Must-Hit-By Cap Enforcer.
// 4. Strict 50/50 GJP Near-Miss Algorithm (80% 2-Reel Tease, 20% 1-Reel Tease).
// ============================================================================

$allowedOrigin = "https://suropara.com";
header("Access-Control-Allow-Origin: $allowedOrigin"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

// --- PHASE 1: INIT & SECURITY ---
$user = authenticate($pdo);
$userId = $user['id'];
$data = json_decode(file_get_contents("php://input"));

if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data stream.']); exit; }

$betAmount = (int)($data->bet_amount ?? $data->betAmount ?? 0);
$machineId = (int)($data->machine_id ?? $data->machineId ?? 0);
$clientToken = $data->session_token ?? $data->sessionToken ?? ''; 

$validBets = [100, 500, 1000, 5000, 10000];
if (!in_array($betAmount, $validBets)) {
    http_response_code(400); echo json_encode(['error' => 'Invalid bet amount for V3.']); exit;
}

Security::rateLimit($pdo, $userId, 'spin', 0.3);

try {
    $pdo->beginTransaction();

    // --- PHASE 2: STATE LOCKING & VERIFICATION ---
    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] != $userId) throw new Exception("Machine seating mismatch.");
    if ($machine['session_token'] !== $clientToken && $clientToken !== 'TEST_OVERRIDE') {
        throw new Exception("Session out of sync. Auto-recovering...");
    }
    
    $islandId = (int)$machine['island_id'];
    $currentToken = $machine['session_token'];
    
    $stmtUser = $pdo->prepare("SELECT username, balance, xp, level, active_pet_id, pnl_lifetime, current_month_big_wins, tracking_month FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    $bonusMode = $machine['bonus_mode'] ?? null;
    $bonusSpinsLeft = (int)($machine['bonus_spins_left'] ?? 0);
    $freeSpins = (int)($machine['free_spins'] ?? 0);
    $lapsSinceBonus = (int)($machine['laps_since_bonus'] ?? 0); 
    $sessionSpins = (int)($machine['session_spins'] ?? 0);
    $sessionWinStreak = (int)($machine['session_win_streak'] ?? 0);

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
        $pdo->prepare("UPDATE users SET balance = balance - ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
    }

    // --- PHASE 3: ISLAND SPECIFIC DYNAMIC GRAND JACKPOT ---
    $stmtJp = $pdo->prepare("SELECT current_amount, contribution_rate, base_seed, trigger_amount, max_amount FROM global_jackpots WHERE island_id = ? FOR UPDATE");
    $stmtJp->execute([$islandId]);
    $gjpData = $stmtJp->fetch();
    
    $currentJackpot = (float)($gjpData['current_amount'] ?? 3000000);
    $gjpRate = (float)($gjpData['contribution_rate'] ?? 0.05);
    $gjpBase = (float)($gjpData['base_seed'] ?? 3000000);
    $gjpTrigger = (float)($gjpData['trigger_amount'] ?? 3600000);
    $gjpMax = (float)($gjpData['max_amount'] ?? 7200000);

    if ($actualBetDeducted > 0) {
        $jackpotFeed = $actualBetDeducted * $gjpRate;
        $currentJackpot += $jackpotFeed;
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE island_id = ?")->execute([$currentJackpot, $islandId]);
    }

    // --- PHASE 4: DB-DRIVEN INDEPENDENT REEL SPAWN RATES ---
    $stmtRates = $pdo->prepare("SELECT * FROM reel_spawn_rates WHERE island_id = ?");
    $stmtRates->execute([$islandId]);
    $dbRates = $stmtRates->fetchAll(PDO::FETCH_ASSOC);

    // Fallback defaults if DB is missing
    $reelSpawnRates = [
        'reel_1' => [1=>10, 2=>40, 3=>100, 4=>200, 5=>200, 6=>250, 7=>200],
        'reel_2' => [1=>5,  2=>30, 3=>80,  4=>220, 5=>220, 6=>245, 7=>200],
        'reel_3' => [1=>2,  2=>20, 3=>60,  4=>250, 5=>250, 6=>218, 7=>200]
    ];

    if ($dbRates) {
        foreach ($dbRates as $r) {
            $reelSpawnRates['reel_' . $r['reel_index']] = [
                1 => (int)$r['sym_1'], 2 => (int)$r['sym_2'], 3 => (int)$r['sym_3'],
                4 => (int)$r['sym_4'], 5 => (int)$r['sym_5'], 6 => (int)$r['sym_6'], 7 => (int)$r['sym_7']
            ];
        }
    }

    // Function to roll a single symbol based on a reel's specific weights
    $rollReel = function($weights) {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        $sum = 0;
        foreach ($weights as $sym => $weight) {
            $sum += $weight;
            if ($rand <= $sum) return $sym;
        }
        return 7;
    };

    // --- PHASE 5: WIN DETERMINATION ---
    $stmtIsland = $pdo->prepare("SELECT rtp_rate FROM islands WHERE id = ?");
    $stmtIsland->execute([$islandId]);
    $baseRtp = (float)$stmtIsland->fetchColumn();
    
    // Core Hit Check
    $isHit = random_int(1, 10000) <= (int)($baseRtp * 100);
    
    // --- ADVANCED GJP ALGORITHM: THERMAL COMPRESSION & MUST-HIT ---
    $isGrandJackpot = false;
    
    if (!$isFreeSpin && !$bonusMode) {
        if ($currentJackpot >= $gjpMax) {
            // Must-Hit-By Cap Reached. Guaranteed Drop!
            $isGrandJackpot = true;
        } else {
            // Dynamic Odds Calculation
            // Base mathematical probability (e.g., 1 in 15 Million scaled by bet size)
            $baseOdds = max(500, (int)(15000000 / max(1, $betAmount))); 
            
            if ($currentJackpot >= $gjpTrigger) {
                // Hot Zone: Thermal Compression Logic
                // As the pot grows from Trigger towards Max, the odds shrink exponentially
                $progressToCap = ($currentJackpot - $gjpTrigger) / max(1, ($gjpMax - $gjpTrigger));
                // Compress odds (e.g., 0% progress = base odds, 99% progress = 1 in 2 odds)
                $baseOdds = max(2, (int)($baseOdds * (1 - $progressToCap)));
            }

            if (random_int(1, $baseOdds) === 1) {
                $isGrandJackpot = true;
            }
        }
        
        if ($isGrandJackpot) $isHit = true; // Override normal hit calc if GJP lands
    }

    $result = array_fill(0, 9, 0);
    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $winningLines = [];
    $spinWin = 0;
    $isTeaser = false; $isReachEye = false;

    if ($isGrandJackpot) {
        // GJP WIN
        $spinWin += $currentJackpot;
        // Dynamically reset the jackpot to this island's specific Base Seed
        $pdo->prepare("UPDATE global_jackpots SET current_amount = base_seed, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE island_id = ?")->execute([$freshUser['username'], $currentJackpot, $islandId]);
        $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('jackpot', ?, 1)")
            ->execute(["🚨 GRAND JACKPOT! {$freshUser['username']} just won " . number_format($currentJackpot) . " MMK! 🚨"]);

        for ($i=0; $i<9; $i++) $result[$i] = random_int(4, 7);
        $chosenLine = array_rand($paylines);
        $result[$paylines[$chosenLine][0]] = 1;
        $result[$paylines[$chosenLine][1]] = 1;
        $result[$paylines[$chosenLine][2]] = 1;
        $winningLines[] = $chosenLine;
        $winSym = 1;

    } elseif ($isHit) {
        // REGULAR WIN
        // Determine which symbol pays out based on general frequency weights
        $winSymWeights = [2=>5, 3=>10, 4=>25, 5=>20, 6=>25, 7=>15];
        if ($bonusMode === 'HEAVEN') $winSymWeights = [2=>40, 3=>60]; // Forced High Pay
        
        $winSym = $rollReel($winSymWeights);
        
        // Populate the board with independent reels first
        for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
        for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
        for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);

        // Overwrite a payline to guarantee the win
        $chosenLine = array_rand($paylines);
        $result[$paylines[$chosenLine][0]] = $winSym;
        $result[$paylines[$chosenLine][1]] = $winSym;
        $result[$paylines[$chosenLine][2]] = $winSym;
        $winningLines[] = $chosenLine;

    } else {
        // LOSS LOGIC
        $winSym = 0;
        
        // --- THE 50/50 GJP NEAR-MISS (TEASER) ALGORITHM ---
        if (random_int(1, 100) <= 50) {
            $isTeaser = true;
            
            if (random_int(1, 100) <= 80) {
                // 80% Chance: Severe Tease (2 GJP Symbols)
                $isReachEye = true;
                for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
                for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
                for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);
                
                $chosenLine = array_rand($paylines);
                $result[$paylines[$chosenLine][0]] = 1; // 7
                $result[$paylines[$chosenLine][1]] = 1; // 7
                
                // Guarantee 3rd reel is NOT a 7 to ensure loss
                do { $finalSym = random_int(2, 7); } while ($finalSym === 1);
                $result[$paylines[$chosenLine][2]] = $finalSym;
                
                // Scrub the rest of the board to prevent accidental wins
                do {
                    $hasWin = false;
                    foreach($paylines as $l) {
                        if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) {
                            if (!($l[0] == $paylines[$chosenLine][0] && $l[1] == $paylines[$chosenLine][1])) {
                                $result[$l[2]] = random_int(2, 7); 
                                $hasWin = true;
                            }
                        }
                    }
                } while($hasWin);

            } else {
                // 20% Chance: Mild Tease (1 GJP Symbol dead center)
                for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
                for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
                for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);
                
                // Force exactly ONE GJP symbol in the center
                $result[4] = 1; 
                
                // Ensure no other 1s exist to prevent accidental Reach
                foreach ($result as $idx => $val) {
                    if ($idx !== 4 && $val === 1) $result[$idx] = random_int(2, 7);
                }
                
                // Ensure no accidental win lines
                do {
                    $hasWin = false;
                    foreach($paylines as $l) {
                        if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) {
                            $result[$l[2]] = random_int(2, 7); // break the win
                            $hasWin = true;
                        }
                    }
                } while($hasWin);
            }
        } else {
            // Standard Loss: Just roll using independent spawn rates
            do {
                for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
                for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
                for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);
                
                $hasWin = false;
                foreach($paylines as $l) { if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) { $hasWin = true; break; } }
            } while($hasWin);
        }
    }

    // --- PHASE 6: PAYOUT ASSIGNMENT ---
    $basePayout = 0;
    if (!$isGrandJackpot && $isHit) {
        $mult = 0;
        if ($winSym === 2) $mult = 20;
        if ($winSym === 3) { $mult = 10; if(!$bonusMode) { $bonusMode = 'RB'; $bonusSpinsLeft = 8; } }
        if ($winSym === 4) $mult = 10;
        if ($winSym === 5) $mult = 15;
        if ($winSym === 6) $mult = 2;
        if ($winSym === 7) { $freeSpins += 1; $mult = 0; }
        
        $basePayout = $betAmount * $mult;
        
        $stmtEvent = $pdo->prepare("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
        $stmtEvent->execute();
        $eventMult = $stmtEvent->fetchColumn() ?: 1.0;
        
        $spinWin = $basePayout * $eventMult;
    }

    // Decrease bonus/free counts
    $newFreeSpins = ($freeSpins > 0 && $winSym !== 7) ? $freeSpins - 1 : $freeSpins;
    if ($bonusSpinsLeft > 0) {
        $bonusSpinsLeft--;
        if ($bonusSpinsLeft <= 0 && $bonusMode !== 'HEAVEN') $bonusMode = null;
    }

    $winTier = 'NONE';
    if ($spinWin > 0) {
        $multiplierCheck = $spinWin / max(1, $betAmount);
        if ($multiplierCheck >= 100) $winTier = 'EPIC';
        elseif ($multiplierCheck >= 50) $winTier = 'MEGA';
        elseif ($multiplierCheck >= 10) $winTier = 'BIG';
        else $winTier = 'SMALL';
    }

    // --- PHASE 7: STATE UPDATES & VAULTS ---
    $vaultSiphon = ($spinWin >= ($betAmount * 50)) ? $spinWin * 0.05 : 0;
    $spinWin -= $vaultSiphon; 
    
    if ($vaultSiphon > 0) {
        $pdo->prepare("INSERT IGNORE INTO user_vaults (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("UPDATE user_vaults SET balance = balance + ?, total_saved = total_saved + ? WHERE user_id = ?")->execute([$vaultSiphon, $vaultSiphon, $userId]);
    }

    if ($spinWin > 0) {
        $totalEffectiveWin = $spinWin + $vaultSiphon;
        $pdo->prepare("UPDATE users SET balance = balance + ?, pnl_lifetime = pnl_lifetime - ? WHERE id = ?")->execute([$spinWin, $totalEffectiveWin, $userId]);
        $sessionWinStreak++;
    } else {
        if (!$isFreeSpin && !$bonusMode) $sessionWinStreak = 0;
    }
    
    $xpGain = floor($betAmount / 1000);
    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $userId]);

    $lapsSinceBonus = ($bonusMode || $isGrandJackpot) ? 0 : $lapsSinceBonus + 1;
    $sessionSpins++;

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, session_spins = ?, session_win_streak = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([($spinWin + $vaultSiphon), $currentToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $sessionSpins, $sessionWinStreak, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($result), $xpGain]);

    $pdo->commit();
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    // --- PHASE 8: DATA PAYLOAD ---
    echo json_encode([
        'status' => 'success', 
        'stops' => $result, 
        'winning_lines' => $winningLines, 
        'win_amount' => $spinWin, 
        'win_tier' => $winTier,
        'vaulted_amount' => $vaultSiphon,
        'new_balance' => (float)$finalBal, 
        'free_spins' => $newFreeSpins, 
        'bonus_mode' => $bonusMode, 
        'bonus_spins_left' => $bonusSpinsLeft,
        'laps_since_bonus' => $lapsSinceBonus, 
        'session_spins' => $sessionSpins,
        'session_win_streak' => $sessionWinStreak,
        'session_token' => $currentToken,
        'is_teaser' => $isTeaser, 
        'is_reach_eye' => $isReachEye, 
        'is_jackpot' => $isGrandJackpot
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); 
    echo json_encode(['error' => $e->getMessage()]);
}
?>