<?php
// Fix: Robust path check for middleware
$middlewarePath = __DIR__ . '/../utils/auth_middleware.php';
if (!file_exists($middlewarePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Middleware not found at ' . $middlewarePath]);
    exit;
}
require_once $middlewarePath;

// Handle JSON input first to fail fast if invalid
$jsonRaw = file_get_contents("php://input");
$data = json_decode($jsonRaw);

if ($jsonRaw && !$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

try {
    // 1. Authenticate (Wrapped in try-catch to catch DB connection errors in auth)
    $user = authenticate($pdo);
    $userId = $user['id'];

    // 2. Validate Input
    if (!isset($data->choice) || !in_array($data->choice, ['red', 'black'])) {
        throw new Exception("Invalid choice. Please select 'red' or 'black'.");
    }

    $pdo->beginTransaction();

    // 3. Fetch Eligible Win
    // Query checks for the specific column 'is_gamble_win'. 
    // If this fails with "Unknown column", run the database update from Step 29.
    $stmt = $pdo->prepare("SELECT id, win, is_gamble_win FROM game_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$userId]);
    $lastLog = $stmt->fetch();

    if (!$lastLog) {
        throw new Exception("No game history found.");
    }

    if ($lastLog['win'] <= 0) {
        throw new Exception("You didn't win the last spin, so you can't gamble.");
    }

    if ($lastLog['is_gamble_win'] == 1) {
        throw new Exception("You have already gambled this win.");
    }

    // 4. RNG Logic (50/50)
    $winningColor = (rand(1, 2) === 1) ? 'red' : 'black';
    $isWin = ($data->choice === $winningColor);
    
    $originalWin = (float)$lastLog['win'];
    $newWinAmount = $isWin ? $originalWin * 2 : 0;
    
    // 5. Update Balance
    if ($isWin) {
        // Win: Add the original win amount AGAIN (Total = 2x)
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$originalWin, $userId]);
    } else {
        // Lose: Deduct the original win amount (Total = 0)
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$originalWin, $userId]);
    }

    // 6. Update Log
    $pdo->prepare("UPDATE game_logs SET win = ?, is_gamble_win = 1 WHERE id = ?")->execute([$newWinAmount, $lastLog['id']]);

    $pdo->commit();

    // Fetch updated balance
    $stmtBal = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmtBal->execute([$userId]);
    $newBalance = $stmtBal->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'won' => $isWin,
        'choice' => $data->choice,
        'result_color' => $winningColor,
        'new_win_amount' => $newWinAmount,
        'new_balance' => (float)$newBalance
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error to server log for debugging
    error_log("Gamble Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>