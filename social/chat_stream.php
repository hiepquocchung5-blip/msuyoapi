<?php
// api/social/chat_stream.php
// Production-Ready SSE Endpoint

// 1. Core stream settings
set_time_limit(0);
ignore_user_abort(true);

// CRITICAL FIX 1: These headers MUST be active for SSE to work.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, must-revalidate');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// CRITICAL FIX 2: Include db.php directly. 
// DO NOT use auth_middleware.php here. EventSource cannot send Bearer tokens, 
// so the middleware will reject the connection and force a JSON error!
require_once __DIR__ . '/../config/db.php';

// 3. Get the client's last known message ID
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Clear output buffers
while (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

$idleSeconds = 0;

// Prepare statements outside the loop for performance
// Lightweight check to see if new messages exist at all
$checkSql = "SELECT MAX(id) FROM chat_messages";
$checkStmt = $pdo->prepare($checkSql);

// Heavy query to actually fetch the data
$fetchSql = "
    SELECT m.id, m.user_id, m.message, m.type, m.is_pinned, m.created_at, 
           u.username, u.active_pet_id, u.level, u.is_muted
    FROM chat_messages m 
    LEFT JOIN users u ON m.user_id = u.id 
    WHERE m.id > ?
    ORDER BY m.id ASC
";
$fetchStmt = $pdo->prepare($fetchSql);

while (true) {
    if (connection_aborted()) {
        break;
    }

    try {
        // 4. Optimization: Check the MAX(id) first.
        // This is vastly faster than running a LEFT JOIN query every second.
        $checkStmt->execute();
        $latestDbId = (int)$checkStmt->fetchColumn();

        if ($latestDbId > $lastId) {
            // New messages exist! Now run the heavy fetch query.
            $fetchStmt->execute([$lastId]);
            $newMessages = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newMessages)) {
                $lastId = end($newMessages)['id']; // Update last known ID
                
                // Send standard message event
                echo "data: " . json_encode(['status' => 'success', 'data' => $newMessages]) . "\n\n";
                $idleSeconds = 0; 
            }
        } else {
            // 5. Keep-Alive Ping (Prevents the browser from closing the connection due to inactivity)
            $idleSeconds++;
            if ($idleSeconds >= 15) {
                echo ": keepalive\n\n"; 
                $idleSeconds = 0;
            }
        }

    } catch (PDOException $e) {
        // If the DB connection drops momentarily, don't crash the script.
        // Send an error event to the client and wait a bit before retrying.
        error_log("SSE DB Error: " . $e->getMessage());
        echo "event: error\ndata: " . json_encode(['message' => 'Database connection issue']) . "\n\n";
        sleep(5); // Back off for 5 seconds on error
    }

    // Push data to browser immediately
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();

    // 6. Polling delay
    // If you have >100 concurrent users, change this to sleep(2) or sleep(3) 
    // to prevent overwhelming your database server.
    sleep(1);
}
?>