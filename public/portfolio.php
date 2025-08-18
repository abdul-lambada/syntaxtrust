<?php
require_once __DIR__ . '/includes/layout.php';

// Get portfolio items
try {
    $stmt = $pdo->prepare("
        SELECT * FROM portfolio 
        WHERE is_active = 1 
        ORDER BY is_featured DESC, created_at DESC
    ");
    $stmt->execute();
    $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $portfolios = [];
}

// Get unique categories for filtering
$categories = array_unique(array_filter(array_column($portfolios, 'category')));

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
echo renderPageStart('Portfolio - ' . $site_name, 'Lihat portfolio project terbaru kami - ' . $site_description, 'portfolio.php');
?>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'zoom-in': 'zoomIn 0.3s ease-out',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes zoomIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .portfolio-item {
            transition: all 0.3s ease;
        }
        .portfolio-item:hover {
            transform: translateY(-5px);
        }
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
    

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6 animate-fade-in">
                Portfolio Kami
            </h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100 animate-slide-up">
                Lihat hasil karya terbaik yang telah kami buat untuk klien
            </p>
            <div class="flex flex-wrap justify-center gap-4 animate-slide-up">
                <div class="bg-white/20 backdrop-blur-sm rounded-lg px-6 py-3">
                    <div class="text-2xl font-bold"><span id="projects-count"><?= count($portfolios) ?></span>+</div>
                    <div class="text-sm text-blue-100">Project Selesai</div>
                </div>
                <div class="bg-white/20 backdrop-blur-sm rounded-lg px-6 py-3">
                    <div class="text-2xl font-bold"><span id="categories-count"><?= count($categories) ?></span>+</div>
                    <div class="text-sm text-blue-100">Kategori</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter Section -->
    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Filter Portfolio</h2>
                <p class="text-gray-600">Pilih kategori untuk melihat project spesifik</p>
            </div>
            
            <div id="portfolio-filters" class="flex flex-wrap justify-center gap-4 mb-12">
                <button class="filter-btn active px-6 py-3 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold transition-all hover:shadow-lg" data-filter="all">
                    Semua Project
                </button>
                <?php foreach ($categories as $category): ?>
                <button class="filter-btn px-6 py-3 rounded-full bg-gray-200 text-gray-700 font-semibold transition-all hover:bg-gray-300 hover:shadow-lg" data-filter="<?= h(strtolower($category)) ?>">
                    <?= h($category) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Portfolio Grid -->
    <section class="py-12 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div id="portfolio-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($portfolios as $portfolio): ?>
                <div class="portfolio-item bg-white rounded-xl shadow-lg overflow-hidden animate-fade-in" 
                     data-category="<?= h(strtolower($portfolio['category'] ?? '')) ?>">
                    <!-- Image -->
                    <div class="relative overflow-hidden group">
                        <?php if ($portfolio['image_main']): ?>
                        <img src="<?= h($portfolio['image_main']) ?>" 
                             alt="<?= h($portfolio['title']) ?>" 
                             class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-110">
                        <?php else: ?>
                        <div class="w-full h-64 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-image text-white text-4xl"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-60 transition-all duration-300 flex items-center justify-center">
                            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 space-x-4">
                                <button onclick="openModal(<?= $portfolio['id'] ?>)" 
                                        class="bg-white text-gray-900 px-4 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-eye mr-2"></i>Detail
                                </button>
                                <?php if ($portfolio['project_url']): ?>
                                <a href="<?= h($portfolio['project_url']) ?>" 
                                   target="_blank" 
                                   class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-external-link-alt mr-2"></i>Live
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Featured Badge -->
                        <?php if ($portfolio['is_featured']): ?>
                        <div class="absolute top-4 left-4 bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
                            <i class="fas fa-star mr-1"></i>Featured
                        </div>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <div class="absolute top-4 right-4">
                            <?php
                            $statusColors = [
                                'completed' => 'bg-green-500',
                                'ongoing' => 'bg-blue-500',
                                'planned' => 'bg-yellow-500'
                            ];
                            $statusColor = $statusColors[$portfolio['status']] ?? 'bg-gray-500';
                            ?>
                            <span class="<?= $statusColor ?> text-white px-3 py-1 rounded-full text-sm font-semibold capitalize">
                                <?= h($portfolio['status']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div class="p-6">
                        <!-- Category -->
                        <div class="mb-3">
                            <span class="bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full">
                                <?= h($portfolio['category'] ?? 'Uncategorized') ?>
                            </span>
                        </div>
                        
                        <!-- Title -->
                        <h3 class="text-xl font-bold text-gray-900 mb-2 line-clamp-2">
                            <?= h($portfolio['title']) ?>
                        </h3>
                        
                        <!-- Client -->
                        <?php if ($portfolio['client_name']): ?>
                        <p class="text-sm text-gray-600 mb-3">
                            <i class="fas fa-user mr-1"></i>
                            <?= h($portfolio['client_name']) ?>
                        </p>
                        <?php endif; ?>
                        
                        <!-- Description -->
                        <p class="text-gray-600 mb-4 line-clamp-3">
                            <?= h($portfolio['short_description'] ?: substr($portfolio['description'], 0, 120) . '...') ?>
                        </p>
                        
                        <!-- Technologies -->
                        <?php if ($portfolio['technologies']): ?>
                        <div class="mb-4">
                            <div class="flex flex-wrap gap-2">
                                <?php
                                $technologies = json_decode($portfolio['technologies'], true) ?: [];
                                foreach (array_slice($technologies, 0, 3) as $tech):
                                ?>
                                <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">
                                    <?= h($tech) ?>
                                </span>
                                <?php endforeach; ?>
                                <?php if (count($technologies) > 3): ?>
                                <span class="bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded">
                                    +<?= count($technologies) - 3 ?> more
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Action Button -->
                        <button onclick="openModal(<?= $portfolio['id'] ?>)" 
                                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 rounded-lg font-semibold hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                            Lihat Detail
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($portfolios)): ?>
            <div class="text-center py-20">
                <div class="max-w-md mx-auto">
                    <i class="fas fa-folder-open text-6xl text-gray-300 mb-6"></i>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Belum Ada Portfolio</h3>
                    <p class="text-gray-600 mb-8">Portfolio project akan segera ditampilkan di sini.</p>
                    <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                        Hubungi Kami
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Modal -->
    <div id="portfolio-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto animate-zoom-in">
            <div id="modal-content">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu?.classList.toggle('hidden');
        });

        // Portfolio filter functionality (re-bindable)
        function bindPortfolioFilters() {
            const filterBtns = document.querySelectorAll('.filter-btn');
            const portfolioItems = document.querySelectorAll('.portfolio-item');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    // Update active button
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    // Filter items
                    portfolioItems.forEach(item => {
                        const category = item.getAttribute('data-category');
                        if (filter === 'all' || category === filter) {
                            item.style.display = 'block';
                            item.classList.add('animate-fade-in');
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
        }

        // Initial bind for SSR content
        bindPortfolioFilters();

        // Client-side hydration from API
        (function hydratePortfolio() {
            const grid = document.getElementById('portfolio-grid');
            const filtersWrap = document.getElementById('portfolio-filters');
            const projectsCount = document.getElementById('projects-count');
            const categoriesCount = document.getElementById('categories-count');
            if (!grid || !filtersWrap) return;
            fetch('api/portfolio_list.php', { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (!data?.success || !Array.isArray(data.items)) return;
                    const items = data.items;
                    // Update counts
                    if (projectsCount) projectsCount.textContent = String(items.length);
                    const categories = Array.from(new Set(items.map(it => (it.category || '').toLowerCase()).filter(Boolean)));
                    if (categoriesCount) categoriesCount.textContent = String(categories.length);
                    // Render filters
                    filtersWrap.innerHTML = `
                        <button class="filter-btn active px-6 py-3 rounded-full bg-gradient-to-r from-blue-600 to-purple-600 text-white font-semibold transition-all hover:shadow-lg" data-filter="all">Semua Project</button>
                        ${categories.map(cat => `
                            <button class="filter-btn px-6 py-3 rounded-full bg-gray-200 text-gray-700 font-semibold transition-all hover:bg-gray-300 hover:shadow-lg" data-filter="${cat}">
                                ${cat.replace(/\b\w/g, c => c.toUpperCase())}
                            </button>
                        `).join('')}
                    `;
                    // Render grid
                    grid.innerHTML = items.map(p => {
                        const technologies = p.technologies ? (() => { try { return JSON.parse(p.technologies) || []; } catch { return []; } })() : [];
                        const statusColors = { completed: 'bg-green-500', ongoing: 'bg-blue-500', planned: 'bg-yellow-500' };
                        const statusColor = statusColors[p.status] || 'bg-gray-500';
                        const imgMain = p.image_main ? `<img src="${p.image_main}" alt="${p.title}" class="w-full h-64 object-cover transition-transform duration-300 group-hover:scale-110">` : `
                            <div class=\"w-full h-64 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center\">\n                                <i class=\"fas fa-image text-white text-4xl\"></i>\n                            </div>`;
                        const liveBtn = p.project_url ? `<a href="${p.project_url}" target="_blank" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors"><i class=\"fas fa-external-link-alt mr-2\"></i>Live</a>` : '';
                        const client = p.client_name ? `<p class=\"text-sm text-gray-600 mb-3\"><i class=\"fas fa-user mr-1\"></i>${p.client_name}</p>` : '';
                        const techChips = technologies.slice(0,3).map(t => `<span class=\"bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded\">${t}</span>`).join('');
                        const moreTech = technologies.length > 3 ? `<span class=\"bg-gray-100 text-gray-700 text-xs px-2 py-1 rounded\">+${technologies.length - 3} more</span>` : '';
                        const shortDesc = p.short_description || (p.description ? (p.description.substring(0,120) + '...') : '');
                        const category = (p.category || 'uncategorized');
                        return `
                        <div class=\"portfolio-item bg-white rounded-xl shadow-lg overflow-hidden animate-fade-in\" data-category=\"${category.toLowerCase()}\">
                            <div class=\"relative overflow-hidden group\">${imgMain}
                                <div class=\"absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-60 transition-all duration-300 flex items-center justify-center\">
                                    <div class=\"opacity-0 group-hover:opacity-100 transition-opacity duration-300 space-x-4\">
                                        <button onclick=\"openModal(${p.id})\" class=\"bg-white text-gray-900 px-4 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors\"><i class=\"fas fa-eye mr-2\"></i>Detail</button>
                                        ${liveBtn}
                                    </div>
                                </div>
                                ${p.is_featured ? `<div class=\"absolute top-4 left-4 bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-3 py-1 rounded-full text-sm font-semibold\"><i class=\"fas fa-star mr-1\"></i>Featured</div>` : ''}
                                <div class=\"absolute top-4 right-4\"><span class=\"${statusColor} text-white px-3 py-1 rounded-full text-sm font-semibold capitalize\">${p.status || ''}</span></div>
                            </div>
                            <div class=\"p-6\">
                                <div class=\"mb-3\"><span class=\"bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full\">${p.category || 'Uncategorized'}</span></div>
                                <h3 class=\"text-xl font-bold text-gray-900 mb-2 line-clamp-2\">${p.title}</h3>
                                ${client}
                                <p class=\"text-gray-600 mb-4 line-clamp-3\">${shortDesc}</p>
                                ${technologies.length ? `<div class=\"mb-4\"><div class=\"flex flex-wrap gap-2\">${techChips}${moreTech}</div></div>` : ''}
                                <button onclick=\"openModal(${p.id})\" class=\"w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 rounded-lg font-semibold hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1\">Lihat Detail</button>
                            </div>
                        </div>`;
                    }).join('');
                    // Re-bind filters on the newly rendered elements
                    bindPortfolioFilters();
                })
                .catch(() => {});
        })();

        // Modal functionality
        const modal = document.getElementById('portfolio-modal');
        const modalContent = document.getElementById('modal-content');

        function openModal(portfolioId) {
            // Fetch portfolio details via AJAX
            fetch(`api/get_portfolio.php?id=${portfolioId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalContent.innerHTML = generateModalContent(data.portfolio);
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function closeModal() {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function generateModalContent(portfolio) {
            const technologies = portfolio.technologies ? JSON.parse(portfolio.technologies) : [];
            const images = portfolio.images ? JSON.parse(portfolio.images) : [];
            
            return `
                <div class="relative">
                    <button onclick="closeModal()" class="absolute top-4 right-4 z-10 bg-white rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                    
                    ${portfolio.image_main ? `
                    <div class="relative h-64 md:h-80 overflow-hidden">
                        <img src="${portfolio.image_main}" alt="${portfolio.title}" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                    </div>
                    ` : ''}
                    
                    <div class="p-6 md:p-8">
                        <div class="mb-4">
                            <span class="bg-blue-100 text-blue-800 text-sm font-semibold px-3 py-1 rounded-full">
                                ${portfolio.category || 'Uncategorized'}
                            </span>
                        </div>
                        
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">${portfolio.title}</h2>
                        
                        ${portfolio.client_name ? `
                        <p class="text-gray-600 mb-4">
                            <i class="fas fa-user mr-2"></i>
                            <strong>Client:</strong> ${portfolio.client_name}
                        </p>
                        ` : ''}
                        
                        <div class="prose max-w-none mb-6">
                            <p class="text-gray-700 leading-relaxed">${portfolio.description || portfolio.short_description}</p>
                        </div>
                        
                        ${technologies.length > 0 ? `
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Technologies Used</h3>
                            <div class="flex flex-wrap gap-2">
                                ${technologies.map(tech => `
                                    <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm font-medium">
                                        ${tech}
                                    </span>
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        ${images.length > 0 ? `
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Gallery</h3>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                ${images.map(img => `
                                    <img src="${img}" alt="Gallery image" class="w-full h-32 object-cover rounded-lg hover:opacity-75 transition-opacity cursor-pointer">
                                `).join('')}
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="flex flex-wrap gap-4">
                            ${portfolio.project_url ? `
                            <a href="${portfolio.project_url}" target="_blank" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                Visit Website
                            </a>
                            ` : ''}
                            
                            ${portfolio.github_url ? `
                            <a href="${portfolio.github_url}" target="_blank" class="bg-gray-800 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-900 transition-colors">
                                <i class="fab fa-github mr-2"></i>
                                View Code
                            </a>
                            ` : ''}
                            
                            <a href="contact.php" class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                                <i class="fas fa-comments mr-2"></i>
                                Diskusi Project
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }

        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
    <?php echo renderPageEnd(); ?>
