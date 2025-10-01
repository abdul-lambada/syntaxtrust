<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../config/database.php';

$brand = 'SyntaxTrust';
try {
    $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key IN ('site_title','site_name','company_name') AND setting_value IS NOT NULL AND setting_value <> '' LIMIT 1");
    $st->execute();
    $bval = $st->fetchColumn();
    if ($bval) { $brand = trim((string)$bval); }
} catch (Throwable $e) { /* ignore */ }

$pageTitle = 'Terima Kasih - ' . $brand;
$currentPage = '';

$order_number = isset($_GET['order_number']) ? trim((string)$_GET['order_number']) : '';
$order = null;
if ($order_number !== '') {
    try {
        $stmt = $pdo->prepare("SELECT o.*, s.name AS service_name, pp.name AS plan_name FROM orders o LEFT JOIN services s ON o.service_id = s.id LEFT JOIN pricing_plans pp ON o.pricing_plan_id = pp.id WHERE o.order_number = ? LIMIT 1");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { $order = null; }
}

// Build base URLs
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$rootBase = $scheme . '://' . $host . str_replace('/public', '', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));
$invoice_url = $rootBase . '/public/api/generate_invoice.php?order_number=' . urlencode($order_number);

// Load public link secret
$secret = null;
try {
    $s = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $s->execute(['public_link_secret']);
    $secret = $s->fetchColumn() ?: null;
} catch (Throwable $e) { /* ignore */ }
// Helper sign function
function tysign(array $params, string $secret): string { ksort($params); return hash_hmac('sha256', http_build_query($params), $secret); }
$exp = time() + 48 * 3600;

$full_url = $part30_url = $part50_url = '#';
$paid_sum = 0.0; $remaining = 0.0; $show_settle = false; $settle_url = '#';
if ($order) {
    // Build shortlinks, consistent with checkout_create_order
    // If order lacks service_id but has pricing_plan_id, derive service_id from the plan
    if ((int)($order['service_id'] ?? 0) <= 0 && !empty($order['pricing_plan_id'])) {
        try {
            if ($pdo instanceof PDO) {
                $ps = $pdo->prepare('SELECT service_id FROM pricing_plans WHERE id = ? LIMIT 1');
                $ps->execute([$order['pricing_plan_id']]);
                $derivedSid = (int)($ps->fetchColumn() ?: 0);
                if ($derivedSid > 0) { $order['service_id'] = $derivedSid; }
            }
        } catch (Throwable $e) { /* ignore */ }
    }
    // Build links to payment_intent_quick; include signature if secret exists
    $baseQuick = $rootBase . '/public/api/payment_intent_quick.php';
    $common = [
      'service_id' => (int)($order['service_id'] ?? 0),
      'pricing_plan_id' => (int)($order['pricing_plan_id'] ?? 0),
      'order_number' => (string)($order['order_number'] ?? ''),
    ];
    // Full payment
    $pFull = $common;
    $pFull['amount'] = (string)$order['total_amount'];
    // 30% installment
    $p30 = $common; $p30['percent'] = '30';
    // 50% installment
    $p50 = $common; $p50['percent'] = '50';

    if ($secret) {
      $pFull['exp'] = (string)$exp; $pFull['sig'] = tysign(['service_id'=>$pFull['service_id'],'pricing_plan_id'=>$pFull['pricing_plan_id'],'exp'=>$pFull['exp'],'amount'=>$pFull['amount']], $secret);
      $p30['exp'] = (string)$exp;  $p30['sig']  = tysign(['service_id'=>$p30['service_id'],'pricing_plan_id'=>$p30['pricing_plan_id'],'exp'=>$p30['exp'],'percent'=>$p30['percent']], $secret);
      $p50['exp'] = (string)$exp;  $p50['sig']  = tysign(['service_id'=>$p50['service_id'],'pricing_plan_id'=>$p50['pricing_plan_id'],'exp'=>$p50['exp'],'percent'=>$p50['percent']], $secret);
    }
    $full_url   = $baseQuick . '?' . http_build_query($pFull);
    $part30_url = $baseQuick . '?' . http_build_query($p30);
    $part50_url = $baseQuick . '?' . http_build_query($p50);

    // Compute paid sum from payment_intents
    try {
        $q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_intents WHERE JSON_EXTRACT(notes, '$.order_number') = ? AND status = 'paid'");
        $q->execute([$order['order_number']]);
        $paid_sum = (float)$q->fetchColumn();
        $remaining = max(0.0, (float)$order['total_amount'] - $paid_sum);
        $show_settle = ($remaining > 0.0 && $paid_sum > 0.0);
        if ($show_settle) {
            $pSettle = $common; $pSettle['amount'] = (string)$remaining;
            if ($secret) {
                $pSettle['exp'] = (string)$exp;
                $pSettle['sig'] = tysign(['service_id'=>$pSettle['service_id'],'pricing_plan_id'=>$pSettle['pricing_plan_id'],'exp'=>$pSettle['exp'],'amount'=>$pSettle['amount']], $secret);
            }
            $settle_url = $baseQuick . '?' . http_build_query($pSettle);
        }
    } catch (Throwable $e) { /* ignore */ }
}

