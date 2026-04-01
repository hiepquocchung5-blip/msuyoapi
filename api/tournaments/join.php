<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

$data = json_decode(file_get_contents("php://input"));
if (!isset($data->tournament_id)) {
    http_response_code(400); echo json_encode(['error' => 'ID required']); exit;
}
$tId = (int)$data->tournament_id;

try {
    $pdo->beginTransaction();

    // 1. Get Config
    $stmtT = $pdo->prepare("SELECT * FROM tournaments WHERE id = ? FOR UPDATE");
    $stmtT->execute([$tId]);
    $tourney = $stmtT->fetch();

    if (!$tourney || $tourney['status'] !== 'active') throw new Exception("Tournament closed or invalid.");
    
    // 2. Check Level
    if ($user['level'] < $tourney['min_level']) throw new Exception("Level {$tourney['min_level']} required.");

    // 3. Check Already Joined
    $stmtCheck = $pdo->prepare("SELECT id FROM tournament_entries WHERE tournament_id = ? AND user_id = ?");
    $stmtCheck->execute([$tId, $userId]);
    if ($stmtCheck->rowCount() > 0) throw new Exception("Already joined!");

    // 4. Pay Entry Fee
    if ($user['balance'] < $tourney['entry_fee']) throw new Exception("Insufficient MMK.");
    
    if ($tourney['entry_fee'] > 0) {
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$tourney['entry_fee'], $userId]);
        // Log Tx
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'withdraw', ?, 'approved', ?)")
            ->execute([$userId, $tourney['entry_fee'], "Tournament Entry: {$tourney['title']}"]);
    }

    // 5. Create Entry
    $pdo->prepare("INSERT INTO tournament_entries (tournament_id, user_id) VALUES (?, ?)")
        ->execute([$tId, $userId]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Joined successfully! Good luck.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>