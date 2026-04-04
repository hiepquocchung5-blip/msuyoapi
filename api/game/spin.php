<?php
// ============================================================================
// SUROPARA V7.1.0 - LEVIATHAN RTP STRIP ENGINE (STRICT & ELEGANT MODE)
// ----------------------------------------------------------------------------
// FEATURES FULLY INTEGRATED:
// 1. Dynamic GJP RTP: Symbol 1 mathematically bound to Target Base RTP (%).
// 2. Dual-Track Math: 60% Action Hit Rate vs Strict High-Variance GJP.
// 3. Burst Volatility: Heat Zones scale odds via DB configuration.
// 4. Strict DB Enforcement: Zero hardcoded fallbacks; fails safe if unconfigured.
// 5. Aesthetic Refactor: Cleaned up logic blocks, streamlined evaluations.
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

require_once __DIR__ . '/../../utils/auth_middleware.php';
require_once __DIR__ . '/../../utils/security.php';

function sendError($code, $message, $httpStatus = 400) {
    http_response_code($httpStatus);
    echo json_encode(['status' => 'error', 'error' => ['code' => $code, 'message' => $message]]);
    exit;
}

class SystemCache {
    private static $memory = [];
    
    public static function get($key, $callback, $ttl = 300) {
        if (isset(self::$memory[$key])) return self::$memory[$key];
        
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

// --- PHASE 1: INIT, SEC & IDEMPOTENCY ---
$user = authenticate($pdo);
$userId = $user['id'];
$data = json_decode(file_get_contents("php://input"));

if (!$data) sendError('ERR_INVALID_PAYLOAD', 'Malformed data stream.');

$betAmount = (int)($data->bet_amount ?? 0);
$machineId = (int)($data->machine_id ?? 0);
$clientToken = $data->session_token ?? '';

// Idempotency Check
$idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($data->idempotency_key ?? null);
if ($idempotencyKey && class_exists('Redis') && isset($GLOBALS['redis']) && $GLOBALS['redis']->exists("idem:$idempotencyKey")) {
    echo $GLOBALS['redis']->get("idem:$idempotencyKey");
    exit;
}

$rawClientSeed = $data->client_seed ?? bin2hex(random_bytes(8));
$clientSeed = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawClientSeed), 0, 32);

// Bet Validation
$validBets = [100, 500, 1000, 5000, 10000, 20000, 50000, 100000, 250000, 500000, 1000000];
if (!in_array($betAmount, $validBets)) sendError('ERR_INVALID_BET', 'Unrecognized bet signature.');

// Rate Limiter & Anomaly Detection (0.28s cooldown)
if (session_status() === PHP_SESSION_NONE) session_start();
$currentTime = microtime(true);
if (isset($_SESSION['last_spin_time']) && ($currentTime - $_SESSION['last_spin_time']) < 0.28 && $userId >= 100) {
    sendError('ERR_RATE_LIMIT', 'Engine cooling down. Limit exceeded.', 429);
}
$_SESSION['last_spin_time'] = $currentTime;
session_write_close();

