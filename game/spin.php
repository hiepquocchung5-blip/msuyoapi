<?php
// ============================================================================
// SUROPARA V5.6 - OMNI-GOD SPIN ENGINE (THE TRUE LEVIATHAN)
// ----------------------------------------------------------------------------
// FEATURES FULLY INTEGRATED:
// 1. 24-Hour Rolling Memory: Prevents session-reset exploitation.
// 2. Unchained GJP: Jackpots bypass RTP caps (Marketing Budget).
// 3. Isolated GJP Spawns: Symbol 1 removed from natural RNG, strictly controlled.
// 4. Illusion of Winning: Free players get hits, but capped at low-yield symbols.
// ============================================================================

$allowedOrigin = "https://suropara.com"; 
header("Access-Control-Allow-Origin: *"); 
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
    http_response_code(400); echo json_encode(['error' => 'Invalid bet signature.']); exit;
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
    
    $stmtUser = $pdo->prepare("SELECT username, balance, xp, level, active_pet_id, pnl_lifetime FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    $bonusMode = $machine['bonus_mode'] ?? null;
    $bonusSpinsLeft = (int)($machine['bonus_spins_left'] ?? 0);
    $freeSpins = (int)($machine['free_spins'] ?? 0);
    $lapsSinceBonus = (int)($machine['laps_since_bonus'] ?? 0); 
    $sessionSpins = (int)($machine['session_spins'] ?? 0);
    $sessionWinStreak = (int)($machine['session_win_streak'] ?? 0);
    $sessionIn = (float)($machine['session_in'] ?? 0);
    $sessionOut = (float)($machine['session_out'] ?? 0);

    $isFreeSpin = ($freeSpins > 0 || $bonusSpinsLeft > 0);
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ($freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient balance.");
    
    if ($actualBetDeducted > 0) {
        $pdo->prepare("UPDATE users SET balance = balance - ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
        $freshUser['balance'] -= $actualBetDeducted;
        $sessionIn += $actualBetDeducted;
    }

    // --- PHASE 3: THE TRUE LEVIATHAN DIRECTOR (24H ROLLING MEMORY) ---
    
    $stmtRates = $pdo->prepare("SELECT * FROM island_win_rates WHERE island_id = ?");
    $stmtRates->execute([$islandId]);
    $winRateConfig = $stmtRates->fetch(PDO::FETCH_ASSOC);
    
    $targetHitRate = (float)($winRateConfig['base_hit_rate'] ?? 22.00000000);
    $maxRtpCap = (float)($winRateConfig['max_rtp_cap'] ?? 95.00000000);
    $volatilityIndex = (float)($winRateConfig['burst_volatility'] ?? 1.50000000);

    // Fetch Lifetime & 24H Financials to prevent session-spoofing
    $stmtFin = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END), 0) as total_dep,
            COALESCE(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END), 0) as total_with
        FROM transactions WHERE user_id = ? AND status = 'approved'
    ");
    $stmtFin->execute([$userId]);
    $fin = $stmtFin->fetch();
    
    $totalDeposited = max(1, (float)$fin['total_dep']); 
    $totalExtractedValue = $freshUser['balance'] + (float)$fin['total_with'];
    $lifetimeRTP = ($totalExtractedValue / $totalDeposited) * 100;

    // 24-Hour Rolling RTP Check (Defeats session reset exploit)
    $stmt24h = $pdo->prepare("
        SELECT COALESCE(SUM(bet), 0) as vol_in, COALESCE(SUM(win), 0) as vol_out 
        FROM game_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt24h->execute([$userId]);
    $rolling24h = $stmt24h->fetch();
    $rollingRTP = $rolling24h['vol_in'] > 0 ? ($rolling24h['vol_out'] / $rolling24h['vol_in']) * 100 : 0;
    $rollingSpins = $pdo->query("SELECT COUNT(*) FROM game_logs WHERE user_id = $userId AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

    // Director AI Logic
    $directorState = 'NEUTRAL';
    $dynamicHitRate = $targetHitRate;

    // 1. Protection Protocol (Hard Ceiling on Lifetime)
    if ($lifetimeRTP >= $maxRtpCap) {
        $directorState = 'CAP_REJECT';
        $dynamicHitRate = $targetHitRate * 0.3; // Tank hit rate
    } 
    // 2. Rolling Correction (Player bleeding too fast over last 24h, not just session)
    elseif ($rollingSpins > 50 && $rollingRTP < 30.0) {
        $directorState = 'BURST';
        $dynamicHitRate = $targetHitRate * $volatilityIndex; 
    } 
    // 3. Gathering Phase (Player running hot recently)
    elseif ($rollingRTP > 120.0) {
        $directorState = 'GATHER';
        $dynamicHitRate = $targetHitRate * 0.7; 
    }

    // V5.6 Free Player Illusion Fix (No deposits)
    $isFreePlayer = ((float)$fin['total_dep'] === 0.0);
    if ($isFreePlayer && $freshUser['balance'] > 15000) {
        $directorState = 'FREE_BLEED';
        $dynamicHitRate = $targetHitRate * 0.9; // Keep hitting so it's fun
    }

    // --- PHASE 4: UNCHAINED GJP & DYNAMIC MULTIPLIERS ---
    
    $stmtJp = $pdo->prepare("SELECT current_amount, contribution_rate, max_amount, trigger_amount, base_seed FROM global_jackpots WHERE island_id = ? FOR UPDATE");
    $stmtJp->execute([$islandId]);
    $gjpData = $stmtJp->fetch();
    
    $currentJackpot = (float)($gjpData['current_amount'] ?? 3000000);
    if ($actualBetDeducted > 0) {
        $currentJackpot += ($actualBetDeducted * (float)($gjpData['contribution_rate'] ?? 0.05));
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE island_id = ?")->execute([$currentJackpot, $islandId]);
    }

    $stmtPayouts = $pdo->prepare("SELECT * FROM island_symbol_payouts WHERE island_id = ?");
    $stmtPayouts->execute([$islandId]);
    $dbPayouts = $stmtPayouts->fetch(PDO::FETCH_ASSOC);

    $symMultipliers = [
        1 => (float)($dbPayouts['sym_1_mult'] ?? 100.0),
        2 => (float)($dbPayouts['sym_2_mult'] ?? 20.0),
        3 => (float)($dbPayouts['sym_3_mult'] ?? 10.0),
        4 => (float)($dbPayouts['sym_4_mult'] ?? 10.0),
        5 => (float)($dbPayouts['sym_5_mult'] ?? 15.0),
        6 => (float)($dbPayouts['sym_6_mult'] ?? 2.0),
        7 => (float)($dbPayouts['sym_7_mult'] ?? 0.0),
    ];

    // Symbol Win Weights (Dynamic based on Director State)
    if ($bonusMode === 'HEAVEN') {
        $winSymWeights = [2=>50, 3=>50]; 
    } elseif ($directorState === 'GATHER' || $directorState === 'CAP_REJECT') {
        $winSymWeights = [4=>5, 5=>5, 6=>80, 7=>10]; // Force low-tier
    } elseif ($directorState === 'FREE_BLEED') {
        $winSymWeights = [6=>85, 7=>15]; // Illusion of winning: Only Cherries and Replays
    } elseif ($directorState === 'BURST') {
        $winSymWeights = [2=>30, 3=>30, 4=>20, 5=>10, 6=>5, 7=>5]; // Force relief wins
    } else {
        $winSymWeights = [2=>5, 3=>10, 4=>25, 5=>20, 6=>25, 7=>15]; // Balanced
    }

    // Note: Symbol 1 (GJP) is intentionally missing from $winSymWeights. It cannot roll naturally.

    // Extreme Precision 10-Billion Scale RNG
    $finalHitRate = max(0.00000001, min(100.0, $dynamicHitRate));
    $isHit = (random_int(1, 10000000000) <= (int)($finalHitRate * 100000000));
    $isGrandJackpot = false;
    
    // --- V5.6 UNCHAINED GJP LOGIC ---
    $gjpMax = (float)($gjpData['max_amount'] ?? 7200000);
    $gjpTrigger = (float)($gjpData['trigger_amount'] ?? 3600000);
    
    if (!$isFreeSpin && !$bonusMode) {
        $progress = ($currentJackpot - $gjpTrigger) / max(1, ($gjpMax - $gjpTrigger));
        if ($progress < 0) $progress = 0;
        
        $baseOdds = max(500, (int)(15000000 / max(1, $betAmount))); 
        $adjustedOdds = max(2, (int)($baseOdds * (1 - $progress))); // Odds compress as pool fills
        
        if (random_int(1, $adjustedOdds) === 1 || $currentJackpot >= $gjpMax) {
            // V5.6 LIBERATION: The GJP bypasses the RTP cap entirely. It is a true jackpot.
            $isGrandJackpot = true; 
            $isHit = true;
        }
    }

    // --- PHASE 5: PHYSICS SIMULATION & ISOLATED BOARD GENERATION ---
    $stmtDbRates = $pdo->prepare("SELECT * FROM reel_spawn_rates WHERE island_id = ?");
    $stmtDbRates->execute([$islandId]);
    $dbRates = $stmtDbRates->fetchAll(PDO::FETCH_ASSOC);

    $reelSpawnRates = [];
    foreach ($dbRates as $r) {
        $slip = function($val) { return max(1, $val + random_int(-5, 5)); };
        
        $weights = [
            1 => 0, // V5.6 ISOLATION: GJP symbol NEVER spawns naturally in the background
            2 => $slip((int)$r['sym_2']), 3 => $slip((int)$r['sym_3']), 
            4 => $slip((int)$r['sym_4']), 5 => $slip((int)$r['sym_5']), 
            6 => $slip((int)$r['sym_6']), 7 => $slip((int)$r['sym_7'])
        ];
        
        if ($directorState === 'GATHER' || $directorState === 'CAP_REJECT' || $directorState === 'FREE_BLEED') {
            $weights[2] = max(1, (int)($weights[2] * 0.1)); // Starve Chars visually
            $weights[6] = (int)($weights[6] * 2.0); // Flood Cherries visually
        }
        $reelSpawnRates['reel_'.$r['reel_index']] = $weights;
    }

    // Fallbacks
    if (empty($reelSpawnRates)) {
        $reelSpawnRates = [
            'reel_1' => [1=>0, 2=>40, 3=>100, 4=>200, 5=>200, 6=>250, 7=>200],
            'reel_2' => [1=>0, 2=>30, 3=>80,  4=>220, 5=>220, 6=>245, 7=>200],
            'reel_3' => [1=>0, 2=>20, 3=>60,  4=>250, 5=>250, 6=>218, 7=>200]
        ];
    }

    $rollReel = function($weights) {
        $rand = random_int(1, array_sum($weights));
        $sum = 0;
        foreach ($weights as $sym => $w) { $sum += $w; if ($rand <= $sum) return $sym; }
        return 7; 
    };

    $result = array_fill(0, 9, 0);
    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $winningLines = [];
    $spinWin = 0;
    $isTeaser = false; $isReachEye = false;

    if ($isGrandJackpot) {
        // GJP EXECUTION
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
        $winSym = $rollReel($winSymWeights);
        $proposedWinAmount = $betAmount * ($symMultipliers[$winSym] ?? 0);
        
        // STRICT MULTIPLIER CAP CALCULUS (Bypassed if GJP)
        $futureExtracted = $totalExtractedValue + $proposedWinAmount;
        $futureRTP = ($futureExtracted / $totalDeposited) * 100;
        
        if ($futureRTP > $maxRtpCap && !$bonusMode) {
            // Downgrade to Cherry
            $winSym = 6; 
            $proposedWinAmount = $betAmount * ($symMultipliers[6] ?? 2.0);
            if ((($totalExtractedValue + $proposedWinAmount) / $totalDeposited) * 100 > $maxRtpCap) {
                $isHit = false; // Even Cherry breaks math, force loss
            }
        }

        if ($isHit) {
            for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
            for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
            for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);

            $chosenLine = array_rand($paylines);
            $result[$paylines[$chosenLine][0]] = $winSym;
            $result[$paylines[$chosenLine][1]] = $winSym;
            $result[$paylines[$chosenLine][2]] = $winSym;
            $winningLines[] = $chosenLine;
        }
    } 

    if (!$isHit && !$isGrandJackpot) {
        // --- LOSS LOGIC & PSYCHOLOGICAL TEASERS ---
        $winSym = 0;
        
        $stmtTeaser = $pdo->prepare("SELECT value FROM system_settings WHERE key_name = 'teaser_rate'");
        $stmtTeaser->execute();
        $baseTeaserRate = (int)($stmtTeaser->fetchColumn() ?: 30);
        
        $teaserChance = ($directorState === 'GATHER' || $directorState === 'CAP_REJECT' || $directorState === 'FREE_BLEED') ? 60 : $baseTeaserRate; 

        if (random_int(1, 100) <= $teaserChance) {
            $isTeaser = true;
            $isReachEye = (random_int(1, 100) <= 50); 
            
            for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
            for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
            for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);
            
            $chosenLine = array_rand($paylines);
            
            // V5.6 Teasers: We explicitly allow GJP (1) to be teased here, even though it's removed from natural spawns
            $teaseSym = random_int(1, 3); 
            
            if ($isReachEye) {
                $result[$paylines[$chosenLine][0]] = $teaseSym;
                $result[$paylines[$chosenLine][1]] = $teaseSym;
                do { $finalSym = $rollReel($reelSpawnRates['reel_3']); } while ($finalSym === $teaseSym);
                $result[$paylines[$chosenLine][2]] = $finalSym;
            } else {
                $result[4] = $teaseSym; 
            }
        } else {
            for($i=0; $i<=6; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_1']);
            for($i=1; $i<=7; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_2']);
            for($i=2; $i<=8; $i+=3) $result[$i] = $rollReel($reelSpawnRates['reel_3']);
        }

        // BRUTAL FAILSAFE: Break accidental lines
        $failsafeLoops = 0;
        do {
            $hasWin = false;
            foreach($paylines as $l) {
                if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) {
                    $result[$l[2]] = ($result[$l[2]] % 7) + 1; 
                    if ($result[$l[2]] == 1) $result[$l[2]] = 2; // Double check GJP doesn't spawn accidentally
                    $hasWin = true;
                }
            }
            $failsafeLoops++;
        } while($hasWin && $failsafeLoops < 15);
    }

    // --- PHASE 6: PAYOUT EXECUTION ---
    $basePayout = 0;
    if (!$isGrandJackpot && $isHit) {
        $mult = $symMultipliers[$winSym] ?? 0;
        
        if ($winSym === 3 && !$bonusMode) { 
            $bonusMode = 'RB'; 
            $bonusSpinsLeft = 8; 
        }
        if ($winSym === 7) { 
            $freeSpins += 1; 
        }
        
        $basePayout = $betAmount * $mult;
        
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
    $vaultSiphon = ($spinWin >= ($betAmount * 50)) ? $spinWin * 0.05 : 0;
    $spinWin -= $vaultSiphon; 
    
    if ($vaultSiphon > 0) {
        $pdo->prepare("INSERT IGNORE INTO user_vaults (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("UPDATE user_vaults SET balance = balance + ?, total_saved = total_saved + ? WHERE user_id = ?")->execute([$vaultSiphon, $vaultSiphon, $userId]);
    }

    if ($spinWin > 0) {
        $totalEffectiveWin = $spinWin + $vaultSiphon;
        $pdo->prepare("UPDATE users SET balance = balance + ?, pnl_lifetime = pnl_lifetime - ? WHERE id = ?")->execute([$spinWin, $totalEffectiveWin, $userId]);
        $sessionOut += $totalEffectiveWin;
        $sessionWinStreak++;
    } else {
        if (!$isFreeSpin && !$bonusMode) $sessionWinStreak = 0;
    }
    
    $xpGain = floor($betAmount / 1000);
    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $userId]);

    $lapsSinceBonus = ($bonusMode || $isGrandJackpot) ? 0 : $lapsSinceBonus + 1;
    $sessionSpins++;

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, session_spins = ?, session_win_streak = ?, session_in = ?, session_out = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([($spinWin + $vaultSiphon), $currentToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $sessionSpins, $sessionWinStreak, $sessionIn, $sessionOut, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($result), $xpGain]);

    // Tournaments & Missions Tracking
    $today = date('Y-m-d');
    $pdo->prepare("UPDATE tournament_entries te JOIN tournaments t ON te.tournament_id = t.id SET te.spins_used = te.spins_used + 1, te.current_score = te.current_score + ? WHERE te.user_id = ? AND t.status = 'active' AND t.start_time <= NOW() AND t.end_time > NOW() AND te.spins_used < t.spin_limit")->execute([$spinWin, $userId]);
    $pdo->prepare("INSERT IGNORE INTO user_mission_progress (user_id, mission_id, tracking_date) SELECT ?, id, ? FROM daily_missions WHERE is_active = 1")->execute([$userId, $today]);
    $pdo->prepare("UPDATE user_mission_progress ump JOIN daily_missions dm ON ump.mission_id = dm.id SET ump.progress = ump.progress + 1 WHERE ump.user_id = ? AND ump.tracking_date = ? AND dm.action_type = 'spin'")->execute([$userId, $today]);
    if ($spinWin > 0) {
        $pdo->prepare("UPDATE user_mission_progress ump JOIN daily_missions dm ON ump.mission_id = dm.id SET ump.progress = ump.progress + ? WHERE ump.user_id = ? AND ump.tracking_date = ? AND dm.action_type = 'win_total'")->execute([$spinWin, $userId, $today]);
    }

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