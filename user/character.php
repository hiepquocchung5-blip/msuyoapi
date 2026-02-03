<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    // Fetch all characters and flag ownership
    // We join the master 'characters' table with the 'user_characters' inventory
    $sql = "
        SELECT 
            c.char_key, 
            c.name, 
            c.island_id, 
            c.is_premium,
            CASE WHEN uc.id IS NOT NULL OR c.price = 0 THEN 1 ELSE 0 END as is_owned,
            uc.obtained_at
        FROM characters c
        LEFT JOIN user_characters uc ON c.char_key = uc.char_key AND uc.user_id = ?
        ORDER BY c.is_premium ASC, c.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add metadata for frontend
    foreach ($roster as &$char) {
        $char['is_active'] = ($char['char_key'] === $user['active_pet_id']);
        // Mock Affection Level (could be real DB column later)
        $char['affection'] = $char['is_owned'] ? rand(1, 100) : 0;
    }

    echo json_encode([
        'status' => 'success',
        'active_char' => $user['active_pet_id'],
        'roster' => $roster
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>