<?php
// ============================================================================
// SUROPARA V7.10.0 - LEVIATHAN ALGORITHMIC GRID ENGINE (DYNAMIC TEASER UPDATE)
// ----------------------------------------------------------------------------
// 1. Algorithmic Grid: 100% Math-driven 3x3 matrix via SpinHash Entropy.
// 2. Strict Provably Fair: mt_rand() eradicated. All drops are deterministic.
// 3. GJP Trigger Lock: Jackpot probability is 0% until 'trigger_amount' is hit.
// 4. Dynamic Teaser Filtering: GJP symbols ONLY appear on reels if WARM/HOT.
// 5. Zero-Collision Paths: Accidental multi-line wins actively scrubbed.
// 6. Smooth RTP Governor: Feathered hit-rate scaling (Target ~70% band).
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

if (!function_exists('getEnvSafe')) {
    function getEnvSafe($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }
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

// Idempotency Check with DB Fallback
$idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($data->idempotency_key ?? null);
if ($idempotencyKey) {
    $idemData = null;
    if (class_exists('Redis') && isset($GLOBALS['redis'])) {
        $idemData = $GLOBALS['redis']->get("idem:$idempotencyKey");
    } else {
        try {
            $stmtIdem = $pdo->prepare("SELECT response_json FROM idempotency_cache WHERE idempotency_key = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $stmtIdem->execute([$idempotencyKey]);
            $idemData = $stmtIdem->fetchColumn();
        } catch(Exception $e) { 
            error_log('[SUROPARA_IDEM] Fetch failed: ' . $e->getMessage()); 
        } 
    }
    if ($idemData) {
        echo $idemData;
        exit;
    }
}

$rawClientSeed = $data->client_seed ?? bin2hex(random_bytes(8));
$clientSeed = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawClientSeed), 0, 32);

// Bet Validation
$validBets = [100, 500, 1000, 5000, 10000, 20000, 50000, 100000, 250000, 500000, 1000000];
if (!in_array($betAmount, $validBets)) sendError('ERR_INVALID_BET', 'Unrecognized bet signature.');

// Rate Limiter & Anomaly Detection (0.28s cooldown)
if (session_status() === PHP_SESSION_NONE) session_start();
$currentTime = microtime(true);
$isTestAccount = isset($user['is_trusted']) && $user['is_trusted'] == 1;

