<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->phone) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing credentials']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
$stmt->execute([$data->phone]);
$user = $stmt->fetch();

if ($user && password_verify($data->password, $user['password_hash'])) {
    // Generate New Token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Clear old tokens (Optional: or keep multiple sessions)
    $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?")->execute([$user['id']]);
    
    $stmtToken = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmtToken->execute([$user['id'], $token, $expiry]);

    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'user' => $user
    ]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}
?>