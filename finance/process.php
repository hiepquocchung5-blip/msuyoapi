<?php
require_once __DIR__ . '/../utils/admin_middleware.php'; 

// 1. Authenticate Admin (Must be FINANCE or GOD role)
$admin = authenticateAdmin($pdo, 'FINANCE');

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->transaction_id) || !isset($data->action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Transaction ID and Action (approve/reject) required']);
    exit;
}

$trxId = (int)$data->transaction_id;
$action = $data->action; // 'approve' or 'reject'
$note = $data->note ?? '';

// Fetch Transaction
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND status = 'pending'");
$stmt->execute([$trxId]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    echo json_encode(['error' => 'Transaction not found or already processed']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'approve') {
        // IF DEPOSIT: Add money to user
        if ($transaction['type'] === 'deposit') {
            $stmtCred = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmtCred->execute([$transaction['amount'], $transaction['user_id']]);
        }
        // IF WITHDRAWAL: Money was already deducted on request. Just mark approved.
        
        $newStatus = 'approved';
    } elseif ($action === 'reject') {
        // IF DEPOSIT: Do nothing, just reject.
        // IF WITHDRAWAL: Refund the money back to user.
        if ($transaction['type'] === 'withdraw') {
            $stmtRef = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmtRef->execute([$transaction['amount'], $transaction['user_id']]);
        }
        
        $newStatus = 'rejected';
    } else {
        throw new Exception("Invalid action");
    }

    // Update Transaction Status
    $stmtUpd = $pdo->prepare("UPDATE transactions SET status = ?, processed_by_admin_id = ?, admin_note = ? WHERE id = ?");
    $stmtUpd->execute([$newStatus, $admin['id'], $note, $trxId]);

    // Audit Log
    $stmtAudit = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'transactions')");
    $auditAction = strtoupper($action) . " Transaction #" . $trxId;
    $stmtAudit->execute([$admin['id'], $auditAction]);

    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => "Transaction $newStatus"]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Processing failed: ' . $e->getMessage()]);
}
?>