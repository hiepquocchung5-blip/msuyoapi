<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// 1. Authenticate User
$user = authenticate($pdo);
$userId = $user['id'];
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 2. Parse Input
$data = json_decode(file_get_contents("php://input"));
$action = $data->action ?? '';
$machineId = (int)($data->machine_id ?? 0);

if (!$machineId) {
    http_response_code(400);
    echo json_encode(['error' => 'Machine ID required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 3. Lock Machine Row for Update
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmt->execute([$machineId]);
    $machine = $stmt->fetch();

    if (!$machine) {
        throw new Exception("Machine not found");
    }

    // --- ACTION: ENTER ---
    if ($action === 'enter') {
        
        // Anti-Squatter: Require at least 200 MMK to sit down
        if ($user['balance'] < 200 && $machine['free_spins'] == 0 && $machine['bonus_spins_left'] == 0) {
            http_response_code(402);
            throw new Exception("Insufficient funds to occupy this machine.");
        }

        // Check if occupied by another user
        if ($machine['status'] !== 'free' && $machine['current_user_id'] != $userId) {
            // Check for Ghost Session (If other user hasn't pinged in > 5 mins)
            if ($machine['last_ping_at'] && strtotime($machine['last_ping_at']) < strtotime('-5 minutes')) {
                // Ghost session detected. Overwrite them.
            } else {
                http_response_code(409); // Conflict
                throw new Exception("Machine is occupied by another player.");
            }
        }

        $sessionToken = bin2hex(random_bytes(32));

        // Clear any other machines this user might be sitting at
        $pdo->prepare("UPDATE machines SET status = 'free', current_user_id = NULL, session_token = NULL, last_ping_at = NULL, session_spins = 0, session_win_streak = 0 WHERE current_user_id = ? AND id != ?")
            ->execute([$userId, $machineId]);

        // Occupy new machine and WIPE previous streak/spin counts for fairness
        $stmtEnter = $pdo->prepare("UPDATE machines SET status = 'occupied', current_user_id = ?, session_token = ?, last_ping_at = NOW(), last_client_ip = ?, session_spins = 0, session_win_streak = 0 WHERE id = ?");
        $stmtEnter->execute([$userId, $sessionToken, $clientIp, $machineId]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "Link Established. Machine #{$machine['machine_number']} secured.",
            'session_token' => $sessionToken
        ]);

    } 
    // --- ACTION: LEAVE ---
    elseif ($action === 'leave') {
        
        if ($machine['current_user_id'] != $userId) {
            $pdo->rollBack(); 
            echo json_encode(['status' => 'success', 'message' => 'Not seated here']);
            exit;
        }

        // Deep wipe of session data to ensure the next player gets a fresh state
        $pdo->prepare("UPDATE machines SET status = 'free', current_user_id = NULL, session_token = NULL, last_ping_at = NULL, last_client_ip = NULL, session_spins = 0, session_win_streak = 0 WHERE id = ?")
            ->execute([$machineId]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Machine released']);

    } 
    // --- ACTION: PING (HEARTBEAT) ---
    elseif ($action === 'ping') {
        
        if ($machine['current_user_id'] != $userId) {
            throw new Exception("Not seated at this machine.");
        }

        // Keep session alive
        $pdo->prepare("UPDATE machines SET last_ping_at = NOW() WHERE id = ?")->execute([$machineId]);
        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => 'Heartbeat acknowledged']);
    } 
    else {
        throw new Exception("Invalid action. Use 'enter', 'leave', or 'ping'.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>