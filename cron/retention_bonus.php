<?php
// cron/retention_bonus.php
// RUN DAILY: php retention_bonus.php

require_once __DIR__ . '/../api/config/db.php';

echo "Starting Retention Bonus Check...\n";

// 1. Find Inactive Users (7-14 days inactive)
$sql = "
    SELECT id, username, phone 
    FROM users 
    WHERE id NOT IN (SELECT DISTINCT user_id FROM game_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    AND balance < 1000 -- Only if broke
    LIMIT 100
";
$inactive = $pdo->query($sql)->fetchAll();

$count = 0;
foreach ($inactive as $u) {
    try {
        $pdo->beginTransaction();
        
        // Give 2000 MMK "Come Back" Bonus
        $bonus = 2000;
        $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$bonus, $u['id']]);
        
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, status, admin_note) VALUES (?, 'bonus', ?, 'approved', 'Retention Auto-Bonus')")
            ->execute([$u['id'], $bonus]);

        // Send Notification (Mock: In real app, integrate SMS Gateway here)
        // sms_send($u['phone'], "We miss you! Here is 2000 MMK free credit.");
        
        $pdo->commit();
        $count++;
        echo "Bonused User: {$u['username']}\n";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Failed: {$u['id']}\n";
    }
}

echo "Completed. Sent $count bonuses.\n";
?>