<?php
// Centralized environment configuration
// Adjust values below for production before deploying.

// Detect environment (prefer explicit env var; otherwise: localhost => development, real domain => production)
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$envFromServer = getenv('APP_ENV');
if ($envFromServer) {
    $detectedEnv = strtolower($envFromServer) === 'production' ? 'production' : 'development';
} else {
    $isLocal = preg_match('/^(localhost|127\.0\.0\.1)(:\\d+)?$/i', $host) || empty($host);
    $detectedEnv = $isLocal ? 'development' : 'production';
}

$APP_CONFIG = [
    'env' => $detectedEnv,

    // Base path: leading slash, no trailing slash. '' means domain root.
    // Development matches current XAMPP folder structure
    'base_path' => $detectedEnv === 'development' ? '/syntaxtrust' : '',

    // Database credentials per environment
    'db' => [
        'development' => [
            'host' => 'localhost',
            'name' => 'syntaxtrust_db',
            'user' => 'root',
            'pass' => '',
            'socket' => null
        ],
        'production' => [
            // Use environment variables from hosting panel. No unsafe defaults.
            // Ensure DB_HOST, DB_NAME, DB_USER, DB_PASS are set in production.
            'host' => getenv('DB_HOST') ?: 'localhost',
            'name' => getenv('DB_NAME') ?: null,
            'user' => getenv('DB_USER') ?: null,
            'pass' => getenv('DB_PASS') ?: null,
            // If your server uses UNIX socket, set it via env DB_SOCKET or leave null
            // Common paths: /var/lib/mysql/mysql.sock (cPanel), custom path if provided
            'socket' => getenv('DB_SOCKET') ?: null
        ]
    ],

    // CORS allowed origins. Keep empty in production unless needed.
    'cors_origins' => [
        'development' => [
            'http://localhost:8080',
            'http://localhost:8081'
        ],
        'production' => [
            'https://syntaxtrust.my.id'
        ]
    ]
];

// Helper getters
function app_env() { global $APP_CONFIG; return $APP_CONFIG['env'] ?? 'development'; }
function app_base_path() { global $APP_CONFIG; return $APP_CONFIG['base_path'] ?? ''; }
function app_db($key) {
    global $APP_CONFIG; $env = app_env();
    $db = $APP_CONFIG['db'][$env] ?? $APP_CONFIG['db']['development'];
    return $db[$key] ?? null;
}
function app_cors_origins() {
    global $APP_CONFIG; $env = app_env();
    $all = $APP_CONFIG['cors_origins'] ?? [];
    return $all[$env] ?? [];
}
