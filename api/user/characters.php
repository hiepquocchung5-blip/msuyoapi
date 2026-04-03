<?php
// ============================================================================
// SUROPARA API - COMPANION ROSTER & INVENTORY MATRIX
// ============================================================================

require_once __DIR__ . '/../../utils/auth_middleware.php'; 

// 1. Explicit CORS & Preflight Handling (Failsafe)
header("Access-Control-Allow-Origin: https://suropara.com");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Authenticate Operative
$user = authenticate($pdo);
$userId = $user['id'];

try {
    // 3. Fetch Roster & Ownership Matrix
    // Joins the master 'characters' table with the 'user_characters' inventory
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

    // 4. Data Formatting & Telemetry Injection
    foreach ($roster as &$char) {
        $char['is_active'] = ($char['char_key'] === $user['active_pet_id']);
        
        // Strict casting for React frontend logic
        $char['is_owned'] = (bool)$char['is_owned'];
        $char['is_premium'] = (bool)$char['is_premium'];
        
        // Mock Affection Level (Can be hooked into a real DB column for V11)
        $char['affection'] = $char['is_owned'] ? rand(10, 100) : 0;
    }

    echo json_encode([
        'status' => 'success',
        'active_char' => $user['active_pet_id'],
        'roster' => $roster
    ]);

} catch (PDOException $e) {
    // Write detailed errors to server logs to keep the API response clean
    error_log("Roster API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'error' => 'Database error while fetching companion matrix.'
    ]);
}
?>