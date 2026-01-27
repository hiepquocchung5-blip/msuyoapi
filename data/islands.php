<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// User authentication is optional for viewing public island data, 
// but recommended to ensure the request comes from a valid client.
$user = authenticate($pdo);

// Fetch all active islands with full visual and economic config
// This data drives the 3D Carousel and Particle Effects on the frontend
$sql = "SELECT 
            id, 
            name, 
            slug, 
            `desc`, 
            unlock_price, 
            rtp_rate, 
            hostess_char_id, 
            atmosphere_type, 
            icon_emoji 
        FROM islands 
        WHERE is_active = 1 
        ORDER BY id ASC";

try {
    $stmt = $pdo->query($sql);
    $islands = $stmt->fetchAll();

    // Transform numeric types if necessary (PDO sometimes returns strings)
    foreach ($islands as &$island) {
        $island['unlock_price'] = (float)$island['unlock_price'];
        $island['rtp_rate'] = (float)$island['rtp_rate'];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $islands
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch islands']);
}
?>