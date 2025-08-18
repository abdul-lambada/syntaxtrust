<?php
require_once __DIR__ . '/includes/layout.php';

// Get post by slug
$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: blog.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published'");
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        header('HTTP/1.0 404 Not Found');
        include '404.php';
        exit;
    }
    
    // Update view count
    $update_stmt = $pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?");
    $update_stmt->execute([$post['id']]);
    
    // Get related posts
    $related_stmt = $pdo->prepare("
        SELECT * FROM blog_posts 
        WHERE status = 'published' AND id != ? AND category = ? 
        ORDER BY published_at DESC 
        LIMIT 3
    ");
    $related_stmt->execute([$post['id'], $post['category']]);
    $related_posts = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: blog.php');
    exit;
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$tags = $post['tags'] ? json_decode($post['tags'], true) : [];

$extraHead = '';
$extraHead .= '<meta property="og:title" content="' . h($post['title']) . '">';
$extraHead .= '<meta property="og:description" content="' . h($post['excerpt']) . '">';
$extraHead .= '<meta property="og:image" content="' . h($post['featured_image']) . '">';
$extraHead .= '<meta property="og:type" content="article">';
$extraHead .= '<link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet">';
$extraHead .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>';
$extraHead .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>';

echo renderPageStart(($post['meta_title'] ?: $post['title']) . ' - ' . $site_name, ($post['meta_description'] ?: $post['excerpt']), 'blog.php', $extraHead);
?>
    <style>
        .prose { max-width: none; }
        .prose img { border-radius: 0.5rem; margin: 1.5rem 0; }
        .prose h2, .prose h3 { color: #1f2937; margin-top: 2rem; }
        .reading-progress { height: 4px; background: linear-gradient(90deg, #3B82F6, #8B5CF6); }
        .share-sticky { position: fixed; left: 20px; top: 50%; transform: translateY(-50%); }
        @media (max-width: 1024px) { .share-sticky { display: none; } }
    </style>
    <!-- Reading Progress Bar -->
    <div id="reading-progress" class="reading-progress fixed top-0 left-0 z-50 transition-all duration-300" style="width: 0%"></div>

    

    <!-- Sticky Share Buttons -->
    <div class="share-sticky">
        <div class="bg-white rounded-lg shadow-lg p-3 space-y-3">
            <button onclick="sharePost('facebook')" class="block w-10 h-10 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fab fa-facebook-f"></i>
            </button>
            <button onclick="sharePost('twitter')" class="block w-10 h-10 bg-sky-500 text-white rounded-lg hover:bg-sky-600 transition-colors">
                <i class="fab fa-twitter"></i>
            </button>
            <button onclick="sharePost('linkedin')" class="block w-10 h-10 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors">
                <i class="fab fa-linkedin-in"></i>
            </button>
            <button onclick="sharePost('whatsapp')" class="block w-10 h-10 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                <i class="fab fa-whatsapp"></i>
            </button>
            <button onclick="copyLink()" class="block w-10 h-10 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-link"></i>
            </button>
        </div>
    </div>

    <!-- Article Header -->
    <article class="bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Breadcrumb -->
            <nav class="mb-8">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="index.php" class="hover:text-blue-600">Beranda</a></li>
                    <li><i class="fas fa-chevron-right"></i></li>
                    <li><a href="blog.php" class="hover:text-blue-600">Blog</a></li>
                    <li><i class="fas fa-chevron-right"></i></li>
                    <li class="text-gray-900"><?= h($post['title']) ?></li>
                </ol>
            </nav>

            <!-- Article Meta -->
            <div class="mb-8">
                <?php if ($post['category']): ?>
                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-semibold mb-4 inline-block">
                    <?= h($post['category']) ?>
                </span>
                <?php endif; ?>
                
                <h1 class="text-3xl md:text-5xl font-bold text-gray-900 mb-6 leading-tight">
                    <?= h($post['title']) ?>
                </h1>
                
                <div class="flex items-center justify-between text-gray-600 mb-6">
                    <div class="flex items-center space-x-6">
                        <div class="flex items-center">
                            <i class="fas fa-calendar mr-2"></i>
                            <?= date('d F Y', strtotime($post['published_at'])) ?>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-eye mr-2"></i>
                            <?= number_format($post['view_count']) ?> views
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            <?= ceil(str_word_count(strip_tags($post['content'])) / 200) ?> min read
                        </div>
                    </div>
                </div>
                
                <?php if ($post['excerpt']): ?>
                <p class="text-xl text-gray-600 leading-relaxed mb-8">
                    <?= h($post['excerpt']) ?>
                </p>
                <?php endif; ?>
            </div>

            <!-- Featured Image -->
            <?php if ($post['featured_image']): ?>
            <div class="mb-12">
                <img src="<?= h($post['featured_image']) ?>" alt="<?= h($post['title']) ?>" class="w-full h-64 md:h-96 object-cover rounded-xl shadow-lg">
            </div>
            <?php endif; ?>

            <!-- Article Content -->
            <div class="prose prose-lg max-w-none">
                <?= $post['content'] ?>
            </div>

            <!-- Tags -->
            <?php if (!empty($tags)): ?>
            <div class="mt-12 pt-8 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tags:</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                    <a href="blog.php?search=<?= urlencode($tag) ?>" class="bg-gray-100 hover:bg-blue-100 text-gray-700 hover:text-blue-800 px-3 py-1 rounded-full text-sm transition-colors">
                        #<?= h($tag) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Share Buttons -->
            <div class="mt-12 pt-8 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Bagikan Artikel:</h3>
                <div class="flex space-x-4">
                    <button onclick="sharePost('facebook')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fab fa-facebook-f mr-2"></i>Facebook
                    </button>
                    <button onclick="sharePost('twitter')" class="bg-sky-500 text-white px-4 py-2 rounded-lg hover:bg-sky-600 transition-colors">
                        <i class="fab fa-twitter mr-2"></i>Twitter
                    </button>
                    <button onclick="sharePost('linkedin')" class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition-colors">
                        <i class="fab fa-linkedin-in mr-2"></i>LinkedIn
                    </button>
                    <button onclick="sharePost('whatsapp')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </article>

    <!-- Related Posts -->
    <?php if (!empty($related_posts)): ?>
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-12 text-center">Artikel Terkait</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($related_posts as $related): ?>
                <article class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                    <?php if ($related['featured_image']): ?>
                    <img src="<?= h($related['featured_image']) ?>" alt="<?= h($related['title']) ?>" class="w-full h-48 object-cover">
                    <?php endif; ?>
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3 line-clamp-2">
                            <a href="blog-detail.php?slug=<?= h($related['slug']) ?>" class="hover:text-blue-600 transition-colors">
                                <?= h($related['title']) ?>
                            </a>
                        </h3>
                        <p class="text-gray-600 mb-4 line-clamp-3">
                            <?= h($related['excerpt'] ?: substr(strip_tags($related['content']), 0, 120) . '...') ?>
                        </p>
                        <div class="text-sm text-gray-500">
                            <?= date('d M Y', strtotime($related['published_at'])) ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="py-16 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold mb-6">Butuh Bantuan dengan Project Anda?</h2>
            <p class="text-xl mb-8 text-blue-100">Tim ahli kami siap membantu mewujudkan ide digital Anda</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-envelope mr-2"></i>Konsultasi Gratis
                </a>
                <a href="services.php" class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                    <i class="fas fa-cogs mr-2"></i>Lihat Layanan
                </a>
            </div>
        </div>
    </section>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });

        // Reading progress
        window.addEventListener('scroll', function() {
            const article = document.querySelector('article');
            const progress = document.getElementById('reading-progress');
            
            if (article && progress) {
                const articleTop = article.offsetTop;
                const articleHeight = article.offsetHeight;
                const windowHeight = window.innerHeight;
                const scrollTop = window.pageYOffset;
                
                const articleBottom = articleTop + articleHeight - windowHeight;
                const scrollProgress = Math.min(Math.max((scrollTop - articleTop) / (articleBottom - articleTop), 0), 1);
                
                progress.style.width = (scrollProgress * 100) + '%';
            }
        });

        // Share functions
        function sharePost(platform) {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent(document.title);
            const text = encodeURIComponent('<?= h($post['excerpt']) ?>');
            
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${title}%20${url}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        function copyLink() {
            navigator.clipboard.writeText(window.location.href).then(function() {
                // Show toast notification
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                toast.textContent = 'Link berhasil disalin!';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
    <?php echo renderPageEnd(); ?>
