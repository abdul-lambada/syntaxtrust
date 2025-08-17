<?php
// Ensure consistent session name
if (session_name() !== 'SYNTRUST_SESS') {
    session_name('SYNTRUST_SESS');
}
// Start session if not started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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

// Redirect to public homepage with a flag
$target = 'index.php?logged_out=1';
if (!headers_sent()) {
    header('Location: ' . $target);
    exit();
}
// Fallback if headers already sent
?><!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>"></head><body>
<script>window.location.href = <?php echo json_encode($target); ?>;</script>
<a href="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>">Continue</a>
</body></html>
