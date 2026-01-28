<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Suropara API – Production Entry Point
|--------------------------------------------------------------------------
| Purpose:
| - Health check
| - Load balancer probe
| - Root API availability verification
|--------------------------------------------------------------------------
*/

// ----------------------------
// CORS (frontend only)
// ----------------------------
header('Access-Control-Allow-Origin: https://m.suropara.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// ----------------------------
// Preflight
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ----------------------------
// Health response (NO AUTH)
// ----------------------------
http_response_code(200);

echo json_encode([
    'status' => 'ok',
    'service' => 'suropara-api',
    'environment' => 'production',
    'timestamp' => gmdate('c')
]);
?>