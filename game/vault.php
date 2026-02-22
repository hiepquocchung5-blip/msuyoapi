<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

// 1. Authenticate
$user = authenticate($pdo);
$userId = $user['id'];

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

// CONFIG: When is the vault open? (e.g., Saturday & Sunday)
$dayOfWeek = date('w'); // 0 (Sun) - 6 (Sat)
$isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
$isOpen = $isWeekend; 

try {
    // --- GET: Check Status ---
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT balance, total_saved FROM user_vaults WHERE user_id = ?");
        $stmt->execute([$userId]);
        $vault = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$vault) {
            // Auto-init if missing
            $pdo->prepare("INSERT IGNORE INTO user_vaults (user_id) VALUES (?)")->execute([$userId]);
            $vault = ['balance' => 0, 'total_saved' => 0];
        }

        echo json_encode([
            'status' => 'success',
            'balance' => (float)$vault['balance'],
            'total_saved' => (float)$vault['total_saved'],
            'is_open' => $isOpen,
            'open_days' => 'Saturday & Sunday'
        ]);
        exit;
    }

    // --- POST: Smash (Claim) ---
    if ($method === 'POST') {
        if (!$isOpen) {
            http_response_code(403);
            echo json_encode(['error' => 'Vault is locked! Come back on the weekend.']);
            exit;
        }

        $pdo->beginTransaction();

        // Lock row
        $stmt = $pdo->prepare("SELECT balance FROM user_vaults WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $balance = (float)$stmt->fetchColumn();

        if ($balance <= 0) {
            throw new Exception("Vault is empty.");
        }

        // Transfer to Main Balance
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$balance, $userId]);
        
        // Reset Vault
        $pdo->prepare("UPDATE user_vaults SET balance = 0, last_smashed_at = NOW() WHERE user_id = ?")->execute([$userId]);

        // Log
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', 'Vault Smash')")
            ->execute([$userId, $balance]);

        $pdo->commit();

        // Get new user balance
        $stmtUser = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $newWalletBalance = $stmtUser->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'claimed_amount' => $balance,
            'new_wallet_balance' => (float)$newWalletBalance
        ]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>