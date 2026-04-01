<?php
// ============================================================================
// SUROPARA API - AAA AUTHENTICATION MIDDLEWARE
// ----------------------------------------------------------------------------
// Handles CORS, Pre-flight routing, Token Extraction, and DB Auth Locks.
// ============================================================================

// --- 1. DYNAMIC CORS & HEADERS ---
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedDomains = [
    'https://suropara.com', 
    'https://m.suropara.com', 
    'http://localhost:3000',
    'https://localhost:3000'
];

if (in_array($origin, $allowedDomains)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Safe fallback for strict environments
    header("Access-Control-Allow-Origin: https://suropara.com"); 
}

header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Idempotency-Key");

// Instantly resolve Preflight OPTIONS requests without hitting the DB
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- 2. CORE DEPENDENCIES ---
// Correctly maps from /api/utils/ to /api/config/
require_once __DIR__ . '/../api/config/db.php';

// --- 3. AUTHENTICATION ENGINE ---
function authenticate($pdo) {
    // Attempt standard extraction
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    // Failsafe: Apache/FastCGI sometimes strips the HTTP_AUTHORIZATION array key
    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $authHeader = $requestHeaders['Authorization'] ?? $requestHeaders['authorization'] ?? '';
    }

    // Validate Bearer Format
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error', 
            'error' => [
                'code' => 'ERR_UNAUTHORIZED', 
                'message' => 'No cryptographic token provided.'
            ]
        ]);
        exit;
    }

    $token = $matches[1];
    
    // Validate Token and Expiry Boundary
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM user_tokens WHERE token = ?");
    $stmt->execute([$token]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session || strtotime($session['expires_at']) < time()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error', 
            'error' => [
                'code' => 'ERR_TOKEN_EXPIRED', 
                'message' => 'Session expired or invalidated.'
            ]
        ]);
        exit;
    }

    // Fetch User & Enforce Active Status (Instantly kicks banned users)
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmtUser->execute([$session['user_id']]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error', 
            'error' => [
                'code' => 'ERR_ACCOUNT_LOCKED', 
                'message' => 'Account suspended or deleted.'
            ]
        ]);
        exit;
    }

    return $user;
}
?>