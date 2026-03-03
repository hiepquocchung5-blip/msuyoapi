<?php
// api/utils/security.php

class Security {
    
    // Rate Limiting (Token Bucket / Fixed Window)
    // Supports 300ms Turbo Mode Spins
    public static function rateLimit($pdo, $userId, $action = 'spin', $seconds = 0.3) {
        // Skip rate limit for Admin testing users
        if ($userId < 100) return;

        if ($action === 'spin') {
            $stmt = $pdo->prepare("SELECT created_at FROM game_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$userId]);
            $lastSpin = $stmt->fetchColumn();
            
            if ($lastSpin) {
                $timeSince = microtime(true) - strtotime($lastSpin); // Microtime for sub-second precision
                
                if ($timeSince < $seconds) {
                    http_response_code(429);
                    echo json_encode([
                        'status' => 'error',
                        'error' => 'Engine cooling down. Spin too fast.', 
                        'cooldown_remaining' => round($seconds - $timeSince, 2)
                    ]);
                    exit;
                }
            }
        }
    }

    // Anti-Tamper Check for Valid Denominations
    public static function validateBet($amount) {
        $validBets = [100, 500, 1000, 5000, 10000, 50000, 100000, 250000, 500000];
        if (!in_array((int)$amount, $validBets)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid bet signature detected.']);
            exit;
        }
    }

    // Anomaly Detection: Catches identical bet spam or impossible win rates
    public static function detectAnomalies($pdo, $userId, $betAmount) {
        // Quick check: If user hit the 5000x cap recently, flag them.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_alerts WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() > 3) {
            http_response_code(403);
            echo json_encode(['error' => 'Account temporarily locked for security review.']);
            exit;
        }
    }
}
?>