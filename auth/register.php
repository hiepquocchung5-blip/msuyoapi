<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// Handle CORS if not handled by middleware for public endpoints
header("Access-Control-Allow-Origin: https://m.api.suropara.com");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->phone) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone and Password required']);
    exit;
}

// Check if user already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
$stmt->execute([$data->phone]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Phone already registered']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 0. Fetch System Configuration
    // We get the welcome bonus amount dynamically from the admin settings
    $stmtConfig = $pdo->prepare("SELECT value FROM system_settings WHERE key_name = 'welcome_bonus'");
    $stmtConfig->execute();
    $configBonus = $stmtConfig->fetchColumn();

    // Use configured bonus or default to 0 if not set
    $startingBalance = ($configBonus !== false) ? (float)$configBonus : 0;

    // 1. Referral Logic
    $referrerId = null;
    $referralBonus = 500;    // Extra per person

    if (isset($data->ref_code) && !empty($data->ref_code)) {
        $refCode = strtoupper(trim($data->ref_code));
        $stmtRef = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmtRef->execute([$refCode]);
        $referrer = $stmtRef->fetch();
        
        if ($referrer) {
            $referrerId = $referrer['id'];
            $startingBalance += $referralBonus; // Bonus for new user
            
            // Reward Referrer
            $pdo->prepare("UPDATE users SET balance = balance + ?, commission_balance = commission_balance + ? WHERE id = ?")
                ->execute([$referralBonus, $referralBonus, $referrerId]);
                
            // Log Referrer Transaction
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")
                ->execute([$referrerId, $referralBonus, "Referral Bonus (New User)"]);
        }
    }

    // 2. Create User
    $passwordHash = password_hash($data->password, PASSWORD_DEFAULT);
    $username = 'Player_' . substr($data->phone, -4);
    $defaultIslands = json_encode([1, 2]); // Start with Vegas & Kohana
    
    // Generate unique referral code for this new user
    $newRefCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

    $sql = "INSERT INTO users (phone, password_hash, username, owned_islands, balance, referral_code, referrer_id, active_pet_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'luna')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$data->phone, $passwordHash, $username, $defaultIslands, $startingBalance, $newRefCode, $referrerId]);
    
    $userId = $pdo->lastInsertId();
    
    // 3. Generate Token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$userId, $token, $expiry]);
        
    // 4. Log Welcome Bonus
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")
        ->execute([$userId, $startingBalance, "Welcome Bonus" . ($referrerId ? " + Referral" : "")]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'balance' => (float)$startingBalance,
            'referral_code' => $newRefCode,
            'active_pet_id' => 'luna',
            'owned_islands' => [1, 2]
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
}
?>