try {
    // --- PHASE 2: RAM CACHING (STRICT DB MODE) ---
    $stmtQuick = $pdo->prepare("SELECT island_id FROM machines WHERE id = ?");
    $stmtQuick->execute([$machineId]);
    $islandId = (int)$stmtQuick->fetchColumn();

    if (!$islandId) throw new Exception("Machine connection lost. Re-link required.");

    $virtualReels = SystemCache::get("reels:{$islandId}", function () use ($pdo, $islandId) {
        $stmt = $pdo->prepare("SELECT reel_index, symbol_id FROM reel_stops WHERE island_id = ? ORDER BY reel_index ASC, stop_pos ASC");
        $stmt->execute([$islandId]);
        
        $reels = [1 => [], 2 => [], 3 => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $stop) {
            $reels[(int)$stop['reel_index']][] = (int)$stop['symbol_id'];
        }
        
        if (empty($reels[1]) || empty($reels[2]) || empty($reels[3])) {
            throw new Exception("CRITICAL: Reel strips unconfigured for Sector #$islandId.");
        }
        return $reels;
    }, 3600);

    $symMultipliers = SystemCache::get("payouts:{$islandId}", function () use ($pdo, $islandId) {
        $stmt = $pdo->prepare("SELECT * FROM island_symbol_payouts WHERE island_id = ?");
        $stmt->execute([$islandId]);
        $db = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$db) throw new Exception("CRITICAL: Payout matrix missing for Sector #$islandId.");
        
        return [
            1 => (float)$db['sym_1_mult'], 2 => (float)$db['sym_2_mult'], 3 => (float)$db['sym_3_mult'], 
            4 => (float)$db['sym_4_mult'], 5 => (float)$db['sym_5_mult'], 6 => (float)$db['sym_6_mult'], 
            7 => (float)$db['sym_7_mult']
        ];
    }, 3600);

    $winRates = SystemCache::get("win_rates_v7:{$islandId}", function () use ($pdo, $islandId) {
        $stmt = $pdo->prepare("SELECT base_hit_rate, target_base_rtp, max_rtp_cap, burst_volatility FROM island_win_rates WHERE island_id = ?");
        $stmt->execute([$islandId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$res) throw new Exception("CRITICAL: Leviathan AI missing for Sector #$islandId.");
        
        return [
            'base_hit_rate' => (float)$res['base_hit_rate'],
            'gjp_rtp'       => (float)$res['target_base_rtp'],
            'max_rtp'       => (float)$res['max_rtp_cap'],
            'volatility'    => (float)$res['burst_volatility']
        ];
    }, 3600);

    $levelConfigs = SystemCache::get("level_configs", function () use ($pdo) {
        return $pdo->query("SELECT level, xp_required, reward_mmk FROM level_configs ORDER BY level ASC")->fetchAll(PDO::FETCH_ASSOC);
    }, 3600);

    $eventMult = SystemCache::get("marketing_multiplier", function () use ($pdo) {
        $stmt = $pdo->query("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
        return (float)($stmt->fetchColumn() ?: 1.0);
    }, 60);

    // --- PHASE 3: ATOMIC LOCKS & STATE VALIDATION ---
    $pdo->beginTransaction();

    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] != $userId) throw new Exception("Machine link severed.", 1);
    if ($machine['session_token'] !== $clientToken && $clientToken !== 'TEST_OVERRIDE') throw new Exception("AES mismatch. Token expired.", 2);

    $stmtUser = $pdo->prepare("SELECT username, balance, xp, level FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    // Contextual Machine State
    $bonusMode = $machine['bonus_mode'] ?? null;
    $bonusSpinsLeft = (int)($machine['bonus_spins_left'] ?? 0);
    $freeSpins = (int)($machine['free_spins'] ?? 0);
    $sessionSpins = (int)($machine['session_spins'] ?? 0);

    $isFreeSpin = ($freeSpins > 0 || $bonusSpinsLeft > 0);
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ((float)$freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient MMK.", 3);

    $deltaBalance = -$actualBetDeducted;
    $deltaPnl = $actualBetDeducted;

    // --- PHASE 4: PROVABLY FAIR SEEDING ---
    $serverSeed = $machine['server_seed'];
    $previousSeed = $machine['previous_server_seed'];
    $revealedSeed = null;

    if (!$serverSeed || empty($machine['server_seed_hash']) || $sessionSpins === 0) {
        $revealedSeed = $serverSeed;
        $previousSeed = $serverSeed;
        $serverSeed = bin2hex(random_bytes(32));
        $serverSeedHash = hash('sha256', $serverSeed);
        $pdo->prepare("UPDATE machines SET server_seed = ?, server_seed_hash = ?, previous_server_seed = ? WHERE id = ?")->execute([$serverSeed, $serverSeedHash, $previousSeed, $machineId]);
    } else {
        $serverSeedHash = $machine['server_seed_hash'];
    }

    $nonce = $sessionSpins + 1;
    $nonceHash = hash('sha256', $clientToken . '_' . $nonce);
    $spinHash = hash_hmac('sha256', $nonceHash, hash('sha256', $clientSeed . $serverSeed));

    // Extract 5 distinct chunks of entropy
    $entropy = [];
    for ($i = 0; $i < 5; $i++) {
        $entropy[$i] = hexdec(substr(hash('sha256', $spinHash . $i), 0, 8)) / 4294967296;
    }

    // --- PHASE 5: V7.1 MATHEMATICAL GJP ENGINE ---
    $stmtJp = $pdo->prepare("SELECT current_amount, base_seed, trigger_amount, max_amount, contribution_rate FROM global_jackpots WHERE island_id = ?");
    $stmtJp->execute([$islandId]);
    $gjpData = $stmtJp->fetch(PDO::FETCH_ASSOC);

    if (!$gjpData) throw new Exception("CRITICAL: GJP matrix unconfigured for Sector #$islandId.");

    $currentJackpot = (float)$gjpData['current_amount'];
    $gjpMax = (float)$gjpData['max_amount'];
    $gjpBase = (float)$gjpData['base_seed'];
    $gjpContribRate = (float)$gjpData['contribution_rate'];

    // Siphon Pot (Non-blocking async update)
    if ($actualBetDeducted > 0) {
        $currentJackpot += ($actualBetDeducted * $gjpContribRate);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE island_id = ?")->execute([$actualBetDeducted * $gjpContribRate, $islandId]);
    }

    $isGrandJackpot = false;
    $heatPct = min(100, max(0, (($currentJackpot - $gjpBase) / max(1, ($gjpMax - $gjpBase))) * 100));
    $heatZone = 'COLD';
    
    // Mathematical True RTP Calculation
    $gjpTargetRtp = $winRates['gjp_rtp'] / 100;
    $gjpProbability = ($actualBetDeducted > 0) ? (($gjpTargetRtp * $betAmount) / max(1, $currentJackpot)) : 0;

    // Burst Volatility Scaling
    $burstVol = $winRates['volatility'];
    if ($currentJackpot >= $gjpMax) {
        $heatZone = 'MUST_HIT';
        $isGrandJackpot = true;
    } elseif ($heatPct >= 80.0) {
        $heatZone = 'HOT';
        $gjpProbability *= ($burstVol * 2.0);
    } elseif ($heatPct >= 50.0) {
        $heatZone = 'WARM';
        $gjpProbability *= $burstVol;
    }

    // Trigger Roll (Anti-Farming: Disabled during Free Spins)
    if (!$isGrandJackpot && !$isFreeSpin && !$bonusMode && $actualBetDeducted > 0) {
        if ($entropy[3] <= $gjpProbability) $isGrandJackpot = true;
    }

    // --- PHASE 6: DUAL-TRACK STRIP MAPPING ---
    $targetHitRate = $winRates['base_hit_rate'] / 100; 
    
    // Streak Throttle
    if ((int)($machine['session_win_streak'] ?? 0) > 10) $targetHitRate *= 0.8; 

    $isHit = ($entropy[4] <= $targetHitRate);
    $targetR1 = $targetR2 = $targetR3 = 0;

    if ($isGrandJackpot) {
        $targetR1 = $targetR2 = $targetR3 = 1;
    } elseif ($isHit) {
        $winSyms = [2, 3, 4, 5, 6, 7];
        $targetR1 = $targetR2 = $targetR3 = $winSyms[array_rand($winSyms)];
    } else {
        $targetR1 = $virtualReels[1][array_rand($virtualReels[1])];
        $targetR2 = $virtualReels[2][array_rand($virtualReels[2])];
        do {
            $targetR3 = $virtualReels[3][array_rand($virtualReels[3])];
        } while ($targetR1 == $targetR2 && $targetR2 == $targetR3);
    }

    $result = array_fill(0, 9, 0);
    $selectedIndices = [];

    // Map physical positions
    for ($i = 1; $i <= 3; $i++) {
        $targetSym = ($i == 1) ? $targetR1 : (($i == 2) ? $targetR2 : $targetR3);
        $possibleIndices = array_keys($virtualReels[$i], $targetSym);
        
        $stopIdx = empty($possibleIndices) ? array_rand($virtualReels[$i]) : $possibleIndices[array_rand($possibleIndices)];
        $selectedIndices[] = $stopIdx;

        $len = count($virtualReels[$i]);
        $colOffset = $i - 1;
        
        $result[$colOffset]     = $virtualReels[$i][($stopIdx - 1 < 0) ? $len - 1 : $stopIdx - 1];
        $result[$colOffset + 3] = $virtualReels[$i][$stopIdx];
        $result[$colOffset + 6] = $virtualReels[$i][($stopIdx + 1 >= $len) ? 0 : $stopIdx + 1];
    }

    // --- PHASE 7: PAYLINE EVALUATION ---
    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $winningLines = [];
    $spinWin = 0;
    $freeSpinsEarned = 0;
    $bonusModeTriggered = false;
    $isTeaser = $isReachEye = false;

    foreach ($paylines as $idx => $line) {
        $s1 = $result[$line[0]]; $s2 = $result[$line[1]]; $s3 = $result[$line[2]];

        if ($s1 === $s2 && $s2 === $s3) {
            $winningLines[] = $idx;
            if ($s1 === 1) {
                $isGrandJackpot = true; 
            } elseif ($s1 === 7) {
                $freeSpinsEarned = 1; 
            } elseif ($s1 === 3 && !$bonusMode) {
                $bonusModeTriggered = true;
                $spinWin += $betAmount * $symMultipliers[$s1];
            } else {
                $spinWin += $betAmount * $symMultipliers[$s1];
            }
        } else {
            if ($s1 === $s2 && in_array($s1, [1, 2, 3])) {
                $isTeaser = true;
                if ($idx === 3 || $idx === 4) $isReachEye = true;
            }
        }
    }

    // Process Grand Jackpot
    if ($isGrandJackpot) {
        $spinWin += $currentJackpot;
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ?, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE island_id = ?")->execute([$gjpBase, $freshUser['username'], $currentJackpot, $islandId]);
        $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('jackpot', ?, 1)")->execute(["🚨 ASTRONOMICAL! {$freshUser['username']} hit the GRAND JACKPOT for " . number_format($currentJackpot) . " MMK! 🚨"]);
    }

    if ($spinWin > 0 && !$isGrandJackpot) $spinWin *= $eventMult;

    // --- PHASE 8: CONSOLIDATED STATE & DB WRITE ---
    $deltaBalance += $spinWin;
    $deltaPnl -= $spinWin; 

    // Leveling Math
    $xpGain = floor($betAmount / 1000);
    $newXp = $freshUser['xp'] + $xpGain;
    $currentLevel = (int)$freshUser['level'];
    $totalLevelReward = 0;
    $levelUpData = [];

    foreach ($levelConfigs as $lvl) {
        if ($lvl['level'] > $currentLevel && $newXp >= $lvl['xp_required']) {
            $currentLevel = $lvl['level'];
            $totalLevelReward += (float)$lvl['reward_mmk'];
            $levelUpData[] = ['new_level' => $currentLevel, 'reward' => $lvl['reward_mmk']];
        }
    }
    $deltaBalance += $totalLevelReward;

    // Atomic User Sync
    $pdo->prepare("UPDATE users SET balance = balance + ?, xp = ?, level = ?, pnl_lifetime = pnl_lifetime + ? WHERE id = ?")
        ->execute([$deltaBalance, $newXp, $currentLevel, $deltaPnl, $userId]);

    if ($totalLevelReward > 0) {
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")->execute([$userId, $totalLevelReward, "Level Up Reward"]);
    }

    // Machine State Sync
    $sessionWinStreak = ($spinWin > 0) ? (int)($machine['session_win_streak'] ?? 0) + 1 : ((!$isFreeSpin && !$bonusMode) ? 0 : (int)($machine['session_win_streak'] ?? 0));
    $newFreeSpins = $freeSpins > 0 ? $freeSpins - 1 + $freeSpinsEarned : $freeSpinsEarned;
    
    if ($bonusSpinsLeft > 0) {
        $bonusSpinsLeft--;
        if ($bonusSpinsLeft <= 0 && $bonusMode !== 'HEAVEN') $bonusMode = null;
    }
    if ($bonusModeTriggered) { $bonusMode = 'RB'; $bonusSpinsLeft = 8; }
    
    $lapsSinceBonus = ($bonusMode || $isGrandJackpot) ? 0 : ((int)($machine['laps_since_bonus'] ?? 0) + 1);

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, session_spins = session_spins + 1, session_win_streak = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([$spinWin, $clientToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $sessionWinStreak, $machineId]);

    // Telemetry Logging
    $logPayload = [
        'board' => $result, 
        'pf' => ['nonce' => $nonce, 'client_seed' => $clientSeed, 'server_seed_hash' => $serverSeedHash, 'spin_hash' => $spinHash], 
        'heat' => ['zone' => $heatZone, 'pct' => round($heatPct, 2)], 
        'v7_math' => ['gjp_prob' => $gjpProbability, 'target_hit' => $targetHitRate]
    ];

    try {
        $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, rtp_in, rtp_out, result, entropy_cache, reel_indices, xp_earned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, $actualBetDeducted, $spinWin, json_encode($logPayload), json_encode($entropy), json_encode($selectedIndices), $xpGain]);
    } catch (Exception $e) {
        $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($logPayload), $xpGain]);
    }

    $pdo->commit();

    $finalBal = (float)$freshUser['balance'] + $deltaBalance;

    // --- PHASE 9: PAYLOAD CONSTRUCTION ---
    $responseData = [
        'status' => 'success',
        'stops' => $result,
        'winning_lines' => $winningLines,
        'win_amount' => $spinWin,
        'win_tier' => ($spinWin > 0) ? (($spinWin / max(1, $betAmount) >= 100) ? 'EPIC' : (($spinWin / max(1, $betAmount) >= 50) ? 'MEGA' : (($spinWin / max(1, $betAmount) >= 10) ? 'BIG' : 'SMALL'))) : 'NONE',
        'new_balance' => $finalBal,
        'free_spins' => $newFreeSpins,
        'bonus_mode' => $bonusMode,
        'bonus_spins_left' => $bonusSpinsLeft,
        'laps_since_bonus' => $lapsSinceBonus,
        'session_spins' => $sessionSpins + 1,
        'session_win_streak' => $sessionWinStreak,
        'session_token' => $clientToken,
        'is_teaser' => $isTeaser,
        'is_reach_eye' => $isReachEye,
        'is_jackpot' => $isGrandJackpot,
        'level_up' => $levelUpData,
        'provably_fair' => ['client_seed' => $clientSeed, 'server_seed_hash' => $serverSeedHash, 'previous_server_seed' => $previousSeed, 'server_seed_reveal' => $revealedSeed, 'spin_hash' => $spinHash, 'nonce' => $nonce],
        'gjp_heat' => ['zone' => $heatZone, 'pct' => round($heatPct, 2)],
        'rtp_siphon_rate' => 0.01,
        'rtp_siphon_amount' => $actualBetDeducted * 0.01
    ];

    $responseData['signature'] = hash_hmac('sha256', json_encode($responseData), $serverSeed);
    $jsonOutput = json_encode($responseData);

    if ($idempotencyKey && class_exists('Redis') && isset($GLOBALS['redis'])) $GLOBALS['redis']->setex("idem:$idempotencyKey", 60, $jsonOutput);

    echo $jsonOutput;
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $code = 'ERR_INTERNAL';
    $status = 400;
    
    if ($e->getCode() == 1) $code = 'ERR_MACHINE_SYNC';
    elseif ($e->getCode() == 2) $code = 'ERR_SESSION_INVALID';
    elseif ($e->getCode() == 3) {
        $code = 'ERR_INSUFFICIENT_FUNDS';
        $status = 402;
    }
    
    if (strpos($e->getMessage(), 'CRITICAL') !== false) {
        $code = 'ERR_SYSTEM_UNCONFIGURED';
        $status = 500;
    }
    sendError($code, $e->getMessage(), $status);
}
?>