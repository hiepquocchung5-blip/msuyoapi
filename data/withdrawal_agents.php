<?php
// api/data/withdrawal_agents.php

// Prevent HTML errors breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Auth Check (Recommended but optional if you want public availability)
$userId = null;
try {
    $headers = apache_request_headers();
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        $stmt = $pdo->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$matches[1]]);
        $userId = $stmt->fetchColumn();
    }
} catch (Exception $e) {}

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$provider = $_GET['provider'] ?? '';

try {
    // 1. Find Specialists: Online Agents who have a wallet for this provider
    // This query joins admin_users with payment_methods to find matches.
    $sqlSpecific = "
        SELECT DISTINCT 
            a.id, 
            a.username, 
            'SPECIALIST' as badge_type,
            a.last_login
        FROM admin_users a
        JOIN payment_methods pm ON a.id = pm.admin_id
        WHERE a.is_online = 1 
          AND a.is_active = 1 
          AND pm.is_active = 1 
          AND (pm.provider_name = ? OR pm.provider_name LIKE ?)
        ORDER BY a.last_login DESC
    ";

    $stmt = $pdo->prepare($sqlSpecific);
    $stmt->execute([$provider, "%$provider%"]);
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fallback: General Online Finance Staff
    // If no specialist is found (e.g. no one has a KBZ wallet added but they are online),
    // we return general finance staff so the user isn't blocked.
    if (empty($agents)) {
        $sqlGeneral = "
            SELECT 
                id, 
                username, 
                'GENERAL' as badge_type,
                last_login
            FROM admin_users 
            WHERE is_online = 1 
              AND is_active = 1 
              AND (role = 'FINANCE' OR role = 'GOD')
            ORDER BY last_login DESC
            LIMIT 5
        ";
        $stmtGen = $pdo->query($sqlGeneral);
        $agents = $stmtGen->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Format Data for Frontend
    foreach ($agents as &$agent) {
        if ($agent['badge_type'] === 'SPECIALIST') {
            $agent['badge'] = 'FAST'; // Has the specific bank app ready
        } else {
            $agent['badge'] = 'ONLINE'; // General staff
        }
        
        // Simulate response time based on last login activity (mock)
        $agent['avg_time'] = rand(2, 8) . ' mins'; 
    }

    echo json_encode([
        'status' => 'success',
        'data' => $agents
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>