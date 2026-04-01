<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Referral code required']);
    exit;
}

$code = strtoupper(trim($data->code));

// Basic Config
$referrerBonus = 2000;
$refereeBonus = 1000;

try {
    $pdo->beginTransaction();

    // 1. Validation
    if ($user['referrer_id'] !== null) {
        throw new Exception("You have already claimed a referral code");
    }

    if ($user['referral_code'] === $code) {
        throw new Exception("You cannot use your own code");
    }

    // 2. Find Referrer
    $stmtRef = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmtRef->execute([$code]);
    $referrer = $stmtRef->fetch();

    if (!$referrer) {
        throw new Exception("Invalid referral code");
    }

    // 3. Apply Rewards
    // Reward Referrer
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
        ->execute([$referrerBonus, $referrer['id']]);
    
    // Reward User (Referee) & Link them
    $pdo->prepare("UPDATE users SET balance = balance + ?, referrer_id = ? WHERE id = ?")
        ->execute([$refereeBonus, $referrer['id'], $userId]);

    // 4. Log Transactions
    $sqlLog = "INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)";
    $stmtLog = $pdo->prepare($sqlLog);
    
    // Log for Referrer
    $stmtLog->execute([$referrer['id'], $referrerBonus, "Referral Bonus (User #$userId)"]);
    // Log for User
    $stmtLog->execute([$userId, $refereeBonus, "Referral Claim (Code: $code)"]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => "Code claimed! You got {$refereeBonus} MMK.",
        'new_balance' => $user['balance'] + $refereeBonus
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>