<?php
// ============================================================================
// SUROPARA API - LIVE GLOBAL TICKER (v10.2 Production)
// ============================================================================

require_once __DIR__ . '/../config/db.php';

header("Access-Control-Allow-Origin: https://suropara.com"); 
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate"); 

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // --- 1. FETCH CURRENT JACKPOT (Per Island or Global Highest) ---
    $islandId = isset($_GET['island_id']) ? (int)$_GET['island_id'] : null;

    if ($islandId && $islandId >= 1 && $islandId <= 5) {
        $stmtJP = $pdo->prepare("SELECT current_amount FROM global_jackpots WHERE island_id = ?");
        $stmtJP->execute([$islandId]);
        $jackpot = $stmtJP->fetchColumn();
    } else {
        // Fallback: Just grab the highest jackpot if no island specified
        $stmtJP = $pdo->query("SELECT current_amount FROM global_jackpots ORDER BY current_amount DESC LIMIT 1");
        $jackpot = $stmtJP->fetchColumn();
    }
    
    if ($jackpot === false) $jackpot = 3000000.00;

    // --- 2. FETCH RECENT BIG WINS ---
    $sqlWins = "
        SELECT 
            u.username, 
            g.win as amount, 
            i.name as island_name 
        FROM game_logs g
        JOIN users u ON g.user_id = u.id
        JOIN machines m ON g.machine_id = m.id
        JOIN islands i ON m.island_id = i.id
        WHERE g.win >= (g.bet * 50) 
          AND g.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY g.created_at DESC 
        LIMIT 5
    ";
    
    $stmtWins = $pdo->query($sqlWins);
    $recentWins = $stmtWins->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. FORMAT MESSAGES FOR FRONTEND ---
    $tickerItems = [];
    
    $tickerItems[] = [
        'type' => 'jackpot',
        'text' => "GRAND JACKPOT: " . number_format($jackpot) . " MMK",
        'highlight' => true
    ];

    foreach ($recentWins as $w) {
        $displayUser = htmlspecialchars($w['username']);
        $tickerItems[] = [
            'type' => 'win',
            'text' => "{$displayUser} just won " . number_format($w['amount']) . " MMK in {$w['island_name']}!",
            'highlight' => false
        ];
    }

    if (count($recentWins) < 2) {
        $tickerItems[] = ['type' => 'info', 'text' => "🔥 Kyoto Zen RTP is surging! Secure a machine now.", 'highlight' => false];
        $tickerItems[] = ['type' => 'info', 'text' => "💎 Daily Missions reset at midnight. Don't forget to claim!", 'highlight' => false];
    }

    echo json_encode([
        'status' => 'success',
        'jackpot_amount' => (float)$jackpot,
        'messages' => $tickerItems,
        'timestamp' => time() 
    ]);

} catch (PDOException $e) {
    error_log("Ticker API Error: " . $e->getMessage());
    http_response_code(200); 
    echo json_encode([
        'status' => 'success',
        'jackpot_amount' => 3000000,
        'messages' => [['type' => 'jackpot', 'text' => "GRAND JACKPOT: 3,000,000 MMK", 'highlight' => true]]
    ]);
}
?>