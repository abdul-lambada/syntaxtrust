<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Input via query params for easy clickable links
$service_id      = isset($_GET['service_id']) && $_GET['service_id'] !== '' ? (int)$_GET['service_id'] : null;
$pricing_plan_id = isset($_GET['pricing_plan_id']) && $_GET['pricing_plan_id'] !== '' ? (int)$_GET['pricing_plan_id'] : null;
$customer_name   = trim((string)($_GET['customer_name'] ?? ''));
$customer_email  = trim((string)($_GET['customer_email'] ?? ''));
$customer_phone  = trim((string)($_GET['customer_phone'] ?? ''));
$amount_param    = isset($_GET['amount']) ? (string)$_GET['amount'] : '';
$percent_param   = isset($_GET['percent']) ? (string)$_GET['percent'] : '';
$exp_param       = isset($_GET['exp']) ? (string)$_GET['exp'] : '';
$sig_param       = isset($_GET['sig']) ? (string)$_GET['sig'] : '';
$order_number    = isset($_GET['order_number']) ? trim((string)$_GET['order_number']) : '';
$notes           = null;

if (!$service_id) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing required fields (service_id).";
    exit;
}

// Optional HMAC signature verification (if secret exists in settings)
function build_signature(array $params, string $secret): string {
    ksort($params);
    $base = http_build_query($params);
    return hash_hmac('sha256', $base, $secret);
}

$secret = null;
try {
    $s = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $s->execute(['public_link_secret']);
    $secret = $s->fetchColumn() ?: null;
} catch (Throwable $e) { /* ignore */ }

if ($secret) {
    // Require exp and sig
    if ($exp_param === '' || $sig_param === '') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid link (missing signature).';
        exit;
    }
    if ((int)$exp_param < time()) {
        http_response_code(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Link expired.';
        exit;
    }
    $toSign = [
        'service_id' => $service_id,
        'pricing_plan_id' => $pricing_plan_id,
        'exp' => (string)$exp_param,
    ];
    if ($amount_param !== '') { $toSign['amount'] = (string)$amount_param; }
    if ($percent_param !== '') { $toSign['percent'] = (string)$percent_param; }
    $calc = build_signature($toSign, $secret);
    if (!hash_equals($calc, $sig_param)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid signature.';
        exit;
    }
}

$amount = null;
if ($amount_param !== '') {
    $amount = (float)$amount_param;
}

// If percent is provided, derive from plan price
if ($amount === null && $percent_param !== '' && $pricing_plan_id) {
    try {
        $pp = $pdo->prepare('SELECT price FROM pricing_plans WHERE id = ? LIMIT 1');
        $pp->execute([$pricing_plan_id]);
        $row = $pp->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $plan_price = (float)$row['price'];
            $p = (float)$percent_param;
            if ($p > 0 && $plan_price > 0) {
                $amount = round($plan_price * ($p / 100));
                $notes = 'Installment ' . $p . '% of plan price';
            }
        }
    } catch (Throwable $e) { /* ignore */ }
}

// Fallback: if amount still null and plan exists, use full plan price
if ($amount === null && $pricing_plan_id) {
    try {
        $pp = $pdo->prepare('SELECT price FROM pricing_plans WHERE id = ? LIMIT 1');
        $pp->execute([$pricing_plan_id]);
        $row = $pp->fetch(PDO::FETCH_ASSOC);
        if ($row) { $amount = (float)$row['price']; }
    } catch (Throwable $e) { /* ignore */ }
}

if ($amount === null) { $amount = 0.0; }

$intent_number = 'PI-' . date('Ymd') . '-' . str_pad((string)random_int(1,9999), 4, '0', STR_PAD_LEFT);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Compose structured notes JSON for reconciliation
$meta = [];
if ($order_number !== '') { $meta['order_number'] = $order_number; }
if ($percent_param !== '') { $meta['percent'] = (float)$percent_param; }
$meta['kind'] = ($percent_param !== '') ? 'installment' : 'full';
$notes = json_encode($meta, JSON_UNESCAPED_UNICODE);

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
        $notes,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Failed to create payment intent';
    exit;
}

// Render minimal confirmation HTML for user
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rootBase = $scheme . '://' . $host . str_replace('/public/api', '', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));

// Determine label for the selected option
$payLabel = 'Bayar Penuh';
if ($percent_param !== '') {
    $p = (float)$percent_param;
    if ($p == 30.0) $payLabel = 'Cicilan 30%';
    else if ($p == 50.0) $payLabel = 'Cicilan 50%';
    else $payLabel = 'Cicilan ' . rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.') . '%';
}

// Build invoice download link using overrides
$invoice_url = $rootBase . '/public/api/generate_invoice.php?order_number=' . urlencode($order_number)
    . '&amount=' . urlencode((string)$amount)
    . '&note=' . urlencode($payLabel);

