<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../config/database.php';

$order = null;
$order_number = $_GET['order_number'] ?? '';
$error_message = $_GET['error'] ?? 'Pembayaran gagal diproses';

// Fetch order details if order number is provided
if (!empty($order_number)) {
    try {
        $stmt = $pdo->prepare("SELECT o.*, s.name as service_name, pp.name as plan_name 
                               FROM orders o 
                               LEFT JOIN services s ON o.service_id = s.id 
                               LEFT JOIN pricing_plans pp ON o.pricing_plan_id = pp.id 
                               WHERE o.order_number = ?");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error silently
    }
}

echo renderPageStart('Pembayaran Gagal', 'Maaf, pembayaran Anda gagal diproses.', '');
?>
<main class="max-w-4xl mx-auto px-4 py-16">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
            <i class="fas fa-times-circle text-3xl text-red-600"></i>
        </div>
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Pembayaran Gagal</h1>
        <p class="text-lg text-gray-600">Jangan khawatir, Anda masih bisa menyelesaikan pembayaran</p>
    </div>

    <!-- Error Message -->
    <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
            <div>
                <h3 class="text-lg font-semibold text-red-800">Pembayaran Tidak Berhasil</h3>
                <p class="text-red-700 mt-1"><?= h($error_message) ?></p>
            </div>
        </div>
    </div>

    <?php if ($order): ?>
    <!-- Order Details Card -->
    <div class="bg-white rounded-lg shadow-lg border border-gray-200 mb-8">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                <i class="fas fa-receipt mr-2"></i>
                Detail Pesanan
            </h2>
        </div>
        <div class="p-6">
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-3">Informasi Pesanan</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Nomor Pesanan:</span>
                            <span class="font-medium text-blue-600"><?= h($order['order_number']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status Pembayaran:</span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                <?= ucfirst($order['payment_status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 mb-3">Detail Layanan</h3>
                    <div class="space-y-2 text-sm">
                        <?php if ($order['service_name']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Layanan:</span>
                            <span class="font-medium"><?= h($order['service_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($order['plan_name']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Paket:</span>
                            <span class="font-medium"><?= h($order['plan_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total:</span>
                            <span class="font-bold text-lg text-gray-900">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Retry Options -->
    <div class="bg-blue-50 rounded-lg border border-blue-200 p-6 mb-8">
        <h2 class="text-xl font-semibold text-blue-900 mb-4 flex items-center">
            <i class="fas fa-redo mr-2"></i>
            Opsi Pembayaran
        </h2>
        <div class="grid md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <h3 class="font-semibold text-blue-900">Metode Pembayaran Alternatif</h3>
                <div class="space-y-3">
                    <div class="flex items-center p-3 bg-white rounded-lg border">
                        <i class="fas fa-university text-blue-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium">Transfer Bank</h4>
                            <p class="text-sm text-gray-600">BCA, Mandiri, BNI, BRI</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-white rounded-lg border">
                        <i class="fas fa-mobile-alt text-green-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium">E-Wallet</h4>
                            <p class="text-sm text-gray-600">GoPay, OVO, DANA, ShopeePay</p>
                        </div>
                    </div>
                    <div class="flex items-center p-3 bg-white rounded-lg border">
                        <i class="fab fa-whatsapp text-green-600 mr-3"></i>
                        <div>
                            <h4 class="font-medium">WhatsApp Payment</h4>
                            <p class="text-sm text-gray-600">Bantuan langsung dari tim kami</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <h3 class="font-semibold text-blue-900">Kemungkinan Penyebab</h3>
                <div class="space-y-2 text-sm text-blue-800">
                    <div class="flex items-start">
                        <i class="fas fa-circle text-xs mt-2 mr-2"></i>
                        <span>Koneksi internet tidak stabil</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-circle text-xs mt-2 mr-2"></i>
                        <span>Saldo atau limit kartu tidak mencukupi</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-circle text-xs mt-2 mr-2"></i>
                        <span>Gangguan sementara pada sistem pembayaran</span>
                    </div>
                    <div class="flex items-start">
                        <i class="fas fa-circle text-xs mt-2 mr-2"></i>
                        <span>Data kartu atau informasi pembayaran salah</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="text-center space-y-4">
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <?php if (!empty($order_number)): ?>
            <a href="order-tracking.php?order_number=<?= urlencode($order_number) ?>" 
               class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-credit-card mr-2"></i>
                Coba Bayar Lagi
            </a>
            <?php else: ?>
            <a href="checkout.php" 
               class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-credit-card mr-2"></i>
                Coba Bayar Lagi
            </a>
            <?php endif; ?>
            
            <a href="https://wa.me/6285156553226?text=Halo%2C%20saya%20mengalami%20masalah%20pembayaran%20untuk%20pesanan%20<?= urlencode($order_number ?? '') ?>" 
               target="_blank"
               class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors">
                <i class="fab fa-whatsapp mr-2"></i>
                Hubungi Support
            </a>
        </div>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="index.php" 
               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-home mr-2"></i>
                Kembali ke Beranda
            </a>
            <a href="pricing.php" 
               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-tags mr-2"></i>
                Lihat Paket Lain
            </a>
        </div>
    </div>
</main>
<?php echo renderPageEnd(); ?>
