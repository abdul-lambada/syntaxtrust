<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

// Shortlink resolver for payment links
// Usage: /public/api/pay_l.php?v=BASE64URL(payload)&s=HMAC
// payload (query-string style minimal):
//  sid=1&pid=2&a=299000&e=TIMESTAMP[&o=ORD-...]
//  or   sid=1&pid=2&p=30&e=TIMESTAMP[&o=ORD-...]

function b64u_decode(string $s): string {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) { $s .= str_repeat('=', 4 - $pad); }
    return base64_decode($s) ?: '';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') { http_response_code(405); echo 'Method Not Allowed'; exit; }

$v = isset($_GET['v']) ? (string)$_GET['v'] : '';
$s = isset($_GET['s']) ? (string)$_GET['s'] : '';
if ($v === '' || $s === '') { http_response_code(400); echo 'Bad Request'; exit; }

// Load secret
$secret = null;
try {
    $st = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $st->execute(['public_link_secret']);
    $secret = $st->fetchColumn() ?: null;
} catch (Throwable $e) { /* ignore */ }
if (!$secret) { http_response_code(500); echo 'Shortlink not configured'; exit; }

$calc = hash_hmac('sha256', $v, $secret);
if (strncmp($calc, $s, strlen($s)) !== 0) { http_response_code(400); echo 'Invalid signature'; exit; }

$payload = [];
parse_str(b64u_decode($v), $payload);
$sid = isset($payload['sid']) ? (int)$payload['sid'] : 0;
$pid = isset($payload['pid']) && $payload['pid'] !== '' ? (int)$payload['pid'] : null;
$amount = isset($payload['a']) ? (string)$payload['a'] : '';
$percent = isset($payload['p']) ? (string)$payload['p'] : '';
$exp = isset($payload['e']) ? (string)$payload['e'] : '';
$ord = isset($payload['o']) ? (string)$payload['o'] : '';

if ($sid <= 0 || $exp === '') { http_response_code(400); echo 'Invalid link'; exit; }
if ((int)$exp < time()) { http_response_code(410); echo 'Link expired'; exit; }

// Build canonical params for payment_intent_quick and sign them
function build_signature(array $params, string $secret): string {
    ksort($params);
    return hash_hmac('sha256', http_build_query($params), $secret);
}

$q = [
    'service_id' => $sid,
    'pricing_plan_id' => $pid,
    'exp' => $exp,
];
if ($amount !== '') { $q['amount'] = $amount; }
if ($percent !== '') { $q['percent'] = $percent; }
$ordParam = $ord !== '' ? $ord : null;
if ($ordParam) { $q['order_number'] = $ordParam; }
$q['sig'] = build_signature($q, $secret);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $scheme . '://' . $host . str_replace('/public/api', '', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));

$target = $base . '/public/api/payment_intent_quick.php?' . http_build_query($q);
header('Location: ' . $target, true, 302);
exit;
