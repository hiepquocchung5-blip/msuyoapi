<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// 1. Authenticate User
$user = authenticate($pdo);
$userId = $user['id'];

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->char_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Character ID (Key) required']);
    exit;
}

$charKey = $data->char_id; // e.g., 'luna', 'kira'

try {
    // 2. Fetch Character Details
    $stmtChar = $pdo->prepare("SELECT id, char_key, island_id, price FROM characters WHERE char_key = ?");
    $stmtChar->execute([$charKey]);
    $character = $stmtChar->fetch();

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Character not found']);
        exit;
    }

    // 3. Verify Ownership
    // Logic: User owns character if price is 0 OR if they own the linked Island
    $isFree = (float)$character['price'] == 0;
    $ownedIslands = json_decode($user['owned_islands'], true) ?? [];
    
    // Check if Island ID is in user's owned list
    $hasIsland = in_array($character['island_id'], $ownedIslands);

    // Note: If you add direct character purchases later, add 'owned_characters' check here
    if (!$isFree && !$hasIsland) {
        http_response_code(403);
        echo json_encode(['error' => 'You have not unlocked this character yet']);
        exit;
    }

    // 4. Update Profile
    $stmtUpdate = $pdo->prepare("UPDATE users SET active_pet_id = ? WHERE id = ?");
    $stmtUpdate->execute([$charKey, $userId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Companion equipped!',
        'active_pet_id' => $charKey
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to equip character']);
}
?>