<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

$data = json_decode(file_get_contents("php://input"));

// Item Type: 'island' or 'character' (if we had a separate table, logic is similar)
// For now, we focus on unlocking Islands as per schema
if (!isset($data->island_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Island ID required']);
    exit;
}

$islandId = (int)$data->island_id;

try {
    $pdo->beginTransaction();

    // 1. Get Item Info
    $stmtItem = $pdo->prepare("SELECT name, unlock_price FROM islands WHERE id = ?");
    $stmtItem->execute([$islandId]);
    $island = $stmtItem->fetch();

    if (!$island) {
        throw new Exception("Island not found");
    }

    // 2. Check Ownership
    $owned = json_decode($user['owned_islands'], true) ?? [];
    if (in_array($islandId, $owned)) {
        throw new Exception("You already own this island");
    }

    // 3. Check Balance
    if ($user['balance'] < $island['unlock_price']) {
        http_response_code(402); // Payment Required
        echo json_encode(['error' => 'Insufficient MMK']);
        exit;
    }

    // 4. Process Purchase
    // Deduct Money
    $newBalance = $user['balance'] - $island['unlock_price'];
    
    // Add to Inventory
    $owned[] = $islandId;
    $newOwnedJson = json_encode($owned);

    $stmtUpd = $pdo->prepare("UPDATE users SET balance = ?, owned_islands = ? WHERE id = ?");
    $stmtUpd->execute([$newBalance, $newOwnedJson, $userId]);

    // Log Transaction (Internal System Spend)
    $stmtLog = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'withdraw', ?, 'approved', ?)");
    $stmtLog->execute([$userId, $island['unlock_price'], "Shop Purchase: " . $island['name']]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Unlocked ' . $island['name'],
        'new_balance' => $newBalance,
        'owned_islands' => $owned
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>