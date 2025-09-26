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
    function tysign_short(string $s, string $secret): string { return hash_hmac('sha256', $s, $secret); }
    function b64u_enc_thankyou(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

    $mini = function(array $p): string {
        $p = array_filter($p, fn($v)=>$v!==null && $v!=='');
        ksort($p);
        return http_build_query($p);
    };
    $make_short = function(array $p) use ($secret, $mini, $rootBase) {
        $v = b64u_enc_thankyou($mini($p));
        $s = $secret ? tysign_short($v, $secret) : '';
        return $rootBase . '/public/api/pay_l.php?v=' . $v . ($secret ? ('&s=' . $s) : '');
    };

    $full_url = $make_short(['sid'=>$order['service_id'], 'pid'=>$order['pricing_plan_id'], 'a'=>(string)$order['total_amount'], 'e'=>(string)$exp, 'o'=>$order['order_number']]);
    $part30_url = $make_short(['sid'=>$order['service_id'], 'pid'=>$order['pricing_plan_id'], 'p'=>'30', 'e'=>(string)$exp, 'o'=>$order['order_number']]);
    $part50_url = $make_short(['sid'=>$order['service_id'], 'pid'=>$order['pricing_plan_id'], 'p'=>'50', 'e'=>(string)$exp, 'o'=>$order['order_number']]);

    // Compute paid sum from payment_intents
    try {
        $q = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payment_intents WHERE JSON_EXTRACT(notes, '$.order_number') = ? AND status = 'paid'");
        $q->execute([$order['order_number']]);
        $paid_sum = (float)$q->fetchColumn();
        $remaining = max(0.0, (float)$order['total_amount'] - $paid_sum);
        $show_settle = ($remaining > 0.0 && $paid_sum > 0.0);
        if ($show_settle) {
            $settle_url = $make_short(['sid'=>$order['service_id'], 'pid'=>$order['pricing_plan_id'], 'a'=>(string)$remaining, 'e'=>(string)$exp, 'o'=>$order['order_number']]);
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
