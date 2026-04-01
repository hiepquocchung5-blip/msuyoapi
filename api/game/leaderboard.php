<?php
require_once __DIR__ . '/../utils/auth_middleware.php'; 

$user = authenticate($pdo);
$userId = $user['id'];

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$type = $_GET['type'] ?? 'balance'; // balance, wins, referrals

try {
    $data = [];
    $myRank = 0;
    
    if ($type === 'balance') {
        // Top Balances
        $sql = "SELECT id, username, active_pet_id, balance as value FROM users ORDER BY balance DESC LIMIT 50";
        // Simple rank query for self (inefficient for huge DBs, okay for MVP)
        $rankSql = "SELECT COUNT(*) + 1 FROM users WHERE balance > (SELECT balance FROM users WHERE id = ?)";
    } 
    elseif ($type === 'wins') {
        // Top Wins (Last 24 Hours) - Sum of wins or single highest win? Let's do Total Winnings Today.
        $sql = "
            SELECT u.id, u.username, u.active_pet_id, SUM(g.win) as value 
            FROM game_logs g 
            JOIN users u ON g.user_id = u.id 
            WHERE g.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY u.id 
            ORDER BY value DESC 
            LIMIT 50
        ";
        // Rank calc is harder for aggregates, skipping exact 'myRank' for performance or doing a simpler query
        $rankSql = "SELECT 0"; // Placeholder
    }
    elseif ($type === 'referrals') {
        // Top Agents
        $sql = "
            SELECT u.id, u.username, u.active_pet_id, COUNT(r.id) as value 
            FROM users u 
            JOIN users r ON u.id = r.referrer_id 
            GROUP BY u.id 
            ORDER BY value DESC 
            LIMIT 50
        ";
        $rankSql = "SELECT 0";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calc My Rank if simple
    if ($type === 'balance') {
        $stmtRank = $pdo->prepare($rankSql);
        $stmtRank->execute([$userId]);
        $myRank = $stmtRank->fetchColumn();
    } else {
        // Check if user is in top 50
        foreach ($data as $i => $row) {
            if ($row['id'] == $userId) {
                $myRank = $i + 1;
                break;
            }
        }
    }

    // Mask Phone Numbers/Usernames for privacy if needed
    foreach ($data as &$row) {
        // Keep username, maybe mask part of it if it's a phone number
        $row['value'] = (float)$row['value'];
        $row['is_me'] = ($row['id'] == $userId);
    }

    echo json_encode([
        'status' => 'success',
        'type' => $type,
        'my_rank' => $myRank ?: '-',
        'list' => $data
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>