<?php
// Simple .env parser for PHP without Composer
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments (#) and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue; 
        }

        // Validate line contains an equals sign before exploding
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                // Safely check if putenv is allowed on the server
                if (function_exists('putenv')) {
                    putenv(sprintf('%s=%s', $name, $value));
                }
                // Always set these, as they are what PHP mostly uses anyway
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load from project root (assuming api/utils/ is 1 or 2 levels deep from root depending on your setup)
$envPath = __DIR__ . '/../.env';
loadEnv($envPath);
?>