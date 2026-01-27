<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// 1. Authenticate User
$user = authenticate($pdo);
$userId = $user['id'];

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
    // This prevents race conditions where two users try to sit at the same time
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ? FOR UPDATE");
    $stmt->execute([$machineId]);
    $machine = $stmt->fetch();

    if (!$machine) {
        throw new Exception("Machine not found");
    }

    if ($action === 'enter') {
        // --- ENTER LOGIC ---

        // Check if occupied by another user
        if ($machine['status'] !== 'free' && $machine['current_user_id'] != $userId) {
            http_response_code(409); // Conflict
            throw new Exception("Machine is occupied by another player.");
        }

        // GENERATE SESSION TOKEN (Critical for Anti-Replay Security)
        // This token will be required for every spin call while seated
        $sessionToken = bin2hex(random_bytes(32));

        // 1. Clear any other machines this user might be sitting at (Force Stand Up)
        // Ensures a user can only occupy ONE machine at a time
        $pdo->prepare("UPDATE machines SET status = 'free', current_user_id = NULL, session_token = NULL WHERE current_user_id = ? AND id != ?")
            ->execute([$userId, $machineId]);

        // 2. Occupy the new machine
        $stmtEnter = $pdo->prepare("UPDATE machines SET status = 'occupied', current_user_id = ?, session_token = ? WHERE id = ?");
        $stmtEnter->execute([$userId, $sessionToken, $machineId]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "Welcome to Machine #{$machine['machine_number']}",
            'session_token' => $sessionToken // Frontend MUST store this for /spin calls
        ]);

    } elseif ($action === 'leave') {
        // --- LEAVE LOGIC ---

        // Only allow leaving if currently seated
        // If the user isn't seated here, we still return success to ensure the UI doesn't get stuck
        if ($machine['current_user_id'] != $userId) {
            $pdo->rollBack(); 
            echo json_encode(['status' => 'success', 'message' => 'Not seated here']);
            exit;
        }

        // Clear Session Data & Free Machine
        $pdo->prepare("UPDATE machines SET status = 'free', current_user_id = NULL, session_token = NULL WHERE id = ?")
            ->execute([$machineId]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Left machine']);

    } else {
        throw new Exception("Invalid action. Use 'enter' or 'leave'.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Return 400 for bad requests (unless already set to 409 above)
    if (http_response_code() === 200) http_response_code(400);
    
    echo json_encode(['error' => $e->getMessage()]);
}
?>