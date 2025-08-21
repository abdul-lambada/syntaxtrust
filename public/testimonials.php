<?php
require_once __DIR__ . '/includes/layout.php';

// Get testimonials
try {
    $stmt = $pdo->prepare("
        SELECT t.*, s.name as service_name 
        FROM testimonials t 
        LEFT JOIN services s ON t.service_id = s.id 
        WHERE t.is_active = 1 
        ORDER BY t.is_featured DESC, t.sort_order ASC, t.created_at DESC
    ");
    $stmt->execute();
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $testimonials = [];
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
$extraHead = '<link href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" rel="stylesheet">'
           . '<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>';
echo renderPageStart('Testimoni - ' . $site_name, 'Testimoni klien yang puas dengan layanan kami - ' . $site_description, 'testimonials.php', $extraHead);
?>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .testimonial-card { transition: all 0.3s ease; }
        .testimonial-card:hover { transform: translateY(-5px); }
        .swiper-pagination-bullet-active { background: #3B82F6 !important; }
        /* Position pagination on the right side vertically */
        .testimonial-swiper { position: relative; padding-right: 28px; }
        .testimonial-swiper .swiper-pagination {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .testimonial-swiper .swiper-pagination-bullet { margin: 0 !important; }
    </style>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Testimoni Klien</h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">Dengarkan pengalaman klien yang telah mempercayai layanan kami</p>
            <div class="flex justify-center items-center space-x-8 mt-12">
                <div class="text-center">
                    <div class="text-3xl font-bold"><span id="happy-count"><?= count($testimonials) ?></span>+</div>
                    <div class="text-blue-100">Happy Clients</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">4.9</div>
                    <div class="text-blue-100">Rating</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">100%</div>
                    <div class="text-blue-100">Satisfaction</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Testimonials Carousel -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Testimoni Unggulan</h2>
                <p class="text-xl text-gray-600">Pengalaman terbaik dari klien-klien kami</p>
            </div>
            
            <div class="swiper testimonial-swiper">
                <div id="testimonials-swiper-wrapper" class="swiper-wrapper">
                    <?php foreach (array_filter($testimonials, fn($t) => $t['is_featured']) as $testimonial): ?>
                    <div class="swiper-slide">
                        <div class="testimonial-card bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-8 mx-4 shadow-lg">
                            <div class="flex items-center mb-6">
                                <?php if ($testimonial['client_image']): ?>
                                <img src="<?= h(assetUrl($testimonial['client_image'])) ?>" alt="<?= h($testimonial['client_name']) ?>" class="w-16 h-16 rounded-full object-cover mr-4">
                                <?php else: ?>
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-user text-white text-xl"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900"><?= h($testimonial['client_name']) ?></h3>
                                    <p class="text-gray-600"><?= h($testimonial['client_position']) ?></p>
                                    <?php if ($testimonial['client_company']): ?>
                                    <p class="text-blue-600 font-semibold"><?= h($testimonial['client_company']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($testimonial['rating']): ?>
                            <div class="flex items-center mb-4">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                                <?php endfor; ?>
                                <span class="ml-2 text-gray-600">(<?= $testimonial['rating'] ?>/5)</span>
                            </div>
                            <?php endif; ?>
                            <blockquote class="text-gray-700 text-lg leading-relaxed mb-6 italic">"<?= h($testimonial['content']) ?>"</blockquote>
                            <?php if ($testimonial['project_name'] || $testimonial['service_name']): ?>
                            <div class="flex flex-wrap gap-2">
                                <?php if ($testimonial['project_name']): ?>
                                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold"><?= h($testimonial['project_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($testimonial['service_name']): ?>
                                <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-semibold"><?= h($testimonial['service_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination mt-8"></div>
                <div class="swiper-button-next text-blue-600"></div>
                <div class="swiper-button-prev text-blue-600"></div>
            </div>
        </div>
    </section>

    <!-- All Testimonials Grid -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Semua Testimoni</h2>
                <p class="text-xl text-gray-600">Kepuasan klien adalah prioritas utama kami</p>
            </div>
            
            <div id="testimonials-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($testimonials as $index => $testimonial): ?>
                <div class="testimonial-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300" style="animation: slideUp 0.6s ease-out <?= $index * 0.1 ?>s both;">
                    <div class="flex items-center mb-4">
                        <?php if ($testimonial['client_image']): ?>
                        <img src="<?= h(assetUrl($testimonial['client_image'])) ?>" alt="<?= h($testimonial['client_name']) ?>" class="w-12 h-12 rounded-full object-cover mr-3">
                        <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <?php endif; ?>
                        <div>
                            <h3 class="font-bold text-gray-900"><?= h($testimonial['client_name']) ?></h3>
                            <p class="text-sm text-gray-600"><?= h($testimonial['client_position']) ?></p>
                            <?php if ($testimonial['client_company']): ?>
                            <p class="text-sm text-blue-600 font-semibold"><?= h($testimonial['client_company']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($testimonial['rating']): ?>
                    <div class="flex items-center mb-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star text-sm <?= $i <= $testimonial['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                        <?php endfor; ?>
                        <span class="ml-2 text-sm text-gray-600">(<?= $testimonial['rating'] ?>)</span>
                    </div>
                    <?php endif; ?>
                    
                    <blockquote class="text-gray-700 leading-relaxed mb-4 italic">
                        "<?= h(strlen($testimonial['content']) > 150 ? substr($testimonial['content'], 0, 150) . '...' : $testimonial['content']) ?>"
                    </blockquote>
                    
                    <?php if ($testimonial['project_name'] || $testimonial['service_name']): ?>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($testimonial['project_name']): ?>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold">
                            <?= h($testimonial['project_name']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($testimonial['service_name']): ?>
                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs font-semibold">
                            <?= h($testimonial['service_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($testimonials)): ?>
            <div class="text-center py-20">
                <i class="fas fa-comments text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Testimoni Segera Hadir</h3>
                <p class="text-gray-600 mb-8">Testimoni dari klien yang puas akan segera ditampilkan di sini.</p>
                <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Jadilah Klien Pertama
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Ingin Menjadi Klien Berikutnya?</h2>
            <p class="text-xl mb-8 text-blue-100">Bergabunglah dengan klien-klien yang telah merasakan kepuasan layanan kami</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-envelope mr-2"></i>
                    Mulai Project
                </a>
                <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik dengan layanan Anda" 
                   target="_blank" 
                   class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>
                    Chat Sekarang
                </a>
            </div>
        </div>
    </section>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });

        // Swiper initializer (call after DOM render)
        function initTestimonialsSwiper() {
            if (window.__testimonialSwiper) { try { window.__testimonialSwiper.destroy(true, true); } catch (e) {} }
            window.__testimonialSwiper = new Swiper('.testimonial-swiper', {
                slidesPerView: 1,
                spaceBetween: 30,
                loop: true,
                // Faster autoplay and smoother transition
                autoplay: { delay: 1800, disableOnInteraction: false },
                speed: 500,
                effect: 'slide',
                direction: 'horizontal',
                centeredSlides: false,
                initialSlide: 0,
                loopAdditionalSlides: 2,
                // Make sure Swiper respects container sizing and doesn't re-center
                observer: true,
                observeParents: true,
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                breakpoints: { 768: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } }
            });
        }
        // Initialize for SSR content
        initTestimonialsSwiper();

        // Hydrate testimonials from API
        (function hydrateTestimonials() {
            const grid = document.getElementById('testimonials-grid');
            const wrapper = document.getElementById('testimonials-swiper-wrapper');
            const happyCount = document.getElementById('happy-count');
            fetch('api/testimonials_list.php', { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (!data?.success || !Array.isArray(data.items)) return;
                    const items = data.items;
                    if (happyCount) happyCount.textContent = String(items.length);
                    const featured = items.filter(t => Number(t.is_featured) === 1);
                    // Render featured into Swiper wrapper
                    if (wrapper) {
                        wrapper.innerHTML = featured.map(t => {
                            const img = t.client_image ? `<img src="${window.normalizeImageSrc(t.client_image)}" alt="${t.client_name}" class="w-16 h-16 rounded-full object-cover mr-4">` : `
                                <div class=\"w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-4\"><i class=\"fas fa-user text-white text-xl\"></i></div>`;
                            const company = t.client_company ? `<p class=\"text-blue-600 font-semibold\">${t.client_company}</p>` : '';
                            const rating = t.rating ? `<div class=\"flex items-center mb-4\">${Array.from({length:5},(_,i)=>`<i class=\"fas fa-star ${i<Number(t.rating)?'text-yellow-400':'text-gray-300'}\"></i>`).join('')}<span class=\"ml-2 text-gray-600\">(${t.rating}/5)</span></div>` : '';
                            const projServ = (t.project_name || t.service_name) ? `<div class=\"flex flex-wrap gap-2\">${t.project_name?`<span class=\"bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold\">${t.project_name}</span>`:''}${t.service_name?`<span class=\"bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-semibold\">${t.service_name}</span>`:''}</div>` : '';
                            return `<div class=\"swiper-slide\"><div class=\"testimonial-card bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-8 mx-4 shadow-lg\">\n<div class=\"flex items-center mb-6\">${img}<div><h3 class=\"text-xl font-bold text-gray-900\">${t.client_name}</h3><p class=\"text-gray-600\">${t.client_position||''}</p>${company}</div></div>${rating}<blockquote class=\"text-gray-700 text-lg leading-relaxed mb-6 italic\">\"${t.content}\"</blockquote>${projServ}</div></div>`;
                        }).join('');
                        initTestimonialsSwiper();
                    }
                    // Render all testimonials grid
                    if (grid) {
                        grid.innerHTML = items.map((t, idx) => {
                            const img = t.client_image ? `<img src=\"${window.normalizeImageSrc(t.client_image)}\" alt=\"${t.client_name}\" class=\"w-12 h-12 rounded-full object-cover mr-3\">` : `<div class=\"w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mr-3\"><i class=\"fas fa-user text-white\"></i></div>`;
                            const company = t.client_company ? `<p class=\"text-sm text-blue-600 font-semibold\">${t.client_company}</p>` : '';
                            const rating = t.rating ? `<div class=\"flex items-center mb-3\">${Array.from({length:5},(_,i)=>`<i class=\"fas fa-star text-sm ${i<Number(t.rating)?'text-yellow-400':'text-gray-300'}\"></i>`).join('')}<span class=\"ml-2 text-sm text-gray-600\">(${t.rating})</span></div>` : '';
                            const chips = (t.project_name || t.service_name) ? `<div class=\"flex flex-wrap gap-2\">${t.project_name?`<span class=\"bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-semibold\">${t.project_name}</span>`:''}${t.service_name?`<span class=\"bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs font-semibold\">${t.service_name}</span>`:''}</div>` : '';
                            const excerpt = t.content && t.content.length>150 ? `${t.content.substring(0,150)}...` : (t.content||'');
                            return `<div class=\"testimonial-card bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-all duration-300\" style=\"animation: slideUp 0.6s ease-out ${idx*0.1}s both;\">\n<div class=\"flex items-center mb-4\">${img}<div><h3 class=\"font-bold text-gray-900\">${t.client_name}</h3><p class=\"text-sm text-gray-600\">${t.client_position||''}</p>${company}</div></div>${rating}<blockquote class=\"text-gray-700 leading-relaxed mb-4 italic\">\"${excerpt}\"</blockquote>${chips}</div>`;
                        }).join('');
                    }
                })
                .catch(() => {});
        })();
    </script>
    <?php echo renderPageEnd(); ?>
