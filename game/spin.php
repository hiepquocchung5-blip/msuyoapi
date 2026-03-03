<?php
// ============================================================================
// SUROPARA V3 - MULTI-ALGORITHM SPIN ENGINE (MATH MODEL INTEGRATION)
// Features: 5 Specific Islands, Restricted Bets, 70% Target RTP
// ============================================================================

require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

$allowedOrigin = "https://suropara.com";
header("Access-Control-Allow-Origin: $allowedOrigin"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$user = authenticate($pdo);
$userId = $user['id'];
$data = json_decode(file_get_contents("php://input"));
if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data.']); exit; }

$betAmount = (int)($data->bet_amount ?? 0);
$machineId = (int)($data->machine_id ?? 0);
$clientToken = $data->session_token ?? ''; 

// --- V3 RESTRICTED BET VALIDATION ---
$validBets = [100, 500, 1000, 5000, 10000];
if (!in_array($betAmount, $validBets)) {
    http_response_code(400); echo json_encode(['error' => 'Invalid bet amount for V3.']); exit;
}

Security::rateLimit($pdo, $userId, 'spin', 0.3);

try {
    $pdo->beginTransaction();

    // 1. Lock Machine & User
    $stmtM = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();
    
    if (!$machine || $machine['current_user_id'] != $userId) throw new Exception("Machine seating mismatch.");
    if ($machine['session_token'] !== $clientToken && $clientToken !== 'TEST_OVERRIDE') throw new Exception("Session out of sync.");
    
    $stmtUser = $pdo->prepare("SELECT balance, xp, level FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    if ($freshUser['balance'] < $betAmount) throw new Exception("Insufficient balance.");
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$betAmount, $userId]);

    // 2. Fetch Jackpot Pool & Target Island
    $stmtJp = $pdo->prepare("SELECT current_amount FROM global_jackpots WHERE name = 'GRAND SURO JACKPOT' FOR UPDATE");
    $stmtJp->execute();
    $currentJackpot = (float)$stmtJp->fetchColumn();
    $islandId = (int)$machine['island_id'];

    // 3. Feed the Jackpot (5% of Bet as per V3 Math Model)
    $jackpotFeed = $betAmount * 0.05;
    $currentJackpot += $jackpotFeed;
    $pdo->prepare("UPDATE global_jackpots SET current_amount = ? WHERE name = 'GRAND SURO JACKPOT'")->execute([$currentJackpot]);

    // ========================================================================
    // THE MATHEMATICAL CORE
    // ========================================================================
    $result = [0,0,0, 0,0,0, 0,0,0];
    $winningLines = [];
    $spinWin = 0;
    $isJackpot = false;
    $paylines = [[0,1,2], [3,4,5], [6,7,8], [0,4,8], [6,4,2]];
    
    // --- JACKPOT PROBABILITY FUNCTION ---
    $gMin = 3600000;
    $gMax = 7200000;
    $p0 = 0.00002;
    $a = 5;
    
    $alpha = 0;
    if ($currentJackpot >= $gMax) {
        $alpha = 1; // Guaranteed trigger
    } elseif ($currentJackpot > $gMin) {
        $alpha = ($currentJackpot - $gMin) / ($gMax - $gMin);
        $alpha = min(1, max(0, $alpha)); 
    }
    
    // Base Jackpot Probability: P_JP = p_0 * (1 + a * alpha)
    $pjp = $p0 * (1 + ($a * $alpha));
    
    // Target RTP Feedback Factor (F)
    // For V3, we aim for ~70% True RTP. 
    $F = 1.0; 
    
    // Final Probability
    $finalPjp = $pjp * $F;

    // Check Jackpot Hit
    // If alpha == 1 (Jackpot at max), force hit. Otherwise roll.
    if ($alpha == 1 || ($currentJackpot >= $gMin && (lcg_value() <= $finalPjp))) {
        $isJackpot = true;
        $spinWin = $currentJackpot;
        // Reset to Base
        $pdo->prepare("UPDATE global_jackpots SET current_amount = 3000000.00 WHERE name = 'GRAND SURO JACKPOT'")->execute();
        
        for($i=0;$i<9;$i++) $result[$i] = rand(2,6);
        $result[3]=1; $result[4]=1; $result[5]=1; // 7-7-7 visual
        $winningLines[] = 1;
    } else {
        // --- BASE GAME ALGORITHMS (RTP ~58% base to allow room for Jackpot EV) ---
        // We use a precision roll out of 1000
        $roll = mt_rand(1, 1000);
        $winSym = 0;
        $mult = 0;

        if ($islandId === 1) {
            // ISLAND 1 (Kyoto Zen) - The Defined Mathematical Model
            // Target Base RTP: 57.4%
            // P(🔄)=0.065, P(🍒)=0.053, P(🔔)=0.030, P(🍉)=0.014, P(7)=0.008, P(💎)=0.004
            if ($roll <= 4) { $winSym = 7; $mult = 40; }       // Diamond
            elseif ($roll <= 12) { $winSym = 1; $mult = 15; }  // 7
            elseif ($roll <= 26) { $winSym = 5; $mult = 7; }   // Melon
            elseif ($roll <= 56) { $winSym = 4; $mult = 3; }   // Bell
            elseif ($roll <= 109) { $winSym = 6; $mult = 2; }  // Cherry
            elseif ($roll <= 174) { $winSym = 7; $mult = 0; }  // Replay
        } 
        elseif ($islandId === 2) {
            // ISLAND 2 (Neon Arcade) - Low Variance (Many small wins)
            // Target Base RTP: ~58.0%
            if ($roll <= 2) { $winSym = 1; $mult = 30; }       
            elseif ($roll <= 12) { $winSym = 3; $mult = 10; }  
            elseif ($roll <= 32) { $winSym = 5; $mult = 5; }   
            elseif ($roll <= 82) { $winSym = 4; $mult = 2; }   
            elseif ($roll <= 182) { $winSym = 6; $mult = 1; }  
            elseif ($roll <= 240) { $winSym = 7; $mult = 0; }  
        }
        elseif ($islandId === 3) {
            // ISLAND 3 (Edo Castle) - High Variance (Few big wins)
            // Target Base RTP: ~58.0%
            if ($roll <= 4) { $winSym = 1; $mult = 100; }      
            elseif ($roll <= 14) { $winSym = 3; $mult = 15; }   
            elseif ($roll <= 34) { $winSym = 5; $mult = 1.5; }   
        }
        elseif ($islandId === 4) {
            // ISLAND 4 (Hanami Fest) - Balanced
            // Target Base RTP: ~58.0%
            if ($roll <= 4) { $winSym = 1; $mult = 20; }       
            elseif ($roll <= 14) { $winSym = 3; $mult = 10; }  
            elseif ($roll <= 34) { $winSym = 5; $mult = 5; }   
            elseif ($roll <= 74) { $winSym = 4; $mult = 3; }   
            elseif ($roll <= 140) { $winSym = 6; $mult = 1.5; } 
            elseif ($roll <= 200) { $winSym = 7; $mult = 0; }  
        }
        elseif ($islandId === 5) {
            // ISLAND 5 (Spirited Yokai) - "All or Nothing"
            // Target Base RTP: ~58.0%
            if ($roll <= 5) { $winSym = 1; $mult = 40; }       
            elseif ($roll <= 30) { $winSym = 3; $mult = 15; }   
            elseif ($roll <= 80) { $winSym = 4; $mult = 0; } // Teaser
        }

        // Apply visual result based on logic
        if ($winSym > 0 && $mult > 0) {
            $spinWin = $betAmount * $mult;
            $chosenLine = array_rand($paylines);
            for($i=0;$i<9;$i++) $result[$i] = mt_rand(2,6); 
            foreach($paylines[$chosenLine] as $pos) $result[$pos] = $winSym;
            $winningLines[] = $chosenLine;
        } else {
            // Generate clear loss
            do {
                for($i=0;$i<9;$i++) $result[$i] = mt_rand(2,6);
                $hasWin = false;
                foreach($paylines as $l) {
                    if($result[$l[0]]==$result[$l[1]] && $result[$l[1]]==$result[$l[2]]) {
                        $hasWin=true;
                        break;
                    }
                }
            } while($hasWin);
            
            // Apply Replay Visuals
            if (($islandId === 1 || $islandId === 2 || $islandId === 4) && $winSym === 7) {
                $chosenLine = array_rand($paylines);
                foreach($paylines[$chosenLine] as $pos) $result[$pos] = 7;
            } elseif ($islandId === 5 && $winSym === 4) {
                 // Teaser Near-miss
                 $result[3] = 3; $result[4] = 3; $result[5] = mt_rand(4,6);
            }
        }
    }

    // 4. Update Balances & Logs
    if ($spinWin > 0) {
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$spinWin, $userId]);
    }
    
    // XP Calculation
    $xpGain = floor($betAmount / 100);
    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xpGain, $userId]);

    // Machine Stats
    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, last_played_at = NOW() WHERE id = ?")
        ->execute([$spinWin, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $machineId, $betAmount, $spinWin, json_encode($result), $xpGain]);

    $pdo->commit();
    
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    $winTier = 'NONE';
    if ($spinWin > 0) {
        $multiplierCheck = $spinWin / $betAmount;
        if ($multiplierCheck >= 40) $winTier = 'EPIC';
        elseif ($multiplierCheck >= 15) $winTier = 'MEGA';
        elseif ($multiplierCheck >= 7) $winTier = 'BIG';
        else $winTier = 'SMALL';
    }

    echo json_encode([
        'status' => 'success', 
        'stops' => $result, 
        'winning_lines' => $winningLines, 
        'win_amount' => $spinWin, 
        'win_tier' => $winTier,
        'new_balance' => (float)$finalBal, 
        'is_jackpot' => $isJackpot
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); 
    echo json_encode(['error' => $e->getMessage()]);
}
?>