<?php
require_once __DIR__ . '/includes/layout.php';

$pageTitle = 'Lacak Pesanan';
$currentPage = 'order-tracking';

$order = null;
$error = '';

// Accept either GET or POST to keep it simple
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $method === 'POST' ? $_POST : $_GET;

$order_number = trim($input['order_number'] ?? '');
$email = trim($input['email'] ?? '');

if ($order_number !== '') {
    try {
        // Basic lookup by order_number, optionally constrain by email if provided
        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT order_number, status, payment_status, total_amount, service_id, pricing_plan_id, created_at, estimated_completion FROM orders WHERE order_number = ? AND customer_email = ? LIMIT 1");
            $stmt->execute([$order_number, $email]);
        } else {
            $stmt = $pdo->prepare("SELECT order_number, status, payment_status, total_amount, service_id, pricing_plan_id, created_at, estimated_completion FROM orders WHERE order_number = ? LIMIT 1");
            $stmt->execute([$order_number]);
        }
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$order) { $error = 'Pesanan tidak ditemukan. Periksa nomor pesanan dan email Anda.'; }
    } catch (Throwable $e) {
        $error = 'Terjadi kesalahan saat mengambil data pesanan.';
    }
}

$badgeStatus = function($s) {
    $s = (string)$s;
    switch ($s) {
        case 'completed': return ['Selesai', 'bg-green-100 text-green-800'];
        case 'in_progress': return ['Diproses', 'bg-yellow-100 text-yellow-800'];
        case 'confirmed': return ['Dikonfirmasi', 'bg-blue-100 text-blue-800'];
        case 'cancelled': return ['Dibatalkan', 'bg-red-100 text-red-800'];
        default: return ['Pending', 'bg-gray-100 text-gray-800'];
    }
};

$badgePay = function($p) {
    $p = (string)$p;
    switch ($p) {
        case 'paid': return ['Lunas', 'bg-green-100 text-green-800'];
        case 'refunded': return ['Refund', 'bg-yellow-100 text-yellow-800'];
        default: return ['Belum Bayar', 'bg-red-100 text-red-800'];
    }
};

echo renderPageStart($pageTitle, 'Lacak status pesanan Anda dengan memasukkan nomor pesanan dan email.', $currentPage);
?>

