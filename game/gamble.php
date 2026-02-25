<?php
// api/game/gamble.php
require_once __DIR__ . '/../../utils/auth_middleware.php';

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit;
}

try {
    $user = authenticate($pdo);
    $userId = $user['id'];

    if (!isset($data->choice) || !in_array($data->choice, ['red', 'black'])) {
        throw new Exception("Invalid choice.");
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, win, bet, is_gamble_win FROM game_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$userId]);
    $lastLog = $stmt->fetch();

    if (!$lastLog || $lastLog['win'] <= 0) throw new Exception("No valid win to gamble.");
    if ($lastLog['is_gamble_win'] == 1) throw new Exception("Already gambled.");

    // --- ADVANCED GAMBLE ALGORITHM ---
    $winningColor = (mt_rand(1, 100) <= 50) ? 'red' : 'black';
    $isWin = ($data->choice === $winningColor);
    
    $originalWin = (float)$lastLog['win'];
    $newWinAmount = 0;
    
    // Dynamic Modifiers
    $multiplier = 2; 
    $isCritical = false;
    $isPity = false;

    if ($isWin) {
        // 10% Chance for a "Critical Hit" -> 3x Multiplier instead of 2x
        if (mt_rand(1, 100) <= 10) {
            $multiplier = 3;
            $isCritical = true;
        }
        $newWinAmount = $originalWin * $multiplier;
        
        // Add the difference (since they already have the original win in their balance)
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([($newWinAmount - $originalWin), $userId]);
    } else {
        // 5% Chance for a "Lucky Save" -> Don't lose everything, keep 50%
        if (mt_rand(1, 100) <= 5) {
            $newWinAmount = $originalWin * 0.5;
            $isPity = true;
            // Deduct only half
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([($originalWin - $newWinAmount), $userId]);
        } else {
            // Standard Loss: Deduct the whole original win
            $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$originalWin, $userId]);
        }
    }

    // Update the game log
    $pdo->prepare("UPDATE game_logs SET win = ?, is_gamble_win = 1 WHERE id = ?")->execute([$newWinAmount, $lastLog['id']]);

    $pdo->commit();

    $newBalance = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'won' => $isWin,
        'choice' => $data->choice,
        'result_color' => $winningColor,
        'new_win_amount' => $newWinAmount,
        'new_balance' => (float)$newBalance,
        'is_critical' => $isCritical,
        'is_pity' => $isPity
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
?>