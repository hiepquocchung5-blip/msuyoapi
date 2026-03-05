<?php
// ============================================================================
// SUROPARA API - LIVE GLOBAL TICKER (v10.1 Production)
// ============================================================================
// Purpose: Provides live data (Jackpot, Recent Big Wins) for the frontend lobby.
// Security: Public endpoint. High frequency access expected. Optimize heavily.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/auth_middleware.php'; 

// --- 1. CORS & HEADERS ---
$allowedOrigin = "https://suropara.com";
header("Access-Control-Allow-Origin: $allowedOrigin"); // Restrict to your domain in production via Nginx/Apache if preferred
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate"); // Prevent caching of live data

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // --- 2. FETCH CURRENT JACKPOT ---
    // Read directly from the global_jackpots table
    $stmtJP = $pdo->query("SELECT current_amount FROM global_jackpots WHERE name = 'GRAND SURO JACKPOT'");
    $jackpot = $stmtJP->fetchColumn();
    
    // Fallback if table is empty
    if ($jackpot === false) {
        $jackpot = 3000000.00;
    }

    // --- 3. FETCH RECENT BIG WINS ---
    // Optimized Query: Only look at the last 24 hours to keep the index scan small.
    // "Big Win" defined here as winning 50x the bet amount or more.
    // Limits to 5 results to keep the payload very small.
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

    // --- 4. FORMAT MESSAGES FOR FRONTEND ---
    $tickerItems = [];
    
    // Primary item is always the Jackpot
    $tickerItems[] = [
        'type' => 'jackpot',
        'text' => "GRAND JACKPOT: " . number_format($jackpot) . " MMK",
        'highlight' => true
    ];

    // Append actual user wins
    foreach ($recentWins as $w) {
        // Mask part of the username for privacy if desired (e.g., User99***)
        $displayUser = htmlspecialchars($w['username']);
        
        $tickerItems[] = [
            'type' => 'win',
            'text' => "{$displayUser} just won " . number_format($w['amount']) . " MMK in {$w['island_name']}!",
            'highlight' => false
        ];
    }

    // --- 5. FALLBACK / HYPE MESSAGES ---
    // If the server hasn't had many big wins recently, inject hype messages 
    // to keep the ticker moving and looking active.
    if (count($recentWins) < 2) {
        $tickerItems[] = [
            'type' => 'info', 
            'text' => "🔥 Kyoto Zen RTP is surging! Secure a machine now.",
            'highlight' => false
        ];
        $tickerItems[] = [
            'type' => 'info', 
            'text' => "💎 Daily Missions reset at midnight. Don't forget to claim!",
            'highlight' => false
        ];
    }

    // --- 6. OUTPUT PAYLOAD ---
    echo json_encode([
        'status' => 'success',
        'jackpot_amount' => (float)$jackpot,
        'messages' => $tickerItems,
        'timestamp' => time() // Useful if the frontend wants to track lag
    ]);

} catch (PDOException $e) {
    // Fail silently with generic fallback data to prevent breaking the frontend UI
    error_log("Ticker API Error: " . $e->getMessage());
    
    http_response_code(200); // Send 200 so UI doesn't crash, just show default
    echo json_encode([
        'status' => 'success',
        'jackpot_amount' => 3000000,
        'messages' => [
            ['type' => 'jackpot', 'text' => "GRAND JACKPOT: 3,000,000 MMK", 'highlight' => true],
            ['type' => 'info', 'text' => "Welcome to Suropara Casino!", 'highlight' => false]
        ]
    ]);
}
?>