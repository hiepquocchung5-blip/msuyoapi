<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];
$today = date('Y-m-d');

header("Access-Control-Allow-Origin: https://suropara.com");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Fetch active missions and user's progress for today
        $sql = "
            SELECT 
                dm.id, 
                dm.description as task, 
                dm.target_val as total, 
                dm.reward_mmk as reward,
                COALESCE(ump.progress, 0) as progress,
                COALESCE(ump.is_claimed, 0) as claimed
            FROM daily_missions dm
            LEFT JOIN user_mission_progress ump 
                ON dm.id = ump.mission_id AND ump.user_id = ? AND ump.tracking_date = ?
            WHERE dm.is_active = 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $today]);
        $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format booleans/ints
        foreach ($missions as &$m) {
            $m['claimed'] = (bool)$m['claimed'];
            // Cap progress at total for UI purposes
            if ($m['progress'] > $m['total']) $m['progress'] = $m['total'];
        }

        echo json_encode(['status' => 'success', 'data' => $missions]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"));
        $missionId = (int)($data->mission_id ?? 0);

        $pdo->beginTransaction();

        // Check mission config and user progress
        $stmt = $pdo->prepare("
            SELECT dm.reward_mmk, dm.target_val, COALESCE(ump.progress, 0) as progress, COALESCE(ump.is_claimed, 0) as is_claimed
            FROM daily_missions dm
            LEFT JOIN user_mission_progress ump ON dm.id = ump.mission_id AND ump.user_id = ? AND ump.tracking_date = ?
            WHERE dm.id = ? FOR UPDATE
        ");
        $stmt->execute([$userId, $today, $missionId]);
        $mission = $stmt->fetch();

        if (!$mission) throw new Exception("Mission not found.");
        if ($mission['is_claimed']) throw new Exception("Already claimed.");
        if ($mission['progress'] < $mission['target_val']) throw new Exception("Mission incomplete.");

        $reward = (int)$mission['reward_mmk'];

        // Mark as claimed
        $pdo->prepare("UPDATE user_mission_progress SET is_claimed = 1 WHERE user_id = ? AND mission_id = ? AND tracking_date = ?")
            ->execute([$userId, $missionId, $today]);

        // Reward User
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$reward, $userId]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")
            ->execute([$userId, $reward, "Daily Mission Reward #$missionId"]);

        $pdo->commit();

        // Get new balance
        $newBal = $pdo->query("SELECT balance FROM users WHERE id = $userId")->fetchColumn();

        echo json_encode([
            'status' => 'success',
            'message' => "Claimed $reward MMK!",
            'new_balance' => (float)$newBal
        ]);
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>