<main class="max-w-3xl mx-auto px-4 py-10">
  <h1 class="text-3xl font-bold mb-6">Lacak Pesanan</h1>

  <form method="get" class="bg-white shadow rounded p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Pesanan</label>
        <input type="text" name="order_number" value="<?= h($order_number) ?>" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring" placeholder="mis. ORD-20250818-0001">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email (opsional)</label>
        <input type="email" name="email" value="<?= h($email) ?>" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring" placeholder="email saat pemesanan">
      </div>
    </div>
    <div class="mt-4">
      <button type="submit" class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><i class="fa fa-search mr-2"></i>Cari</button>
    </div>
  </form>

  <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-6"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if ($order): 
        list($sLabel, $sClass) = $badgeStatus($order['status'] ?? 'pending');
        list($pLabel, $pClass) = $badgePay($order['payment_status'] ?? 'unpaid');
        $isPaid = ($order['payment_status'] ?? 'unpaid') === 'paid';
        $totalAmount = (float)($order['total_amount'] ?? 0);
  ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Order Details -->
      <div class="lg:col-span-2">
        <section class="bg-white shadow rounded-lg p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Status Pesanan: <span class="text-gray-700">#<?= h($order['order_number']) ?></span></h2>
            <div class="space-x-2">
              <span class="inline-block text-xs px-2 py-1 rounded <?= h($sClass) ?>"><?= h($sLabel) ?></span>
              <span class="inline-block text-xs px-2 py-1 rounded <?= h($pClass) ?>"><?= h($pLabel) ?></span>
            </div>
          </div>
          
          <!-- Progress Bar -->
          <div class="mb-6">
            <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
              <span>Progress Pesanan</span>
              <span><?php 
                $progress = 0;
                switch($order['status']) {
                  case 'pending': $progress = 25; break;
                  case 'confirmed': $progress = 50; break;
                  case 'in_progress': $progress = 75; break;
                  case 'completed': $progress = 100; break;
                  default: $progress = 10;
                }
                echo $progress;
              ?>%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?= $progress ?>%"></div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
            <div class="space-y-2">
              <div><span class="text-gray-500">Total:</span> <strong class="text-lg">Rp <?= number_format($totalAmount, 0, ',', '.') ?></strong></div>
              <div><span class="text-gray-500">Dibuat:</span> <?= h($order['created_at'] ? date('d M Y H:i', strtotime($order['created_at'])) : '-') ?></div>
            </div>
            <div class="space-y-2">
              <div><span class="text-gray-500">Estimasi Selesai:</span> <?= h(!empty($order['estimated_completion']) ? date('d M Y', strtotime($order['estimated_completion'])) : '-') ?></div>
              <?php if ($order['service_id']): ?>
                <div><span class="text-gray-500">Service ID:</span> #<?= h($order['service_id']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </section>
      </div>

      <!-- Payment & Actions Sidebar -->
      <div class="lg:col-span-1">
        <div class="bg-white shadow rounded-lg p-6 sticky top-6">
          <h3 class="text-lg font-semibold mb-4">Pembayaran</h3>
          
          <?php if (!$isPaid && $totalAmount > 0): ?>
            <!-- Unpaid Status -->
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
              <div class="flex items-center mb-2">
                <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                <span class="font-medium text-red-900">Menunggu Pembayaran</span>
              </div>
              <p class="text-sm text-red-700">Pesanan Anda belum dibayar. Silakan lakukan pembayaran untuk memproses pesanan.</p>
            </div>
            
            <!-- Payment Amount -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
              <div class="text-center">
                <p class="text-sm text-gray-600">Total Pembayaran</p>
                <p class="text-2xl font-bold text-gray-900">Rp <?= number_format($totalAmount, 0, ',', '.') ?></p>
              </div>
            </div>

            <!-- Payment Methods -->
            <div class="space-y-3">
              <h4 class="font-medium text-gray-900">Metode Pembayaran:</h4>
              
              <!-- Bank Transfer -->
              <button onclick="showBankTransfer()" class="w-full flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                  <i class="fas fa-university text-blue-600 mr-3"></i>
                  <span class="font-medium">Transfer Bank</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
              </button>

              <!-- E-Wallet -->
              <button onclick="showEWallet()" class="w-full flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                  <i class="fas fa-mobile-alt text-green-600 mr-3"></i>
                  <span class="font-medium">E-Wallet</span>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
              </button>

              <!-- WhatsApp Payment -->
              <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya ingin melakukan pembayaran untuk pesanan <?= urlencode($order['order_number']) ?> sebesar Rp <?= number_format($totalAmount, 0, ',', '.') ?>" 
                 target="_blank" 
                 class="w-full flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex items-center">
                  <i class="fab fa-whatsapp text-green-600 mr-3"></i>
                  <span class="font-medium">Chat WhatsApp</span>
                </div>
                <i class="fas fa-external-link-alt text-gray-400"></i>
              </a>
            </div>

          <?php elseif ($isPaid): ?>
            <!-- Paid Status -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
              <div class="flex items-center mb-2">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                <span class="font-medium text-green-900">Pembayaran Lunas</span>
              </div>
              <p class="text-sm text-green-700">Terima kasih! Pembayaran Anda telah diterima dan pesanan sedang diproses.</p>
            </div>

            <!-- Download Invoice -->
            <button onclick="downloadInvoice('<?= h($order['order_number']) ?>')" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 transition-colors mb-3">
              <i class="fas fa-download mr-2"></i>Download Invoice
            </button>

          <?php else: ?>
            <!-- Custom Pricing -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
              <div class="flex items-center mb-2">
                <i class="fas fa-calculator text-yellow-600 mr-2"></i>
                <span class="font-medium text-yellow-900">Harga Custom</span>
              </div>
              <p class="text-sm text-yellow-700">Harga untuk pesanan ini akan ditentukan setelah konsultasi dengan tim kami.</p>
            </div>
          <?php endif; ?>

          <!-- Contact Support -->
          <div class="pt-4 border-t border-gray-200">
            <h4 class="font-medium text-gray-900 mb-3">Butuh Bantuan?</h4>
            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya butuh bantuan dengan pesanan <?= urlencode($order['order_number']) ?>" 
               target="_blank" 
               class="w-full block text-center bg-green-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
              <i class="fab fa-whatsapp mr-2"></i>Chat Support
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Modals -->
    <div id="bankTransferModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Transfer Bank</h3>
          <button onclick="closeBankTransfer()" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="space-y-4">
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="font-medium">Bank BCA</p>
            <p class="text-sm text-gray-600">No. Rekening: <span class="font-mono">1234567890</span></p>
            <p class="text-sm text-gray-600">A.n. SyntaxTrust</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="font-medium">Bank Mandiri</p>
            <p class="text-sm text-gray-600">No. Rekening: <span class="font-mono">0987654321</span></p>
            <p class="text-sm text-gray-600">A.n. SyntaxTrust</p>
          </div>
          <div class="bg-blue-50 p-4 rounded-lg">
            <p class="text-sm text-blue-800">
              <strong>Jumlah Transfer:</strong> Rp <?= number_format($totalAmount, 0, ',', '.') ?><br>
              <strong>Kode Unik:</strong> <?= h($order['order_number']) ?>
            </p>
          </div>
          <p class="text-xs text-gray-600">Setelah transfer, silakan kirim bukti pembayaran melalui WhatsApp untuk konfirmasi.</p>
        </div>
      </div>
    </div>

    <div id="eWalletModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">E-Wallet</h3>
          <button onclick="closeEWallet()" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="space-y-4">
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="font-medium">GoPay / OVO / DANA</p>
            <p class="text-sm text-gray-600">No. HP: <span class="font-mono">+62851-5655-3226</span></p>
            <p class="text-sm text-gray-600">A.n. SyntaxTrust</p>
          </div>
          <div class="bg-blue-50 p-4 rounded-lg">
            <p class="text-sm text-blue-800">
              <strong>Jumlah Transfer:</strong> Rp <?= number_format($totalAmount, 0, ',', '.') ?><br>
              <strong>Catatan:</strong> <?= h($order['order_number']) ?>
            </p>
          </div>
          <p class="text-xs text-gray-600">Setelah transfer, silakan kirim screenshot bukti pembayaran melalui WhatsApp.</p>
        </div>
      </div>
    </div>

  <?php endif; ?>
</main>

<script>
  function showBankTransfer() {
    document.getElementById('bankTransferModal').classList.remove('hidden');
  }

  function closeBankTransfer() {
    document.getElementById('bankTransferModal').classList.add('hidden');
  }

  function showEWallet() {
    document.getElementById('eWalletModal').classList.remove('hidden');
  }

  function closeEWallet() {
    document.getElementById('eWalletModal').classList.add('hidden');
  }

  function downloadInvoice(orderNumber) {
    // Placeholder for invoice download functionality
    alert('Fitur download invoice akan segera tersedia. Silakan hubungi support untuk mendapatkan invoice.');
  }

  // Close modals when clicking outside
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('fixed') && e.target.classList.contains('inset-0')) {
      closeBankTransfer();
      closeEWallet();
    }
  });
</script>

<?php echo renderPageEnd(); ?>
