<?php
// Suropara API Entry Point (Health Check)

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Return Server Status
echo json_encode([
    'status' => 'online',
    'message' => 'Suropara API is running',
    'version' => '10.1',
    'environment' => 'production',
    'timestamp' => date('Y-m-d H:i:s'),
    'server_time_zone' => date_default_timezone_get()
]);
?>