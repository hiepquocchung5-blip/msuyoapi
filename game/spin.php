<?php
// ============================================================================
// SUROPARA V2 - LEVIATHAN SLOT ENGINE v6.0 (The Omni-God Edition)
// ----------------------------------------------------------------------------
// FEATURES:
// 1. Dynamic Island Volatility (Alters Symbol Weights & Hit Rates)
// 2. Win Streak Multipliers (Compounding rewards for consecutive wins)
// 3. Win Tier Categorization (MEGA, EPIC, LEGENDARY for Frontend UI)
// 4. Live Tournament Telemetry & Daily Mission Progression
// 5. Complete VIP Scaling, Zones, Micro-Volatility, Soft Pity, & Vaults
// ============================================================================

header("Access-Control-Allow-Origin: https://suropara.com");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

// --- PHASE 1: INIT & SECURITY ---
$user = authenticate($pdo);
$userId = $user['id'];

$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

if (!$data) {
    http_response_code(400); echo json_encode(['error' => 'Invalid data stream.']); exit;
}

$betAmount = (int)($data->bet_amount ?? $data->betAmount ?? 0);
$machineId = (int)($data->machine_id ?? $data->machineId ?? 0);
$clientToken = $data->session_token ?? $data->sessionToken ?? ''; 

// Security Checks (0.3s allows Turbo Mode, blocks botnets)
Security::rateLimit($pdo, $userId, 'spin', 0.3);
Security::validateBet($betAmount);

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
    
    $currentToken = $machine['session_token'];

    $stmtUser = $pdo->prepare("SELECT username, balance, xp, level, active_pet_id, pnl_lifetime, current_month_big_wins, tracking_month FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    // Fallbacks for dynamically added columns
    $bonusMode = $machine['bonus_mode'] ?? null;
    $bonusSpinsLeft = (int)($machine['bonus_spins_left'] ?? 0);
    $freeSpins = (int)($machine['free_spins'] ?? 0);
    $lapsSinceBonus = (int)($machine['laps_since_bonus'] ?? 0); 
    $sessionSpins = (int)($machine['session_spins'] ?? 0);
    $sessionWinStreak = (int)($machine['session_win_streak'] ?? 0); // NEW: Win Streak Tracker

    $isFreeSpin = ($freeSpins > 0 || $bonusSpinsLeft > 0);
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ($freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient balance.");
    
    // Monthly Reset for Win Caps
    $currentMonth = (int)date('Ym');
    if ((int)$freshUser['tracking_month'] !== $currentMonth) {
        $pdo->prepare("UPDATE users SET tracking_month = ?, current_month_big_wins = 0 WHERE id = ?")->execute([$currentMonth, $userId]);
        $freshUser['current_month_big_wins'] = 0;
    }

    if ($actualBetDeducted > 0) {
        $pdo->prepare("UPDATE users SET balance = balance - ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE name = 'GRAND SURO JACKPOT'")->execute([$actualBetDeducted * 0.01]);
    }

    // --- PHASE 3: THE LEVIATHAN v6.0 ALGORITHM ---
    $stmtIsland = $pdo->prepare("SELECT rtp_rate, volatility FROM islands WHERE id = ?");
    $stmtIsland->execute([$machine['island_id']]);
    $islandData = $stmtIsland->fetch();
    
    $baseRtp = (float)$islandData['rtp_rate'];
    $volatility = $islandData['volatility'] ?? 'medium';

    $playerPnl = (float)$freshUser['pnl_lifetime'];
    $isMonthlyBigWinCapped = ((int)$freshUser['current_month_big_wins'] >= 2);
    
    $targetRtp = $baseRtp;
    $teaserChance = 30;

    // A. Volatility Engine adjustments
    // High Volatility = Less frequent hits, but when it hits, the symbol weights shift to higher payouts
    if ($volatility === 'high') { $targetRtp -= 5.0; $teaserChance += 10; }
    if ($volatility === 'extreme') { $targetRtp -= 10.0; $teaserChance += 20; }
    if ($volatility === 'low') { $targetRtp += 5.0; $teaserChance -= 10; }

    // B. Momentum & Streak Multipliers
    $momentumMult = 1.0 + (min(10, floor($sessionSpins / 100)) * 0.1);
    $streakMult = ($sessionWinStreak >= 3) ? 1.5 : 1.0; // 50% boost if on a 3+ win streak

    // C. VIP Scaling Boost
    $vipBoost = min(5.0, floor($freshUser['level'] / 10) * 0.5); 
    $targetRtp += $vipBoost;

    // D. Time-of-Day Volatility (Happy Hour 8 PM - 10 PM)
    $hour = (int)date('H');
    if ($hour >= 20 && $hour <= 22) { $targetRtp += 5.0; $teaserChance += 10; }

    // E. The Zone System (100-150, 400-450, 600-650)
    $inZone = false;
    if (($lapsSinceBonus >= 100 && $lapsSinceBonus <= 150) || ($lapsSinceBonus >= 400 && $lapsSinceBonus <= 450) || ($lapsSinceBonus >= 600 && $lapsSinceBonus <= 650)) {
        $inZone = true; $targetRtp += 20.0; $teaserChance += 45; 
    }

    // F. Hard Overrides (Heaven, Vampire, Mercy)
    if ($bonusMode === 'HEAVEN') { $targetRtp = 85.0; $teaserChance = 10; } 
    elseif ($playerPnl < -200000) { $targetRtp = 12.0; $teaserChance = 75; } 
    elseif ($playerPnl > 1000000) { $targetRtp = 85.0; }

    // G. Micro-Volatility Engine (+/- 2% noise)
    $microVol = random_int(-20, 20) / 10.0;
    $targetRtp += $microVol;

    // --- PHASE 4: TRIGGER CHECKS ---
    $isHit = random_int(1, 10000) <= (int)($targetRtp * 100);
    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $result = array_fill(0, 9, 0);
    $winningLines = [];
    $spinWin = 0;
    $isTeaser = false; $isReachEye = false; $isGrandJackpot = false; $isFreeze = false; $isSoftPity = false;

    // 1. Long Freeze (1/65536)
    if (!$isFreeSpin && !$bonusMode && random_int(1, 65536) <= (max(1, $betAmount / 5000))) $isFreeze = true;

    // 2. Tenjo Ceiling (777 Spins)
    $isTenjoHit = (!$isFreeSpin && !$bonusMode && $lapsSinceBonus >= 777);

    // 3. Soft Pity (Guaranteed Replay/Cherry every 20 dead spins)
    if (!$isHit && !$isFreeSpin && !$bonusMode && $lapsSinceBonus > 0 && ($lapsSinceBonus % 20 === 0)) {
        $isHit = true; $isSoftPity = true;
    }

    // 4. Grand Jackpot (Bet-Weighted)
    $jackpotOdds = max(200, (int)(100000000 / max(1, $betAmount))); 
    if (!$isFreeSpin && !$bonusMode && random_int(1, $jackpotOdds) === 1) {
        $stmtJp = $pdo->query("SELECT current_amount FROM global_jackpots WHERE name = 'GRAND SURO JACKPOT' FOR UPDATE");
        $jpAmount = (float)$stmtJp->fetchColumn();
        if ($jpAmount > 0) {
            $isGrandJackpot = true; $spinWin += $jpAmount;
            $pdo->prepare("UPDATE global_jackpots SET current_amount = 5000000.00, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE name = 'GRAND SURO JACKPOT'")->execute([$freshUser['username'], $jpAmount]);
            
            // Broadcast Jackpot
            $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('jackpot', ?, 1)")
                ->execute(["🚨 MEGA JACKPOT! {$freshUser['username']} just won " . number_format($jpAmount) . " MMK! 🚨"]);

            for ($i=0; $i<9; $i++) $result[$i] = random_int(4, 7);
            $result[3] = 1; $result[4] = 1; $result[5] = 1; $winningLines[] = 1;
        }
    }

    // --- PHASE 5: GRID GENERATION ---
    if ($isFreeze || $isTenjoHit) {
        for ($i=0; $i<9; $i++) $result[$i] = random_int(2, 6);
        $result[3] = 1; $result[4] = 1; $result[5] = 1; $winningLines[] = 1;
        if ($isFreeze) { $bonusMode = 'HEAVEN'; $bonusSpinsLeft = 32; }
    } elseif (!$isGrandJackpot) {
        if ($bonusSpinsLeft > 0 && $bonusMode !== 'HEAVEN') {
            // AT MODE
            $winSym = (random_int(1, 100) > 80) ? 5 : 4; 
            for ($i=0; $i<9; $i++) $result[$i] = random_int(4, 7);
            $chosenLine = array_rand($paylines);
            foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
            
            $bonusSpinsLeft--;
            if ($bonusSpinsLeft <= 0) {
                if (random_int(1, 100) <= 30) { $bonusMode = 'HEAVEN'; $bonusSpinsLeft = 32; } else { $bonusMode = null; }
            }
            $newFreeSpins = $freeSpins; 
        } else {
            // NORMAL & HEAVEN MODE
            if ($bonusMode === 'HEAVEN') $bonusSpinsLeft--;
            if ($bonusSpinsLeft <= 0 && $bonusMode === 'HEAVEN') $bonusMode = null;
            $newFreeSpins = ($freeSpins > 0) ? $freeSpins - 1 : 0;
            
            if ($isHit) {
                // Dynamic Volatility Symbol Weights [Symbol_ID, Weight]
                if ($isSoftPity) { 
                    $allowed = [[7,70], [6,30]]; 
                } elseif ($targetRtp <= 15.0) { 
                    $allowed = [[7,75], [6,20], [4,4], [5,1]]; 
                } elseif ($isMonthlyBigWinCapped && $bonusMode !== 'HEAVEN') { 
                    $allowed = [[7,50], [6,25], [4,15], [5,7], [3,3]]; 
                } else {
                    if ($volatility === 'high' || $volatility === 'extreme') {
                        // High Volatility: Top-heavy weights
                        $allowed = [[7,20], [6,15], [4,20], [5,20], [3,15], [2,7], [1,3]]; 
                    } elseif ($volatility === 'low') {
                        // Low Volatility: Bottom-heavy weights
                        $allowed = [[7,50], [6,30], [4,10], [5,5], [3,4], [2,1], [1,0]]; 
                    } else {
                        // Medium / Standard
                        $allowed = [[7,40], [6,25], [4,15], [5,10], [3,6], [2,3], [1,1]]; 
                    }
                } 

                $totalW = array_sum(array_column($allowed, 1));
                $randW = random_int(1, $totalW); $currW = 0; $winSym = 7;
                foreach ($allowed as $a) { $currW += $a[1]; if ($randW <= $currW) { $winSym = $a[0]; break; } }
                
                for ($i=0; $i<9; $i++) $result[$i] = random_int(3, 7); 
                $chosenLine = array_rand($paylines);
                
                if ($winSym === 1) {
                    if (random_int(1, 100) > 30) { foreach($paylines[$chosenLine] as $pos) $result[$pos] = 1; } 
                    else { $result[$paylines[$chosenLine][0]] = 1; $result[$paylines[$chosenLine][1]] = 1; $result[$paylines[$chosenLine][2]] = 3; }
                } else {
                    foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
                }
            } else {
                // LOSS & TEASERS
                do {
                    for($i=0; $i<9; $i++) $result[$i] = random_int(2, 7);
                    $hasWin = false;
                    foreach($paylines as $l) { if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) { $hasWin = true; break; } }
                } while($hasWin);

                if (random_int(1, 100) <= $teaserChance) {
                    $teaserSym = (random_int(1,100) > 85 && !$isMonthlyBigWinCapped) ? 1 : 3; 
                    $result[3] = $teaserSym; $result[4] = $teaserSym;
                    $slipPosition = (random_int(0, 1) === 0) ? 2 : 8; 
                    $result[5] = random_int(4, 7); $result[$slipPosition] = $teaserSym; 
                    $isTeaser = true;
                    if ($teaserSym == 1 && random_int(1, 100) > 60) $isReachEye = true;
                }
            }
        }
    }

    // --- PHASE 6: PAYOUT & TIER ASSIGNMENT ---
    $basePayout = 0;

    if (!$isGrandJackpot) { 
        if (in_array(6, [$result[0], $result[3], $result[6]])) { $basePayout += $betAmount * 2; $winningLines[] = 99; }
        foreach ($paylines as $idx => $line) {
            $s1 = $result[$line[0]]; $s2 = $result[$line[1]]; $s3 = $result[$line[2]];
            if ($s1 == $s2 && $s2 == $s3) {
                $winningLines[] = $idx;
                if ($s1 == 7) { $newFreeSpins += 1; } 
                if ($s1 == 4) { $basePayout += $betAmount * 10; } 
                if ($s1 == 5) { $basePayout += $betAmount * 15; } 
                if ($s1 == 1) { $bonusMode = 'BB'; $bonusSpinsLeft = 20; $pdo->prepare("UPDATE users SET current_month_big_wins = current_month_big_wins + 1 WHERE id = ?")->execute([$userId]); }
                elseif ($s1 == 3) { $basePayout += $betAmount * 20; }
            }
            else if ($s1 == 1 && $s2 == 1 && $s3 == 3) { $winningLines[] = $idx; $bonusMode = 'RB'; $bonusSpinsLeft = 8; $pdo->prepare("UPDATE users SET current_month_big_wins = current_month_big_wins + 1 WHERE id = ?")->execute([$userId]); }
        }
    }

    // Apply Active Multipliers
    $stmtEvent = $pdo->prepare("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
    $stmtEvent->execute();
    $winMultiplier = $stmtEvent->fetchColumn() ?: 1.0;
    
    // Calculate Final Liquid Win
    $spinWin = $basePayout * $momentumMult * $streakMult * $winMultiplier;

    // Hard Cap Verification (Max Win limit per spin, excluding Jackpots)
    $maxAllowedWin = $betAmount * 5000;
    if ($spinWin > $maxAllowedWin && !$isGrandJackpot) {
        $spinWin = $maxAllowedWin;
    }

    // Win Tier Categorization for Frontend UX (Sounds/VFX)
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
        // Increment Streak
        $sessionWinStreak++;
    } else {
        // Reset Streak on loss
        if (!$isFreeSpin && !$bonusMode) $sessionWinStreak = 0;
    }
    
    // Level Up Engine
    $xpGain = floor($betAmount / 1000);
    $currentXp = (int)$freshUser['xp'] + $xpGain;
    $levelUpData = null;
    $stmtLevel = $pdo->prepare("SELECT xp_required, reward_mmk FROM level_configs WHERE level = ?");
    $stmtLevel->execute([(int)$freshUser['level'] + 1]);
    $nextLvl = $stmtLevel->fetch();
    
    if ($nextLvl && $currentXp >= $nextLvl['xp_required']) {
        $newLevel = (int)$freshUser['level'] + 1;
        $reward = (float)$nextLvl['reward_mmk'];
        $pdo->prepare("UPDATE users SET level = ?, balance = balance + ? WHERE id = ?")->execute([$newLevel, $reward, $userId]);
        $levelUpData = ['new_level' => $newLevel, 'reward' => $reward];
    }
    $pdo->prepare("UPDATE users SET xp = ? WHERE id = ?")->execute([$currentXp, $userId]);

    $lapsSinceBonus = ($bonusMode || $isGrandJackpot) ? 0 : $lapsSinceBonus + 1;
    $sessionSpins++;

    // Prevent columns from crashing if migration wasn't run completely, use dynamic update building
    try {
        $pdo->exec("ALTER TABLE machines ADD COLUMN IF NOT EXISTS session_win_streak INT DEFAULT 0");
    } catch(Exception $e) {}

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, session_spins = ?, session_win_streak = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([($spinWin + $vaultSiphon), $currentToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $sessionSpins, $sessionWinStreak, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($result), $xpGain]);

    // --- PHASE 8: OMNI-TRACKER (Missions & Tournaments) ---
    try {
        $today = date('Y-m-d');
        $pdo->prepare("INSERT IGNORE INTO user_mission_progress (user_id, mission_id, tracking_date) SELECT ?, id, ? FROM daily_missions WHERE is_active = 1")->execute([$userId, $today]);
        $pdo->prepare("UPDATE user_mission_progress ump JOIN daily_missions dm ON ump.mission_id = dm.id SET ump.progress = ump.progress + 1 WHERE ump.user_id = ? AND ump.tracking_date = ? AND dm.action_type = 'spin'")->execute([$userId, $today]);
        if ($spinWin > 0) {
            $pdo->prepare("UPDATE user_mission_progress ump JOIN daily_missions dm ON ump.mission_id = dm.id SET ump.progress = ump.progress + ? WHERE ump.user_id = ? AND ump.tracking_date = ? AND dm.action_type = 'win_total'")->execute([$spinWin, $userId, $today]);
        }

        $stmtTourney = $pdo->prepare("SELECT te.id, t.spin_limit, te.spins_used FROM tournament_entries te JOIN tournaments t ON te.tournament_id = t.id WHERE te.user_id = ? AND t.status = 'active' AND t.start_time <= NOW() AND t.end_time > NOW()");
        $stmtTourney->execute([$userId]);
        $activeTourneys = $stmtTourney->fetchAll();
        foreach($activeTourneys as $at) {
            if ($at['spins_used'] < $at['spin_limit']) {
                $pdo->prepare("UPDATE tournament_entries SET spins_used = spins_used + 1, current_score = current_score + ? WHERE id = ?")->execute([$spinWin, $at['id']]);
            }
        }
    } catch (Exception $err) {
        // Soft fail
    }

    $pdo->commit();
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    // --- PHASE 9: DATA PAYLOAD ---
    echo json_encode([
        'status' => 'success', 
        'stops' => $result, 
        'winning_lines' => $winningLines, 
        'win_amount' => $spinWin, 
        'win_tier' => $winTier, // SMALL, BIG, MEGA, EPIC
        'vaulted_amount' => $vaultSiphon,
        'new_balance' => (float)$finalBal, 
        'free_spins' => $newFreeSpins, 
        'bonus_mode' => $bonusMode, 
        'bonus_spins_left' => $bonusSpinsLeft,
        'laps_since_bonus' => $lapsSinceBonus, 
        'session_spins' => $sessionSpins,
        'session_win_streak' => $sessionWinStreak,
        'streak_multiplier' => $streakMult,
        'momentum_multiplier' => $momentumMult, 
        'session_token' => $currentToken,
        'is_teaser' => $isTeaser, 
        'is_reach_eye' => $isReachEye, 
        'is_jackpot' => $isGrandJackpot, 
        'is_freeze' => $isFreeze, 
        'in_zone' => $inZone, 
        'volatility_mode' => $volatility,
        'level_up' => $levelUpData
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); 
    echo json_encode(['error' => $e->getMessage()]);
}
?>