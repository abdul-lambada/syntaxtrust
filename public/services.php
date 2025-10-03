<?php
require_once __DIR__ . '/includes/layout.php';

// Get services
try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY is_featured DESC, sort_order ASC");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $services = [];
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
echo renderPageStart('Layanan - ' . $site_name, 'Layanan profesional kami - ' . $site_description, 'services.php');
?>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        .service-card { transition: all 0.3s ease; }
        .service-card:hover { transform: translateY(-10px); }
        .float-animation { animation: float 3s ease-in-out infinite; }
    </style>
    

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Layanan Profesional</h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">Solusi digital terbaik untuk mahasiswa dan UMKM</p>
            <div class="grid grid-cols-2 md:grid-cols-1 gap-6 mt-12">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation">
                    <i class="fas fa-code text-3xl mb-2"></i>
                    <div class="text-sm">Web Development</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Audience Offerings Section (dynamic) -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Untuk Siapa Layanan Kami?</h2>
                <p class="text-gray-600 mt-3 max-w-3xl mx-auto">Pilih paket yang tepat sesuai kebutuhan Anda. Harga fleksibel dan bisa dinego.</p>
            </div>
            <?php
            // Load audience offerings from services (Option B)
            $audience = [];
            if (isset($pdo) && $pdo instanceof PDO) {
                try {
                    $stmt = $pdo->query("SELECT id, name, icon, audience_enabled, audience_slug, audience_subtitle, audience_features, audience_wa_text FROM services WHERE is_active = 1 AND audience_enabled = 1 ORDER BY sort_order ASC, name ASC");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($rows as $r) {
                        $features = [];
                        if (!empty($r['audience_features'])) {
                            $tmp = json_decode($r['audience_features'], true);
                            $features = is_array($tmp) ? $tmp : [];
                        }
                        $audience[] = [
                            'slug' => $r['audience_slug'] ?: preg_replace('/[^a-z0-9\-]/', '-', strtolower($r['name'])),
                            'title' => $r['name'],
                            'subtitle' => $r['audience_subtitle'] ?: '',
                            'icon' => $r['icon'] ?: 'tags',
                            'features' => $features,
                            'wa_text' => $r['audience_wa_text'] ?: ('Halo, saya tertarik: ' . $r['name']),
                            'service_id' => (int)$r['id'],
                        ];
                    }
                } catch (Throwable $e) { /* ignore and fallback */ }
            }
            // No legacy fallback: if empty, show message only
            ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($audience as $item): 
                    $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($item['slug'] ?? 'offer'));
                    $icon = h($item['icon'] ?? 'tags');
                    $title = h($item['title'] ?? 'Paket');
                    $subtitle = h($item['subtitle'] ?? '');
                    // Derive dynamic starting price from pricing_plans if service_id is provided
                    $price_label = $item['price_label'] ?? '';
                    $service_id_ref = isset($item['service_id']) ? (int)$item['service_id'] : null;
                    if ($service_id_ref && isset($pdo) && $pdo instanceof PDO) {
                        try {
                            // Prefer starting plan
                            $stmtMin = $pdo->prepare("SELECT MIN(price) AS min_price FROM pricing_plans WHERE is_active = 1 AND price > 0 AND service_id = :sid AND is_starting_plan = 1");
                            $stmtMin->execute([':sid' => $service_id_ref]);
                            $rowMin = $stmtMin->fetch(PDO::FETCH_ASSOC);
                            $minPrice = $rowMin && $rowMin['min_price'] !== null ? (float)$rowMin['min_price'] : null;
                            if ($minPrice === null) {
                                $stmtMin = $pdo->prepare("SELECT MIN(price) AS min_price FROM pricing_plans WHERE is_active = 1 AND price > 0 AND service_id = :sid");
                                $stmtMin->execute([':sid' => $service_id_ref]);
                                $rowMin = $stmtMin->fetch(PDO::FETCH_ASSOC);
                                if ($rowMin && $rowMin['min_price'] !== null) { $minPrice = (float)$rowMin['min_price']; }
                            }
                            if ($minPrice !== null) { $price_label = 'Mulai dari Rp ' . number_format($minPrice, 0, ',', '.'); }
                        } catch (Throwable $e) { /* ignore */ }
                    }
                    if (!$price_label) {
                        $price_label = 'Mulai dari Rp ' . number_format((int)($item['price'] ?? 0), 0, ',', '.');
                    }
                    $price_label = h($price_label);
                    $features = isset($item['features']) && is_array($item['features']) ? $item['features'] : [];
                    $wa_text = urlencode($item['wa_text'] ?? ('Halo, saya tertarik: ' . $title));
                ?>
                <a id="<?= $slug ?>" class="hidden"></a>
                <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-100 service-card">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-<?= $icon ?> text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $title ?></h3>
                            <?php if ($subtitle): ?><p class="text-sm text-gray-500"><?= $subtitle ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($features): ?>
                    <ul class="space-y-2 mb-6 text-sm text-gray-700">
                        <?php foreach ($features as $f): ?>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i><?= h($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <div class="text-2xl font-bold text-blue-600 mb-6"><?= $price_label ?></div>
                    <div class="flex gap-3">
                        <a href="pricing.php" class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold text-center">Lihat Paket</a>
                        <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=<?= $wa_text ?>" target="_blank" class="bg-green-600 text-white py-3 px-6 rounded-lg font-semibold"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Why Choose SyntaxTrust -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-12 text-sm">
                <div class="flex items-center bg-white rounded-xl p-4 shadow-sm"><i class="fas fa-graduation-cap text-blue-600 mr-3"></i><span><strong>Harga Mahasiswa</strong> & bersahabat</span></div>
                <div class="flex items-center bg-white rounded-xl p-4 shadow-sm"><i class="fas fa-exchange-alt text-blue-600 mr-3"></i><span><strong>Sangat Fleksibel</strong> sesuai kebutuhan</span></div>
                <div class="flex items-center bg-white rounded-xl p-4 shadow-sm"><i class="fas fa-book text-blue-600 mr-3"></i><span><strong>Paham Akademik</strong> & kriteria penilaian</span></div>
                <div class="flex items-center bg-white rounded-xl p-4 shadow-sm"><i class="fas fa-comments text-blue-600 mr-3"></i><span><strong>Komunikasi Santai</strong> & cepat</span></div>
                <div class="flex items-center bg-white rounded-xl p-4 shadow-sm"><i class="fas fa-sync-alt text-blue-600 mr-3"></i><span><strong>Revisi Sampai Cocok</strong></span></div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Siap Memulai Project Anda?</h2>
            <p class="text-xl mb-8 text-blue-100">Konsultasikan kebutuhan digital Anda dengan tim ahli kami</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-envelope mr-2"></i>
                    Konsultasi Gratis
                </a>
                <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya ingin konsultasi tentang layanan digital" 
                   target="_blank" 
                   class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>
                    WhatsApp
                </a>
            </div>
        </div>
    </section>

    <!-- Modal -->
    <div id="service-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div id="modal-content"></div>
        </div>
    </div>

    <script>
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });

        const modal = document.getElementById('service-modal');
        
        function openServiceModal(serviceId) {
            fetch(`api/get_service.php?id=${serviceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('modal-content').innerHTML = generateServiceModal(data.service);
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                });
        }

        function closeServiceModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function generateServiceModal(service) {
            const features = service.features ? JSON.parse(service.features) : [];
            return `
                <div class="relative">
                    <button onclick="closeServiceModal()" class="absolute top-4 right-4 z-10 bg-white rounded-full p-2 shadow-lg">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                    
                    <div class="p-8">
                        <div class="flex items-center mb-6">
                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-${service.icon} text-white text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-3xl font-bold text-gray-900">${service.name}</h2>
                                <p class="text-gray-600">${service.short_description}</p>
                            </div>
                        </div>
                        
                        <div class="prose max-w-none mb-8">
                            <p class="text-gray-700 leading-relaxed">${service.description}</p>
                        </div>
                        
                        ${features.length > 0 ? `
                        <div class="mb-8">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4">Fitur Lengkap</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                ${features.map(feature => `
                                    <div class="flex items-center">
                                        <i class="fas fa-check text-green-500 mr-3"></i>
                                        <span class="text-gray-700">${feature}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="flex flex-wrap gap-4 justify-center">
                            <a href="contact.php" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                                <i class="fas fa-envelope mr-2"></i>Konsultasi
                            </a>
                            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan layanan ${service.name}" 
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
            if (e.target === modal) closeServiceModal();
        });

        // Client-side integration: hydrate services grid from API
        (function hydrateServices() {
            const grid = document.getElementById('services-grid');
            if (!grid) return;
            fetch('api/services_list.php', { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (!data?.success || !Array.isArray(data.items)) return;
                    const items = data.items;
                    grid.innerHTML = items.map((service, index) => {
                        const features = service.features ? (() => { try { return JSON.parse(service.features); } catch { return []; } })() : [];
                        const imgUrl = window.normalizeImageSrc(service.image);
                        const hasImage = !!imgUrl;
                        const priceBlock = (service.price && Number(service.price) > 0)
                            ? `<div class="text-3xl font-bold text-blue-600">Rp ${Number(service.price).toLocaleString('id-ID')}</div>`
                            : `<div class="text-2xl font-bold text-gray-600">Custom Quote</div>`;
                        const duration = service.duration ? `<div class="text-sm text-gray-500"><i class=\"fas fa-clock mr-1\"></i>${service.duration}</div>` : '';
                        return `
                        <div class="service-card bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100" style="animation-delay: ${index * 0.2}s;">
                            ${hasImage ? `
                            <div class="h-48 overflow-hidden">
                                <img src="${imgUrl}" alt="${service.name}" class="w-full h-full object-cover">
                            </div>` : ''}
                            <div class="p-8">
                                <div class="flex items-center mb-4">
                                    ${service.icon ? `
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-4">
                                        <i class="fas fa-${service.icon} text-white text-xl"></i>
                                    </div>` : ''}
                                    <div>
                                        <h3 class="text-2xl font-bold text-gray-900">${service.name}</h3>
                                        ${service.is_featured ? `
                                        <span class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-2 py-1 rounded-full text-xs font-semibold">
                                            <i class="fas fa-star mr-1"></i>Popular
                                        </span>` : ''}
                                    </div>
                                </div>
                                <p class="text-gray-600 mb-6 leading-relaxed">${service.short_description || (service.description ? (service.description.substring(0,150) + '...') : '')}</p>
                                <div class="flex items-center justify-between mb-6">
                                    ${priceBlock}
                                    ${duration}
                                </div>
                                ${features && features.length ? `
                                <div class="mb-6">
                                    <h4 class="font-semibold text-gray-900 mb-3">Fitur Utama:</h4>
                                    <ul class="space-y-2">
                                        ${features.slice(0,4).map(f => `
                                            <li class="flex items-center text-sm text-gray-600">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                ${f}
                                            </li>
                                        `).join('')}
                                        ${features.length > 4 ? `<li class="text-sm text-blue-600 font-medium">+${features.length - 4} fitur lainnya</li>` : ''}
                                    </ul>
                                </div>` : ''}
                                <div class="flex gap-3">
                                    <button onclick="openServiceModal(${service.id})" class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold hover:shadow-lg transition-all duration-300">
                                        Detail Layanan
                                    </button>
                                    <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan layanan ${encodeURIComponent(service.name)}" 
                                       target="_blank" 
                                       class="bg-green-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                </div>
                            </div>
                        </div>`;
                    }).join('');
                })
                .catch(() => {});
        })();
    </script>
    <?php echo renderPageEnd(); ?>
