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
    if (!empty($socket)) {
        $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=utf8mb4";
    } else {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    }
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

