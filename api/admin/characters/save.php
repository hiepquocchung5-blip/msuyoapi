<?php
require_once __DIR__ . '/../../utils/admin_middleware.php'; 

// Staff can update visuals, but maybe restrict pricing to Finance/God
$admin = authenticateAdmin($pdo); 

$data = json_decode(file_get_contents("php://input"));

// Validation
if (!isset($data->char_key) || !isset($data->name) || !isset($data->island_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required character fields']);
    exit;
}

try {
    // Check if character exists (Update vs Insert)
    $stmtCheck = $pdo->prepare("SELECT id FROM characters WHERE char_key = ?");
    $stmtCheck->execute([$data->char_key]);
    $exists = $stmtCheck->fetch();

    if ($exists) {
        // UPDATE
        $sql = "UPDATE characters SET name=?, island_id=?, svg_data=?, price=?, is_premium=? WHERE char_key=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data->name,
            $data->island_id,
            $data->svg_data ?? null, // Long text SVG string
            $data->price ?? 0,
            $data->is_premium ?? 0,
            $data->char_key
        ]);
        $action = "Updated Character: " . $data->char_key;
    } else {
        // INSERT
        $sql = "INSERT INTO characters (char_key, name, island_id, svg_data, price, is_premium) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data->char_key,
            $data->name,
            $data->island_id,
            $data->svg_data ?? null,
            $data->price ?? 0,
            $data->is_premium ?? 0
        ]);
        $action = "Created Character: " . $data->char_key;
    }

    // Audit
    $pdo->prepare("INSERT INTO audit_logs (admin_id, action, target_table) VALUES (?, ?, 'characters')")
        ->execute([$admin['id'], $action]);

    echo json_encode(['status' => 'success', 'message' => $action]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Operation failed: ' . $e->getMessage()]);
}
?>