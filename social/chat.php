<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 
require_once __DIR__ . '/../utils/security.php';

header("Access-Control-Allow-Origin: https://m.suropara.com");
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: FETCH MESSAGES ---
if ($method === 'GET') {
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    try {
        // Fetch Pinned Messages (Always) OR Recent Messages (Newer than last_id)
        // We order by is_pinned ASC so that when we reverse the array for the frontend, 
        // pinned messages (1) end up at the top if handled visually, or simply included in the feed.
        $sql = "
            SELECT m.id, m.user_id, m.message, m.type, m.is_pinned, m.created_at, 
                   u.username, u.active_pet_id, u.level, u.is_muted
            FROM chat_messages m 
            LEFT JOIN users u ON m.user_id = u.id 
            WHERE (m.id > ? OR m.is_pinned = 1)
            ORDER BY m.is_pinned ASC, m.id DESC 
            LIMIT 50
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reverse array to show chronological order (Oldest -> Newest) for chat UI
        // Pinned messages will appear at the "top" of the fetched batch in chronological context
        echo json_encode(['status' => 'success', 'data' => array_reverse($messages)]);
    } catch (PDOException $e) {
        http_response_code(500); 
        echo json_encode(['error' => 'Chat unavailable']);
    }
    exit;
}

// --- POST: SEND MESSAGE ---
if ($method === 'POST') {
    try {
        $user = authenticate($pdo);
        
        // 1. MUTE CHECK
        // Check if the user has the 'is_muted' flag set to 1
        if (isset($user['is_muted']) && $user['is_muted'] == 1) {
            http_response_code(403);
            echo json_encode(['error' => 'You have been muted by an admin.']);
            exit;
        }

        $userId = $user['id'];

        // 2. Rate Limit (1 message every 2 seconds)
        Security::rateLimit($pdo, $userId, 'chat', 2);
        
        $data = json_decode(file_get_contents("php://input"));
        $msg = trim($data->message ?? '');
        
        // 3. Validation
        if (empty($msg)) {
            http_response_code(400); 
            echo json_encode(['error' => 'Message empty']); 
            exit;
        }
        if (strlen($msg) > 200) {
            http_response_code(400); 
            echo json_encode(['error' => 'Message too long (Max 200 chars)']); 
            exit;
        }
        
        // 4. Profanity Filter
        $badWords = ['scam', 'cheat', 'fake', 'admin', 'badword', 'steal', 'hack'];
        foreach ($badWords as $word) {
            if (stripos($msg, $word) !== false) {
                $msg = str_repeat('*', strlen($msg)); // Censor entire message if bad word found
                break;
            }
        }

        // 5. Insert Message
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, message, type, is_pinned) VALUES (?, ?, 'user', 0)");
        $stmt->execute([$userId, $msg]);
        
        echo json_encode(['status' => 'success']);

    } catch (Exception $e) {
        http_response_code(500); 
        echo json_encode(['error' => 'Send failed: ' . $e->getMessage()]);
    }
    exit;
}
?>