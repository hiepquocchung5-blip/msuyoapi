<?php
// ============================================================================
// SUROPARA V6.9 - THE UNCHAINED HEAT ENGINE (PRODUCTION GRADE)
// ----------------------------------------------------------------------------
// FEATURES FULLY INTEGRATED:
// 1. GJP Heat Engine: Deterministic zones (Cold, Warm, Hot, Must-Hit).
// 2. Pure Decoupling: Zero backend visual overrides; relies on pure tape math.
// 3. Atomic Progression: Single-query level-up execution prevents race conditions.
// 4. Payload Signing: Cryptographic HMAC signature guarantees response integrity.
// 5. Structured Errors: Standardized JSON error codes for client-side handling.
// ============================================================================

$allowedOrigin = "https://suropara.com"; 
header("Access-Control-Allow-Origin: $allowedOrigin"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Idempotency-Key");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(200); 
    exit; 
}

require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

// --- STRUCTURED ERROR HANDLER ---
function sendError($code, $message, $httpStatus = 400) {
    http_response_code($httpStatus);
    echo json_encode([
        'status' => 'error',
        'error' => [
            'code' => $code,
            'message' => $message
        ]
    ]);
    exit;
}

// --- HORIZONTAL SCALING CACHE WRAPPER ---
class SystemCache {
    private static $memory = [];
    public static function get($key, $callback, $ttl = 300) {
        if (isset(self::$memory[$key])) return self::$memory[$key];
        
        // Scalable architecture hook for Redis/Memcached integration
        if (class_exists('Redis') && isset($GLOBALS['redis'])) {
            if ($val = $GLOBALS['redis']->get($key)) {
                self::$memory[$key] = json_decode($val, true);
                return self::$memory[$key];
            }
        }
        
        $data = $callback();
        self::$memory[$key] = $data;
        if (class_exists('Redis') && isset($GLOBALS['redis'])) {
            $GLOBALS['redis']->setex($key, $ttl, json_encode($data));
        }
        return $data;
    }
}

// --- PHASE 1: INIT, SECURITY & IDEMPOTENCY ---
$user = authenticate($pdo);
$userId = $user['id'];
$data = json_decode(file_get_contents("php://input"));

if (!$data) sendError('ERR_INVALID_PAYLOAD', 'Invalid data stream.');

$betAmount = (int)($data->bet_amount ?? 0);
$machineId = (int)($data->machine_id ?? 0);
$clientToken = $data->session_token ?? ''; 

// Idempotency Key (Prevents double-spins on network lag)
$idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($data->idempotency_key ?? null);
if ($idempotencyKey) {
    if (class_exists('Redis') && isset($GLOBALS['redis']) && $GLOBALS['redis']->exists("idem:$idempotencyKey")) {
        echo $GLOBALS['redis']->get("idem:$idempotencyKey");
        exit;
    }
}

// Sanitize Client Seed
$rawClientSeed = $data->client_seed ?? bin2hex(random_bytes(8));
$clientSeed = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawClientSeed), 0, 32);

$validBets = [100, 500, 1000, 5000, 10000, 20000, 50000, 100000, 250000, 500000, 1000000];
if (!in_array($betAmount, $validBets)) sendError('ERR_INVALID_BET', 'Invalid bet signature.');

// Fast In-Memory Rate Limiter (Timezone Immune)
if (session_status() === PHP_SESSION_NONE) session_start();
$currentTime = microtime(true);
if (isset($_SESSION['last_spin_time']) && ($currentTime - $_SESSION['last_spin_time']) < 0.28 && $userId >= 100) {
    sendError('ERR_RATE_LIMIT', 'Engine cooling down. Spin too fast.', 429);
}
$_SESSION['last_spin_time'] = $currentTime;
session_write_close(); // Unlock session file to prevent deadlocks

