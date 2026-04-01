<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Optional: Authenticate User
try { $user = authenticate($pdo); } catch (Exception $e) {}

try {
    // SMART ROUTING QUERY
    // Only fetch payment methods where:
    // 1. The method itself is active (is_active = 1)
    // 2. The linked Staff Member is ONLINE (is_online = 1)
    // 3. The linked Staff Member is ACTIVE (not banned)
    
    $sql = "
        SELECT 
            pm.id, 
            pm.provider_name, 
            pm.account_name, 
            pm.account_number, 
            pm.logo_url 
        FROM payment_methods pm
        JOIN admin_users au ON pm.admin_id = au.id
        WHERE pm.is_active = 1 
          AND au.is_active = 1 
          AND au.is_online = 1
        ORDER BY pm.id ASC
    ";

    $stmt = $pdo->query($sql);
    $methods = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $methods,
        'count' => count($methods),
        'message' => count($methods) === 0 ? "No online agents available. Please check back later." : "Active channels loaded."
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch payment methods']);
}
?>