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

// Parse JSON body or form
$raw = file_get_contents('php://input');
$payload = [];
if ($raw) {
    $json = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) { $payload = $json; }
}
if (empty($payload)) { $payload = $_POST; }

$customer_name  = trim((string)($payload['customer_name'] ?? ''));
$customer_email = trim((string)($payload['customer_email'] ?? ''));
$customer_phone = trim((string)($payload['customer_phone'] ?? ''));
$service_id     = isset($payload['service_id']) && $payload['service_id'] !== '' ? (int)$payload['service_id'] : null;
$pricing_plan_id= isset($payload['pricing_plan_id']) && $payload['pricing_plan_id'] !== '' ? (int)$payload['pricing_plan_id'] : null;
$project_description = trim((string)($payload['project_description'] ?? ''));
$requirements_input  = $payload['requirements'] ?? '';
// Optional: multiple services list (for message display)
$services_payload = [];
if (isset($payload['services']) && is_array($payload['services'])) {
    foreach ($payload['services'] as $sid) {
        $sid = (int)$sid; if ($sid > 0) { $services_payload[] = $sid; }
    }
}

// Anti-bot checks: honeypot and minimum fill time (3 seconds)
$hp = isset($payload['hp']) ? (string)$payload['hp'] : '';
$form_started_at = isset($payload['form_started_at']) ? (string)$payload['form_started_at'] : '';
if ($hp !== '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid submission']);
    exit;
}
if ($form_started_at !== '') {
    $startedMs = (int)$form_started_at;
    $nowMs = (int)round(microtime(true) * 1000);
    if ($nowMs - $startedMs < 3000) { // less than 3s
        http_response_code(400);
        echo json_encode(['error' => 'Form submitted too quickly']);
        exit;
    }
}

if ($customer_name === '' || $customer_email === '' || !$service_id) {
    http_response_code(422);
    echo json_encode(['error' => 'customer_name, customer_email, and service_id are required']);
    exit;
}

// Derive total amount from pricing plan if provided
$total_amount = null;
if ($pricing_plan_id) {
    try {
        $pp = $pdo->prepare('SELECT price FROM pricing_plans WHERE id = ? LIMIT 1');
        $pp->execute([$pricing_plan_id]);
        $row = $pp->fetch(PDO::FETCH_ASSOC);
        if ($row) { $total_amount = (float)$row['price']; }
    } catch (Throwable $e) { /* ignore */ }
}
if ($total_amount === null) {
    // fallback amount from payload if present
    if (isset($payload['total_amount']) && $payload['total_amount'] !== '') {
        $total_amount = (float)$payload['total_amount'];
    } else {
        $total_amount = 0.0;
    }
}

// Normalize requirements to JSON string
$requirements = '[]';
if (is_array($requirements_input)) {
    $requirements = json_encode($requirements_input, JSON_UNESCAPED_UNICODE);
} else if (is_string($requirements_input)) {
    $trim = trim($requirements_input);
    if ($trim === '') { $requirements = '[]'; }
    else if ($trim[0] === '{' || $trim[0] === '[') { $requirements = $trim; }
    else { $requirements = json_encode($trim, JSON_UNESCAPED_UNICODE); }
}

