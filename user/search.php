<?php
require_once __DIR__ . '/../../utils/admin_middleware.php'; 

// Authenticate Admin (STAFF role minimum)
$admin = authenticateAdmin($pdo, 'STAFF');

$query = $_GET['q'] ?? '';

if (strlen($query) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Search query too short']);
    exit;
}

try {
    // Search by Phone, Username, or ID
    $sql = "SELECT id, username, phone, balance, status, created_at 
            FROM users 
            WHERE phone LIKE ? OR username LIKE ? OR id = ? 
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $likeQuery = "%$query%";
    $stmt->execute([$likeQuery, $likeQuery, $query]);
    $users = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $users
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
?>