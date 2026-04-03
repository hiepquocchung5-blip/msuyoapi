<?php
// ============================================================================
// SUROPARA API - SECURE LOGIN ENDPOINT
// ============================================================================

require_once __DIR__ . '/../../utils/auth_middleware.php';

// 1. Explicit CORS & Preflight Handling for Public Endpoints
header("Access-Control-Allow-Origin: https://suropara.com");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->phone) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Missing credentials']);
    exit;
}

// 2. Fetch User & Enforce Active Status
$stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? AND status = 'active'");
$stmt->execute([$data->phone]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($data->password, $user['password_hash'])) {
    
    // 3. Cryptographic Token Generation
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // 4. Session Management (Clear old tokens to prevent session hijacking)
    $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?")->execute([$user['id']]);
    
    $stmtToken = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmtToken->execute([$user['id'], $token, $expiry]);

    // 5. Update Security Telemetry (Feeds the Admin Risk Radar)
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $pdo->prepare("UPDATE users SET last_login_at = NOW(), last_ip = ? WHERE id = ?")
        ->execute([$clientIp, $user['id']]);

    // 6. Payload Sanitization (NEVER send the password hash to the client)
    unset($user['password_hash']);

    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'user' => $user
    ]);
    
} else {
    // Generic error message prevents username enumeration attacks
    http_response_code(401);
    echo json_encode(['status' => 'error', 'error' => 'Invalid credentials or account suspended.']);
}
?>