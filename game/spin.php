<?php
// ============================================================================
// SUROPARA V5.0 - OMNI-GOD SPIN ENGINE (THE LEVIATHAN CORE)
// ----------------------------------------------------------------------------
// FEATURES FULLY INTEGRATED:
// 1. DB-Driven Independent Reel Spawn Rates with Dynamic Multiplication.
// 2. Escalating Grand Jackpots with Thermal Compression Odds & Must-Hit Cap.
// 3. Leviathan P&L Clamp (-3% to +3% Target Margin based on lifetime deposits).
// 4. Auto-Tracking for Daily Missions & Tournament Leaderboards.
// 5. Vault Siphoning (Forced Piggy Bank Savings on Big Wins).
// 6. Psychological Bleeding & Teasers (High near-miss rates when cold).
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

// Validate Bet Amount against V3 Luxury Tiers
$validBets = [100, 500, 1000, 5000, 10000, 20000, 50000, 100000, 250000, 500000, 1000000];
if (!in_array($betAmount, $validBets)) {
    http_response_code(400); echo json_encode(['error' => 'Invalid bet amount for V3.']); exit;
}

// 0.3s Rate Limit for Turbo Mode
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
    
    if ($actualBetDeducted > 0) {
        $pdo->prepare("UPDATE users SET balance = balance - ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
        $freshUser['balance'] -= $actualBetDeducted; // Update local variable for math
    }

    // --- PHASE 3: LEVIATHAN AUTO-RTP CLAMP (-3% to +3% MARGIN) ---
    $stmtIsland = $pdo->prepare("SELECT rtp_rate FROM islands WHERE id = ?");
    $stmtIsland->execute([$islandId]);
    $targetRtpPercent = (float)$stmtIsland->fetchColumn(); // Usually 70.0%
    
    $rtpUpperBound = $targetRtpPercent + 3.0; // e.g., 73.0%
    $rtpLowerBound = $targetRtpPercent - 3.0; // e.g., 67.0%

    // Fetch Lifetime Deposits & Withdrawals
    $stmtFin = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END), 0) as total_dep,
            COALESCE(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END), 0) as total_with
        FROM transactions WHERE user_id = ? AND status = 'approved'
    ");
    $stmtFin->execute([$userId]);
    $fin = $stmtFin->fetch();
    
    $totalDeposited = (float)$fin['total_dep'];
    $totalWithdrawn = (float)$fin['total_with'];
    
    $machineState = 'NEUTRAL'; // HOT, COLD, or NEUTRAL
    $baseHitFreq = 22.0; // Standard 22% chance to hit *something*

    if ($totalDeposited == 0) {
        // Free/Bonus Money Player: Cap them hard so they don't drain the house without depositing
        if ($freshUser['balance'] > 15000) {
            $machineState = 'COLD';
            $baseHitFreq = 10.0; // Starve them out
        }
    } else {
        // Calculate Actual User RTP: (Current Balance + Total Withdrawn) / Total Deposited
        $totalExtractedValue = $freshUser['balance'] + $totalWithdrawn;
        $currentRtpPercent = ($totalExtractedValue / max(1, $totalDeposited)) * 100;
        
        if ($currentRtpPercent > $rtpUpperBound) {
            // Player is beating the upper margin. CLAMP DOWN.
            $machineState = 'COLD';
            $baseHitFreq = 16.0; // Tighter wins
        } elseif ($currentRtpPercent < $rtpLowerBound) {
            // Player is losing too fast. BOOST THEM.
            $machineState = 'HOT';
            $baseHitFreq = 28.0; // Looser wins to bring them back up
        }
    }

    // --- PHASE 4: ISLAND GJP & DYNAMIC SPAWN RATE MANIPULATION ---
    $stmtJp = $pdo->prepare("SELECT current_amount, contribution_rate, max_amount, trigger_amount, base_seed FROM global_jackpots WHERE island_id = ? FOR UPDATE");
    $stmtJp->execute([$islandId]);
    $gjpData = $stmtJp->fetch();
    
    $currentJackpot = (float)($gjpData['current_amount'] ?? 3000000);
    if ($actualBetDeducted > 0) {
        $currentJackpot += ($actualBetDeducted * (float)($gjpData['contribution_rate'] ?? 0.05));
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE island_id = ?")->execute([$currentJackpot, $islandId]);
    }

    $stmtRates = $pdo->prepare("SELECT * FROM reel_spawn_rates WHERE island_id = ?");
    $stmtRates->execute([$islandId]);
    $dbRates = $stmtRates->fetchAll(PDO::FETCH_ASSOC);

    $reelSpawnRates = [
        'reel_1' => [1=>10, 2=>40, 3=>100, 4=>200, 5=>200, 6=>250, 7=>200],
        'reel_2' => [1=>5,  2=>30, 3=>80,  4=>220, 5=>220, 6=>245, 7=>200],
        'reel_3' => [1=>2,  2=>20, 3=>60,  4=>250, 5=>250, 6=>218, 7=>200]
    ];
    if ($dbRates) {
        foreach ($dbRates as $r) {
            $reelSpawnRates['reel_'.$r['reel_index']] = [
                1=>(int)$r['sym_1'], 2=>(int)$r['sym_2'], 3=>(int)$r['sym_3'], 4=>(int)$r['sym_4'], 5=>(int)$r['sym_5'], 6=>(int)$r['sym_6'], 7=>(int)$r['sym_7']
            ];
        }
    }

    // DYNAMIC SPAWN RATE MODIFIERS based on Hot/Cold State
    foreach ($reelSpawnRates as $reelKey => &$weights) {
        if ($machineState === 'COLD') {
            // Starve the reels of premium symbols, flood with Cherries and Replays
            $weights[1] = max(1, (int)($weights[1] * 0.1)); // Nerf 7s
            $weights[2] = max(1, (int)($weights[2] * 0.2)); // Nerf Chars
            $weights[3] = max(1, (int)($weights[3] * 0.5)); // Nerf BARs
            $weights[6] = (int)($weights[6] * 1.5);         // Boost Cherries
        } elseif ($machineState === 'HOT') {
            // Loosen up the reels for premium drops
            $weights[2] = (int)($weights[2] * 1.5);         // Boost Chars
            $weights[3] = (int)($weights[3] * 1.5);         // Boost BARs
            $weights[4] = (int)($weights[4] * 1.2);         // Boost Bells
        }
    }

    $rollReel = function($weights) {
        $rand = random_int(1, array_sum($weights));
        $sum = 0;
        foreach ($weights as $sym => $w) { $sum += $w; if ($rand <= $sum) return $sym; }
        return 7; // Fallback to Replay
    };

    // --- PHASE 5: WIN DETERMINATION & ALGORITHMS ---
    $isHit = (random_int(1, 10000) <= (int)($baseHitFreq * 100));
    $isGrandJackpot = false;
    
    // GJP Logic: Thermal Compression Odds & Must-Hit Cap
    $gjpMax = (float)($gjpData['max_amount'] ?? 7200000);
    $gjpTrigger = (float)($gjpData['trigger_amount'] ?? 3600000);
    
    if (!$isFreeSpin && !$bonusMode) {
        if ($currentJackpot >= $gjpMax) {
            $isGrandJackpot = true; // Must-Hit Enforced
        } else {
            $baseOdds = max(500, (int)(15000000 / max(1, $betAmount))); 
            if ($currentJackpot >= $gjpTrigger) {
                // Compress odds significantly as it approaches the cap
                $progress = ($currentJackpot - $gjpTrigger) / max(1, ($gjpMax - $gjpTrigger));
                $baseOdds = max(2, (int)($baseOdds * (1 - $progress)));
            }
            if (random_int(1, $baseOdds) === 1) $isGrandJackpot = true;
        }
        if ($isGrandJackpot) $isHit = true;
    }

    $result = array_fill(0, 9, 0);
    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $winningLines = [];
    $spinWin = 0;
    $isTeaser = false; $isReachEye = false;

    if ($isGrandJackpot) {
        // --- GJP EXECUTED ---
        $spinWin += $currentJackpot;
        $baseSeed = (float)($gjpData['base_seed'] ?? 3000000);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ?, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE island_id = ?")->execute([$baseSeed, $freshUser['username'], $currentJackpot, $islandId]);
        $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('jackpot', ?, 1)")->execute(["🚨 GRAND JACKPOT! {$freshUser['username']} just won " . number_format($currentJackpot) . " MMK! 🚨"]);

        for ($i=0; $i<9; $i++) $result[$i] = random_int(4, 7);
        $chosenLine = array_rand($paylines);
        $result[$paylines[$chosenLine][0]] = 1;
        $result[$paylines[$chosenLine][1]] = 1;
        $result[$paylines[$chosenLine][2]] = 1;
        $winningLines[] = $chosenLine;
        $winSym = 1;

    } elseif ($isHit) {
        // --- DYNAMIC WIN LOGIC ---
        $winSymWeights = [2=>5, 3=>10, 4=>25, 5=>20, 6=>25, 7=>15]; // Default
        
        if ($bonusMode === 'HEAVEN') {
            $winSymWeights = [2=>40, 3=>60]; 
        } elseif ($machineState === 'COLD' && !$bonusMode) {
            // The Bleed Clamp: Force small wins
            $winSymWeights = [4=>5, 5=>5, 6=>70, 7=>20]; 
        } elseif ($machineState === 'HOT' && !$bonusMode) {
            // The Generous State: Give back mid/high wins
            $winSymWeights = [2=>15, 3=>25, 4=>20, 5=>20, 6=>10, 7=>10]; 
        }
        
        $winSym = $rollReel($winSymWeights);
        
        for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
        for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
        for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);

        $chosenLine = array_rand($paylines);
        $result[$paylines[$chosenLine][0]] = $winSym;
        $result[$paylines[$chosenLine][1]] = $winSym;
        $result[$paylines[$chosenLine][2]] = $winSym;
        $winningLines[] = $chosenLine;

    } else {
        // --- LOSS LOGIC & PSYCHOLOGICAL TEASERS ---
        $winSym = 0;
        
        // Fetch global teaser setting or default to 30%. If COLD, force 70% tease rate.
        $stmtTeaser = $pdo->prepare("SELECT value FROM system_settings WHERE key_name = 'teaser_rate'");
        $stmtTeaser->execute();
        $globalTeaser = $stmtTeaser->fetchColumn();
        $baseTeaserRate = ($globalTeaser !== false) ? (int)$globalTeaser : 30;
        
        $teaserChance = ($machineState === 'COLD') ? 70 : $baseTeaserRate; 

        if (random_int(1, 100) <= $teaserChance) {
            $isTeaser = true;
            $isReachEye = (random_int(1, 100) <= 60); // 60% chance for a 2-symbol deep tease
            
            for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
            for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
            for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);
            
            $chosenLine = array_rand($paylines);
            $teaseSym = random_int(1, 3); // Tease high value symbols (1, 2, or 3)
            
            if ($isReachEye) {
                $result[$paylines[$chosenLine][0]] = $teaseSym;
                $result[$paylines[$chosenLine][1]] = $teaseSym;
                do { $finalSym = random_int(1, 7); } while ($finalSym === $teaseSym);
                $result[$paylines[$chosenLine][2]] = $finalSym;
            } else {
                $result[4] = $teaseSym; 
            }
        } else {
            // Standard Loss Drop
            for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
            for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
            for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);
        }

        // FAILSAFE: Ensure no accidental win lines formed by RNG
        $failsafeLoops = 0;
        do {
            $hasWin = false;
            foreach($paylines as $l) {
                if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) {
                    $result[$l[2]] = ($result[$l[2]] % 7) + 1; // Shift symbol by 1 to break win
                    $hasWin = true;
                }
            }
            $failsafeLoops++;
        } while($hasWin && $failsafeLoops < 10);
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
        
        // Optional Event Multipliers (Marketing Events)
        $stmtEvent = $pdo->prepare("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
        $stmtEvent->execute();
        $eventMult = $stmtEvent->fetchColumn() ?: 1.0;
        
        $spinWin = $basePayout * $eventMult;
    }

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
    // Siphon 5% of any win that is 50x or greater into the Piggy Bank Vault
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
    
    // XP & Leveling
    $xpGain = floor($betAmount / 1000);
    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $userId]);

    $lapsSinceBonus = ($bonusMode || $isGrandJackpot) ? 0 : $lapsSinceBonus + 1;
    $sessionSpins++;

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, session_spins = ?, session_win_streak = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([($spinWin + $vaultSiphon), $currentToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $sessionSpins, $sessionWinStreak, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($result), $xpGain]);

    // --- PHASE 8: PROGRESSION & COMPETITION TRACKING ---
    $today = date('Y-m-d');
    
    // 1. Tournaments Update
    $stmtTourney = $pdo->prepare("
        UPDATE tournament_entries te 
        JOIN tournaments t ON te.tournament_id = t.id 
        SET te.spins_used = te.spins_used + 1, 
            te.current_score = te.current_score + ? 
        WHERE te.user_id = ? 
          AND t.status = 'active' 
          AND t.start_time <= NOW() 
          AND t.end_time > NOW() 
          AND te.spins_used < t.spin_limit
    ");
    $stmtTourney->execute([$spinWin, $userId]);

    // 2. Daily Missions Update
    $pdo->prepare("INSERT IGNORE INTO user_mission_progress (user_id, mission_id, tracking_date) SELECT ?, id, ? FROM daily_missions WHERE is_active = 1")->execute([$userId, $today]);

    // Increment Spin Missions
    $pdo->prepare("UPDATE user_mission_progress ump JOIN daily_missions dm ON ump.mission_id = dm.id SET ump.progress = ump.progress + 1 WHERE ump.user_id = ? AND ump.tracking_date = ? AND dm.action_type = 'spin'")->execute([$userId, $today]);

    // Increment Win Amount Missions
    if ($spinWin > 0) {
        $pdo->prepare("UPDATE user_mission_progress ump JOIN daily_missions dm ON ump.mission_id = dm.id SET ump.progress = ump.progress + ? WHERE ump.user_id = ? AND ump.tracking_date = ? AND dm.action_type = 'win_total'")->execute([$spinWin, $userId, $today]);
    }

    $pdo->commit();
    
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    // --- PHASE 9: DATA PAYLOAD ---
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
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); 
    echo json_encode(['error' => $e->getMessage()]);
}
?>