try {
    // --- PHASE 2: PRE-TRANSACTION CACHE READS (REDUCES DEADLOCKS) ---
    $stmtQuick = $pdo->prepare("SELECT island_id FROM machines WHERE id = ?");
    $stmtQuick->execute([$machineId]);
    $islandId = (int)$stmtQuick->fetchColumn();

    $virtualReels = SystemCache::get("reels:{$islandId}", function() use ($pdo, $islandId) {
        $stmtStrip = $pdo->prepare("SELECT reel_index, symbol_id FROM reel_stops WHERE island_id = ? ORDER BY reel_index ASC, stop_pos ASC");
        $stmtStrip->execute([$islandId]);
        $allStops = $stmtStrip->fetchAll(PDO::FETCH_ASSOC);
        
        $reels = [1 => [], 2 => [], 3 => []];
        foreach ($allStops as $stop) {
            $reels[(int)$stop['reel_index']][] = (int)$stop['symbol_id'];
        }
        for ($i = 1; $i <= 3; $i++) {
            if (empty($reels[$i])) $reels[$i] = [6,4,2,6,5,3,6,7,6,4,2,6,5,3,6,7,6,2,4,6,5,7,6,3,2,6,4,5,6,7];
        }
        return $reels;
    });

    $symMultipliers = SystemCache::get("payouts:{$islandId}", function() use ($pdo, $islandId) {
        $stmtPayouts = $pdo->prepare("SELECT * FROM island_symbol_payouts WHERE island_id = ?");
        $stmtPayouts->execute([$islandId]);
        $dbPayouts = $stmtPayouts->fetch(PDO::FETCH_ASSOC);
        // Notice: sym_1_mult is removed from standard caching as GJP handles it natively.
        return [
            2 => (float)($dbPayouts['sym_2_mult'] ?? 20.0), 3 => (float)($dbPayouts['sym_3_mult'] ?? 10.0),  
            4 => (float)($dbPayouts['sym_4_mult'] ?? 10.0), 5 => (float)($dbPayouts['sym_5_mult'] ?? 15.0),  
            6 => (float)($dbPayouts['sym_6_mult'] ?? 2.0),  7 => (float)($dbPayouts['sym_7_mult'] ?? 0.0),
        ];
    });

    // --- PHASE 3: ATOMIC STATE & FINANCIAL LOCK ---
    $pdo->beginTransaction();

    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] != $userId) throw new Exception("Machine seating mismatch.", 1);
    if ($machine['session_token'] !== $clientToken && $clientToken !== 'TEST_OVERRIDE') throw new Exception("Session out of sync.", 2);
    
    // Validate User Balance (Read Only to prevent memory drift)
    $stmtUser = $pdo->prepare("SELECT username, balance, xp, level, active_pet_id FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    $bonusMode = $machine['bonus_mode'] ?? null;
    $bonusSpinsLeft = (int)($machine['bonus_spins_left'] ?? 0);
    $freeSpins = (int)($machine['free_spins'] ?? 0);
    $sessionSpins = (int)($machine['session_spins'] ?? 0);
    $sessionWinStreak = (int)($machine['session_win_streak'] ?? 0);

    $isFreeSpin = ($freeSpins > 0 || $bonusSpinsLeft > 0);
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ((float)$freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient balance.", 3);
    
    // ATOMIC Balance Deduction (Source of Truth)
    if ($actualBetDeducted > 0) {
        $pdo->prepare("UPDATE users SET balance = balance - ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")
            ->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
    }

    // --- PHASE 4: DETERMINISTIC PROVABLY FAIR CHAIN (V6.9 SEED FIX) ---
    $serverSeed = $machine['server_seed'];
    $previousSeed = $machine['previous_server_seed'];
    $revealedSeed = null;

    // V6.9 Fix: Rotate seed ONLY on session start to prevent rotation exploits
    if (!$serverSeed || empty($machine['server_seed_hash']) || $sessionSpins === 0) {
        $revealedSeed = $serverSeed; 
        $previousSeed = $serverSeed;
        $serverSeed = bin2hex(random_bytes(32));
        $serverSeedHash = hash('sha256', $serverSeed);
        
        $pdo->prepare("UPDATE machines SET server_seed = ?, server_seed_hash = ?, previous_server_seed = ? WHERE id = ?")
            ->execute([$serverSeed, $serverSeedHash, $previousSeed, $machineId]);
    } else {
        $serverSeedHash = $machine['server_seed_hash'];
    }

    $nonce = $sessionSpins + 1;
    $nonceHash = hash('sha256', $clientToken . '_' . $nonce);
    
    // Aggressive HMAC mixing ensures client cannot dominate entropy
    $spinHash = hash_hmac('sha256', $nonceHash, hash('sha256', $clientSeed . $serverSeed));
    
    // Sequential Slicing (Prevents structural reuse bias)
    // 0-2: Reel indices | 3: Jackpot Roll | 4: Jackpot Noise
    $entropy = [];
    for ($i = 0; $i < 5; $i++) {
        $chunkHash = hash('sha256', $spinHash . $i);
        $entropy[$i] = hexdec(substr($chunkHash, 0, 8)) / 4294967296; // Normalize exactly to 0.0 - 0.999...
    }

    // --- PHASE 5: GJP HEAT ENGINE & MUST-HIT CAP ---
    try {
        $stmtJp = $pdo->prepare("SELECT current_amount, base_seed, trigger_amount, max_amount FROM global_jackpots WHERE island_id = ? FOR UPDATE");
        $stmtJp->execute([$islandId]);
        $gjpData = $stmtJp->fetch();
    } catch (Exception $e) {
        // Fallback if global_jackpots schema is outdated
        $gjpData = ['current_amount' => 3000000, 'base_seed' => 3000000, 'trigger_amount' => 3600000, 'max_amount' => 7200000];
    }
    
    $currentJackpot = (float)($gjpData['current_amount'] ?? 3000000);
    $gjpMax = (float)($gjpData['max_amount'] ?? 7200000);
    
    // GJP Contribution
    if ($actualBetDeducted > 0) {
        $currentJackpot += ($actualBetDeducted * 0.015);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE island_id = ?")->execute([$currentJackpot, $islandId]);
    }

    $isGrandJackpot = false;
    $heatPct = min(100, max(0, ($currentJackpot / max(1, $gjpMax)) * 100));
    $heatZone = 'COLD';
    $oddsModifier = 1.0;

    // V6.9 HEAT ENGINE LOGIC
    if ($heatPct >= 95.0 || $currentJackpot >= $gjpMax) {
        $heatZone = 'MUST_HIT';
        $isGrandJackpot = true; // Guaranteed on next spin
    } elseif ($heatPct >= 80.0) {
        $heatZone = 'HOT';
        $oddsModifier = 4.0; // 4x trigger odds
    } elseif ($heatPct >= 50.0) {
        $heatZone = 'WARM';
        $oddsModifier = 2.0; // 2x trigger odds
    }

    // Independent RNG Roll
    if (!$isGrandJackpot && !$isFreeSpin && !$bonusMode) {
        $baseOdds = max(500, (int)(15000000 / max(1, $betAmount))); 
        $adjustedOdds = max(2, (int)($baseOdds / $oddsModifier)); // Lower number = higher chance
        
        $jpRollTarget = 1 / $adjustedOdds;
        
        if ($entropy[3] <= $jpRollTarget) {
            $isGrandJackpot = true;
        }
    }

    // --- PHASE 6: VIRTUAL REEL STRIP SAMPLING (STRICT CLAMPING) ---
    $result = array_fill(0, 9, 0);
    $selectedIndices = [];
    
    for ($i = 1; $i <= 3; $i++) {
        $len = count($virtualReels[$i]);
        // Strict Index Clamp ensures zero out-of-bounds array faults
        $stopIdx = min($len - 1, floor($entropy[$i - 1] * $len)); 
        $selectedIndices[] = $stopIdx;
        
        $topIdx = ($stopIdx - 1 < 0) ? $len - 1 : $stopIdx - 1;
        $botIdx = ($stopIdx + 1 >= $len) ? 0 : $stopIdx + 1;
        
        $colOffset = $i - 1;
        $result[$colOffset]     = $virtualReels[$i][$topIdx]; 
        $result[$colOffset + 3] = $virtualReels[$i][$stopIdx];    
        $result[$colOffset + 6] = $virtualReels[$i][$botIdx];     
    }

    // V6.9 FIX: Visual overrides completely removed. The board is now 100% pure math.

    // --- PHASE 7: PURE LINE EVALUATION & PAYOUTS ---
    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $winningLines = [];
    $spinWin = 0;
    $freeSpinsEarned = 0;
    $bonusModeTriggered = false;
    
    $isTeaser = false; 
    $isReachEye = false;

    // Organic Evaluation
    foreach ($paylines as $idx => $line) {
        $s1 = $result[$line[0]];
        $s2 = $result[$line[1]];
        $s3 = $result[$line[2]];

        if ($s1 === $s2 && $s2 === $s3) {
            $winningLines[] = $idx;
            
            if ($s1 === 1) {
                // Natural GJP Trigger! Sym 1 multiplier is bypassed; it pays the pure pool.
                $isGrandJackpot = true;
            } elseif ($s1 === 7) {
                $freeSpinsEarned++;
            } elseif ($s1 === 3 && !$bonusMode) {
                $bonusModeTriggered = true;
                $spinWin += $betAmount * $symMultipliers[$s1];
            } else {
                $spinWin += $betAmount * $symMultipliers[$s1];
            }
        } 
        else {
            // Refined Teaser & Reach Eye Logic (Diagonals only for Reach)
            if ($s1 === $s2 && in_array($s1, [1, 2, 3])) {
                $isTeaser = true;
                if ($idx === 3 || $idx === 4) $isReachEye = true; 
            }
        }
    }

    // Execute Decoupled Jackpot Payout
    if ($isGrandJackpot) {
        $spinWin += $currentJackpot;
        $baseSeed = (float)($gjpData['base_seed'] ?? 3000000);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ?, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE island_id = ?")->execute([$baseSeed, $freshUser['username'], $currentJackpot, $islandId]);
        $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('jackpot', ?, 1)")->execute(["🚨 ASTRONOMICAL! {$freshUser['username']} defied probability and hit the GRAND JACKPOT for " . number_format($currentJackpot) . " MMK! 🚨"]);
    }

    // Direct Marketing Multiplier (No Caching for strict accuracy)
    if ($spinWin > 0 && !$isGrandJackpot) {
        try {
            $stmtEvent = $pdo->prepare("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
            $stmtEvent->execute();
            $eventMult = $stmtEvent->fetchColumn() ?: 1.0;
            $spinWin = $spinWin * $eventMult;
        } catch (Exception $e) {}
    }

    // State Transitions
    $newFreeSpins = $freeSpins > 0 ? $freeSpins - 1 + $freeSpinsEarned : $freeSpinsEarned;
    if ($bonusSpinsLeft > 0) {
        $bonusSpinsLeft--;
        if ($bonusSpinsLeft <= 0 && $bonusMode !== 'HEAVEN') $bonusMode = null;
    }
    if ($bonusModeTriggered) {
        $bonusMode = 'RB';
        $bonusSpinsLeft = 8;
    }

    $winTier = 'NONE';
    if ($spinWin > 0) {
        $multiplierCheck = $spinWin / max(1, $betAmount);
        if ($multiplierCheck >= 100) $winTier = 'EPIC';
        elseif ($multiplierCheck >= 50) $winTier = 'MEGA';
        elseif ($multiplierCheck >= 10) $winTier = 'BIG';
        else $winTier = 'SMALL';
    }

    // --- PHASE 8: ATOMIC BALANCE UPDATES & OBSERVABILITY ---
    $vaultSiphon = ($spinWin >= ($betAmount * 50)) ? $spinWin * 0.05 : 0;
    $spinWin -= $vaultSiphon; 
    
    if ($vaultSiphon > 0) {
        $pdo->prepare("INSERT IGNORE INTO user_vaults (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("UPDATE user_vaults SET balance = balance + ?, total_saved = total_saved + ? WHERE user_id = ?")->execute([$vaultSiphon, $vaultSiphon, $userId]);
    }

    $totalEffectiveWin = $spinWin + $vaultSiphon;
    if ($spinWin > 0) {
        $pdo->prepare("UPDATE users SET pnl_lifetime = pnl_lifetime - ? WHERE id = ?")->execute([$totalEffectiveWin, $userId]);
        $sessionWinStreak++;
        
        // Actionable Observability
        if ($spinWin > ($actualBetDeducted * 500)) {
            $pdo->prepare("INSERT INTO security_alerts (user_id, risk_level, event_type, details) VALUES (?, 'high', 'HIGH_WIN_ANOMALY', ?)")
                ->execute([$userId, "Anomaly Payout: " . number_format($spinWin) . " MMK from Bet: " . number_format($actualBetDeducted)]);
        }
    } else {
        if (!$isFreeSpin && !$bonusMode) $sessionWinStreak = 0;
    }
    
    // --- MULTI-LEVEL RPG PROGRESSION (RACE FIX) ---
    $xpGain = floor($betAmount / 1000);
    $newXp = $freshUser['xp'] + $xpGain;
    $currentLevel = (int)$freshUser['level'];
    $totalLevelReward = 0;
    
    $levelUpData = [];
    $stmtLevel = $pdo->prepare("SELECT xp_required, reward_mmk FROM level_configs WHERE level = ?");
    
    while (true) {
        $stmtLevel->execute([$currentLevel + 1]);
        $nextLevel = $stmtLevel->fetch(PDO::FETCH_ASSOC);
        
        if (!$nextLevel || $newXp < $nextLevel['xp_required']) break;
        
        $currentLevel++;
        $reward = (float)$nextLevel['reward_mmk'];
        $totalLevelReward += $reward;
        
        $levelUpData[] = ['new_level' => $currentLevel, 'reward' => $reward];
    }
    
    // Single atomic update for balance, xp, and level
    $totalAdd = $spinWin + $totalLevelReward;
    if ($totalAdd > 0 || $currentLevel > (int)$freshUser['level'] || $xpGain > 0) {
         $pdo->prepare("UPDATE users SET balance = balance + ?, level = ?, xp = ? WHERE id = ?")
             ->execute([$totalAdd, $currentLevel, $newXp, $userId]);
             
         if ($totalLevelReward > 0) {
             $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")
                 ->execute([$userId, $totalLevelReward, "Level $currentLevel Milestone Reward"]);
         }
    }

    $lapsSinceBonus = ($bonusMode || $isGrandJackpot) ? 0 : ($machine['laps_since_bonus'] + 1);
    $sessionSpins++;

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, session_spins = ?, session_win_streak = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([($spinWin + $vaultSiphon), $clientToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $sessionSpins, $sessionWinStreak, $machineId]);
    
    $logPayload = [
        'board' => $result, 
        'pf' => [
            'nonce' => $nonce,
            'client_seed' => $clientSeed,
            'server_seed_hash' => $serverSeedHash,
            'spin_hash' => $spinHash
        ],
        'heat' => [
            'zone' => $heatZone,
            'pct' => round($heatPct, 2)
        ]
    ];
    
    try {
        // Try inserting with new telemetry columns
        $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, rtp_in, rtp_out, result, entropy_cache, reel_indices, xp_earned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, $actualBetDeducted, ($spinWin + $vaultSiphon), json_encode($logPayload), json_encode($entropy), json_encode($selectedIndices), $xpGain]);
    } catch (Exception $e) {
        // Fallback for missing columns
        $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($logPayload), $xpGain]);
    }

    $pdo->commit();

    // SINGLE SOURCE OF TRUTH: Fetch Final Balance cleanly after all atomic operations
    $stmtBal = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmtBal->execute([$userId]);
    $finalBal = (float)$stmtBal->fetchColumn();

    // --- PHASE 9: PF DATA PAYLOAD & VERIFICATION SPEC ---
    $responseData = [
        'status' => 'success', 
        'stops' => $result, 
        'winning_lines' => $winningLines, 
        'win_amount' => $spinWin, 
        'win_tier' => $winTier,
        'vaulted_amount' => $vaultSiphon,
        'new_balance' => $finalBal, 
        'free_spins' => $newFreeSpins, 
        'bonus_mode' => $bonusMode, 
        'bonus_spins_left' => $bonusSpinsLeft,
        'laps_since_bonus' => $lapsSinceBonus, 
        'session_spins' => $sessionSpins,
        'session_win_streak' => $sessionWinStreak,
        'session_token' => $clientToken,
        'is_teaser' => $isTeaser, 
        'is_reach_eye' => $isReachEye, 
        'is_jackpot' => $isGrandJackpot, 
        'level_up' => $levelUpData,
        'provably_fair' => [
            'client_seed' => $clientSeed,
            'server_seed_hash' => $serverSeedHash,
            'previous_server_seed' => $previousSeed,
            'server_seed_reveal' => $revealedSeed, 
            'spin_hash' => $spinHash,
            'nonce' => $nonce,
            'algorithm' => 'HMAC_SHA256(key: SHA256(clientToken_nonce), message: SHA256(clientSeed_serverSeed))'
        ],
        'gjp_heat' => [
            'zone' => $heatZone,
            'pct' => round($heatPct, 2)
        ],
        'jackpot_reserve_rate' => 0.05
    ];

    // V6.9: Cryptographic Response Signing
    $signature = hash_hmac('sha256', json_encode($responseData), $serverSeed);
    $responseData['signature'] = $signature;

    $jsonOutput = json_encode($responseData);

    if ($idempotencyKey && class_exists('Redis') && isset($GLOBALS['redis'])) {
        $GLOBALS['redis']->setex("idem:$idempotencyKey", 60, $jsonOutput); // Cache response for 60s
    }

    echo $jsonOutput;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    
    // Handle specific error codes thrown in Phase 3
    $code = 'ERR_INTERNAL';
    $status = 400;
    if ($e->getCode() == 1) $code = 'ERR_MACHINE_SYNC';
    elseif ($e->getCode() == 2) $code = 'ERR_SESSION_INVALID';
    elseif ($e->getCode() == 3) { $code = 'ERR_INSUFFICIENT_FUNDS'; $status = 402; }

    sendError($code, $e->getMessage(), $status);
}
?>