<?php
// api/utils/security.php

class Security {
    
    // Rate Limiting (Token Bucket / Fixed Window)
    // Updated: Default spin limit lowered to 0.8s for better gameplay flow
    public static function rateLimit($pdo, $userId, $action = 'spin', $seconds = 0.8) {
        // Skip rate limit for Admin testing users (Optional: IDs < 100)
        if ($userId < 100) return;

        if ($action === 'spin') {
            // Check last spin time
            $stmt = $pdo->prepare("SELECT created_at FROM game_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$userId]);
            $lastSpin = $stmt->fetchColumn();
            
            if ($lastSpin) {
                // Use microtime calculation if DB supports it, otherwise standard seconds
                $timeSince = time() - strtotime($lastSpin);
                
                // Block if trying to spin faster than the cooldown
                if ($timeSince < $seconds) {
                    http_response_code(429); // Too Many Requests
                    echo json_encode([
                        'status' => 'error',
                        'error' => 'Spinning too fast', 
                        'cooldown_remaining' => $seconds - $timeSince
                    ]);
                    exit;
                }
            }
        }
    }

    // Anti-Tamper Check
    // Ensures bet amounts match valid denominations
    public static function validateBet($amount) {
        $validBets = [200, 500, 1000, 2000, 3000, 4000, 5000, 10000, 20000, 50000, 100000, 500000, 900000];
        if (!in_array((int)$amount, $validBets)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid bet amount detected']);
            exit;
        }
    }
}
?>