// Create order
$order_number = 'ORD-' . date('Ymd') . '-' . str_pad((string)random_int(1,9999), 4, '0', STR_PAD_LEFT);
try {
    $stmt = $pdo->prepare('INSERT INTO orders (order_number, user_id, service_id, pricing_plan_id, customer_name, customer_email, customer_phone, project_description, requirements, total_amount, status, payment_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $order_number,
        null,
        $service_id,
        $pricing_plan_id,
        $customer_name,
        $customer_email,
        $customer_phone !== '' ? $customer_phone : null,
        $project_description !== '' ? $project_description : null,
        $requirements,
        $total_amount,
        'pending',
        'unpaid',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create order']);
    exit;
}

// Build absolute base URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$rootBase = $scheme . '://' . $host . str_replace('/public/api', '', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));

$invoice_url = $rootBase . '/public/api/generate_invoice.php?order_number=' . urlencode($order_number);

// Prepare quick payment links (full / 30% / 50%) via GET helper with HMAC signature
// Load public link secret from settings or fallback to random per request (less ideal)
$secret = null;
try {
    $s = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $s->execute(['public_link_secret']);
    $secret = $s->fetchColumn();
} catch (Throwable $e) { /* ignore */ }
if (!$secret) { $secret = bin2hex(random_bytes(16)); }

// Expiry timestamp (48 hours)
$exp = time() + 48 * 3600;

function sign_link(array $params, string $secret): string {
    ksort($params);
    $base = http_build_query($params);
    return hash_hmac('sha256', $base, $secret);
}

// Shortlink helpers
function b64u_encode(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function shortlink(string $rootBase, array $params, string $secret): string {
    // Map to compact keys used by pay_l.php: sid, pid, a, p, e
    $mini = [
        'sid' => $params['service_id'] ?? null,
        'pid' => $params['pricing_plan_id'] ?? null,
        'e'   => (string)($params['exp'] ?? ''),
    ];
    if (isset($params['amount'])) { $mini['a'] = (string)$params['amount']; }
    if (isset($params['percent'])) { $mini['p'] = (string)$params['percent']; }
    if (isset($params['order_number']) && $params['order_number'] !== '') { $mini['o'] = (string)$params['order_number']; }
    // remove nulls
    $mini = array_filter($mini, fn($v) => $v !== null && $v !== '');
    $v = http_build_query($mini);
    $v64 = b64u_encode($v);
    $sig = hash_hmac('sha256', $v64, $secret);
    return $rootBase . '/public/api/pay_l.php?v=' . $v64 . '&s=' . $sig;
}

// Build short links (embed order_number for reconciliation)
$full_url   = shortlink($rootBase, ['service_id'=>$service_id,'pricing_plan_id'=>$pricing_plan_id,'amount'=>(string)$total_amount,'exp'=>$exp,'order_number'=>$order_number], $secret);
$part30_url = shortlink($rootBase, ['service_id'=>$service_id,'pricing_plan_id'=>$pricing_plan_id,'percent'=>'30','exp'=>$exp,'order_number'=>$order_number], $secret);
$part50_url = shortlink($rootBase, ['service_id'=>$service_id,'pricing_plan_id'=>$pricing_plan_id,'percent'=>'50','exp'=>$exp,'order_number'=>$order_number], $secret);

// Normalize phone to international format for WhatsApp
$digits = preg_replace('/\D+/', '', (string)$customer_phone);
if ($digits !== '' && strpos($digits, '0') === 0) { $digits = '62' . substr($digits, 1); }

// Fetch names for service and plan to show in WhatsApp neatly
try {
    if ($service_id) {
        $snameStmt = $pdo->prepare('SELECT name FROM services WHERE id = ?');
        $snameStmt->execute([$service_id]);
        $service_name = (string)($snameStmt->fetchColumn() ?: '');
    }
    if ($pricing_plan_id) {
        $pnameStmt = $pdo->prepare('SELECT name FROM pricing_plans WHERE id = ?');
        $pnameStmt->execute([$pricing_plan_id]);
        $plan_name = (string)($pnameStmt->fetchColumn() ?: '');
    }
    // If multiple services were provided, fetch their names
    $ordered_services = [];
    if (!empty($services_payload)) {
        // Ensure primary service_id is included first
        if ($service_id && !in_array($service_id, $services_payload, true)) {
            array_unshift($services_payload, $service_id);
        }
        $ids = array_values(array_unique(array_filter($services_payload, fn($v)=>$v>0)));
        if (!empty($ids)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $qs = $pdo->prepare("SELECT id, name FROM services WHERE id IN ($in)");
            $qs->execute($ids);
            $rows = $qs->fetchAll(PDO::FETCH_KEY_PAIR); // id => name
            foreach ($ids as $id) {
                if (isset($rows[$id])) { $ordered_services[] = $rows[$id]; }
            }
        }
    }
} catch (Throwable $e) { /* ignore */ }

$brand = 'SyntaxTrust';
try {
    // Try common brand keys in settings
    foreach (['site_title','site_name','company_name'] as $k) {
        $st = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$k]);
        $val = trim((string)($st->fetchColumn() ?: ''));
        if ($val !== '') { $brand = $val; break; }
    }
} catch (Throwable $e) { /* ignore */ }

// Load Fonnte token
$token = null;
try {
    $qs = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $qs->execute(['fonnte_token']);
    $token = $qs->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

if ($token && $digits !== '') {
    $lines = [];
    $lines[] = 'Halo ' . $customer_name . ', terima kasih telah melakukan checkout di ' . $brand . '.';
    $lines[] = 'Pesanan: ' . $order_number;
    if (!empty($service_name)) {
        $svcLine = 'Layanan: ' . $service_name;
        if (!empty($plan_name)) { $svcLine .= ' • Paket: ' . $plan_name; }
        $lines[] = $svcLine;
    }
    if (!empty($ordered_services)) {
        $lines[] = 'Semua layanan yang dipesan:';
        foreach ($ordered_services as $idx => $nm) {
            $lines[] = ($idx+1) . ') ' . $nm;
        }
    }
    $lines[] = 'Total: Rp ' . number_format($total_amount, 0, ',', '.');
    $lines[] = '';
    $lines[] = 'Invoice: ' . $invoice_url;
    $lines[] = '';
    $lines[] = 'Pilih cara bayar:';
    $lines[] = '• Bayar Penuh:';
    $lines[] = $full_url;
    $lines[] = '• Cicilan 30%:';
    $lines[] = $part30_url;
    $lines[] = '• Cicilan 50%:';
    $lines[] = $part50_url;
    $lines[] = '';
    $lines[] = '— ' . $brand;
    $message = implode("\n", $lines);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'target' => $digits,
            'message'=> $message,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $res = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    // best-effort, ignore delivery errors here
}

echo json_encode([
    'success' => true,
    'order_number' => $order_number,
    'invoice_url' => $invoice_url,
    'payment_links' => [
        'full' => $full_url,
        'installment_30' => $part30_url,
        'installment_50' => $part50_url,
    ],
]);
