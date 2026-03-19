<?php
// api/social/chat_stream.php
// Production-Ready SSE Endpoint

// 1. Include Database First (Before any output or headers)
require_once __DIR__ . '/../config/db.php';

// 2. Handle CORS & Preflight (In case Nginx doesn't catch it)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Disable all server compression and buffering
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

// 4. STRICT SSE Headers (Forces proxies and browsers to stream)
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Connection: keep-alive');

// 5. Defeat Server/Proxy Buffering (CRITICAL FOR NGINX/APACHE)
header('X-Accel-Buffering: no'); // Disables Nginx buffering
@apache_setenv('no-gzip', 1);    // Disables Apache GZIP

// 6. Core PHP stream settings
set_time_limit(0);
ignore_user_abort(true);

// Clear all levels of output buffering so data flows instantly
while (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

// 7. Streaming Loop
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
$idleSeconds = 0;

// Prepare statements outside the loop for extreme performance
$checkStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) FROM chat_messages");
$fetchStmt = $pdo->prepare("
    SELECT m.id, m.user_id, m.message, m.type, m.is_pinned, m.created_at, 
           u.username, u.active_pet_id, u.level, u.is_muted
    FROM chat_messages m 
    LEFT JOIN users u ON m.user_id = u.id 
    WHERE m.id > ?
    ORDER BY m.id ASC
");

// Send an initial handshake event so the browser knows the stream is alive
echo "event: handshake\ndata: {\"message\":\"Stream established\"}\n\n";
if (ob_get_level() > 0) ob_flush();
flush();

while (true) {
    if (connection_aborted()) {
        break;
    }

    try {
        // Check if new messages exist
        $checkStmt->execute();
        $latestDbId = (int)$checkStmt->fetchColumn();

        if ($latestDbId > $lastId) {
            // Fetch the new data
            $fetchStmt->execute([$lastId]);
            $newMessages = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($newMessages)) {
                $lastId = end($newMessages)['id']; // Update last known ID
                
                // Push standard message event
                echo "data: " . json_encode(['status' => 'success', 'data' => $newMessages]) . "\n\n";
                $idleSeconds = 0; 
            }
        } else {
            // Keep-Alive Ping: Prevents browser and Cloudflare/Proxies from closing idle connections
            $idleSeconds++;
            if ($idleSeconds >= 10) { 
                echo ": keepalive\n\n"; 
                $idleSeconds = 0;
            }
        }

    } catch (PDOException $e) {
        error_log("SSE DB Error: " . $e->getMessage());
        echo "event: error\ndata: {\"message\":\"Database connection issue\"}\n\n";
        sleep(5); // Back off to avoid spamming logs
    }

    // Force push data to browser
    if (ob_get_level() > 0) ob_flush();
    flush();

    // Polling delay
    sleep(1);
}
?>