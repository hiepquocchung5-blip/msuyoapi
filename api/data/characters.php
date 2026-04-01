<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Authentication is recommended to ensure only valid app users fetch data
// If you want this public, you can remove this line.
try {
    $user = authenticate($pdo);
} catch (Exception $e) {
    // If token invalid, return 401
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Fetch all characters including their SVG data and price
    $sql = "SELECT 
                id, 
                char_key, 
                name, 
                island_id, 
                price, 
                is_premium, 
                svg_data 
            FROM characters 
            ORDER BY id ASC";

    $stmt = $pdo->query($sql);
    $characters = $stmt->fetchAll();

    // Data formatting
    foreach ($characters as &$char) {
        $char['price'] = (float)$char['price'];
        $char['is_premium'] = (bool)$char['is_premium'];
        // Note: svg_data is stored as a JSON string in DB, 
        // passing it raw allows frontend to parse or use as needed.
    }

    echo json_encode([
        'status' => 'success',
        'data' => $characters
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch characters']);
}
?>