// Fetch bank account details from settings (dynamic)
$banks = [];
try {
    if ($pdo instanceof PDO) {
        // Prefer a JSON setting storing an array of banks
        $s1 = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $s1->execute(['payment_banks_json']);
        $json = $s1->fetchColumn();
        if ($json) {
            $arr = json_decode((string)$json, true);
            if (is_array($arr)) {
                foreach ($arr as $b) {
                    $label = trim((string)($b['label'] ?? ($b['bank'] ?? 'Bank')));
                    $accNo = trim((string)($b['account_number'] ?? ''));
                    $accNm = trim((string)($b['account_name'] ?? ''));
                    if ($accNo !== '') { $banks[] = ['label'=>$label, 'number'=>$accNo, 'name'=>$accNm]; }
                }
            }
        }
        // Fallback to specific keys if JSON not provided
        if (empty($banks)) {
            $keys = ['seabank_account_number','seabank_account_name','jago_account_number','jago_account_name'];
            $in = implode(',', array_fill(0, count($keys), '?'));
            $st = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($in)");
            $st->execute($keys);
            $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            $seabank_number = trim((string)($rows['seabank_account_number'] ?? ''));
            $seabank_name   = trim((string)($rows['seabank_account_name'] ?? ''));
            $jago_number    = trim((string)($rows['jago_account_number'] ?? ''));
            $jago_name      = trim((string)($rows['jago_account_name'] ?? ''));
            if ($seabank_number !== '') { $banks[] = ['label'=>'Seabank', 'number'=>$seabank_number, 'name'=>$seabank_name ?: '']; }
            if ($jago_number !== '') { $banks[] = ['label'=>'Bank Jago', 'number'=>$jago_number, 'name'=>$jago_name ?: '']; }
        }
    }
} catch (Throwable $e) { /* ignore */ }

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Intent Dibuat</title>
  <style>
    body{font-family:Arial,sans-serif;margin:2rem;color:#222}
    .card{border:1px solid #e5e7eb;border-radius:8px;padding:1rem;max-width:680px}
    .muted{color:#6b7280}
    .btn{display:inline-block;background:#10b981;color:#fff;padding:.5rem 1rem;border-radius:6px;text-decoration:none}
    .btn:hover{background:#059669}
    code{background:#f3f4f6;padding:2px 4px;border-radius:4px}
    .banks{display:grid;grid-template-columns:1fr;gap:12px;margin-top:12px}
    .bank{border:1px solid #e5e7eb;border-radius:8px;padding:12px}
    .bank h4{margin:0 0 6px 0}
    .btn-secondary{display:inline-block;background:#2563eb;color:#fff;padding:.5rem 1rem;border-radius:6px;text-decoration:none}
    .btn-secondary:hover{background:#1d4ed8}
  </style>
</head>
<body>
  <h1>Payment Intent Berhasil Dibuat</h1>
  <div class="card">
    <p><strong>Nomor Intent:</strong> <code><?= htmlspecialchars($intent_number) ?></code></p>
    <p><strong>Nama:</strong> <?= htmlspecialchars($customer_name) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($customer_email) ?></p>
    <?php if (!empty($customer_phone)): ?><p><strong>Telepon:</strong> <?= htmlspecialchars($customer_phone) ?></p><?php endif; ?>
    <p><strong>Jumlah:</strong> Rp <?= number_format($amount, 0, ',', '.') ?></p>
    <?php if (!empty($notes)): ?><p class="muted">Catatan: <?= htmlspecialchars($notes) ?></p><?php endif; ?>

    <h3>Instruksi Pembayaran</h3>
    <p class="muted">Silakan lakukan transfer sesuai nominal ke salah satu rekening berikut:</p>
    <div class="banks">
      <div class="bank">
        <h4>Seabank</h4>
        <div><strong>No. Rekening:</strong> <?= htmlspecialchars($seabank_number ?: '1234567890') ?></div>
        <div><strong>Nama:</strong> <?= htmlspecialchars($seabank_name ?: 'SyntaxTrust') ?></div>
      </div>
      <div class="bank">
        <h4>Bank Jago</h4>
        <div><strong>No. Rekening:</strong> <?= htmlspecialchars($jago_number ?: '9876543210') ?></div>
        <div><strong>Nama:</strong> <?= htmlspecialchars($jago_name ?: 'SyntaxTrust') ?></div>
      </div>
    </div>

    <p style="margin-top:12px">Setelah transfer, balas pesan WhatsApp kami atau kirim bukti ke email agar bisa kami verifikasi.</p>

    <div style="margin-top:12px">
      <a href="<?= htmlspecialchars($invoice_url) ?>" class="btn-secondary">Unduh Invoice (<?= htmlspecialchars($payLabel) ?>)</a>
      <a href="<?= htmlspecialchars($rootBase) ?>" class="btn" style="margin-left:8px">Kembali ke Beranda</a>
    </div>
  </div>
  <p class="muted" style="margin-top:12px">Tim kami akan meninjau pembayaran Anda. Terima kasih.</p>
</body>
</html>
