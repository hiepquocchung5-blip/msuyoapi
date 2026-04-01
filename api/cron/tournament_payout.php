<?php
/**
 * TOURNAMENT PAYOUT ENGINE
 * Run this script via Cron Job every 5 minutes:
**/

require_once __DIR__ . '/../api/config/db.php';

echo "Scanning for ended tournaments...\n";

try {
    $pdo->beginTransaction();

    // Find tournaments that have ended but haven't been paid out yet
    $stmt = $pdo->query("
        SELECT * FROM tournaments 
        WHERE status = 'active' 
        AND end_time <= NOW()
    ");
    
    $endedTourneys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($endedTourneys)) {
        echo "No tournaments require payout at this time.\n";
        $pdo->commit();
        exit;
    }

    foreach ($endedTourneys as $tourney) {
        echo "Processing Tournament #{$tourney['id']}: {$tourney['title']}\n";
        
        $prizePool = (float)$tourney['prize_pool'];
        
        // Payout Structure: 1st (50%), 2nd (30%), 3rd (20%)
        $payouts = [
            1 => $prizePool * 0.50,
            2 => $prizePool * 0.30,
            3 => $prizePool * 0.20
        ];

        // Get the Top 3 players from the leaderboard
        $stmtLeaders = $pdo->prepare("
            SELECT user_id, current_score 
            FROM tournament_entries 
            WHERE tournament_id = ? 
            ORDER BY current_score DESC, joined_at ASC 
            LIMIT 3
        ");
        $stmtLeaders->execute([$tourney['id']]);
        $winners = $stmtLeaders->fetchAll(PDO::FETCH_ASSOC);

        $rank = 1;
        foreach ($winners as $winner) {
            $reward = $payouts[$rank];
            $userId = $winner['user_id'];
            
            // Add funds to winner
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$reward, $userId]);
            
            // Log transaction
            $note = "Tournament Reward: {$tourney['title']} (Rank #$rank)";
            $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', ?)")
                ->execute([$userId, $reward, $note]);

            // Broadcast victory to global chat
            $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $username = $stmtUser->fetchColumn();
            
            $chatMsg = "🏆 TOURNAMENT WINNER! {$username} placed #{$rank} in '{$tourney['title']}' and won " . number_format($reward) . " MMK!";
            $pdo->prepare("INSERT INTO chat_messages (type, message, is_pinned) VALUES ('system', ?, 0)")->execute([$chatMsg]);
            
            echo "-> Paid Rank #$rank (User #$userId): $reward MMK\n";
            $rank++;
        }

        // Mark tournament as ended
        $pdo->prepare("UPDATE tournaments SET status = 'ended' WHERE id = ?")->execute([$tourney['id']]);
    }

    $pdo->commit();
    echo "All pending tournaments processed successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
?>