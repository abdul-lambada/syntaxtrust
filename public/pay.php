<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'Pembayaran';
$currentPage = 'pay';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $method === 'POST' ? $_POST : $_GET;

$order_number = trim($input['order_number'] ?? '');
$mode = strtolower(trim($input['mode'] ?? 'full'));
$percent = isset($input['percent']) ? (int)$input['percent'] : 50;
$percent = max(10, min(90, $percent)); // clamp 10%-90%

$order = null;
$error = '';
$intent_success = false;
$intent_number = '';
$amount_to_pay = 0.00;

// Fetch order by order_number
if ($order_number !== '') {
    try {
        $stmt = $pdo->prepare("SELECT o.*, s.name as service_name, pp.name as plan_name, pp.price as plan_price FROM orders o LEFT JOIN services s ON o.service_id = s.id LEFT JOIN pricing_plans pp ON o.pricing_plan_id = pp.id WHERE o.order_number = ? LIMIT 1");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$order) { $error = 'Pesanan tidak ditemukan.'; }
    } catch (Throwable $e) {
        $error = 'Terjadi kesalahan saat mengambil data pesanan.';
    }
} else {
    $error = 'Nomor pesanan diperlukan.';
}

// Determine amount to pay
if ($order) {
    $total = (float)($order['total_amount'] ?? 0.00);
    if ($mode === 'partial') {
        $amount_to_pay = round($total * ($percent / 100), 2);
    } else {
        $mode = 'full';
        $amount_to_pay = round($total, 2);
    }
}

// Handle intent creation
if ($order && $method === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Sesi kadaluarsa. Silakan refresh halaman.';
    } else {
        try {
            $intent_number = 'PI-' . date('Ymd') . '-' . str_pad((string)random_int(1,9999), 4, '0', STR_PAD_LEFT);
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            $stmt = $pdo->prepare('INSERT INTO payment_intents (intent_number, service_id, pricing_plan_id, order_id, customer_name, customer_email, customer_phone, amount, status, ip_address, user_agent, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                $intent_number,
                !empty($order['service_id']) ? (int)$order['service_id'] : null,
                isset($order['pricing_plan_id']) && $order['pricing_plan_id'] !== null ? (int)$order['pricing_plan_id'] : null,
                isset($order['id']) ? (int)$order['id'] : null,
                $order['customer_name'],
                $order['customer_email'],
                !empty($order['customer_phone']) ? $order['customer_phone'] : null,
                $amount_to_pay,
                'submitted',
                $ip,
                $ua,
                'Created from pay.php (' . $mode . ', ' . ($mode === 'partial' ? ($percent . '%') : '100%') . ') for order ' . $order_number,
            ]);

            $intent_success = true;
        } catch (Throwable $e) {
            $error = 'Gagal membuat payment intent. Silakan coba lagi.';
        }
    }
}

// Utility: normalize company WhatsApp target if available
function normalize_phone_for_wa($phone) {
    $p = preg_replace('/[^0-9+]/', '', (string)$phone);
    if ($p === '') return '';
    if (strpos($p, '+') === 0) { $p = substr($p, 1); }
    if (strpos($p, '0') === 0) { $p = '62' . substr($p, 1); }
    return $p;
}

