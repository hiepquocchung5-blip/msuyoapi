<?php
// api/social/chat_stream.php
// Server-Sent Events (SSE) Endpoint for Real-time Chat

require_once __DIR__ . '/../utils/auth_middleware.php'; 

// SSE Headers are different from standard JSON headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: https://suropara.com'); // Or your specific domain

// We don't necessarily need strict auth for viewing, but let's keep it secure if needed.
// $user = authenticate($pdo); // Optional: comment out if chat viewing is public

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Turn off output buffering to send data immediately
if (ob_get_level()) ob_end_clean();

// Infinite loop to keep connection open
while (true) {
    // 1. Check for new messages since the client's last known ID
    $sql = "
        SELECT m.id, m.user_id, m.message, m.type, m.is_pinned, m.created_at, 
               u.username, u.active_pet_id, u.level, u.is_muted
        FROM chat_messages m 
        LEFT JOIN users u ON m.user_id = u.id 
        WHERE m.id > ?
        ORDER BY m.id ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$lastId]);
    $newMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. If new messages exist, push them to the client
    if (!empty($newMessages)) {
        // Update lastId so we don't send these again next loop
        $lastId = end($newMessages)['id'];
        
        // SSE format requires "data: {json}\n\n"
        echo "data: " . json_encode(['status' => 'success', 'data' => $newMessages]) . "\n\n";
        
        // Force PHP to flush the buffer and send to the browser immediately
        flush();
    }

    // 3. Sleep for 1 second before checking DB again.
    // This is vastly better than the client making a full HTTP request every 5 seconds.
    sleep(1);
    
    // Safety check: if the client closed the connection, kill the script
    if (connection_aborted()) break;
}
?>