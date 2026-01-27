<?php
// Public endpoint - No auth required for the ticker (usually) to attract users
// But if you want strict security, uncomment the auth line.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/auth_middleware.php'; 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

try {
    // 1. Get Current Jackpot
    $stmtJP = $pdo->query("SELECT current_amount FROM global_jackpots WHERE name = 'GRAND SURO JACKPOT'");
    $jackpot = $stmtJP->fetchColumn() ?: 5000000;

    // 2. Get Recent Big Wins (Last 10 wins > 50x bet)
    // We join with islands to show location
    $sqlWins = "
        SELECT u.username, g.win, i.name as island_name 
        FROM game_logs g
        JOIN users u ON g.user_id = u.id
        JOIN machines m ON g.machine_id = m.id
        JOIN islands i ON m.island_id = i.id
        WHERE g.win > (g.bet * 50) 
        ORDER BY g.created_at DESC 
        LIMIT 5
    ";
    $recentWins = $pdo->query($sqlWins)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format for Ticker
    $tickerItems = [];
    
    // Always show Jackpot first
    $tickerItems[] = [
        'type' => 'jackpot',
        'text' => "GRAND JACKPOT: " . number_format($jackpot) . " MMK",
        'highlight' => true
    ];

    // Add wins
    foreach ($recentWins as $w) {
        $tickerItems[] = [
            'type' => 'win',
            'text' => "{$w['username']} just won " . number_format($w['win']) . " in {$w['island_name']}!",
            'highlight' => false
        ];
    }

    // Add generic hype if quiet
    if (count($recentWins) < 3) {
        $tickerItems[] = ['type' => 'info', 'text' => "🔥 Inferna Atoll RTP is surging! Play Now!"];
        $tickerItems[] = ['type' => 'info', 'text' => "💎 New Mystery Bonuses added to all machines."];
    }

    echo json_encode([
        'status' => 'success',
        'jackpot_amount' => (float)$jackpot,
        'messages' => $tickerItems
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ticker unavailable']);
}
?>