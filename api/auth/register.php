<?php
// ============================================================================
// SUROPARA API - SECURE REGISTRATION ENDPOINT
// ============================================================================

require_once __DIR__ . '/../../utils/auth_middleware.php';

// 1. Explicit CORS & Preflight Handling
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
    echo json_encode(['status' => 'error', 'error' => 'Phone and Password required.']);
    exit;
}

// 2. Strict Input Sanitization
$phone = preg_replace('/[^0-9]/', '', $data->phone);
if (strlen($phone) < 7) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'error' => 'Invalid phone number format.']);
    exit;
}

// 3. Conflict Check
$stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
$stmt->execute([$phone]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'error' => 'Phone number is already registered.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 4. Fetch System Configuration (Welcome Bonus)
    $stmtConfig = $pdo->prepare("SELECT value FROM system_settings WHERE key_name = 'welcome_bonus'");
    $stmtConfig->execute();
    $configBonus = $stmtConfig->fetchColumn();

    $startingBalance = ($configBonus !== false) ? (float)$configBonus : 5000;

    // 5. Affiliate / Referral Logic
    $referrerId = null;
    $referralBonus = 500;
    
    // Support both ref_code and refCode depending on Axios payload casing
    $incomingRef = $data->ref_code ?? $data->refCode ?? '';

    if (!empty($incomingRef)) {
        $refCode = strtoupper(trim($incomingRef));
        $stmtRef = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmtRef->execute([$refCode]);
        $referrer = $stmtRef->fetch();
        
        if ($referrer) {
            $referrerId = $referrer['id'];
            $startingBalance += $referralBonus; 
            
            // Reward Referrer's Affiliate Wallet (V10.1 Standard)
            $pdo->prepare("UPDATE users SET commission_balance = commission_balance + ? WHERE id = ?")
                ->execute([$referralBonus, $referrerId]);
                
            // Log Referrer Transaction
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'commission', ?, 'approved', ?)")
                ->execute([$referrerId, $referralBonus, "Referral Commission (New User)"]);
        }
    }

    // 6. Create User Identity
    $passwordHash = password_hash($data->password, PASSWORD_DEFAULT);
    $username = 'Player_' . substr($phone, -4);
    $defaultIslands = json_encode([1, 2]); 
    $newRefCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $sql = "INSERT INTO users (phone, password_hash, username, owned_islands, balance, referral_code, referrer_id, active_pet_id, last_ip, last_login_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'luna', ?, NOW(), 'active')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$phone, $passwordHash, $username, $defaultIslands, $startingBalance, $newRefCode, $referrerId, $clientIp]);
    
    $userId = $pdo->lastInsertId();
    
    // 7. Seed Default Gacha Inventory
    $pdo->prepare("INSERT IGNORE INTO user_characters (user_id, char_key) VALUES (?, 'luna'), (?, 'mika')")
        ->execute([$userId, $userId]);
    
    // 8. Generate Cryptographic Session Token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$userId, $token, $expiry]);
        
    // 9. Log Financial Baseline
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")
        ->execute([$userId, $startingBalance, "System Welcome Bonus" . ($referrerId ? " + Referral" : "")]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'phone' => $phone,
            'balance' => (float)$startingBalance,
            'level' => 1,
            'xp' => 0,
            'referral_code' => $newRefCode,
            'active_pet_id' => 'luna',
            'owned_islands' => [1, 2]
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    // Write detailed errors to server logs to keep the API response clean
    error_log("Registration Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Core system failure during registration. Please try again.']);
}
?>