<?php
// api/config/db.php

// 1. Load Environment Variables
// Using a simple custom loader for .env file if exists
$envLoaderPath = __DIR__ . '/../utils/env_loader.php';
if (file_exists($envLoaderPath)) {
    require_once $envLoaderPath;
}

// 2. Database Credentials
// Priority: Environment Variable -> Hardcoded Fallback (Your Local Settings)
$host = getenv('DB_HOST') ?: '107.180.113.69';
$db   = getenv('DB_NAME') ?: 'd0eo6547pca3_msuro';
$user = getenv('DB_USER') ?: 'd0eo6547pca3_myanamrsuyo';
// Check specific false condition for password as it can be empty string
$pass = getenv('DB_PASS') ?: 'rZR5q#mj6DWy';
$charset = 'utf8mb4';

// 3. API URL Configuration
// Used for constructing image paths for the frontend/finance portal
$apiUrl = getenv('API_PUBLIC_URL') ;

if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', $apiUrl);
}

// 4. PDO Connection
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Disable persistent connections for better stability in some shared hosting/serverless envs
    PDO::ATTR_PERSISTENT         => false, 
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Security: Log the actual error to server logs, but show generic error to user
    error_log("Database Connection Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'error' => 'Service Unavailable',
        'message' => 'Database connection failed.'
    ]);
    exit;
}

// 5. Session Management
// Starts session if not already active (needed for Admin/Finance portals sharing this config)
if (session_status() === PHP_SESSION_NONE) {
    // Production Security Settings (Uncomment for HTTPS/Production)
    
    if (getenv('APP_ENV') === 'production') {
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => getenv('COOKIE_DOMAIN') ?: '',
            'secure' => true,
            'httponly' => true
        ]);
    }
    
    session_start();
}

// 6. Global Helper Functions
// Utility to sanitize inputs, available globally wherever db.php is included
if (!function_exists('cleanInput')) {
    function cleanInput($data) {
        if (is_array($data)) {
            return array_map('cleanInput', $data);
        }
        return htmlspecialchars(stripslashes(trim($data)));
    }
}
?>