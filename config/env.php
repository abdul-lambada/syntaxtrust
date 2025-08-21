<?php
// Centralized environment configuration
// Adjust values below for production before deploying.

// Detect environment (prefer server env var if set)
$detectedEnv = getenv('APP_ENV') ?: 'production';

$APP_CONFIG = [
    'env' => $detectedEnv,

    // Base path: leading slash, no trailing slash. '' means domain root.
    // Development matches current XAMPP folder structure
    'base_path' => $detectedEnv === 'production' ? '' : '/syntaxtrust',

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
            // TODO: set your production DB credentials here
            'host' => 'localhost',
            'name' => 'syntaxtrust_db',
            'user' => 'root',
            'pass' => '',
            // If your server uses UNIX socket (shown in panel), set it here
            // Example from screenshot: /home/mysql/mysql.sock
            'socket' => '/home/mysql/mysql.sock'
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
