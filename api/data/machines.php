<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);

// specific island ID required
if (!isset($_GET['island_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Island ID required']);
    exit;
}

$islandId = (int)$_GET['island_id'];

try {
    // Fetch all 100 machines for this island
    // We fetch stats (laps/payout) to display on the cabinet digital readout
    $sql = "SELECT 
                id, 
                machine_number, 
                status, 
                total_laps, 
                total_payout, 
                paint_skin, 
                sticker_char_id 
            FROM machines 
            WHERE island_id = ? 
            ORDER BY machine_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$islandId]);
    $machines = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'island_id' => $islandId,
        'count' => count($machines),
        'machines' => $machines
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load machine hall']);
}
?>