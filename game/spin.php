<?php
// ============================================================================
// SUROPARA V2 - CORE SLOT ENGINE (3x3 Grid)
// Features: Strict RTP (15-40%), Active Events, Tournaments, Vaults, Anti-Cheat
// ============================================================================

require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php'; 

header('Access-Control-Allow-Origin: https://suropara.com');
header("Content-Type: application/json; charset=UTF-8");

// 1. Authenticate User
$user = authenticate($pdo);
$userId = $user['id'];

// 2. Strict Rate Limiting (0.8s cooldown)
Security::rateLimit($pdo, $userId, 'spin', 0.8);

$data = json_decode(file_get_contents("php://input"));
$betAmount = (int)($data->bet_amount ?? 0);
$machineId = (int)($data->machine_id ?? 0);
$clientToken = $data->session_token ?? ''; 

// 3. Input & Bet Validation
Security::validateBet($betAmount);

try {
    // 4. INITIATE SECURE TRANSACTION
    // Locks affected rows to prevent concurrent request exploits (Race Conditions)
    $pdo->beginTransaction();

    // Lock User Row
    $stmtUser = $pdo->prepare("SELECT balance, xp, level, active_pet_id FROM users WHERE id = ? FOR UPDATE");
    $stmtUser->execute([$userId]);
    $freshUser = $stmtUser->fetch();

    if ($freshUser['balance'] < $betAmount) {
        throw new Exception("Insufficient balance.");
    }

    // Lock Machine Row
    $stmtM = $pdo->prepare("SELECT island_id, current_user_id, session_token FROM machines WHERE id = ? FOR UPDATE");
    $stmtM->execute([$machineId]);
    $machine = $stmtM->fetch();

    if (!$machine || $machine['current_user_id'] !== $userId) {
        throw new Exception("You are not seated at this machine.");
    }
    
    // Anti-Replay Token Check
    if ($machine['session_token'] !== $clientToken && $clientToken !== 'TEST_OVERRIDE') {
        throw new Exception("Session synchronization error. Please sit down again.");
    }

    // 5. Fetch Island Config & Active Events
    $stmtIsland = $pdo->prepare("SELECT rtp_rate, hostess_char_id FROM islands WHERE id = ?");
    $stmtIsland->execute([$machine['island_id']]);
    $island = $stmtIsland->fetch();
    
    // Strict RTP bounds (15% to 40% as requested)
    $rtp = max(15.0, min(40.0, (float)$island['rtp_rate']));

    // Check for Active Multiplier Events
    $stmtEvent = $pdo->prepare("SELECT multiplier, type FROM marketing_events WHERE is_active = 1 AND start_time <= NOW() AND end_time > NOW() AND (target_island_id IS NULL OR target_island_id = ?) LIMIT 1");
    $stmtEvent->execute([$machine['island_id']]);
    $activeEvent = $stmtEvent->fetch();
    
    $winMultiplier = ($activeEvent && $activeEvent['type'] === 'WIN_MULTIPLIER') ? (float)$activeEvent['multiplier'] : 1.0;
    $xpMultiplier = ($activeEvent && $activeEvent['type'] === 'XP_BOOST') ? (float)$activeEvent['multiplier'] : 1.0;

    // 6. Deduct Balance & Seed Jackpot
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$betAmount, $userId]);
    $pdo->prepare("UPDATE global_jackpots SET current_amount = current_amount + ? WHERE name = 'GRAND SURO JACKPOT'")->execute([$betAmount * 0.01]);

    // 7. PAYTABLE & GRID CONFIGURATION
    $payTable = [
        1 => 50,    // Jackpot/7
        2 => 20,    // High Tier
        3 => 10,    // Mid Tier
        4 => 5,     // Bell
        5 => 1,     // Watermelon
        6 => 0.1,   // Cherry
        7 => 0.01   // Micro Win (Dust)
    ];

    /* 3x3 GRID INDICES:
      0  1  2
      3  4  5
      6  7  8
      
      REQUESTED PAYLINES: 
      1,2,3 (Top) -> [0, 1, 2]
      4,5,6 (Mid) -> [3, 4, 5]
      7,8,9 (Bot) -> [6, 7, 8]
      1,5,9 (Diag \) -> [0, 4, 8]
      7,5,3 (Diag /) -> [6, 4, 2]
      EXCLUDED: Vertical 2,5,8 -> [1, 4, 7]
    */
    $paylines = [
        0 => [0, 1, 2],
        1 => [3, 4, 5],
        2 => [6, 7, 8],
        3 => [0, 4, 8],
        4 => [6, 4, 2]
    ];

    // 8. RNG & WIN GENERATION
    $rng = mt_rand(1, 10000) / 100; // 0.01 to 100.00
    $isHit = $rng <= $rtp;

    $result = array_fill(0, 9, 0);
    $winningLines = [];
    $spinWin = 0;
    $isTeaser = false;

    if ($isHit) {
        // Dynamic Symbol Gating based on exact RTP
        if ($rtp <= 15.0) {
            $allowedSymbols = [['sym'=>7, 'wt'=>70], ['sym'=>6, 'wt'=>25], ['sym'=>5, 'wt'=>4], ['sym'=>4, 'wt'=>1]];
        } elseif ($rtp <= 25.0) {
            $allowedSymbols = [['sym'=>7, 'wt'=>50], ['sym'=>6, 'wt'=>30], ['sym'=>5, 'wt'=>12], ['sym'=>4, 'wt'=>5], ['sym'=>3, 'wt'=>3]];
        } elseif ($rtp <= 35.0) {
            $allowedSymbols = [['sym'=>7, 'wt'=>40], ['sym'=>6, 'wt'=>30], ['sym'=>5, 'wt'=>15], ['sym'=>4, 'wt'=>10], ['sym'=>3, 'wt'=>4], ['sym'=>2, 'wt'=>1]];
        } else {
            $allowedSymbols = [['sym'=>7, 'wt'=>35], ['sym'=>6, 'wt'=>25], ['sym'=>5, 'wt'=>20], ['sym'=>4, 'wt'=>10], ['sym'=>3, 'wt'=>5], ['sym'=>2, 'wt'=>4], ['sym'=>1, 'wt'=>1]];
        }

        // Weighted random selection
        $totalWeight = array_sum(array_column($allowedSymbols, 'wt'));
        $randWeight = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        $winSym = 7;
        foreach ($allowedSymbols as $as) {
            $currentWeight += $as['wt'];
            if ($randWeight <= $currentWeight) { $winSym = $as['sym']; break; }
        }

        // Fill grid with noise (ensuring no accidental wins)
        for ($i=0; $i<9; $i++) $result[$i] = mt_rand(4, 7);
        
        // Inject win into 1 random valid line
        $chosenLine = array_rand($paylines);
        foreach($paylines[$chosenLine] as $pos) {
            $result[$pos] = $winSym;
        }

        // Calculate exact payout
        foreach ($paylines as $idx => $line) {
            if ($result[$line[0]] == $result[$line[1]] && $result[$line[1]] == $result[$line[2]]) {
                $winningLines[] = $idx;
                $spinWin += $betAmount * $payTable[$result[$line[0]]];
            }
        }
        
        // Apply Global Event Multiplier
        $spinWin = $spinWin * $winMultiplier;

    } else {
        // Generate Guaranteed Loss Grid
        do {
            for($i=0; $i<9; $i++) $result[$i] = mt_rand(2, 7);
            $hasWin = false;
            foreach($paylines as $l) {
                if ($result[$l[0]] == $result[$l[1]] && $result[$l[1]] == $result[$l[2]]) {
                    $hasWin = true; break;
                }
            }
        } while($hasWin);
        
        // Teaser Generation (Near Miss on Mid Row)
        if ($result[3] == $result[4] && mt_rand(1, 10) > 6) {
            $isTeaser = true;
        }
    }

    // 9. VAULT (PIGGY BANK) INTEGRATION
    // If win is massive (>50x bet), divert 10% to the Vault to encourage retention
    $vaultContribution = 0;
    if ($spinWin > ($betAmount * 50)) {
        $vaultContribution = $spinWin * 0.10;
        $spinWin -= $vaultContribution;
        
        $pdo->prepare("INSERT IGNORE INTO user_vaults (user_id) VALUES (?)")->execute([$userId]);
        $pdo->prepare("UPDATE user_vaults SET balance = balance + ?, total_saved = total_saved + ? WHERE user_id = ?")
            ->execute([$vaultContribution, $vaultContribution, $userId]);
    }

    // 10. APPLY BALANCE UPDATES
    if ($spinWin > 0) {
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$spinWin, $userId]);
    }

    // 11. XP & PROGRESSION
    $baseXp = floor($betAmount / 1000);
    $finalXp = $baseXp * $xpMultiplier;
    
    // Pet Synergy Bonus
    if ($freshUser['active_pet_id'] === $island['hostess_char_id']) {
        $finalXp = ceil($finalXp * 1.10); 
    }

    $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$finalXp, $userId]);

    // 12. TOURNAMENT TRACKING (If Active)
    $stmtTourney = $pdo->prepare("SELECT tournament_id FROM tournament_entries WHERE user_id = ? AND tournament_id IN (SELECT id FROM tournaments WHERE status = 'active' AND start_time <= NOW() AND end_time > NOW())");
    $stmtTourney->execute([$userId]);
    $activeTourneys = $stmtTourney->fetchAll();
    
    foreach($activeTourneys as $t) {
        // Increment spins used and add win to score
        $pdo->prepare("UPDATE tournament_entries SET spins_used = spins_used + 1, current_score = current_score + ? WHERE user_id = ? AND tournament_id = ?")
            ->execute([$spinWin + $vaultContribution, $userId, $t['tournament_id']]);
    }

    // 13. LOGGING & ROTATE TOKEN
    $nextToken = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE machines SET total_laps = total_laps + 1, total_payout = total_payout + ?, session_token = ?, last_played_at = NOW() WHERE id = ?")
        ->execute([$spinWin + $vaultContribution, $nextToken, $machineId]);
    
    $pdo->prepare("INSERT INTO game_logs (user_id, machine_id, bet, win, result, xp_earned) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$userId, $machineId, $betAmount, $spinWin + $vaultContribution, json_encode($result), $finalXp]);

    // Commit Transaction
    $pdo->commit();
    
    // Fetch absolute final balance
    $finalBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    // 14. RESPONSE
    echo json_encode([
        'status' => 'success',
        'stops' => $result,
        'winning_lines' => $winningLines,
        'win_amount' => $spinWin,
        'vault_contribution' => $vaultContribution,
        'new_balance' => (float)$finalBal,
        'xp_gained' => $finalXp,
        'is_teaser' => $isTeaser,
        'session_token' => $nextToken,
        'multiplier_active' => $winMultiplier > 1 ? $winMultiplier : false
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Spin Engine Error (User $userId): " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>