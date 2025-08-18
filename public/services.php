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
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-12">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation">
                    <i class="fas fa-code text-3xl mb-2"></i>
                    <div class="text-sm">Web Development</div>
                </div>
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation" style="animation-delay: 0.5s;">
                    <i class="fas fa-mobile-alt text-3xl mb-2"></i>
                    <div class="text-sm">Mobile Apps</div>
                </div>
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation" style="animation-delay: 1s;">
                    <i class="fas fa-shopping-cart text-3xl mb-2"></i>
                    <div class="text-sm">E-commerce</div>
                </div>
                <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4 float-animation" style="animation-delay: 1.5s;">
                    <i class="fas fa-chart-line text-3xl mb-2"></i>
                    <div class="text-sm">Digital Marketing</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Grid -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Layanan Kami</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Kami menyediakan berbagai layanan digital profesional yang disesuaikan dengan kebutuhan mahasiswa dan UMKM</p>
            </div>
            
            <div id="services-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-8">
                <?php foreach ($services as $index => $service): ?>
                <div class="service-card bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100" style="animation-delay: <?= $index * 0.2 ?>s;">
                    <?php if ($service['image']): ?>
                    <div class="h-48 overflow-hidden">
                        <img src="<?= h(assetUrl($service['image'])) ?>" alt="<?= h($service['name']) ?>" class="w-full h-full object-cover">
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-8">
                        <div class="flex items-center mb-4">
                            <?php if ($service['icon']): ?>
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-<?= h($service['icon']) ?> text-white text-xl"></i>
                            </div>
                            <?php endif; ?>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-900"><?= h($service['name']) ?></h3>
                                <?php if ($service['is_featured']): ?>
                                <span class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-2 py-1 rounded-full text-xs font-semibold">
                                    <i class="fas fa-star mr-1"></i>Popular
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-6 leading-relaxed"><?= h($service['short_description'] ?: substr($service['description'], 0, 150) . '...') ?></p>
                        
                        <div class="flex items-center justify-between mb-6">
                            <?php if ($service['price'] && $service['price'] > 0): ?>
                            <div class="text-3xl font-bold text-blue-600">
                                Rp <?= number_format($service['price'], 0, ',', '.') ?>
                            </div>
                            <?php else: ?>
                            <div class="text-2xl font-bold text-gray-600">Custom Quote</div>
                            <?php endif; ?>
                            
                            <?php if ($service['duration']): ?>
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                <?= h($service['duration']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($service['features']): ?>
                        <div class="mb-6">
                            <h4 class="font-semibold text-gray-900 mb-3">Fitur Utama:</h4>
                            <ul class="space-y-2">
                                <?php
                                $features = json_decode($service['features'], true) ?: [];
                                foreach (array_slice($features, 0, 4) as $feature):
                                ?>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="fas fa-check text-green-500 mr-2"></i>
                                    <?= h($feature) ?>
                                </li>
                                <?php endforeach; ?>
                                <?php if (count($features) > 4): ?>
                                <li class="text-sm text-blue-600 font-medium">
                                    +<?= count($features) - 4 ?> fitur lainnya
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex gap-3">
                            <button onclick="openServiceModal(<?= $service['id'] ?>)" class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-6 rounded-lg font-semibold hover:shadow-lg transition-all duration-300">
                                Detail Layanan
                            </button>
                            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan layanan <?= urlencode($service['name']) ?>" 
                               target="_blank" 
                               class="bg-green-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($services)): ?>
            <div class="text-center py-20">
                <i class="fas fa-cogs text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Layanan Segera Hadir</h3>
                <p class="text-gray-600 mb-8">Kami sedang mempersiapkan layanan terbaik untuk Anda.</p>
                <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Hubungi Kami
                </a>
            </div>
            <?php endif; ?>
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
