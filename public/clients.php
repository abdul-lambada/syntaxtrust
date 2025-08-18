<?php
require_once __DIR__ . '/includes/layout.php';

// Get clients
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $clients = [];
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
echo renderPageStart('Klien Kami - ' . $site_name, 'Klien-klien yang telah mempercayai layanan kami - ' . $site_description, 'clients.php');
?>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
        .client-card { transition: all 0.3s ease; }
        .client-card:hover { transform: translateY(-5px); }
        .logo-scroll { animation: scroll 30s linear infinite; }
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
    </style>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Klien Kami</h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">Perusahaan dan individu yang telah mempercayai layanan kami</p>
            <div class="flex justify-center items-center space-x-8 mt-12">
                <div class="text-center">
                    <div class="text-3xl font-bold"><span id="clients-count"><?= count($clients) ?></span>+</div>
                    <div class="text-blue-100">Happy Clients</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">50+</div>
                    <div class="text-blue-100">Projects</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">3+</div>
                    <div class="text-blue-100">Years</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Scrolling Logos -->
    <?php if (!empty($clients)): ?>
    <section class="py-12 bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Dipercaya Oleh</h2>
            </div>
            <div class="relative">
                <div id="clients-logos" class="flex logo-scroll">
                    <?php for ($i = 0; $i < 2; $i++): ?>
                        <?php foreach ($clients as $client): ?>
                        <div class="flex-shrink-0 mx-8">
                            <?php if ($client['logo']): ?>
                            <img src="<?= h($client['logo']) ?>" alt="<?= h($client['name']) ?>" class="h-16 w-auto grayscale hover:grayscale-0 transition-all duration-300">
                            <?php else: ?>
                            <div class="h-16 w-32 bg-gray-200 rounded-lg flex items-center justify-center">
                                <span class="text-gray-600 font-semibold text-sm"><?= h($client['name']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Client Cards -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Portfolio Klien</h2>
                <p class="text-xl text-gray-600">Lihat lebih detail tentang klien-klien kami</p>
            </div>
            
            <div id="clients-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($clients as $index => $client): ?>
                <div class="client-card bg-white rounded-xl shadow-lg overflow-hidden" style="animation: slideUp 0.6s ease-out <?= $index * 0.1 ?>s both;">
                    <!-- Logo/Image -->
                    <div class="h-48 bg-gradient-to-br from-blue-50 to-purple-50 flex items-center justify-center p-8">
                        <?php if ($client['logo']): ?>
                        <img src="<?= h($client['logo']) ?>" alt="<?= h($client['name']) ?>" class="max-h-full max-w-full object-contain">
                        <?php else: ?>
                        <div class="text-center">
                            <i class="fas fa-building text-4xl text-blue-600 mb-2"></i>
                            <div class="text-xl font-bold text-gray-900"><?= h($client['name']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Content -->
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-2"><?= h($client['name']) ?></h3>
                        
                        <?php if ($client['description']): ?>
                        <p class="text-gray-600 mb-4 leading-relaxed"><?= h($client['description']) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($client['testimonial']): ?>
                        <blockquote class="bg-blue-50 p-4 rounded-lg mb-4 italic text-gray-700">
                            "<?= h(strlen($client['testimonial']) > 100 ? substr($client['testimonial'], 0, 100) . '...' : $client['testimonial']) ?>"
                        </blockquote>
                        <?php endif; ?>
                        
                        <?php if ($client['rating']): ?>
                        <div class="flex items-center mb-4">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $client['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                            <?php endfor; ?>
                            <span class="ml-2 text-gray-600">(<?= $client['rating'] ?>/5)</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($client['website_url']): ?>
                        <a href="<?= h($client['website_url']) ?>" target="_blank" class="inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Kunjungi Website
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($clients)): ?>
            <div class="text-center py-20">
                <i class="fas fa-handshake text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Klien Segera Hadir</h3>
                <p class="text-gray-600 mb-8">Kami sedang membangun kemitraan dengan klien-klien terbaik.</p>
                <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Jadilah Klien Pertama
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Success Stories -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Kisah Sukses</h2>
                <p class="text-xl text-gray-600">Bagaimana kami membantu klien mencapai tujuan mereka</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Meningkatkan Penjualan Online</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        Dengan membangun website e-commerce yang modern dan user-friendly, klien kami berhasil meningkatkan penjualan online hingga 300% dalam 6 bulan pertama.
                    </p>
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">300%</div>
                            <div class="text-sm text-gray-600">Peningkatan Penjualan</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">50%</div>
                            <div class="text-sm text-gray-600">Lebih Banyak Visitor</div>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="text-2xl font-bold text-purple-600">24/7</div>
                            <div class="text-sm text-gray-600">Online Presence</div>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <div class="bg-gradient-to-br from-blue-500 to-purple-600 rounded-2xl p-8 text-white">
                        <i class="fas fa-chart-line text-6xl mb-4"></i>
                        <h4 class="text-xl font-bold mb-2">Hasil Nyata</h4>
                        <p class="text-blue-100">Setiap project kami dirancang untuk memberikan hasil yang terukur dan berkelanjutan</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Siap Bergabung dengan Klien Kami?</h2>
            <p class="text-xl mb-8 text-blue-100">Jadilah bagian dari kisah sukses berikutnya</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-envelope mr-2"></i>
                    Mulai Project
                </a>
                <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik menjadi klien" 
                   target="_blank" 
                   class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>
                    Konsultasi Gratis
                </a>
            </div>
        </div>
    </section>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });

        // Bind pause/resume for logo scroller
        function bindLogoHover() {
            const scroller = document.querySelector('.logo-scroll');
            if (!scroller) return;
            scroller.addEventListener('mouseenter', function() { this.style.animationPlayState = 'paused'; });
            scroller.addEventListener('mouseleave', function() { this.style.animationPlayState = 'running'; });
        }
        bindLogoHover();

        // Hydrate clients from API
        (function hydrateClients() {
            const countEl = document.getElementById('clients-count');
            const logosEl = document.getElementById('clients-logos');
            const gridEl = document.getElementById('clients-grid');
            fetch('api/clients_list.php', { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (!data?.success || !Array.isArray(data.items)) return;
                    const items = data.items;
                    if (countEl) countEl.textContent = String(items.length);

                    // Rebuild logos scroller (duplicate list for seamless scroll)
                    if (logosEl) {
                        const onePass = items.map(c => {
                            const content = c.logo
                                ? `<img src="${c.logo}" alt="${c.name}" class="h-16 w-auto grayscale hover:grayscale-0 transition-all duration-300">`
                                : `<div class=\"h-16 w-32 bg-gray-200 rounded-lg flex items-center justify-center\"><span class=\"text-gray-600 font-semibold text-sm\">${c.name}</span></div>`;
                            return `<div class=\"flex-shrink-0 mx-8\">${content}</div>`;
                        }).join('');
                        logosEl.innerHTML = onePass + onePass;
                        bindLogoHover();
                    }

                    // Rebuild client cards grid
                    if (gridEl) {
                        gridEl.innerHTML = items.map((c, idx) => {
                            const logo = c.logo
                                ? `<img src=\"${c.logo}\" alt=\"${c.name}\" class=\"max-h-full max-w-full object-contain\">`
                                : `<div class=\"text-center\"><i class=\"fas fa-building text-4xl text-blue-600 mb-2\"></i><div class=\"text-xl font-bold text-gray-900\">${c.name}</div></div>`;
                            const desc = c.description ? `<p class=\"text-gray-600 mb-4 leading-relaxed\">${c.description}</p>` : '';
                            const testi = c.testimonial ? `<blockquote class=\"bg-blue-50 p-4 rounded-lg mb-4 italic text-gray-700\">\"${(c.testimonial.length>100?c.testimonial.substring(0,100)+'...':c.testimonial)}\"</blockquote>` : '';
                            const rating = c.rating ? `<div class=\"flex items-center mb-4\">${Array.from({length:5},(_,i)=>`<i class=\"fas fa-star ${i<Number(c.rating)?'text-yellow-400':'text-gray-300'}\"></i>`).join('')}<span class=\"ml-2 text-gray-600\">(${c.rating}/5)</span></div>` : '';
                            const link = c.website_url ? `<a href=\"${c.website_url}\" target=\"_blank\" class=\"inline-flex items-center bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors\"><i class=\"fas fa-external-link-alt mr-2\"></i>Kunjungi Website</a>` : '';
                            return `<div class=\"client-card bg-white rounded-xl shadow-lg overflow-hidden\" style=\"animation: slideUp 0.6s ease-out ${idx*0.1}s both;\">\n<div class=\"h-48 bg-gradient-to-br from-blue-50 to-purple-50 flex items-center justify-center p-8\">${logo}</div>\n<div class=\"p-6\"><h3 class=\"text-xl font-bold text-gray-900 mb-2\">${c.name}</h3>${desc}${testi}${rating}${link}</div></div>`;
                        }).join('');
                    }
                })
                .catch(() => {});
        })();
    </script>
    <?php echo renderPageEnd(); ?>
