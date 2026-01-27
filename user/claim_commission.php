<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    $pdo->beginTransaction();

    // 1. Re-fetch Balance (Lock Row)
    $stmt = $pdo->prepare("SELECT commission_balance, balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();

    $amount = (float)$userData['commission_balance'];

    if ($amount < 1000) {
        throw new Exception("Minimum claim amount is 1,000 MMK.");
    }

    // 2. Transfer Commission to Main Balance
    $pdo->prepare("UPDATE users SET balance = balance + ?, commission_balance = 0 WHERE id = ?")
        ->execute([$amount, $userId]);

    // 3. Log Transaction
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'commission', ?, 'approved', 'Commission Claim')")
        ->execute([$userId, $amount]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Successfully claimed ' . number_format($amount) . ' MMK!',
        'new_balance' => $userData['balance'] + $amount,
        'new_commission' => 0
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>