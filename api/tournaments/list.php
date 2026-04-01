<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

try {
    // Fetch Active Tournaments
    // Also check if user has already joined (LEFT JOIN entries)
    $sql = "
        SELECT 
            t.*, 
            e.current_score as my_score,
            e.spins_used as my_spins,
            (SELECT COUNT(*) FROM tournament_entries WHERE tournament_id = t.id) as participant_count
        FROM tournaments t
        LEFT JOIN tournament_entries e ON t.id = e.tournament_id AND e.user_id = ?
        WHERE t.end_time > NOW() AND t.status = 'active'
        ORDER BY t.end_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $tournaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Helper: Format times
    foreach ($tournaments as &$t) {
        $t['entry_fee'] = (float)$t['entry_fee'];
        $t['prize_pool'] = (float)$t['prize_pool'];
        $t['is_joined'] = !is_null($t['my_score']);
        
        // Calculate Time Left (simplified string)
        $diff = strtotime($t['end_time']) - time();
        $hours = floor($diff / 3600);
        $t['time_left'] = $hours . 'h ' . floor(($diff % 3600) / 60) . 'm';
    }

    echo json_encode(['status' => 'success', 'data' => $tournaments]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load tournaments']);
}
?>