<?php
require_once __DIR__ . '/env.php';
// Error reporting (hide display in production)
error_reporting(E_ALL);
ini_set('display_errors', app_env() === 'development' ? 1 : 0);

// Database configuration (from env)
$host = app_db('host');
$dbname = app_db('name');
$username = app_db('user');
$password = app_db('pass');
$socket = app_db('socket');

try {
    // Validate required config, especially in production
    if (app_env() === 'production') {
        if (empty($dbname) || empty($username) || $password === null) {
            throw new PDOException('Database credentials are not configured. Set DB_NAME, DB_USER, DB_PASS env vars.');
        }
    }

    if (!empty($socket)) {
        $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=utf8mb4";
    } else {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Uncomment to enable persistent connections if desired
        // PDO::ATTR_PERSISTENT => true,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);
    // Attributes above are set via options
} catch (PDOException $e) {
    // Fail gracefully in production to avoid HTTP 500
    if (!defined('DB_UNAVAILABLE')) {
        define('DB_UNAVAILABLE', true);
    }
    $pdo = null;
    error_log('Database connection failed: ' . $e->getMessage());
    // In development, rethrow to see the error clearly
    if (app_env() === 'development') {
        throw $e;
    }
}

