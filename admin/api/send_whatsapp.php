<?php
require_once __DIR__ . '/../../config/session.php';
header('Content-Type: application/json');

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate method and authentication
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'Unauthorized']);
    exit;
}

// CSRF check
$csrfValid = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
if (!$csrfValid) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Extract inputs and auditing context
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
$phone   = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
$message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
$adminUserId = $_SESSION['user_id'] ?? null;
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($phone === '' || $message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'Phone and message are required']);
    exit;
}

// Normalize phone number to international format (default Indonesia 62 if leading 0)
$digits = preg_replace('/\D+/', '', $phone);
if ($digits === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'Invalid phone number']);
    exit;
}
if (strpos($digits, '0') === 0) {
    $digits = '62' . substr($digits, 1);
}

// Try fetch order number for logging (optional)
$orderNumber = null;
if ($orderId) {
    try {
        $q = $pdo->prepare('SELECT order_number FROM orders WHERE id = ? LIMIT 1');
        $q->execute([$orderId]);
        $orderNumber = $q->fetchColumn() ?: null;
    } catch (Throwable $e) { /* ignore */ }
}

// Load Fonnte token from settings
try {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute(['fonnte_token']);
    $token = $stmt->fetchColumn();
    if (!$token) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'success' => false, 'error' => 'Fonnte token is not configured. Please add setting "fonnte_token" in Admin > Fonnte Integration.']);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'Server error while loading configuration']);
    exit;
}

// Send message via Fonnte API
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.fonnte.com/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'target'  => $digits,
        'message' => $message,
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $token,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 20,
]);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$success = false;
$payload = null;

if ($response === false) {
    // Log notification on failure
    try {
        $n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)');
        $n_user = $adminUserId;
        $n_title = 'WhatsApp send failed';
        $msgText = 'Failed to connect to WhatsApp gateway';
        if ($orderNumber) { $msgText .= ' for order ' . $orderNumber; }
        $msgText .= '. Target: ' . $digits;
        $msgText .= ' | Admin: ' . ($adminUserId ?? 'unknown') . ' | IP: ' . $clientIp . ' | User Agent: ' . $userAgent;
        $n_url = $orderNumber ? ('manage_orders.php?search=' . urlencode($orderNumber)) : null;
        $n->execute([$n_user, $n_title, $msgText, 'warning', $n_url]);
    } catch (Throwable $e2) { /* ignore notification errors */ }
    
    http_response_code(502);
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'Failed to connect to WhatsApp gateway', 'detail' => $curlErr]);
    exit;
}

$decoded = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    $payload = $decoded;
} else {
    $payload = ['raw' => $response];
}

$success = ($httpCode >= 200 && $httpCode < 300);

// Log notification
try {
    $n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)');
    $n_user = $adminUserId;
    if ($success) {
        $n_title = 'WhatsApp sent';
        $n_type = 'success';
    } else {
        $n_title = 'WhatsApp gateway error';
        $n_type = 'warning';
    }
    $msgText = ($success ? 'Message sent' : 'Message not sent (gateway error)');
    if ($orderNumber) { $msgText .= ' for order ' . $orderNumber; }
    $msgText .= '. Target: ' . $digits . '. HTTP ' . $httpCode;
    $msgText .= ' | Admin: ' . ($adminUserId ?? 'unknown') . ' | IP: ' . $clientIp . ' | User Agent: ' . $userAgent;
    $n_url = $orderNumber ? ('manage_orders.php?search=' . urlencode($orderNumber)) : null;
    $n->execute([$n_user, $n_title, $msgText, $n_type, $n_url]);
} catch (Throwable $e2) { /* ignore notification failures */ }

if ($success) {
    echo json_encode([
        'ok'       => true,
        'success'  => true,
        'gateway'  => $payload,
        'httpCode' => $httpCode,
    ]);
} else {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'ok'       => false,
        'success'  => false,
        'error'    => 'Gateway error',
        'gateway'  => $payload,
        'httpCode' => $httpCode,
    ]);
}