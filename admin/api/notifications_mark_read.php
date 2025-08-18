<?php
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$csrfValid = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
if (!$csrfValid) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

try {
    // Mark all visible (user or global) as read
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE (user_id = :uid OR user_id IS NULL)');
    $stmt->execute([':uid' => $uid]);

    // Fetch new unread count
    $cstmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (user_id = :uid OR user_id IS NULL)');
    $cstmt->execute([':uid' => $uid]);
    $unread = (int)$cstmt->fetchColumn();

    echo json_encode(['ok' => true, 'unread' => $unread]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