echo renderPageStart($pageTitle, 'Lakukan pembayaran penuh atau bertahap sesuai kebutuhan Anda.', $currentPage);
?>
<main class="max-w-3xl mx-auto px-4 py-10">
  <h1 class="text-3xl font-bold mb-6">Pembayaran</h1>

  <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6">
      <?= h($error) ?>
    </div>
  <?php endif; ?>

  <?php if ($order): ?>
    <div class="bg-white shadow rounded-lg border border-gray-200 mb-6">
      <div class="px-6 py-4 border-b">
        <h2 class="text-lg font-semibold">Ringkasan Pesanan</h2>
      </div>
      <div class="p-6 text-sm">
        <div class="grid md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <div><span class="text-gray-500">Nomor Pesanan:</span> <span class="font-medium text-blue-600"><?= h($order['order_number']) ?></span></div>
            <div><span class="text-gray-500">Tanggal:</span> <?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
            <div><span class="text-gray-500">Status Pesanan:</span> <?= h(ucfirst($order['status'])) ?></div>
            <div><span class="text-gray-500">Status Pembayaran:</span> <?= h(ucfirst($order['payment_status'])) ?></div>
          </div>
          <div class="space-y-2">
            <?php if (!empty($order['service_name'])): ?>
              <div><span class="text-gray-500">Layanan:</span> <?= h($order['service_name']) ?></div>
            <?php endif; ?>
            <?php if (!empty($order['plan_name'])): ?>
              <div><span class="text-gray-500">Paket:</span> <?= h($order['plan_name']) ?></div>
            <?php endif; ?>
            <div><span class="text-gray-500">Total:</span> <span class="font-bold text-green-600">Rp <?= number_format((float)$order['total_amount'], 0, ',', '.') ?></span></div>
          </div>
        </div>
        <div class="mt-4">
          <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center mb-2">
              <i class="fas fa-credit-card text-blue-600 mr-2"></i>
              <span class="font-medium">Mode Pembayaran: <?= $mode === 'partial' ? ('Bertahap (' . $percent . '%)') : 'Penuh (100%)' ?></span>
            </div>
            <p class="text-sm text-gray-700">Jumlah yang perlu dibayar saat ini:</p>
            <p class="text-2xl font-bold text-blue-700">Rp <?= number_format($amount_to_pay, 0, ',', '.') ?></p>
          </div>
        </div>
      </div>
    </div>

    <?php if (!$intent_success): ?>
      <form method="post" class="bg-white shadow rounded-lg border border-gray-200 p-6 mb-6">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="order_number" value="<?= h($order_number) ?>">
        <input type="hidden" name="mode" value="<?= h($mode) ?>">
        <?php if ($mode === 'partial'): ?>
          <input type="hidden" name="percent" value="<?= h($percent) ?>">
        <?php endif; ?>
        <button type="submit" class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          <i class="fas fa-file-invoice mr-2"></i> Buat Payment Intent
        </button>
        <p class="text-xs text-gray-500 mt-2">Payment intent akan direview oleh tim kami. Anda bisa melakukan pembayaran setelah intent dibuat.</p>
      </form>
    <?php else: ?>
      <div class="bg-green-50 border border-green-200 rounded p-6 mb-6">
        <div class="flex items-center mb-2">
          <i class="fas fa-check-circle text-green-600 mr-2"></i>
          <span class="font-semibold">Payment Intent Berhasil Dibuat</span>
        </div>
        <p class="text-sm text-green-800">Nomor Intent: <span class="font-mono font-semibold"><?= h($intent_number) ?></span></p>
        <p class="text-sm text-green-800">Silakan lakukan pembayaran sesuai instruksi di bawah, kemudian konfirmasi ke admin.</p>
      </div>
    <?php endif; ?>

    <!-- Pembayaran via Transfer Bank -->
    <div class="bg-white shadow rounded-lg border border-gray-200 p-6 mb-6">
      <h3 class="text-lg font-semibold mb-3">Transfer Bank</h3>
      <div class="bg-gray-50 rounded p-4">
        <p class="font-medium">SeaBank</p>
        <p class="text-sm text-gray-700">No. Rekening: <span class="font-mono">901414768802</span></p>
        <p class="text-sm text-gray-700">Nama: SyntaxTrust</p>
      </div>
      <p class="text-xs text-gray-500 mt-2">Pastikan nominal sesuai jumlah yang harus dibayar saat ini.</p>
    </div>

    <!-- Alternatif Pembayaran -->
    <div class="bg-white shadow rounded-lg border border-gray-200 p-6 mb-6">
      <h3 class="text-lg font-semibold mb-3">Metode Alternatif</h3>
      <p class="text-sm text-gray-700">Kami juga menerima pembayaran melalui e-wallet (OVO, GoPay, DANA) dan metode digital lain. Hubungi admin untuk detail lebih lanjut.</p>
    </div>

    <!-- Konfirmasi via WhatsApp -->
    <?php 
      $company_phone = getSetting('company_phone');
      $wa_target = normalize_phone_for_wa($company_phone);
      $wa_message = rawurlencode('Halo Admin, saya ingin konfirmasi pembayaran untuk pesanan ' . $order_number . ' dengan intent ' . ($intent_number ?: '[belum dibuat]') . '. Jumlah: Rp ' . number_format($amount_to_pay, 0, ',', '.') . '.');
      $wa_link = $wa_target !== '' ? ('https://wa.me/' . $wa_target . '?text=' . $wa_message) : '';
    ?>
    <div class="bg-blue-50 border border-blue-200 rounded p-6">
      <h3 class="text-lg font-semibold text-blue-900 mb-2">Konfirmasi Pembayaran</h3>
      <p class="text-sm text-blue-800 mb-4">Setelah transfer, kirim bukti pembayaran ke WhatsApp admin agar segera diproses.</p>
      <div class="flex items-center gap-2">
        <?php if ($wa_link): ?>
          <a href="<?= h($wa_link) ?>" target="_blank" class="inline-flex items-center bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"><i class="fab fa-whatsapp mr-2"></i> Chat Admin</a>
        <?php else: ?>
          <p class="text-sm text-blue-800">Nomor WhatsApp belum dikonfigurasi. Hubungi admin via email: <?= h(getSetting('company_email') ?: 'info@syntaxtrust.com') ?></p>
        <?php endif; ?>
        <a href="/order-tracking.php?order_number=<?= urlencode($order_number) ?>" class="inline-flex items-center bg-gray-100 text-gray-800 px-4 py-2 rounded hover:bg-gray-200"><i class="fas fa-truck mr-2"></i> Lacak Pesanan</a>
        <a href="/api/generate_invoice.php?order_number=<?= urlencode($order_number) ?>" class="inline-flex items-center bg-gray-100 text-gray-800 px-4 py-2 rounded hover:bg-gray-200"><i class="fas fa-file-pdf mr-2"></i> Unduh Invoice</a>
      </div>
    </div>

  <?php endif; ?>
</main>
<?php echo renderPageEnd(); ?>