<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Optional: specific auth check if this info is private
// $user = authenticate($pdo);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    // Fetch limits ordered by deposit amount
    $sql = "SELECT id, deposit_amount, max_withdraw FROM withdrawal_limits ORDER BY deposit_amount ASC";
    $stmt = $pdo->query($sql);
    $limits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast numbers to proper types
    foreach ($limits as &$limit) {
        $limit['deposit_amount'] = (float)$limit['deposit_amount'];
        $limit['max_withdraw'] = (float)$limit['max_withdraw'];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $limits
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch limits']);
}
?>