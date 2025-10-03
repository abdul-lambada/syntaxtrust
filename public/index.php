<?php
require_once __DIR__ . '/includes/layout.php';

// Get featured content (guard when DB not available)
$featured_services = $featured_portfolio = $featured_testimonials = $latest_posts = $clients = $latest_testimonials = [];
try {
    if ($pdo instanceof PDO) {
        // Featured services (use audience-enabled instead of is_featured)
        $services_stmt = $pdo->prepare("SELECT id, name, icon, audience_enabled, audience_subtitle, audience_features FROM services WHERE is_active = 1 AND audience_enabled = 1 ORDER BY sort_order, name LIMIT 3");
        $services_stmt->execute();
        $featured_services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Featured portfolio
        $portfolio_stmt = $pdo->prepare("SELECT * FROM portfolio WHERE is_active = 1 AND is_featured = 1 ORDER BY created_at DESC LIMIT 6");
        $portfolio_stmt->execute();
        $featured_portfolio = $portfolio_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Featured testimonials
        $testimonials_stmt = $pdo->prepare("SELECT * FROM testimonials WHERE is_active = 1 AND is_featured = 1 ORDER BY sort_order LIMIT 3");
        $testimonials_stmt->execute();
        $featured_testimonials = $testimonials_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Newest testimonials (for homepage section)
        $latest_testimonials_stmt = $pdo->prepare("SELECT t.*, s.name AS service_name FROM testimonials t LEFT JOIN services s ON t.service_id = s.id WHERE t.is_active = 1 ORDER BY t.created_at DESC LIMIT 6");
        $latest_testimonials_stmt->execute();
        $latest_testimonials = $latest_testimonials_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Latest blog posts
        $blog_stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 3");
        $blog_stmt->execute();
        $latest_posts = $blog_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Active clients
        $clients_stmt = $pdo->prepare("SELECT * FROM clients WHERE is_active = 1 ORDER BY sort_order LIMIT 8");
        $clients_stmt->execute();
        $clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    // Ignore and keep defaults when DB errors occur
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');

echo renderPageStart($site_name . ' - ' . $site_description, $site_description . ' - Solusi digital terpercaya untuk mahasiswa dan UMKM', 'index.php');
?>
    <style>
        .hero-bg { background: linear-gradient(90deg, #2563eb 0%, #7c3aed 100%); }
        .floating { animation: float 6s ease-in-out infinite; }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        /* Clients carousel */
        @keyframes logosScroll {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-50%); }
        }
        .logo-track {
            animation: logosScroll 6s linear infinite;
            will-change: transform;
        }
        .logo-track:hover { animation-play-state: paused; }
    </style>

    <!-- Hero Section -->
    <section class="hero-bg text-white py-20 lg:py-32 relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <h1 class="text-4xl md:text-6xl font-bold mb-6 animate-slide-up">
                        Wujudkan Impian Digital Anda
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-blue-100 animate-slide-up" style="animation-delay: 0.2s;">
                        <?= h($site_description) ?> dengan teknologi modern dan harga terjangkau
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 mb-12 animate-slide-up" style="animation-delay: 0.4s;">
                        <a href="#audience" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-rocket mr-2"></i>Mulai Project
                        </a>
                        <a href="portfolio.php" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-all duration-300">
                            <i class="fas fa-eye mr-2"></i>Lihat Portfolio
                        </a>
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center animate-slide-up" style="animation-delay: 0.6s;">
                        <?php if (getSetting('hero_show_students_stat', '1') == '1'): ?>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4">
                            <div class="text-2xl font-bold"><?= getSetting('hero_students_count', '50') ?>+</div>
                            <div class="text-sm text-blue-100"><?= getSetting('hero_students_label', 'Mahasiswa Puas') ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (getSetting('hero_show_businesses_stat', '1') == '1'): ?>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4">
                            <div class="text-2xl font-bold"><?= getSetting('hero_businesses_count', '25') ?>+</div>
                            <div class="text-sm text-blue-100"><?= getSetting('hero_businesses_label', 'Bisnis Kecil') ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (getSetting('hero_show_experience_stat', '1') == '1'): ?>
                        <div class="bg-white/20 backdrop-blur-sm rounded-lg p-4">
                            <div class="text-2xl font-bold"><?= getSetting('hero_experience_count', '3') ?>+</div>
                            <div class="text-sm text-blue-100"><?= getSetting('hero_experience_label', 'Tahun Pengalaman') ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center floating">
                    <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
                        <i class="fas fa-laptop-code text-6xl mb-6 text-blue-200"></i>
                        <h3 class="text-2xl font-bold mb-4">Harga Bisa Dinegosiasi</h3>
                        <p class="text-blue-100 mb-4">Project Sesuai Kebutuhan Anda</p>
                        <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan layanan website" 
                           target="_blank" 
                           class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors inline-flex items-center">
                            <i class="fab fa-whatsapp mr-2"></i>Konsultasi Gratis
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    

    <!-- Audience Offerings (Homepage) -->
    <?php
    $audienceCards = [];
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
                $audienceCards[] = [
                    'slug' => $r['audience_slug'] ?: preg_replace('/[^a-z0-9\-]/', '-', strtolower($r['name'])),
                    'title' => $r['name'],
                    'subtitle' => $r['audience_subtitle'] ?: '',
                    'icon' => $r['icon'] ?: 'tags',
                    'features' => $features,
                    'wa_text' => $r['audience_wa_text'] ?: ('Halo, saya tertarik: ' . $r['name']),
                    'service_id' => (int)$r['id'],
                ];
            }
        } catch (Throwable $e) { /* ignore */ }
    }
    ?>
    <?php if (!empty($audienceCards)): ?>
    <section id="audience" class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900">Untuk Siapa Layanan Kami?</h2>
                <p class="text-gray-600 mt-3 max-w-3xl mx-auto">Pilih paket yang tepat sesuai kebutuhan Anda. Harga fleksibel dan bisa dinego.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($audienceCards as $item): 
                    $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($item['slug'] ?? 'offer'));
                    $icon = h($item['icon'] ?? 'tags');
                    $title = h($item['title'] ?? 'Paket');
                    $subtitle = h($item['subtitle'] ?? '');
                    $price_label = '';
                    $usedStarting = false;
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
                            } else { $usedStarting = true; }
                            if ($minPrice !== null) { $price_label = 'Mulai dari Rp ' . number_format($minPrice, 0, ',', '.'); }
                        } catch (Throwable $e) { /* ignore */ }
                    }
                ?>
                <div class="bg-white rounded-2xl p-6 border border-gray-100">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center mr-3">
                            <i class="fas fa-<?= $icon ?>"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-0"><?= $title ?></h3>
                    </div>
                    <?php if ($subtitle): ?>
                    <p class="text-sm text-gray-600 mb-3"><?= $subtitle ?></p>
                    <?php endif; ?>
                    <?php if (!empty($item['features'])): ?>
                    <ul class="text-sm text-gray-700 mb-3 list-disc pl-5">
                        <?php foreach (array_slice($item['features'], 0, 4) as $f): ?>
                        <li><?= h($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($price_label): ?>
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-blue-600 font-bold"><?= h($price_label) ?></div>
                        <?php if ($usedStarting): ?>
                            <span class="badge badge-info" style="background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:9999px;font-size:12px;">Starting Plan</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="flex gap-3">
                        <a href="services.php#<?= h($slug) ?>" class="flex-1 bg-gradient-to-r from-blue-600 to-purple-600 text-white py-2 px-4 rounded-lg font-semibold text-center">Detail</a>
                        <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=<?= urlencode($item['wa_text'] ?? ('Halo, saya tertarik: '.$title)) ?>"
                           target="_blank"
                           class="bg-green-600 text-white py-2 px-4 rounded-lg font-semibold">WA</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Portfolio -->
    <?php if (!empty($featured_portfolio)): ?>
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Portfolio Terbaru</h2>
                <p class="text-xl text-gray-600">Lihat hasil karya terbaik kami</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featured_portfolio as $portfolio): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2 animate-on-scroll">
                    <?php if ($portfolio['image_main']): ?>
                    <img src="<?= h(assetUrl($portfolio['image_main'])) ?>" alt="<?= h($portfolio['title']) ?>" class="w-full h-48 object-cover">
                    <?php endif; ?>
                    <div class="p-6">
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold mb-3 inline-block">
                            <?= h($portfolio['category']) ?>
                        </span>
                        <h3 class="text-xl font-bold text-gray-900 mb-2"><?= h($portfolio['title']) ?></h3>
                        <p class="text-gray-600 mb-4"><?= h($portfolio['short_description']) ?></p>
                        <a href="portfolio.php" class="text-blue-600 font-semibold hover:text-blue-700 transition-colors">
                            Lihat Detail <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="portfolio.php" class="bg-blue-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Lihat Semua Portfolio
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Testimonials -->
    <?php if (!empty($featured_testimonials)): ?>
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Apa Kata Klien</h2>
                <p class="text-xl text-gray-600">Testimoni dari klien yang puas dengan layanan kami</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($featured_testimonials as $testimonial): ?>
                <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-8 animate-on-scroll">
                    <div class="flex items-center mb-4">
                        <?php if ($testimonial['client_image']): ?>
                        <img src="<?= h(assetUrl($testimonial['client_image'])) ?>" alt="<?= h($testimonial['client_name']) ?>" class="w-12 h-12 rounded-full object-cover mr-4">
                        <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <h4 class="font-bold text-gray-900"><?= h($testimonial['client_name']) ?></h4>
                            <p class="text-sm text-gray-600"><?= h($testimonial['client_position']) ?></p>
                        </div>
                    </div>
                    
                    <?php if ($testimonial['rating']): ?>
                    <div class="flex mb-4">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star <?= $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    
                    <blockquote class="text-gray-700 italic">
                        "<?= h($testimonial['content']) ?>"
                    </blockquote>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="testimonials.php" class="bg-blue-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Lihat Semua Testimoni
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Clients (Carousel) -->
    <?php if (!empty($clients)): ?>
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Dipercaya Oleh</h2>
            </div>

            <div class="relative overflow-hidden">
                <div class="flex whitespace-nowrap">
                    <!-- Track 1 -->
                    <div class="logo-track flex items-center gap-12 pr-12">
                        <?php foreach ($clients as $client): ?>
                        <div class="inline-flex items-center justify-center">
                            <?php if ($client['logo']): ?>
                            <img src="<?= h(assetUrl($client['logo'])) ?>" alt="<?= h($client['name']) ?>" class="h-12 w-auto mx-6 grayscale hover:grayscale-0 transition-all duration-300">
                            <?php else: ?>
                            <span class="mx-6 text-gray-600 font-semibold text-sm"><?= h($client['name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Track 2 (duplicate for seamless loop) -->
                    <div class="logo-track flex items-center gap-12 pr-12" aria-hidden="true">
                        <?php foreach ($clients as $client): ?>
                        <div class="inline-flex items-center justify-center">
                            <?php if ($client['logo']): ?>
                            <img src="<?= h(assetUrl($client['logo'])) ?>" alt="<?= h($client['name']) ?>" class="h-12 w-auto mx-6 grayscale hover:grayscale-0 transition-all duration-300">
                            <?php else: ?>
                            <span class="mx-6 text-gray-600 font-semibold text-sm"><?= h($client['name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Latest Blog -->
    <?php if (!empty($latest_posts)): ?>
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Artikel Terbaru</h2>
                <p class="text-xl text-gray-600">Tips dan insight terbaru tentang teknologi</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($latest_posts as $post): ?>
                <article class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-all duration-300 animate-on-scroll">
                    <?php if ($post['featured_image']): ?>
                    <img src="<?= h(assetUrl($post['featured_image'])) ?>" alt="<?= h($post['title']) ?>" class="w-full h-48 object-cover">
                    <?php endif; ?>
                    <div class="p-6">
                        <?php if ($post['category']): ?>
                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold mb-3 inline-block">
                            <?= h($post['category']) ?>
                        </span>
                        <?php endif; ?>
                        <h3 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2">
                            <a href="blog-detail.php?slug=<?= h($post['slug']) ?>" class="hover:text-blue-600 transition-colors">
                                <?= h($post['title']) ?>
                            </a>
                        </h3>
                        <p class="text-gray-600 mb-4 line-clamp-3"><?= h($post['excerpt']) ?></p>
                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <span><?= date('d M Y', strtotime($post['published_at'])) ?></span>
                            <span><?= number_format($post['view_count']) ?> views</span>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-12">
                <a href="blog.php" class="bg-blue-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Lihat Semua Artikel
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Siap Memulai Project Impian Anda?</h2>
            <p class="text-xl mb-8 text-blue-100">Konsultasikan kebutuhan digital Anda dengan tim ahli kami</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-envelope mr-2"></i>Konsultasi Gratis
                </a>
                <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan layanan digital" 
                   target="_blank" 
                   class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp Sekarang
                </a>
            </div>
        </div>
    </section>

    <?php echo renderPageEnd(); ?>
