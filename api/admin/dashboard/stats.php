<?php
require_once __DIR__ . '/../../utils/admin_middleware.php'; 

$admin = authenticateAdmin($pdo);

try {
    // 1. Total Players
    $players = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];

    // 2. Online/Active Machines
    $activeMachines = $pdo->query("SELECT COUNT(*) as count FROM machines WHERE status = 'occupied'")->fetch()['count'];

    // 3. Financials (Total In/Out) - GGR Calculation
    // Total Deposited
    $deposits = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type='deposit' AND status='approved'")->fetch()['total'] ?? 0;
    // Total Withdrawn
    $withdrawals = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type='withdraw' AND status='approved'")->fetch()['total'] ?? 0;
    // Total Bet (Volume)
    $volume = $pdo->query("SELECT SUM(bet) as total FROM game_logs")->fetch()['total'] ?? 0;

    // 4. Pending Requests (Action Items)
    $pendingTx = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE status='pending'")->fetch()['count'];

    echo json_encode([
        'status' => 'success',
        'data' => [
            'total_users' => $players,
            'active_machines' => $activeMachines,
            'financials' => [
                'total_deposits' => (float)$deposits,
                'total_withdrawals' => (float)$withdrawals,
                'net_revenue' => $deposits - $withdrawals,
                'bet_volume' => (float)$volume
            ],
            'pending_tasks' => $pendingTx
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Stats retrieval failed']);
}
?>