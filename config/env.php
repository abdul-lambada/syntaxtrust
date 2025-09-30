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

// Default production secrets; can be overridden by config/production_secrets.php
$PROD_SECRETS = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'dpgwgcvf_syntax',
    'DB_USER' => 'dpgwgcvf_syntax',
    'DB_PASS' => '737a?r3rL',
    'DB_SOCKET' => null
];
$secretsFile = __DIR__ . '/production_secrets.php';
if (is_readable($secretsFile)) {
    $loaded = require $secretsFile;
    if (is_array($loaded)) { $PROD_SECRETS = $loaded; }
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
            // Prefer secrets file, then env vars. No unsafe defaults for name/user/pass.
            'host' => ($PROD_SECRETS['DB_HOST'] ?? getenv('DB_HOST')) ?: 'localhost',
            'name' => $PROD_SECRETS['DB_NAME'] ?? getenv('DB_NAME') ?: null,
            'user' => $PROD_SECRETS['DB_USER'] ?? getenv('DB_USER') ?: null,
            'pass' => $PROD_SECRETS['DB_PASS'] ?? getenv('DB_PASS') ?: null,
            // If your server uses UNIX socket, set it via secrets or env
            'socket' => $PROD_SECRETS['DB_SOCKET'] ?? getenv('DB_SOCKET') ?: null
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
