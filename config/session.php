<?php
// Allow requests from the frontend development server (dynamic based on Origin)
$allowed_origins = [
    'http://localhost:8080',
    'http://localhost:8081',
];
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit();
}

// Session configuration - must be called before session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Session name
session_name('SYNTRUST_SESS');

// Start session
session_start();

// Regenerate session ID for security
if (!isset($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

// Session timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    session_start();
}

// Include database to support remember-me auto login
require_once __DIR__ . '/database.php';

// Auto-login via remember-me cookie if session empty
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['SYNTRUST_REMEMBER'])) {
    $raw = $_COOKIE['SYNTRUST_REMEMBER'];
    if (strpos($raw, ':') !== false) {
        list($selector, $validator) = explode(':', $raw, 2);
        if ($selector && $validator) {
            try {
                $stmt = $pdo->prepare('SELECT rt.user_id, rt.validator_hash, rt.expires_at, u.username, u.email, u.full_name FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.selector = ? LIMIT 1');
                $stmt->execute([$selector]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && strtotime($row['expires_at']) > time()) {
                    if (hash_equals($row['validator_hash'], hash('sha256', $validator))) {
                        // Valid token -> log user in
                        $_SESSION['user_id'] = (int)$row['user_id'];
                        $_SESSION['user_name'] = $row['full_name'];
                        $_SESSION['user_username'] = $row['username'];
                        $_SESSION['user_email'] = $row['email'];
                    }
                } else {
                    // Expired -> cleanup
                    $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$selector]);
                }
            } catch (Throwable $e) {
                // fail silently
            }
        }
    }
}

$_SESSION['LAST_ACTIVITY'] = time();

// Prevent caching of authenticated pages so back button won't expose content after logout
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
}
?>