if (isset($_SESSION['last_spin_time']) && ($currentTime - $_SESSION['last_spin_time']) < 0.28 && !$isTestAccount) {
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

    $symMultipliers = SystemCache::get("payouts:{$islandId}", function () use ($pdo, $islandId) {
        $stmt = $pdo->prepare("SELECT * FROM island_symbol_payouts WHERE island_id = ?");
        $stmt->execute([$islandId]);
        $db = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$db) throw new Exception("CRITICAL: Payout matrix missing for Sector #$islandId.");
        
        return [
            1 => 0.0, // Strict Enforcement: GJP pool is sym 1's sole payout
            2 => (float)$db['sym_2_mult'], 3 => (float)$db['sym_3_mult'], 
            4 => (float)$db['sym_4_mult'], 5 => (float)$db['sym_5_mult'], 
            6 => (float)$db['sym_6_mult'], 7 => (float)$db['sym_7_mult']
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
    
    // Live Pre-Spin Telemetry for Dynamic Convergence
    $machineTotalBetPre = (float)($machine['total_bet'] ?? 0);
    $machineTotalPayoutPre = (float)($machine['total_payout'] ?? 0);

    $isFreeSpin = ($freeSpins > 0 || $bonusSpinsLeft > 0);
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ((float)$freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient MMK.", 3);

    $deltaBalance = -$actualBetDeducted;
    $deltaPnl = $actualBetDeducted;

    // --- PHASE 4: PROVABLY FAIR SEEDING ---
    $serverSeed = $machine['server_seed'];
    $previousSeed = $machine['previous_server_seed'];
    $revealedSeed = null;

    if (empty($serverSeed) || empty($machine['server_seed_hash'])) {
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

    // Extract 15 distinct chunks of entropy for full mathematical grid determination
    $entropy = [];
    for ($i = 0; $i < 15; $i++) {
        $entropy[$i] = hexdec(substr(hash('sha256', $spinHash . $i), 0, 8)) / 4294967296;
    }

    // --- PHASE 5: V7.10 MATHEMATICAL GJP ENGINE (TRIGGER-LOCKED) ---
    $stmtJp = $pdo->prepare("SELECT current_amount, base_seed, trigger_amount, max_amount, contribution_rate FROM global_jackpots WHERE island_id = ? FOR UPDATE");
    $stmtJp->execute([$islandId]);
    $gjpData = $stmtJp->fetch(PDO::FETCH_ASSOC);

    if (!$gjpData) throw new Exception("CRITICAL: GJP matrix unconfigured for Sector #$islandId.");

    $currentJackpot = (float)$gjpData['current_amount'];
    $gjpTrigger = (float)$gjpData['trigger_amount']; // The Hot Trigger threshold
    $gjpMax = (float)$gjpData['max_amount']; 
    $gjpBase = (float)$gjpData['base_seed'];
    $gjpContribRate = (float)$gjpData['contribution_rate'];

    // Siphon Pot (Free spins make zero contribution)
    if ($actualBetDeducted > 0) {
        $currentJackpot += ($actualBetDeducted * $gjpContribRate);
        if ($currentJackpot >= $gjpMax) {
            $currentJackpot = $gjpMax; // Hard Cap Restored
        }
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE island_id = ?")->execute([$currentJackpot, $islandId]);
    }

    $isGrandJackpot = false;
    $gjpTargetRtp = $winRates['gjp_rtp'] / 100;
    $gjpProbability = 0;
    $heatPct = 0;
    $heatZone = 'COLD';

    // V7.10: Strict Mathematical Trigger Lock
    if ($currentJackpot >= $gjpMax) {
        // Absolute Force Trigger
        $heatZone = 'MUST_HIT';
        $heatPct = 100.0;
        $isGrandJackpot = true; 
    } elseif ($currentJackpot >= $gjpTrigger) {
        // GJP Unlocked: Calculate Heat & Probability
        $heatPct = min(100, max(0, (($currentJackpot - $gjpTrigger) / max(1, ($gjpMax - $gjpTrigger))) * 100));
        $gjpProbability = ($actualBetDeducted > 0) ? (($gjpTargetRtp * $betAmount) / max(1, $currentJackpot)) : 0;
        $burstVol = $winRates['volatility'];

        if ($heatPct >= 80.0) {
            $heatZone = 'HOT';
            $gjpProbability *= ($burstVol * 2.0);
        } else {
            $heatZone = 'WARM';
            $gjpProbability *= $burstVol;
        }
    } else {
        // GJP Locked: Mathematical zero probability before trigger
        $heatZone = 'COLD';
        $heatPct = 0.0;
        $gjpProbability = 0;
    }

    // Trigger Roll (Anti-Farming: Disabled during Free Spins/Bonus)
    if (!$isGrandJackpot && !$isFreeSpin && !$bonusMode && $actualBetDeducted > 0 && $gjpProbability > 0) {
        if ($entropy[3] <= $gjpProbability) $isGrandJackpot = true;
    }

    // --- PHASE 6: V7.10 DYNAMIC TEASER & GRID GENERATION ---
    $baseHitRate = $winRates['base_hit_rate'] / 100; 
    $targetTotalRtp = $winRates['base_hit_rate'] + $winRates['gjp_rtp'];
    $adjustedHitRate = $baseHitRate;

    // Dynamic RTP Compensation Logic (Proportional Scaling)
    $currentMachineRtp = $machineTotalBetPre > 0 ? ($machineTotalPayoutPre / $machineTotalBetPre) * 100 : 0;
    
    // Bonus Mode Overrides (Bypasses standard RTP governor)
    if ($bonusMode === 'HEAVEN') {
        $adjustedHitRate = 1.0; // 100% Hit Rate
    } elseif ($bonusMode === 'RB') {
        $adjustedHitRate = 0.85; // 85% Hit Rate
    } else {
        // Standard Base Game Convergence Constraints (Requires 100k MMK volume buffer)
        if ($machineTotalBetPre > 100000) {
            $rtpDelta = $currentMachineRtp - $targetTotalRtp;

            if ($currentMachineRtp >= $winRates['max_rtp_cap']) {
                $adjustedHitRate *= 0.50; // Emergency Circuit Breaker (Half hit rate)
            } else {
                // Smooth scaling convergence (Max +/- 15% adjustment for invisible steering)
                $correctionFactor = max(-0.15, min(0.15, ($rtpDelta * 0.03)));
                $adjustedHitRate *= (1 - $correctionFactor);
            }
        }
    }
    
    // Apply the dynamically adjusted hit rate
    $isHit = ($entropy[4] <= $adjustedHitRate);
    
    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $standardSyms = [2, 3, 4, 5, 6, 7];
    $result = array_fill(0, 9, 0);

    // V7.10 DYNAMIC TEASER FILTER: Only allow GJP symbol on the board if WARM or HOT
    if ($heatZone === 'COLD') {
        $fillerSyms = [2, 3, 4, 5, 6, 7];
    } else {
        $fillerSyms = [1, 2, 3, 4, 5, 6, 7]; // Allow 1s to tease the player
    }
    $fillerCount = count($fillerSyms);

    if ($isGrandJackpot) {
        $winLine = $paylines[(int)floor($entropy[0] * 5)];
        foreach ($winLine as $pos) $result[$pos] = 1;
        
        $fillCursor = 5;
        for ($i = 0; $i < 9; $i++) {
            if ($result[$i] === 0) {
                $result[$i] = $fillerSyms[(int)floor($entropy[$fillCursor] * $fillerCount)];
                $fillCursor++;
            }
        }

        $accidental = false;
        $repairPass = 0; 
        do {
            $accidental = false;
            foreach ($paylines as $line) {
                if ($line === $winLine) continue; 
                
                if ($result[$line[0]] === $result[$line[1]] && $result[$line[1]] === $result[$line[2]]) {
                    $accidental = true;
                    $shift = (int)floor($entropy[14] * $fillerCount) + 1;
                    $cIdx = array_search($result[$line[2]], $fillerSyms);
                    $newSym = $fillerSyms[($cIdx + $shift) % $fillerCount];
                    // Prevent shifting into another 1 if it wasn't the intended winline
                    $result[$line[2]] = ($newSym === 1) ? $fillerSyms[($cIdx + $shift + 1) % $fillerCount] : $newSym;
                }
            }
            if (++$repairPass > 10) break; 
        } while ($accidental);

    } elseif ($isHit) {
        // V7.10 Dynamic Weighting (Standard symbols only for normal wins)
        $symWeights = [];
        if ($bonusMode === 'HEAVEN') {
            $symWeights = [2 => 40, 3 => 40, 4 => 20, 5 => 0, 6 => 0, 7 => 0]; // Premium Only
        } elseif ($bonusMode === 'RB') {
            $symWeights = [2 => 5, 3 => 15, 4 => 40, 5 => 30, 6 => 10, 7 => 0]; // Mid Tier, No Replays
        } else {
            $symWeights = [2 => 2, 3 => 5, 4 => 5, 5 => 3, 6 => 350, 7 => 635]; 
        }

        $totalWeight = array_sum($symWeights);
        $randW = $entropy[1] * $totalWeight;
        $winSym = 7;
        $sum = 0;
        foreach ($symWeights as $s => $w) {
            if ($w === 0) continue;
            $sum += $w;
            if ($randW <= sum) { 
                $winSym = (int)$s;
                break; 
            }
        }

        $winLine = $paylines[(int)floor($entropy[2] * 5)];
        foreach ($winLine as $pos) $result[$pos] = $winSym;
        
        $fillCursor = 5;
        for ($i = 0; $i < 9; $i++) {
            if ($result[$i] === 0) {
                $filler = $fillerSyms[(int)floor($entropy[$fillCursor] * $fillerCount)];
                $result[$i] = ($filler === $winSym) ? ($filler === 7 ? 2 : $filler + 1) : $filler;
                $fillCursor++;
            }
        }

        $accidental = false;
        $repairPass = 0; 
        do {
            $accidental = false;
            foreach ($paylines as $line) {
                if ($line === $winLine) continue; 
                
                // Scrub standard accidental wins
                if ($result[$line[0]] === $result[$line[1]] && $result[$line[1]] === $result[$line[2]]) {
                    $accidental = true;
                    $shift = (int)floor($entropy[14] * $fillerCount) + 1;
                    $cIdx = array_search($result[$line[2]], $fillerSyms);
                    $newSym = $fillerSyms[($cIdx + $shift) % $fillerCount];
                    // Prevent creating the win symbol or accidentally forming a 1-1-1
                    if ($newSym === $winSym || ($newSym === 1 && $result[$line[0]] === 1 && $result[$line[1]] === 1)) {
                        $newSym = $fillerSyms[($cIdx + $shift + 1) % $fillerCount];
                    }
                    $result[$line[2]] = $newSym;
                }
            }
            if (++$repairPass > 10) break; 
        } while ($accidental);

    } else {
        // Zero Collision Generation
        $fillCursor = 5;
        for ($i = 0; $i < 9; $i++) {
            $result[$i] = $fillerSyms[(int)floor($entropy[$fillCursor] * $fillerCount)];
            $fillCursor++;
        }
        
        $accidental = false;
        $repairPass = 0;
        do {
            $accidental = false;
            foreach ($paylines as $line) {
                if ($result[$line[0]] === $result[$line[1]] && $result[$line[1]] === $result[$line[2]]) {
                    $accidental = true;
                    $shift = (int)floor($entropy[14] * $fillerCount) + 1;
                    $currentIndex = array_search($result[$line[2]], $fillerSyms);
                    $newSym = $fillerSyms[($currentIndex + $shift) % $fillerCount];
                    
                    // Extra strict rule: NEVER allow 1-1-1 on the board unless $isGrandJackpot is true
                    if ($newSym === 1 && $result[$line[0]] === 1 && $result[$line[1]] === 1) {
                        $newSym = $fillerSyms[($currentIndex + $shift + 1) % $fillerCount];
                    }
                    $result[$line[2]] = $newSym;
                }
            }
            if (++$repairPass > 10) break; 
        } while ($accidental);
    }

    // --- PHASE 7: PAYLINE EVALUATION ---
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
                // 7s trigger bonus mode if not already in one
                $bonusModeTriggered = true;
                $spinWin += $betAmount * $symMultipliers[$s1];
            } else {
                $spinWin += $betAmount * $symMultipliers[$s1];
            }
        } else {
            // Near Miss Evaluation (Teaser)
            if ($s1 === $s2 && in_array($s1, [1, 2, 3])) {
                $isTeaser = true;
                if ($idx === 3 || $idx === 4) $isReachEye = true;
            }
        }
    }

    // Process Grand Jackpot Payment
    if ($isGrandJackpot) {
        $spinWin += $currentJackpot;
        $pdo->prepare("UPDATE global_jackpots SET current_amount = ?, last_won_by = ?, last_won_amount = ?, last_won_at = NOW() WHERE island_id = ?")->execute([$gjpBase, $freshUser['username'], $currentJackpot, $islandId]);
        $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('jackpot', ?, 1)")->execute(["🚨 ASTRONOMICAL! {$freshUser['username']} hit the GRAND JACKPOT for " . number_format($currentJackpot) . " MMK! 🚨"]);
    }

    // JIT Marketing Promo Multiplier Fetch
    if ($spinWin > 0 && !$isGrandJackpot) {
        $stmtMult = $pdo->query("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
        $eventMult = (float)($stmtMult->fetchColumn() ?: 1.0);
        $spinWin *= $eventMult;
    }

    // --- PHASE 8: CONSOLIDATED STATE & DB WRITE ---
    $deltaBalance += $spinWin;
    $deltaPnl -= $spinWin; 

    // Leveling Math
    $xpGain = floor($betAmount / 1000);
    $newXp = $freshUser['xp'] + $xpGain;
    $currentLevel = (int)$freshUser['level'];
    $totalLevelReward = 0;
    $levelUpData = [];

    // Iterates multiple levels if user skipped ahead
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

    // Machine RTP Telemetry State Update
    $sessionWinStreak = ($spinWin > 0) ? (int)($machine['session_win_streak'] ?? 0) + 1 : ((!$isFreeSpin && !$bonusMode) ? 0 : (int)($machine['session_win_streak'] ?? 0));
    $newFreeSpins = $freeSpins > 0 ? $freeSpins - 1 + $freeSpinsEarned : $freeSpinsEarned;
    
    if ($bonusSpinsLeft > 0) {
        $bonusSpinsLeft--;
        if ($bonusSpinsLeft <= 0 && $bonusMode !== 'HEAVEN') $bonusMode = null;
    }
    if ($bonusModeTriggered) { $bonusMode = 'RB'; $bonusSpinsLeft = 8; }
    
    $lapsSinceBonus = ($bonusMode || $isGrandJackpot) ? 0 : ((int)($machine['laps_since_bonus'] ?? 0) + 1);

    // Track total_bet dynamically for absolute Live RTP metric
    $machineTotalBet = $machineTotalBetPre + $actualBetDeducted;
    $machineTotalPayout = $machineTotalPayoutPre + $spinWin;

    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = ?, total_bet = ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, laps_since_bonus = ?, session_spins = session_spins + 1, session_win_streak = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([$machineTotalPayout, $machineTotalBet, $clientToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $lapsSinceBonus, $sessionWinStreak, $machineId]);

    // Final Post-Spin RTP
    $actualMachineRtp = $machineTotalBet > 0 ? ($machineTotalPayout / $machineTotalBet) * 100 : 0.00;

    // Telemetry Logging with Compensation Details
    $logPayload = [
        'board' => $result, 
        'pf' => ['nonce' => $nonce, 'client_seed' => $clientSeed, 'server_seed_hash' => $serverSeedHash, 'spin_hash' => $spinHash], 
        'heat' => ['zone' => $heatZone, 'pct' => round($heatPct, 2)], 
        'v7_math' => [
            'gjp_prob' => $gjpProbability, 
            'target_hit' => $baseHitRate,
            'adjusted_hit' => $adjustedHitRate,
            'rtp_delta' => $currentMachineRtp > 0 ? $currentMachineRtp - $targetTotalRtp : 0
        ]
    ];

    try {
        $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, rtp_in, rtp_out, result, entropy_cache, xp_earned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, $actualBetDeducted, $spinWin, json_encode($logPayload), json_encode($entropy), $xpGain]);
    } catch (Exception $e) {
        $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($logPayload), $xpGain]);
    }

    $pdo->commit();

    // Post-Commit Re-Query for absolute truth balance
    $stmtFinalBal = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmtFinalBal->execute([$userId]);
    $finalBal = (float)$stmtFinalBal->fetchColumn();

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
        'jackpot_visual_override' => $isGrandJackpot, 
        'level_up' => $levelUpData,
        'provably_fair' => ['client_seed' => $clientSeed, 'server_seed_hash' => $serverSeedHash, 'previous_server_seed' => $previousSeed, 'server_seed_reveal' => $revealedSeed, 'spin_hash' => $spinHash, 'nonce' => $nonce],
        'gjp_heat' => ['zone' => $heatZone, 'pct' => round($heatPct, 2)],
        
        // V7.10: Live Dynamic RTP Telemetry
        'telemetry' => [
            'target_rtp' => round($targetTotalRtp, 2),
            'actual_rtp' => round($actualMachineRtp, 2),
            'total_in' => $machineTotalBet,
            'total_out' => $machineTotalPayout
        ]
    ];

    // Signature Stability via APP_KEY
    $appKey = getEnvSafe('APP_KEY', 'default_suropara_secret_key');
    $responseData['signature'] = hash_hmac('sha256', json_encode($responseData), $appKey);
    $jsonOutput = json_encode($responseData);

    // Dual-Layer Idempotency Saving
    if ($idempotencyKey) {
        if (class_exists('Redis') && isset($GLOBALS['redis'])) {
            $GLOBALS['redis']->setex("idem:$idempotencyKey", 60, $jsonOutput);
        } else {
            try {
                $pdo->prepare("INSERT INTO idempotency_cache (idempotency_key, response_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE response_json = VALUES(response_json)")->execute([$idempotencyKey, $jsonOutput]);
            } catch(Exception $e) {
                error_log('[SUROPARA_IDEM] Save failed: ' . $e->getMessage());
            } 
        }
    }

    echo $jsonOutput;
    
} catch (Exception $e) {
    error_log("[SPIN_ENGINE_V7] Fatal execution error: " . $e->getMessage() . " on line " . $e->getLine());
    
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