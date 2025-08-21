<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Simple rate-limiting per IP (best-effort, in-memory per PHP process)
static $lastHit = [];
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now = microtime(true);
if (isset($lastHit[$ip]) && ($now - $lastHit[$ip]) < 0.25) { // 4 req/sec
    http_response_code(429);
    echo json_encode(['error' => 'Too Many Requests']);
    exit;
}
$lastHit[$ip] = $now;

// Parse JSON or form data
$raw = file_get_contents('php://input');
$payload = [];
if ($raw) {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) { $payload = $json; }
}
if (empty($payload)) { $payload = $_POST; }

$customer_name = trim($payload['customer_name'] ?? '');
$customer_email = trim($payload['customer_email'] ?? '');
$customer_phone = trim($payload['customer_phone'] ?? '');
$service_id = isset($payload['service_id']) && $payload['service_id'] !== '' ? (int)$payload['service_id'] : null;
$pricing_plan_id = isset($payload['pricing_plan_id']) && $payload['pricing_plan_id'] !== '' ? (int)$payload['pricing_plan_id'] : null;
$notes = trim($payload['notes'] ?? '');
$amount = null;
if (isset($payload['amount']) && $payload['amount'] !== '') { $amount = (float)$payload['amount']; }

if ($customer_name === '' || $customer_email === '' || !$service_id) {
    http_response_code(422);
    echo json_encode(['error' => 'customer_name, customer_email, and service_id are required']);
    exit;
}

// If plan provided and amount not provided, derive from plan
if ($pricing_plan_id && $amount === null) {
    try {
        $pp = $pdo->prepare('SELECT price FROM pricing_plans WHERE id = ? LIMIT 1');
        $pp->execute([$pricing_plan_id]);
        $row = $pp->fetch(PDO::FETCH_ASSOC);
        if ($row) { $amount = (float)$row['price']; }
    } catch (Throwable $e) { /* ignore */ }
}

$intent_number = 'PI-' . date('Ymd') . '-' . str_pad((string)random_int(1,9999), 4, '0', STR_PAD_LEFT);
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $stmt = $pdo->prepare('INSERT INTO payment_intents (intent_number, service_id, pricing_plan_id, customer_name, customer_email, customer_phone, amount, status, ip_address, user_agent, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $intent_number,
        $service_id,
        $pricing_plan_id,
        $customer_name,
        $customer_email,
        $customer_phone !== '' ? $customer_phone : null,
        $amount,
        'submitted',
        $ip,
        $ua,
        $notes !== '' ? $notes : null,
    ]);

    echo json_encode([
        'success' => true,
        'intent_number' => $intent_number,
        'status' => 'submitted',
        'amount' => $amount,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create payment intent']);
}
