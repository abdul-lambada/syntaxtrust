<?php
require_once __DIR__ . '/includes/layout.php';

// Get pricing plans with service information
try {
    $stmt = $pdo->prepare("
        SELECT pp.*, s.name as service_name 
        FROM pricing_plans pp 
        LEFT JOIN services s ON pp.service_id = s.id 
        WHERE pp.is_active = 1 
        ORDER BY pp.is_popular DESC, pp.sort_order ASC, pp.created_at DESC
    ");
    $stmt->execute();
    $pricing_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pricing_plans = [];
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
echo renderPageStart('Harga - ' . $site_name, 'Paket harga terjangkau - ' . $site_description, 'pricing.php');
?>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        .pricing-card { transition: all 0.3s ease; }
        .pricing-card:hover { transform: translateY(-10px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .float-animation { animation: float 3s ease-in-out infinite; }
        .pulse-animation { animation: pulse 2s ease-in-out infinite; }
        .popular-badge { animation: pulse 2s ease-in-out infinite; }
    </style>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Paket Harga Terjangkau</h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">Pilih paket yang sesuai dengan kebutuhan dan budget Anda</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-12">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation">
                    <i class="fas fa-money-bill-wave text-3xl mb-2"></i>
                    <div class="text-sm">Harga Terjangkau</div>
                </div>
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation" style="animation-delay: 0.5s;">
                    <i class="fas fa-rocket text-3xl mb-2"></i>
                    <div class="text-sm">Pengerjaan Cepat</div>
                </div>
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation" style="animation-delay: 1s;">
                    <i class="fas fa-shield-alt text-3xl mb-2"></i>
                    <div class="text-sm">Garansi Kualitas</div>
                </div>
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation" style="animation-delay: 1.5s;">
                    <i class="fas fa-headset text-3xl mb-2"></i>
                    <div class="text-sm">Support 24/7</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Negotiable & Offerings Overview -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <!-- Left: Offerings cards (from services) -->
                <div class="lg:col-span-2">
                    <?php
                    $cards = [];
                    if (isset($pdo) && $pdo instanceof PDO) {
                        try {
                            $stmt = $pdo->query("SELECT id, name, icon, audience_enabled, audience_slug, audience_subtitle, audience_features FROM services WHERE is_active = 1 AND audience_enabled = 1 ORDER BY sort_order ASC, name ASC");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                            foreach ($rows as $r) {
                                $features = [];
                                if (!empty($r['audience_features'])) {
                                    $tmp = json_decode($r['audience_features'], true);
                                    $features = is_array($tmp) ? $tmp : [];
                                }
                                $cards[] = [
                                    'slug' => $r['audience_slug'] ?: preg_replace('/[^a-z0-9\-]/', '-', strtolower($r['name'])),
                                    'title' => $r['name'],
                                    'icon' => $r['icon'] ?: 'tags',
                                    'service_id' => (int)$r['id'],
                                    'desc' => !empty($features) ? implode(', ', array_slice($features, 0, 4)) : ($r['audience_subtitle'] ?? ''),
                                ];
                            }
                        } catch (Throwable $e) { /* ignore */ }
                    }
                    if (empty($cards)) {
                        $cards = [
                            ['slug'=>'portfolio-cv','title'=>'Portofolio & CV','icon'=>'id-card','service_id'=>null,'desc'=>'Landing page, galeri karya, tombol WA/Email/LinkedIn, responsif.','price_label'=>'Mulai dari Rp 90.000'],
                            ['slug'=>'tugas-skripsi','title'=>'Tugas & Skripsi','icon'=>'user-graduate','service_id'=>null,'desc'=>'CRUD, database, login, dokumentasi dasar, revisi sesuai kesepakatan.','price_label'=>'Mulai dari Rp 200.000 (nego)'],
                            ['slug'=>'umkm','title'=>'UMKM & Usaha','icon'=>'store','service_id'=>null,'desc'=>'Profil usaha, produk/layanan, kontak, Maps, sosial media.','price_label'=>'Mulai dari Rp 500.000 (fleksibel)'],
                        ];
                    }
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($cards as $card): 
                            $displayPrice = $card['price_label'] ?? '';
                            if (!empty($card['service_id']) && isset($pdo) && $pdo instanceof PDO) {
                                try {
                                    // Prefer a designated starting plan
                                    $st = $pdo->prepare("SELECT MIN(price) AS min_price FROM pricing_plans WHERE is_active = 1 AND price > 0 AND service_id = :sid AND is_starting_plan = 1");
                                    $st->execute([':sid' => $card['service_id']]);
                                    $rmin = $st->fetch(PDO::FETCH_ASSOC);
                                    $minPrice = $rmin && $rmin['min_price'] !== null ? (float)$rmin['min_price'] : null;
                                    if ($minPrice === null) {
                                        $st = $pdo->prepare("SELECT MIN(price) AS min_price FROM pricing_plans WHERE is_active = 1 AND price > 0 AND service_id = :sid");
                                        $st->execute([':sid' => $card['service_id']]);
                                        $rmin = $st->fetch(PDO::FETCH_ASSOC);
                                        if ($rmin && $rmin['min_price'] !== null) { $minPrice = (float)$rmin['min_price']; }
                                    }
                                    if ($minPrice !== null) { $displayPrice = 'Mulai dari Rp ' . number_format($minPrice, 0, ',', '.'); }
                                } catch (Throwable $e) { /* ignore */ }
                            }
                        ?>
                        <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-6 border border-gray-100">
                            <div class="flex items-center mb-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-<?= h($card['icon']) ?> text-white"></i>
                                </div>
                                <h3 class="font-bold text-gray-900"><?= h($card['title']) ?></h3>
                            </div>
                            <?php if (!empty($card['desc'])): ?>
                            <p class="text-sm text-gray-600 mb-4"><?= h($card['desc']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($displayPrice)): ?>
                            <div class="text-blue-600 font-bold"><?= h($displayPrice) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Right: Negotiable note & factors -->
                <div>
                    <div class="bg-white rounded-2xl p-6 shadow-md border border-gray-100">
                        <div class="flex items-start">
                            <div class="w-12 h-12 rounded-full bg-green-100 text-green-700 flex items-center justify-center mr-4">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">Harga Kami Nego-able Banget!</h3>
                                <p class="text-sm text-gray-600 mb-4">Harga final akan disepakati setelah Anda menjelaskan kebutuhan. Kami transparan dan menyesuaikan budget.</p>
                                <h4 class="font-semibold text-gray-900 mb-2">Faktor yang Mempengaruhi Harga:</h4>
                                <ul class="list-disc pl-5 text-sm text-gray-700 space-y-1">
                                    <li>Tingkat kerumitan fitur</li>
                                    <li>Jumlah halaman</li>
                                    <li>Kebutuhan desain (dari Anda/baru)</li>
                                    <li>Deadline (ekspres vs normal)</li>
                                </ul>
                                <div class="flex gap-3 mt-5">
                                    <a href="contact.php" class="bg-blue-600 text-white px-5 py-3 rounded-lg font-semibold">Diskusi Gratis</a>
                                    <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya ingin diskusi paket harga" target="_blank" class="bg-green-600 text-white px-5 py-3 rounded-lg font-semibold"><i class="fab fa-whatsapp mr-1"></i>WhatsApp</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Process summary -->
            <div class="mt-10 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
                <div class="bg-gray-50 rounded-xl p-3 flex items-center"><i class="fas fa-comments text-blue-600 mr-2"></i><span><strong>Diskusi Dulu</strong> (Gratis)</span></div>
                <div class="bg-gray-50 rounded-xl p-3 flex items-center"><i class="fas fa-file-signature text-blue-600 mr-2"></i><span><strong>Deal & DP 50%</strong></span></div>
                <div class="bg-gray-50 rounded-xl p-3 flex items-center"><i class="fas fa-code text-blue-600 mr-2"></i><span><strong>Proses Pengerjaan</strong> + update berkala</span></div>
                <div class="bg-gray-50 rounded-xl p-3 flex items-center"><i class="fas fa-sync-alt text-blue-600 mr-2"></i><span><strong>Revisi & Feedback</strong></span></div>
                <div class="bg-gray-50 rounded-xl p-3 flex items-center"><i class="fas fa-gift text-blue-600 mr-2"></i><span><strong>Pelunasan & Serah Terima</strong></span></div>
            </div>
        </div>
    </section>

    <!-- Pricing Plans Grid -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Pilih Paket Terbaik</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Kami menawarkan berbagai paket dengan harga kompetitif untuk memenuhi kebutuhan digital Anda</p>
            </div>
            
            <div id="pricing-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($pricing_plans as $index => $plan): 
                    $features = [];
                    if (!empty($plan['features'])) {
                        $decoded = json_decode($plan['features'], true);
                        $features = is_array($decoded) ? $decoded : [];
                    }
                    
                    $technologies = [];
                    if (!empty($plan['technologies'])) {
                        $decoded = json_decode($plan['technologies'], true);
                        $technologies = is_array($decoded) ? $decoded : [];
                    }
                ?>
                <div class="pricing-card bg-white rounded-2xl shadow-xl overflow-visible border border-gray-100 relative <?= $plan['is_popular'] ? 'ring-4 ring-yellow-400' : '' ?>" style="animation-delay: <?= $index * 0.2 ?>s;">
                    <?php if ($plan['is_popular']): ?>
                    <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-10">
                        <span class="popular-badge bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-6 py-2 rounded-full text-sm font-bold shadow-lg">
                            <i class="fas fa-star mr-1"></i>PALING POPULER
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-8 <?= $plan['is_popular'] ? 'pt-12' : '' ?>">
                        <div class="text-center mb-6">
                            <?php if ($plan['icon']): ?>
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: <?= h($plan['color'] ?: '#4e73df') ?>;">
                                <i class="fas fa-<?= h($plan['icon']) ?> text-white text-2xl"></i>
                            </div>
                            <?php endif; ?>
                            
                            <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= h($plan['name']) ?></h3>
                            <?php if ($plan['subtitle']): ?>
                            <p class="text-gray-600 mb-4"><?= h($plan['subtitle']) ?></p>
                            <?php endif; ?>
                            
                            <div class="mb-4">
                                <?php if ($plan['price'] > 0): ?>
                                <div class="text-4xl font-bold text-gray-900 mb-1">
                                    <?= $plan['currency'] ?> <?= number_format($plan['price'], 0, ',', '.') ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    / <?= ucfirst(str_replace('_', ' ', $plan['billing_period'])) ?>
                                </div>
                                <?php else: ?>
                                <div class="text-3xl font-bold text-gray-900">Custom Quote</div>
                                <div class="text-sm text-gray-500">Hubungi kami untuk harga</div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($plan['service_name']): ?>
                            <div class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium mb-4">
                                <?= h($plan['service_name']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($plan['description'])): ?>
                        <p class="text-gray-600 mb-6 text-center leading-relaxed"><?= h($plan['description']) ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($features)): ?>
                        <div class="mb-6">
                            <h4 class="font-semibold text-gray-900 mb-4 text-center">Fitur Termasuk:</h4>
                            <ul class="space-y-3">
                                <?php foreach ($features as $feature): ?>
                                <li class="flex items-center text-sm text-gray-700">
                                    <i class="fas fa-check text-green-500 mr-3 flex-shrink-0"></i>
                                    <span><?= h($feature) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($plan['delivery_time']): ?>
                        <div class="mb-6 text-center">
                            <div class="inline-flex items-center text-sm text-gray-600 bg-gray-100 px-3 py-2 rounded-lg">
                                <i class="fas fa-clock mr-2"></i>
                                Estimasi: <?= h($plan['delivery_time']) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($technologies)): ?>
                        <div class="mb-6">
                            <h5 class="font-medium text-gray-900 mb-3 text-center">Teknologi:</h5>
                            <div class="flex flex-wrap gap-2 justify-center">
                                <?php foreach ($technologies as $tech): ?>
                                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-medium"><?= h($tech) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="space-y-3">
                            <a href="checkout.php?plan_id=<?= $plan['id'] ?>&service_id=<?= $plan['service_id'] ?>" 
                               class="w-full block text-center bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold hover:shadow-lg transition-all duration-300 <?= $plan['is_popular'] ? 'pulse-animation' : '' ?>">
                                <i class="fas fa-shopping-cart mr-2"></i>Pesan Sekarang
                            </a>
                            <button onclick="openPricingModal(<?= $plan['id'] ?>)" class="w-full bg-gray-100 text-gray-800 py-3 px-6 rounded-lg font-semibold hover:bg-gray-200 transition-colors">
                                <i class="fas fa-info-circle mr-2"></i>Detail Paket
                            </button>
                            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan paket <?= urlencode($plan['name']) ?>" 
                               target="_blank" 
                               class="w-full block text-center bg-green-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                                <i class="fab fa-whatsapp mr-2"></i>Konsultasi WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($pricing_plans)): ?>
            <div class="text-center py-20">
                <i class="fas fa-tags text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Paket Harga Segera Hadir</h3>
                <p class="text-gray-600 mb-8">Kami sedang mempersiapkan paket harga terbaik untuk Anda.</p>
                <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Hubungi Kami
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Pertanyaan Umum</h2>
                <p class="text-gray-600">Jawaban untuk pertanyaan yang sering ditanyakan tentang paket harga kami</p>
            </div>
            
            <div class="space-y-6">
                <div class="bg-white rounded-lg p-6 shadow-md">
                    <button class="w-full text-left flex justify-between items-center" onclick="toggleFAQ(1)">
                        <h3 class="font-semibold text-gray-900">Apakah ada garansi untuk layanan yang diberikan?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" id="faq-icon-1"></i>
                    </button>
                    <div class="mt-4 text-gray-600 hidden" id="faq-content-1">
                        <p>Ya, kami memberikan garansi untuk semua layanan yang kami berikan sesuai dengan ketentuan yang berlaku. Garansi mencakup bug fixing dan maintenance selama periode tertentu setelah project selesai.</p>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md">
                    <button class="w-full text-left flex justify-between items-center" onclick="toggleFAQ(2)">
                        <h3 class="font-semibold text-gray-900">Bagaimana cara pembayaran?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" id="faq-icon-2"></i>
                    </button>
                    <div class="mt-4 text-gray-600 hidden" id="faq-content-2">
                        <p>Kami menerima pembayaran melalui transfer bank, e-wallet (OVO, GoPay, DANA), dan metode pembayaran digital lainnya. Pembayaran dapat dilakukan secara bertahap sesuai milestone project.</p>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md">
                    <button class="w-full text-left flex justify-between items-center" onclick="toggleFAQ(3)">
                        <h3 class="font-semibold text-gray-900">Apakah bisa request fitur tambahan?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" id="faq-icon-3"></i>
                    </button>
                    <div class="mt-4 text-gray-600 hidden" id="faq-content-3">
                        <p>Tentu saja! Kami dapat menyesuaikan paket dengan kebutuhan spesifik Anda. Fitur tambahan akan dikenakan biaya tambahan yang akan didiskusikan dan disepakati terlebih dahulu.</p>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md">
                    <button class="w-full text-left flex justify-between items-center" onclick="toggleFAQ(4)">
                        <h3 class="font-semibold text-gray-900">Berapa lama waktu pengerjaan project?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" id="faq-icon-4"></i>
                    </button>
                    <div class="mt-4 text-gray-600 hidden" id="faq-content-4">
                        <p>Waktu pengerjaan bervariasi tergantung kompleksitas project. Estimasi waktu sudah tercantum di setiap paket. Kami selalu berusaha menyelesaikan project tepat waktu sesuai kesepakatan.</p>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-md">
                    <button class="w-full text-left flex justify-between items-center" onclick="toggleFAQ(5)">
                        <h3 class="font-semibold text-gray-900">Apakah ada support setelah project selesai?</h3>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" id="faq-icon-5"></i>
                    </button>
                    <div class="mt-4 text-gray-600 hidden" id="faq-content-5">
                        <p>Ya, kami menyediakan support dan maintenance setelah project selesai. Support mencakup bantuan teknis, update minor, dan konsultasi terkait penggunaan sistem yang telah dibuat.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Custom Package CTA -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-8 text-white">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Butuh Paket Khusus?</h2>
                <p class="text-xl mb-6 text-blue-100">Kami juga menyediakan paket custom sesuai kebutuhan spesifik Anda</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="contact.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                        <i class="fas fa-envelope mr-2"></i>
                        Konsultasi Gratis
                    </a>
                    <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya ingin konsultasi paket custom" 
                       target="_blank" 
                       class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                        <i class="fab fa-whatsapp mr-2"></i>
                        WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <div id="pricing-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div id="modal-content"></div>
        </div>
    </div>

    <script>
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });

        const modal = document.getElementById('pricing-modal');
        
        function openPricingModal(planId) {
            fetch(`api/get_pricing_plan.php?id=${planId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modal-content').innerHTML = generatePricingModal(data.plan);
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                })
                .catch(() => {
                    // Fallback: generate modal from existing data
                    const planData = <?= json_encode($pricing_plans) ?>;
                    const plan = planData.find(p => p.id == planId);
                    if (plan) {
                        document.getElementById('modal-content').innerHTML = generatePricingModal(plan);
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                });
        }

        function closePricingModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function generatePricingModal(plan) {
            const features = plan.features ? (() => { try { return JSON.parse(plan.features); } catch { return []; } })() : [];
            const technologies = plan.technologies ? (() => { try { return JSON.parse(plan.technologies); } catch { return []; } })() : [];
            
            return `
                <div class="relative">
                    <button onclick="closePricingModal()" class="absolute top-4 right-4 z-10 bg-white rounded-full p-2 shadow-lg">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                    
                    <div class="p-8">
                        <div class="text-center mb-8">
                            ${plan.icon ? `
                            <div class="w-20 h-20 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: ${plan.color || '#4e73df'};">
                                <i class="fas fa-${plan.icon} text-white text-3xl"></i>
                            </div>` : ''}
                            
                            <h2 class="text-3xl font-bold text-gray-900 mb-2">${plan.name}</h2>
                            ${plan.subtitle ? `<p class="text-gray-600 mb-4">${plan.subtitle}</p>` : ''}
                            
                            <div class="mb-4">
                                ${plan.price > 0 ? `
                                <div class="text-5xl font-bold text-gray-900 mb-2">
                                    ${plan.currency} ${Number(plan.price).toLocaleString('id-ID')}
                                </div>
                                <div class="text-gray-500">
                                    / ${plan.billing_period.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                </div>` : `
                                <div class="text-4xl font-bold text-gray-900">Custom Quote</div>
                                <div class="text-gray-500">Hubungi kami untuk harga</div>`}
                            </div>
                            
                            ${plan.service_name ? `
                            <div class="inline-block bg-blue-100 text-blue-800 px-4 py-2 rounded-full font-medium mb-6">
                                ${plan.service_name}
                            </div>` : ''}
                        </div>
                        
                        ${plan.description ? `
                        <div class="prose max-w-none mb-8 text-center">
                            <p class="text-gray-700 leading-relaxed text-lg">${plan.description}</p>
                        </div>` : ''}
                        
                        <div class="grid md:grid-cols-2 gap-8 mb-8">
                            ${features.length > 0 ? `
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 mb-4">Fitur Lengkap</h3>
                                <ul class="space-y-3">
                                    ${features.map(feature => `
                                        <li class="flex items-start">
                                            <i class="fas fa-check text-green-500 mr-3 mt-1 flex-shrink-0"></i>
                                            <span class="text-gray-700">${feature}</span>
                                        </li>
                                    `).join('')}
                                </ul>
                            </div>` : ''}
                            
                            <div>
                                ${plan.delivery_time ? `
                                <div class="mb-6">
                                    <h4 class="font-semibold text-gray-900 mb-2">Waktu Pengerjaan</h4>
                                    <div class="flex items-center text-gray-700">
                                        <i class="fas fa-clock mr-2"></i>
                                        ${plan.delivery_time}
                                    </div>
                                </div>` : ''}
                                
                                ${technologies.length > 0 ? `
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">Teknologi yang Digunakan</h4>
                                    <div class="flex flex-wrap gap-2">
                                        ${technologies.map(tech => `
                                            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">${tech}</span>
                                        `).join('')}
                                    </div>
                                </div>` : ''}
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-4 justify-center pt-6 border-t">
                            <a href="contact.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                                <i class="fas fa-envelope mr-2"></i>Konsultasi
                            </a>
                            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan paket ${plan.name}" 
                               target="_blank" 
                               class="bg-green-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                                <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }

        modal.addEventListener('click', function(e) {
            if (e.target === modal) closePricingModal();
        });

        // FAQ Toggle functionality
        function toggleFAQ(id) {
            const content = document.getElementById(`faq-content-${id}`);
            const icon = document.getElementById(`faq-icon-${id}`);
            
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('rotate-180');
            }
        }
    </script>
    
    <?php echo renderPageEnd(); ?>