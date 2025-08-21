<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../config/database.php';

$order = null;
$order_number = $_GET['order_number'] ?? '';

// Fetch order details if order number is provided
if (!empty($order_number)) {
    try {
        $stmt = $pdo->prepare("SELECT o.*, s.name as service_name, pp.name as plan_name, pp.price 
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

echo renderPageStart('Pembayaran Berhasil', 'Terima kasih! Pembayaran Anda telah diterima.', '');
?>
<main class="max-w-4xl mx-auto px-4 py-16">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
            <i class="fas fa-check-circle text-3xl text-green-600"></i>
        </div>
        <h1 class="text-4xl font-bold text-gray-900 mb-2">Pembayaran Berhasil!</h1>
        <p class="text-lg text-gray-600">Terima kasih atas kepercayaan Anda kepada SyntaxTrust</p>
    </div>

    <?php if ($order): ?>
    <!-- Order Details Card -->
    <div class="bg-white rounded-lg shadow-lg border border-gray-200 mb-8">
        <div class="bg-green-50 px-6 py-4 border-b border-green-200">
            <h2 class="text-xl font-semibold text-green-800 flex items-center">
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
                            <span class="text-gray-600">Tanggal:</span>
                            <span class="font-medium"><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status:</span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status Pembayaran:</span>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
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
                            <span class="font-bold text-lg text-green-600">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($order['project_description']): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="font-semibold text-gray-900 mb-2">Deskripsi Proyek</h3>
                <p class="text-gray-700 text-sm"><?= nl2br(h($order['project_description'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Next Steps -->
    <div class="bg-blue-50 rounded-lg border border-blue-200 p-6 mb-8">
        <h2 class="text-xl font-semibold text-blue-900 mb-4 flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            Langkah Selanjutnya
        </h2>
        <div class="grid md:grid-cols-3 gap-4 text-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center mr-3 mt-1">
                    <span class="font-bold">1</span>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-900">Konfirmasi Admin</h3>
                    <p class="text-blue-700">Tim kami akan mengkonfirmasi pembayaran Anda dalam 1-2 jam kerja</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center mr-3 mt-1">
                    <span class="font-bold">2</span>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-900">Mulai Pengerjaan</h3>
                    <p class="text-blue-700">Proyek Anda akan segera dimulai setelah pembayaran dikonfirmasi</p>
                </div>
            </div>
            <div class="flex items-start">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center mr-3 mt-1">
                    <span class="font-bold">3</span>
                </div>
                <div>
                    <h3 class="font-semibold text-blue-900">Update Berkala</h3>
                    <p class="text-blue-700">Kami akan memberikan update progress secara berkala</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="text-center space-y-4">
        <?php if (!empty($order_number)): ?>
        <div>
            <a href="order-tracking.php?order_number=<?= urlencode($order_number) ?>" 
               class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-search mr-2"></i>
                Lacak Pesanan
            </a>
        </div>
        <?php endif; ?>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="index.php" 
               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors">
                <i class="fas fa-home mr-2"></i>
                Kembali ke Beranda
            </a>
            <a href="https://wa.me/6285156553226?text=Halo%2C%20saya%20ingin%20bertanya%20tentang%20pesanan%20<?= urlencode($order_number ?? '') ?>" 
               target="_blank"
               class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors">
                <i class="fab fa-whatsapp mr-2"></i>
                Hubungi Support
            </a>
        </div>
    </div>
</main>
<?php echo renderPageEnd(); ?>
