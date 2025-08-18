<?php
require_once __DIR__ . '/includes/layout.php';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Build query
$where_conditions = ["status = 'published'"];
$params = [];

if ($search) {
    $where_conditions[] = "(title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($category) {
    $where_conditions[] = "category = ?";
    $params[] = $category;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM blog_posts WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_posts = $count_stmt->fetchColumn();
    
    // Get posts
    $sql = "SELECT * FROM blog_posts WHERE $where_clause ORDER BY is_featured DESC, published_at DESC LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories
    $cat_stmt = $pdo->prepare("SELECT DISTINCT category FROM blog_posts WHERE status = 'published' AND category IS NOT NULL ORDER BY category");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $posts = [];
    $categories = [];
    $total_posts = 0;
}

$total_pages = ceil($total_posts / $per_page);
$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
echo renderPageStart('Blog - ' . $site_name, 'Artikel dan tips terbaru tentang teknologi - ' . $site_description, 'blog.php');
?>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .blog-card { transition: all 0.3s ease; }
        .blog-card:hover { transform: translateY(-5px); }
        .search-animation { transition: all 0.3s ease; }
        .search-animation:focus { transform: scale(1.02); }
    </style>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Blog & Artikel</h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">Tips, tutorial, dan insight terbaru tentang teknologi dan bisnis digital</p>
            <div class="flex justify-center items-center space-x-8 mt-12">
                <div class="text-center">
                    <div class="text-3xl font-bold"><span id="posts-count"><?= $total_posts ?></span>+</div>
                    <div class="text-blue-100">Artikel</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold"><?= count($categories) ?>+</div>
                    <div class="text-blue-100">Kategori</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">Weekly</div>
                    <div class="text-blue-100">Update</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search and Filter -->
    <section class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gray-50 rounded-2xl p-8">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" name="search" value="<?= h($search) ?>" 
                                   placeholder="Cari artikel..." 
                                   class="search-animation w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="md:w-64">
                        <select name="category" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Semua Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                <?= h($cat) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Cari
                    </button>
                    <?php if ($search || $category): ?>
                    <a href="blog.php" class="bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-700 transition-colors text-center">
                        <i class="fas fa-times mr-2"></i>Reset
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>

    <!-- Blog Posts -->
    <section class="py-12 bg-gray-50">
        <div id="blog-content" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <?php if (!empty($posts)): ?>
            <div id="blog-posts-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($posts as $index => $post): ?>
                <article class="blog-card bg-white rounded-xl shadow-lg overflow-hidden" style="animation: slideUp 0.6s ease-out <?= $index * 0.1 ?>s both;">
                    <!-- Featured Image -->
                    <?php if ($post['featured_image']): ?>
                    <div class="h-48 overflow-hidden">
                        <img src="<?= h(assetUrl($post['featured_image'])) ?>" alt="<?= h($post['title']) ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
                    </div>
                    <?php endif; ?>
                    
                    <div class="p-6">
                        <!-- Category & Featured Badge -->
                        <div class="flex items-center justify-between mb-3">
                            <?php if ($post['category']): ?>
                            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold">
                                <?= h($post['category']) ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($post['is_featured']): ?>
                            <span class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
                                <i class="fas fa-star mr-1"></i>Featured
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Title -->
                        <h2 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2 hover:text-blue-600 transition-colors">
                            <a href="blog-detail.php?slug=<?= h($post['slug']) ?>">
                                <?= h($post['title']) ?>
                            </a>
                        </h2>
                        
                        <!-- Excerpt -->
                        <p class="text-gray-600 mb-4 line-clamp-3">
                            <?= h($post['excerpt'] ?: substr(strip_tags($post['content']), 0, 150) . '...') ?>
                        </p>
                        
                        <!-- Meta Info -->
                        <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                            <div class="flex items-center">
                                <i class="fas fa-calendar mr-2"></i>
                                <?= date('d M Y', strtotime($post['published_at'])) ?>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-eye mr-2"></i>
                                <?= number_format($post['view_count']) ?> views
                            </div>
                        </div>
                        
                        <!-- Tags -->
                        <?php if ($post['tags']): ?>
                        <div class="mb-4">
                            <?php
                            $tags = json_decode($post['tags'], true) ?: [];
                            foreach (array_slice($tags, 0, 3) as $tag):
                            ?>
                            <span class="inline-block bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs mr-2 mb-1">
                                #<?= h($tag) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Read More Button -->
                        <a href="blog-detail.php?slug=<?= h($post['slug']) ?>" 
                           class="inline-flex items-center bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
                            Baca Selengkapnya
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div id="blog-pagination" class="flex justify-center mt-12">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . urlencode($category) : '' ?>" 
                       class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . urlencode($category) : '' ?>" 
                       class="px-4 py-2 <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' ?> rounded-lg transition-colors">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category ? '&category=' . urlencode($category) : '' ?>" 
                       class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- Empty State -->
            <div id="blog-empty" class="text-center py-20">
                <i class="fas fa-newspaper text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">
                    <?= $search || $category ? 'Artikel Tidak Ditemukan' : 'Belum Ada Artikel' ?>
                </h3>
                <p class="text-gray-600 mb-8">
                    <?= $search || $category ? 'Coba gunakan kata kunci atau kategori lain.' : 'Artikel terbaru akan segera dipublikasikan.' ?>
                </p>
                <?php if ($search || $category): ?>
                <a href="blog.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Lihat Semua Artikel
                </a>
                <?php else: ?>
                <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Hubungi Kami
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Newsletter Subscription -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Dapatkan Update Terbaru</h2>
            <p class="text-xl mb-8 text-blue-100">Berlangganan newsletter untuk mendapatkan artikel dan tips terbaru</p>
            <form class="flex flex-col sm:flex-row gap-4 max-w-md mx-auto">
                <input type="email" placeholder="Email Anda" required
                       class="flex-1 px-4 py-3 rounded-lg text-gray-900 focus:ring-2 focus:ring-white focus:outline-none">
                <button type="submit" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i>Subscribe
                </button>
            </form>
        </div>
    </section>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });

        // Search animation
        document.querySelector('input[name="search"]').addEventListener('focus', function() {
            this.parentElement.classList.add('ring-2', 'ring-blue-500');
        });

        document.querySelector('input[name="search"]').addEventListener('blur', function() {
            this.parentElement.classList.remove('ring-2', 'ring-blue-500');
        });

        // Auto-submit search after typing (debounced)
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    this.form.submit();
                }
            }, 1000);
        });

        // Hydrate blog posts from API
        (function hydrateBlog() {
            const contentRoot = document.getElementById('blog-content');
            const postsCountEl = document.getElementById('posts-count');
            if (!contentRoot) return;
            const params = new URLSearchParams(window.location.search);
            const page = params.get('page') || '1';
            const search = params.get('search') || '';
            const category = params.get('category') || '';
            const apiParams = new URLSearchParams();
            if (page) apiParams.set('page', page);
            if (search) apiParams.set('search', search);
            if (category) apiParams.set('category', category);
            fetch('api/blog_list.php?' + apiParams.toString(), { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (!data?.success || !Array.isArray(data.items)) return;
                    const items = data.items;
                    const meta = data.meta || { total: items.length, page: 1, per_page: items.length, total_pages: 1 };
                    if (postsCountEl && typeof meta.total === 'number') postsCountEl.textContent = String(meta.total);

                    if (items.length === 0) {
                        contentRoot.innerHTML = `
                            <div id="blog-empty" class="text-center py-20">
                                <i class=\"fas fa-newspaper text-6xl text-gray-300 mb-6\"></i>
                                <h3 class=\"text-2xl font-bold text-gray-900 mb-4\">${(search || category) ? 'Artikel Tidak Ditemukan' : 'Belum Ada Artikel'}</h3>
                                <p class=\"text-gray-600 mb-8\">${(search || category) ? 'Coba gunakan kata kunci atau kategori lain.' : 'Artikel terbaru akan segera dipublikasikan.'}</p>
                                ${search || category ? '<a href=\"blog.php\" class=\"bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors\">Lihat Semua Artikel</a>' : '<a href=\"contact.php\" class=\"bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors\">Hubungi Kami</a>'}
                            </div>`;
                        return;
                    }

                    const postCards = items.map((post, idx) => {
                        const fi = window.normalizeImageSrc(post.featured_image);
                        const img = fi ? `<div class=\"h-48 overflow-hidden\"><img src=\"${fi}\" alt=\"${post.title}\" class=\"w-full h-full object-cover hover:scale-105 transition-transform duration-300\"></div>` : '';
                        const badge = Number(post.is_featured) ? `<span class=\"bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-3 py-1 rounded-full text-sm font-semibold\"><i class=\"fas fa-star mr-1\"></i>Featured</span>` : '';
                        let tags = [];
                        try { tags = post.tags ? JSON.parse(post.tags) || [] : []; } catch { tags = []; }
                        const tagsHtml = tags.slice(0,3).map(t => `<span class=\"inline-block bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs mr-2 mb-1\">#${t}</span>`).join('');
                        const dateStr = post.published_at ? new Date(post.published_at.replace(' ', 'T')).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '';
                        const views = post.view_count ? Number(post.view_count).toLocaleString('id-ID') : '0';
                        const categoryHtml = post.category ? `<span class=\"bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold\">${post.category}</span>` : '';
                        const excerpt = post.excerpt || post.content_preview || '';
                        return `<article class=\"blog-card bg-white rounded-xl shadow-lg overflow-hidden\" style=\"animation: slideUp 0.6s ease-out ${idx*0.1}s both;\">${img}
                            <div class=\"p-6\">
                                <div class=\"flex items-center justify-between mb-3\">${categoryHtml}${badge}</div>
                                <h2 class=\"text-xl font-bold text-gray-900 mb-3 line-clamp-2 hover:text-blue-600 transition-colors\"><a href=\"blog-detail.php?slug=${post.slug}\">${post.title}</a></h2>
                                <p class=\"text-gray-600 mb-4 line-clamp-3\">${excerpt}</p>
                                <div class=\"flex items-center justify-between text-sm text-gray-500 mb-4\">
                                    <div class=\"flex items-center\"><i class=\"fas fa-calendar mr-2\"></i>${dateStr}</div>
                                    <div class=\"flex items-center\"><i class=\"fas fa-eye mr-2\"></i>${views} views</div>
                                </div>
                                ${tagsHtml ? `<div class=\"mb-4\">${tagsHtml}</div>` : ''}
                                <a href=\"blog-detail.php?slug=${post.slug}\" class=\"inline-flex items-center bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1\">Baca Selengkapnya<i class=\"fas fa-arrow-right ml-2\"></i></a>
                            </div>
                        </article>`;
                    }).join('');

                    // Build pagination (links go to SSR routes for simplicity)
                    function buildPageLink(p) {
                        const sp = new URLSearchParams(window.location.search);
                        sp.set('page', String(p));
                        return `?${sp.toString()}`;
                    }
                    let paginationHtml = '';
                    if (meta.total_pages && meta.total_pages > 1) {
                        const p = Number(meta.page) || 1;
                        const totalPages = Number(meta.total_pages);
                        const prev = p > 1 ? `<a href=\"${buildPageLink(p-1)}\" class=\"px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors\"><i class=\"fas fa-chevron-left\"></i></a>` : '';
                        const next = p < totalPages ? `<a href=\"${buildPageLink(p+1)}\" class=\"px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors\"><i class=\"fas fa-chevron-right\"></i></a>` : '';
                        const start = Math.max(1, p - 2);
                        const end = Math.min(totalPages, p + 2);
                        const nums = Array.from({length: end - start + 1}, (_,i) => {
                            const n = start + i;
                            const active = n === p ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50';
                            return `<a href=\"${buildPageLink(n)}\" class=\"px-4 py-2 ${active} rounded-lg transition-colors\">${n}</a>`;
                        }).join('');
                        paginationHtml = `<div id=\"blog-pagination\" class=\"flex justify-center mt-12\"><nav class=\"flex items-center space-x-2\">${prev}${nums}${next}</nav></div>`;
                    }

                    contentRoot.innerHTML = `<div id=\"blog-posts-grid\" class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8\">${postCards}</div>${paginationHtml}`;
                })
                .catch(() => {});
        })();
    </script>
    <?php echo renderPageEnd(); ?>
