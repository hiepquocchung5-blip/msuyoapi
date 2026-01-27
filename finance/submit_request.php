<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// 1. Authenticate User
$user = authenticate($pdo);
$userId = $user['id'];

// 2. Parse Input
$data = json_decode(file_get_contents("php://input"));

// Validation
if (!isset($data->type) || !isset($data->amount) || !isset($data->provider)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing transaction details (type, amount, or provider)']);
    exit;
}

$amount = (float)$data->amount;
$type = $data->type; // 'deposit' or 'withdraw'
$providerName = htmlspecialchars(stripslashes(trim($data->provider)));

// Basic Amount Check
if ($amount < 1000) {
    http_response_code(400); 
    echo json_encode(['error' => 'Minimum amount is 1,000 MMK']); 
    exit;
}

try {
    $pdo->beginTransaction();

    if ($type === 'deposit') {
        // --- DEPOSIT LOGIC ---
        
        // Find internal Payment Method ID for tracking
        $stmtPM = $pdo->prepare("SELECT id FROM payment_methods WHERE provider_name = ? LIMIT 1");
        $stmtPM->execute([$providerName]);
        $pm = $stmtPM->fetch();
        $pmId = $pm ? $pm['id'] : null;

        // --- REAL WORLD IMAGE HANDLING ---
        $proofPath = null;
        if (isset($data->proof_image_base64) && !empty($data->proof_image_base64)) {
            // Define storage directory (One level up from 'finance' folder, into 'proofs')
            $uploadDir = __DIR__ . '/../proofs/';
            
            // Ensure directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Process Base64
            $imageParts = explode(";base64,", $data->proof_image_base64);
            $imageTypeAux = explode("image/", $imageParts[0]);
            
            // Default to jpg if type detection fails
            $imageType = isset($imageTypeAux[1]) ? $imageTypeAux[1] : 'jpg';
            $imageBase64 = isset($imageParts[1]) ? $imageParts[1] : $data->proof_image_base64;
            
            $imageContent = base64_decode($imageBase64);
            
            if ($imageContent === false) {
                 throw new Exception("Invalid image data provided.");
            }

            // Generate filename
            $filename = uniqid('proof_', true) . '.' . $imageType;
            $fileFullPath = $uploadDir . $filename;

            // Save file
            if (file_put_contents($fileFullPath, $imageContent)) {
                // Store relative path for DB/API access (e.g., 'proofs/abc.jpg')
                $proofPath = 'proofs/' . $filename;
            } else {
                throw new Exception("Failed to save proof image to server.");
            }
        } else {
             throw new Exception("Proof screenshot is required for deposits.");
        }
        
        // Handle Transaction Last Digits
        $lastDigits = isset($data->last_digits) ? substr(htmlspecialchars(stripslashes(trim($data->last_digits))), 0, 6) : null;

        if (!$lastDigits || strlen($lastDigits) !== 6) {
             throw new Exception("Valid 6-digit Transaction ID is required.");
        }

        $sql = "INSERT INTO transactions 
                (user_id, type, amount, payment_method_id, status, proof_image, transaction_last_digits) 
                VALUES (?, 'deposit', ?, ?, 'pending', ?, ?)";
        
        $pdo->prepare($sql)->execute([$userId, $amount, $pmId, $proofPath, $lastDigits]);
        $message = "Deposit submitted for review.";

    } elseif ($type === 'withdraw') {
        // --- WITHDRAWAL LOGIC ---

        // 1. Balance Check (Get fresh balance inside transaction lock)
        $stmtBal = $pdo->prepare("SELECT balance, phone FROM users WHERE id = ? FOR UPDATE");
        $stmtBal->execute([$userId]);
        $freshUser = $stmtBal->fetch();

        if ($freshUser['balance'] < $amount) {
            throw new Exception("Insufficient balance.");
        }

        // 2. TIER LIMIT CHECK (Anti-Abuse Rule)
        // Calculate Total Lifetime Approved Deposits to determine withdrawal tier
        $stmtSum = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'deposit' AND status = 'approved'");
        $stmtSum->execute([$userId]);
        $totalDeposited = (float)$stmtSum->fetchColumn();

        // Fetch applicable limit
        $stmtLimit = $pdo->prepare("SELECT max_withdraw FROM withdrawal_limits WHERE deposit_amount <= ? ORDER BY deposit_amount DESC LIMIT 1");
        $stmtLimit->execute([$totalDeposited]);
        $limitConfig = $stmtLimit->fetch();

        // Default to 0 withdrawal if no deposits made (or set base limit)
        $maxAllowed = $limitConfig ? (float)$limitConfig['max_withdraw'] : 0;

        if ($amount > $maxAllowed) {
            throw new Exception("Withdrawal limit exceeded. Based on your lifetime deposits (" . number_format($totalDeposited) . " MMK), your max withdrawal is " . number_format($maxAllowed) . " MMK.");
        }

        // 3. Deduct Balance immediately (Prevent double-spend)
        $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?")->execute([$amount, $userId]);

        // 4. Create Transaction Record
        $targetAdminId = isset($data->target_admin_id) ? (int)$data->target_admin_id : NULL;
        $adminNote = "Target: {$freshUser['phone']} ($providerName)";
        
        if ($targetAdminId) {
            $adminNote .= " [Reserved for Agent #$targetAdminId]";
        }

        $sql = "INSERT INTO transactions (user_id, type, amount, status, admin_note, processed_by_admin_id) VALUES (?, 'withdraw', ?, 'pending', ?, ?)";
        $pdo->prepare($sql)->execute([$userId, $amount, $adminNote, $targetAdminId]);
        
        $message = "Withdrawal requested successfully.";

    } else {
        throw new Exception("Invalid transaction type");
    }

    $pdo->commit();

    // Fetch updated balance for frontend update
    $stmtFinal = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmtFinal->execute([$userId]);
    $finalBalance = $stmtFinal->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'new_balance' => (float)$finalBalance
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400); // Bad Request
    echo json_encode(['error' => $e->getMessage()]);
}
?>