<?php
// ============================================================================
// SUROPARA V2 - PACHISLOT ENGINE & PROFIT MAXIMIZER
// Features: Whale Tracking, 80% Golden Windows, Teaser Manipulation
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

// 1. Auto-Migrate Psychological Tracker Columns
try {
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS lifetime_wager DECIMAL(20,2) DEFAULT 0.00, 
        ADD COLUMN IF NOT EXISTS lucky_sessions_used INT DEFAULT 0, 
        ADD COLUMN IF NOT EXISTS lucky_week_start DATE DEFAULT NULL");
} catch (Exception $e) {}

$user = authenticate($pdo);
$userId = $user['id'];

Security::rateLimit($pdo, $userId, 'spin', 0.8);

$data = json_decode(file_get_contents("php://input"));
$betAmount = (int)($data->bet_amount ?? 0);
$machineId = (int)($data->machine_id ?? 0);
$clientToken = $data->session_token ?? ''; 

$validBets = [80, 200, 500, 1000, 5000, 10000, 50000, 100000, 250000, 500000];
if (!in_array($betAmount, $validBets)) {
    http_response_code(400); echo json_encode(['error' => 'Invalid bet denomination.']); exit;
}

try {
    $pdo->beginTransaction();

    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] !== $userId) throw new Exception("You are not seated at this machine.");
    
    $stmtUser = $pdo->prepare("SELECT balance, xp, level, active_pet_id, lifetime_wager, lucky_sessions_used, lucky_week_start FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    $isFreeSpin = (int)$machine['free_spins'] > 0;
    $actualBetDeducted = $isFreeSpin ? 0 : $betAmount;

    if ($freshUser['balance'] < $actualBetDeducted) throw new Exception("Insufficient balance.");

    $nextToken = bin2hex(random_bytes(32));
    
    if ($actualBetDeducted > 0) {
        // Update Balance and Tracker
        $pdo->prepare("UPDATE users SET balance = balance - ?, lifetime_wager = lifetime_wager + ? WHERE id = ?")->execute([$actualBetDeducted, $actualBetDeducted, $userId]);
        $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE name = 'GRAND SURO JACKPOT'")->execute([$actualBetDeducted * 0.01]);
    }

    // --- ALGORITHM: PROFIT MAXIMIZATION ENGINE ---
    
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
    $luckySessionsUsed = $freshUser['lucky_sessions_used'];
    
    // Reset weekly tracker if it's a new week
    if ($freshUser['lucky_week_start'] !== $currentWeekStart) {
        $luckySessionsUsed = 0;
        $pdo->prepare("UPDATE users SET lucky_week_start = ?, lucky_sessions_used = 0 WHERE id = ?")->execute([$currentWeekStart, $userId]);
    }

    $isHighRoller = ($freshUser['lifetime_wager'] + $actualBetDeducted) >= 100000; // 1 Lakh threshold
    
    // Default RTP configuration
    $targetRtp = 40.0; // Hard cap at 40% normally
    $teaserChance = 30; // 30% chance to show a near-miss on a loss
    $isGoldenWindow = false;

    // Golden Window Logic (Twice a week, spike to 80%)
    if ($luckySessionsUsed < 2) {
        // 5% chance per spin to trigger a "Golden Session" if they haven't used both this week
        if (mt_rand(1, 100) <= 5) {
            $isGoldenWindow = true;
            $targetRtp = 80.0;
            // Mark a session as used
            $pdo->prepare("UPDATE users SET lucky_sessions_used = lucky_sessions_used + 1 WHERE id = ?")->execute([$userId]);
        }
    }

    // Whale Extractor Logic
    if ($isHighRoller && !$isGoldenWindow) {
        // They spend a lot. Tighten the RTP slightly to extract profit, but MAXIMIZE teasers to keep them addicted.
        $targetRtp = 35.0; 
        $teaserChance = 80; // 80% of their losses will look like they "almost" won a jackpot
    }

    // --- END PROFIT ENGINE ---

    // Active Events
    $stmtEvent = $pdo->prepare("SELECT multiplier FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() LIMIT 1");
    $stmtEvent->execute();
    $winMultiplier = $stmtEvent->fetchColumn() ?: 1.0;

    $paylines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 4, 8], [6, 4, 2]];
    $result = array_fill(0, 9, 0);
    $winningLines = [];
    $spinWin = 0;
    
    $bonusMode = $machine['bonus_mode'];
    $bonusSpinsLeft = (int)$machine['bonus_spins_left'];
    $newFreeSpins = $isFreeSpin ? $machine['free_spins'] - 1 : 0;

    // RNG GENERATION
    if ($bonusSpinsLeft > 0) {
        // AT MODE
        $winSym = (mt_rand(1, 100) > 80) ? 5 : 4; 
        for ($i=0; $i<9; $i++) $result[$i] = mt_rand(4, 7);
        $chosenLine = array_rand($paylines);
        foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
        
        $bonusSpinsLeft--;
        if ($bonusSpinsLeft <= 0) $bonusMode = null; 
    } else {
        // NORMAL SPIN USING DYNAMIC RTP
        $rng = mt_rand(1, 10000) / 100;
        $isHit = $rng <= $targetRtp;

        if ($isHit) {
            // Symbol generation based on RTP tightness
            if ($targetRtp <= 35.0) { 
                $allowed = [[7,60], [6,30], [4,8], [5,2]]; // Very tight (Whale state)
            } elseif ($targetRtp == 80.0) { 
                $allowed = [[7,30], [6,20], [4,20], [5,15], [3,10], [1,5]]; // Golden Window (Loose)
            } else { 
                $allowed = [[7,45], [6,30], [4,15], [5,5], [3,3], [1,2]]; // Standard 40%
            } 

            $totalW = array_sum(array_column($allowed, 1));
            $randW = mt_rand(1, $totalW);
            $currW = 0; $winSym = 7;
            foreach ($allowed as $a) {
                $currW += $a[1];
                if ($randW <= $currW) { $winSym = $a[0]; break; }
            }

            for ($i=0; $i<9; $i++) $result[$i] = mt_rand(2, 7); 
            $chosenLine = array_rand($paylines);
            
            if ($winSym === 1) {
                if (mt_rand(1, 100) > 30) {
                    foreach($paylines[$chosenLine] as $pos) $result[$pos] = 1;
                } else {
                    $result[$paylines[$chosenLine][0]] = 1;
                    $result[$paylines[$chosenLine][1]] = 1;
                    $result[$paylines[$chosenLine][2]] = 3;
                }
            } else {
                foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
            }
        } else {
            // FORCED LOSS
            do {
                for($i=0; $i<9; $i++) $result[$i] = mt_rand(2, 7);
                $hasWin = false;
                foreach($paylines as $l) {
                    if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) { $hasWin = true; break; }
                }
            } while($hasWin);

            // TEASER GENERATION (Psychological Hook)
            if (mt_rand(1, 100) <= $teaserChance) {
                // Force a near miss on the middle row
                $teaserSym = (mt_rand(1,100) > 90) ? 1 : 3; // 10% chance to tease a Jackpot 7
                $result[3] = $teaserSym;
                $result[4] = $teaserSym;
                // Ensure 3rd symbol doesn't match
                $result[5] = ($teaserSym == 1) ? 3 : 7; 
                $isTeaser = true;
            }
        }
    }

    // EVALUATION
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
            }
        }
        else if ($s1 == 1 && $s2 == 1 && $s3 == 3) {
            $winningLines[] = $idx;
            $bonusMode = 'RB'; $bonusSpinsLeft = 8; 
        }
    }

    $spinWin = $spinWin * $winMultiplier;

    // Apply Balances
    if ($spinWin > 0) $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$spinWin, $userId]);
    $xpGain = floor($betAmount / 1000);
    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $userId]);

    // Update Machine State
    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, free_spins = ?, bonus_mode = ?, bonus_spins_left = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([$spinWin, $nextToken, $newFreeSpins, $bonusMode, $bonusSpinsLeft, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $machineId, $actualBetDeducted, $spinWin, json_encode($result), $xpGain]);

    $pdo->commit();
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'stops' => $result,
        'winning_lines' => $winningLines,
        'win_amount' => $spinWin,
        'new_balance' => (float)$finalBal,
        'free_spins' => $newFreeSpins,
        'bonus_mode' => $bonusMode,
        'bonus_spins_left' => $bonusSpinsLeft,
        'session_token' => $nextToken,
        'is_teaser' => $isTeaser ?? false
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); echo json_encode(['error' => $e->getMessage()]);
}
?>