<?php
require_once __DIR__ . '/../../utils/auth_middleware.php'; 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    // Check if user authenticated (optional, depends if you want public events visible)
    // $user = authenticate($pdo); 

    // Fetch Currently Active Events
    $sql = "
        SELECT title, type, multiplier, target_island_id, end_time
        FROM marketing_events
        WHERE is_active = 1 
          AND start_time <= NOW() 
          AND end_time > NOW()
    ";
    
    $stmt = $pdo->query($sql);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate time remaining in seconds for the earliest ending event
    $nextEnd = 0;
    foreach($events as $ev) {
        $end = strtotime($ev['end_time']);
        if ($nextEnd == 0 || $end < $nextEnd) {
            $nextEnd = $end;
        }
    }
    $ttl = max(0, $nextEnd - time());

    echo json_encode([
        'status' => 'success',
        'events' => $events,
        'count' => count($events),
        'ttl_seconds' => $ttl
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB Error']);
}
?>