<?php
// Fix CORS for public endpoint logic
// Note: We removed the hardcoded header("Access-Control-Allow-Origin: ...") 
// because the .htaccess file now handles it globally. 
// Doubling up headers usually causes errors.

require_once __DIR__ . '/../utils/auth_middleware.php'; 

header("Content-Type: application/json; charset=UTF-8");

// Handle Pre-flight request (OPTIONS)
// Although .htaccess handles this, keeping this as a fallback is safe.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Read Input
$data = json_decode(file_get_contents("php://input"));

// Validation
if (!isset($data->phone) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Phone and Password required']);
    exit;
}

// Check if user exists
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
    $stmtConfig = $pdo->prepare("SELECT value FROM system_settings WHERE key_name = 'welcome_bonus'");
    $stmtConfig->execute();
    $configBonus = $stmtConfig->fetchColumn();

    $startingBalance = ($configBonus !== false) ? (float)$configBonus : 0;

    // 1. Referral Logic
    $referrerId = null;
    $referralBonus = 500;

    if (isset($data->ref_code) && !empty($data->ref_code)) {
        $refCode = strtoupper(trim($data->ref_code));
        $stmtRef = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmtRef->execute([$refCode]);
        $referrer = $stmtRef->fetch();
        
        if ($referrer) {
            $referrerId = $referrer['id'];
            $startingBalance += $referralBonus; 
            
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
    // Safer username generation
    $username = 'Player_' . substr(preg_replace('/[^0-9]/', '', $data->phone), -4);
    $defaultIslands = json_encode([1, 2]); 
    
    // Generate unique referral code
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
    // In production, log the actual error but show a generic one to the user
    error_log($e->getMessage());
    echo json_encode(['error' => 'Registration failed. Please try again.']);
}
?>