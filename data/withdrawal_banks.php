<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 1. Fetch Withdrawal Banks with Online Agent Count
    // This subquery counts how many staff members are:
    // - Online (is_online=1)
    // - Have a linked Payment Method matching this bank's name
    $sql = "
        SELECT 
            wb.id, 
            wb.bank_name, 
            wb.logo_url,
            (
                SELECT COUNT(DISTINCT a.id)
                FROM admin_users a
                JOIN payment_methods pm ON a.id = pm.admin_id
                WHERE a.is_online = 1 
                  AND a.is_active = 1 
                  AND pm.is_active = 1 
                  AND pm.provider_name = wb.bank_name
            ) as online_agent_count
        FROM withdrawal_banks wb
        WHERE wb.is_active = 1
        ORDER BY wb.id ASC
    ";

    $stmt = $pdo->query($sql);
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $banks
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch withdrawal banks: ' . $e->getMessage()]);
}
?>