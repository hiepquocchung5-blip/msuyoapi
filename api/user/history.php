<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Authenticate User
$user = authenticate($pdo);
$userId = $user['id'];

try {
    // Fetch last 50 transactions
    $sql = "SELECT 
                id, 
                type, 
                amount, 
                status, 
                created_at, 
                admin_note 
            FROM transactions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $transactions = $stmt->fetchAll();

    // Add provider info (optional join, or parse from note/type if simple)
    // For this schema, we join payment_methods if needed, or just return raw
    // Let's do a simple enrichment
    foreach ($transactions as &$tx) {
        $tx['amount'] = (float)$tx['amount'];
        // Format date for frontend if preferred, or send raw timestamp
        // $tx['date_formatted'] = date('Y-m-d H:i', strtotime($tx['created_at']));
    }

    echo json_encode([
        'status' => 'success',
        'data' => $transactions
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch history']);
}
?>