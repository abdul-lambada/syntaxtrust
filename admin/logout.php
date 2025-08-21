<?php
// Use the same session name as configured
if (session_name() !== 'SYNTRUST_SESS') {
    session_name('SYNTRUST_SESS');
}
// Start session if not started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// DB connection for token revocation
require_once __DIR__ . '/../config/database.php';

// Revoke remember-me token if present
if (!empty($_COOKIE['SYNTRUST_REMEMBER'])) {
    $raw = $_COOKIE['SYNTRUST_REMEMBER'];
    if (strpos($raw, ':') !== false) {
        list($selector, $validator) = explode(':', $raw, 2);
        if ($selector) {
            try {
                $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
                $stmt->execute([$selector]);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
    // Delete remember cookie
    setcookie('SYNTRUST_REMEMBER', '', [
        'expires' => time() - 3600,
        'path' => '/syntaxtrust',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Clear all session variables
$_SESSION = [];
session_unset();

// Delete the session cookie as well
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    // Clear cookie with original params
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    // Also clear cookie on root path to be safe
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Close session after destroy (do not start a new one here)
session_write_close();

// Prevent back button from showing cached authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

// Redirect to login page with a message (path under public/)
$target = '/syntaxtrust/public/login.php?logged_out=1';
if (!headers_sent()) {
    header('Location: ' . $target);
    exit();
}
// Fallback if headers already sent
echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></head><body>';
echo '<script>window.location.href = ' . json_encode($target) . ';</script>';
echo '<a href="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">Continue</a>';
echo '</body></html>';
exit();
?>
