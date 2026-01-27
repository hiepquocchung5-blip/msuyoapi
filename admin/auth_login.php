<?php
// Correct path: Go up one level from /admin to /api, then into /utils
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->username) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and Password required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$data->username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($data->password, $admin['password_hash'])) {
        
        // Create a simple token signature (In prod use JWT)
        $tokenSignature = hash('sha256', $admin['password_hash'] . $_SERVER['REMOTE_ADDR']);
        $token = $admin['id'] . '|' . $tokenSignature;

        // Update Last Login
        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);

        echo json_encode([
            'status' => 'success',
            'token' => $token,
            'role' => $admin['role'],
            'username' => $admin['username']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid admin credentials']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>