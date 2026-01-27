<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// 1. Authenticate
$user = authenticate($pdo);
$userId = $user['id'];

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    // 2. Fetch "Unread-like" Activity (Last 48 hours)
    // We look for finalized transactions (Approved/Rejected) or Bonuses
    $sql = "
        SELECT 
            id, type, amount, status, admin_note, updated_at
        FROM transactions 
        WHERE user_id = ? 
          AND status != 'pending'
          AND updated_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY updated_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format as Notifications
    $notifications = [];
    foreach ($events as $e) {
        $title = "";
        $message = "";
        $icon = "info"; // info, success, error, gift

        if ($e['type'] === 'bonus') {
            $title = "🎁 Bonus Received!";
            $message = "You received " . number_format($e['amount']) . " MMK.";
            $icon = "gift";
        } elseif ($e['type'] === 'deposit') {
            if ($e['status'] === 'approved') {
                $title = "✅ Deposit Success";
                $message = number_format($e['amount']) . " MMK added to your balance.";
                $icon = "success";
            } else {
                $title = "❌ Deposit Rejected";
                $message = "Reason: " . ($e['admin_note'] ?: "Invalid Request");
                $icon = "error";
            }
        } elseif ($e['type'] === 'withdraw') {
            if ($e['status'] === 'approved') {
                $title = "💸 Withdrawal Sent";
                $message = number_format($e['amount']) . " MMK has been sent to you.";
                $icon = "success";
            } else {
                $title = "⚠️ Withdrawal Failed";
                $message = "Funds refunded. Reason: " . ($e['admin_note'] ?: "Generic Error");
                $icon = "error";
            }
        }

        $notifications[] = [
            'id' => $e['id'],
            'title' => $title,
            'message' => $message,
            'time' => date('H:i', strtotime($e['updated_at'])),
            'date' => date('M d', strtotime($e['updated_at'])),
            'type' => $icon
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $notifications,
        'count' => count($notifications)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>