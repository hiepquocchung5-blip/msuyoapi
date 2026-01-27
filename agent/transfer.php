<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$senderId = $user['id'];

if ($user['is_agent'] != 1) {
    http_response_code(403); echo json_encode(['error' => 'Agent access only']); exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->target_phone) || !isset($data->amount)) {
    http_response_code(400); echo json_encode(['error' => 'Missing target or amount']); exit;
}

$targetPhone = $data->target_phone;
$amount = (float)$data->amount;

if ($amount < 1000) {
    http_response_code(400); echo json_encode(['error' => 'Minimum transfer is 1,000 MMK']); exit;
}

if ($user['balance'] < $amount) {
    http_response_code(400); echo json_encode(['error' => 'Insufficient balance']); exit;
}

try {
    $pdo->beginTransaction();

    // 1. Find Recipient
    $stmtTarget = $pdo->prepare("SELECT id, username FROM users WHERE phone = ?");
    $stmtTarget->execute([$targetPhone]);
    $recipient = $stmtTarget->fetch();

    if (!$recipient) {
        throw new Exception("Recipient phone number not found.");
    }
    
    if ($recipient['id'] == $senderId) {
        throw new Exception("Cannot transfer to yourself.");
    }

    // 2. Deduct from Agent
    $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $senderId]);

    // 3. Add to Recipient
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $recipient['id']]);

    // 4. Log for Agent (Withdrawal)
    $stmtLogSender = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'withdraw', ?, 'approved', ?)");
    $stmtLogSender->execute([$senderId, $amount, "Transfer to {$recipient['username']} ($targetPhone)"]);

    // 5. Log for Recipient (Deposit)
    $stmtLogReceiver = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'deposit', ?, 'approved', ?)");
    $stmtLogReceiver->execute([$recipient['id'], $amount, "Transfer from Agent {$user['username']}"]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Successfully transferred " . number_format($amount) . " MMK to " . $recipient['username'],
        'new_balance' => $user['balance'] - $amount
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>