echo renderPageStart($pageTitle, 'Terima kasih, pesanan Anda telah dibuat.', $currentPage);
?>
<main class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-bold mb-2">Terima kasih!</h1>
    <p class="text-gray-600 mb-6">Pesanan Anda telah kami terima di <strong><?= htmlspecialchars($brand) ?></strong>. Silakan cek invoice dan pilih metode pembayaran.</p>

    <?php if ($order): ?>
      <div class="mb-6">
        <div class="text-sm text-gray-600">Nomor Pesanan</div>
        <div class="text-lg font-semibold"><?= htmlspecialchars($order_number) ?></div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="p-4 border rounded">
          <div class="text-sm text-gray-600">Layanan</div>
          <div class="font-medium"><?= htmlspecialchars($order['service_name'] ?? '-') ?></div>
        </div>
        <div class="p-4 border rounded">
          <div class="text-sm text-gray-600">Paket</div>
          <div class="font-medium"><?= htmlspecialchars($order['plan_name'] ?? '-') ?></div>
        </div>
        <div class="p-4 border rounded">
          <div class="text-sm text-gray-600">Total</div>
          <div class="font-semibold">Rp <?= number_format((float)$order['total_amount'], 0, ',', '.') ?></div>
        </div>
        <div class="p-4 border rounded">
          <div class="text-sm text-gray-600">Status</div>
          <div class="font-medium capitalize"><?= htmlspecialchars(str_replace('_',' ', $order['status'])) ?></div>
        </div>
      </div>

      <div class="mb-8">
        <a href="<?= htmlspecialchars($invoice_url) ?>" class="inline-flex items-center bg-gray-100 text-gray-800 px-4 py-2 rounded hover:bg-gray-200">
          <i class="fas fa-file-invoice mr-2"></i> Unduh Invoice <?= htmlspecialchars($brand) ?> (PDF)
        </a>
      </div>

      <h2 class="text-xl font-semibold mb-3">Pilih Pembayaran</h2>
      <div class="space-y-3">
        <a href="<?= htmlspecialchars($full_url) ?>" class="block border rounded p-4 hover:bg-green-50">
          <div class="font-semibold">Bayar Penuh</div>
          <div class="text-sm text-gray-600">Lunasi total pesanan sekarang.</div>
        </a>
        <a href="<?= htmlspecialchars($part30_url) ?>" class="block border rounded p-4 hover:bg-yellow-50">
          <div class="font-semibold">Cicilan 30%</div>
          <div class="text-sm text-gray-600">Bayar 30% dari total sebagai DP.</div>
        </a>
        <a href="<?= htmlspecialchars($part50_url) ?>" class="block border rounded p-4 hover:bg-yellow-50">
          <div class="font-semibold">Cicilan 50%</div>
          <div class="text-sm text-gray-600">Bayar 50% dari total sebagai DP.</div>
        </a>
        <?php if ($show_settle): ?>
        <div class="border rounded p-4 bg-blue-50">
          <div class="font-semibold mb-1">Ringkasan Pembayaran</div>
          <div class="text-sm text-gray-700">Sudah dibayar: <strong>Rp <?= number_format($paid_sum, 0, ',', '.') ?></strong></div>
          <div class="text-sm text-gray-700 mb-3">Sisa: <strong>Rp <?= number_format($remaining, 0, ',', '.') ?></strong></div>
          <a href="<?= htmlspecialchars($settle_url) ?>" class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class="fas fa-credit-card mr-2"></i> Bayar Sisa Rp <?= number_format($remaining, 0, ',', '.') ?>
          </a>
        </div>
        <?php endif; ?>
      </div>

      <?php
        // If fully paid, show testimonial CTA
        $is_paid = false;
        try {
          $q2 = $pdo->prepare("SELECT payment_status FROM orders WHERE order_number = ? LIMIT 1");
          $q2->execute([$order_number]);
          $is_paid = ($q2->fetchColumn() === 'paid');
        } catch (Throwable $e) { $is_paid = false; }
        if ($is_paid):
          $testimonial_url = 'testimonial_submit.php?order_number=' . urlencode($order_number);
      ?>
        <div class="mt-8 p-4 border rounded bg-green-50">
          <div class="font-semibold mb-1">Terima kasih sudah mempercayai <?= htmlspecialchars($brand) ?>!</div>
          <p class="text-sm text-gray-700 mb-3">Bantu kami berkembang dengan memberikan testimoni pengalaman Anda.</p>
          <a href="<?= htmlspecialchars($testimonial_url) ?>" class="inline-flex items-center bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            <i class="fas fa-heart mr-2"></i> Isi Testimoni
          </a>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded">
        Nomor pesanan tidak ditemukan.
      </div>
    <?php endif; ?>

    <div class="mt-8">
      <a href="index.php" class="text-blue-600 hover:underline">Kembali ke Beranda <?= htmlspecialchars($brand) ?></a>
    </div>
  </div>
</main>
<?php echo renderPageEnd(); ?>
