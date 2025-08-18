<?php
// Shared components and utilities for consistent UI across all pages

function renderNavigation($current_page = '') {
    $site_name = getSetting('site_name', 'SyntaxTrust');
    $nav_items = [
        'index.php' => 'Beranda',
        'services.php' => 'Layanan',
        'portfolio.php' => 'Portfolio',
        'team.php' => 'Tim',
        'blog.php' => 'Blog',
        'testimonials.php' => 'Testimoni',
        'clients.php' => 'Klien',
        'contact.php' => 'Kontak'
    ];
    
    ob_start();
    ?>
    <nav class="bg-white shadow-lg sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-2xl font-bold text-gray-800"><?= h($site_name) ?></a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <?php foreach ($nav_items as $page => $label): ?>
                    <a href="<?= $page ?>" class="<?= $current_page === $page ? 'text-blue-600 font-semibold' : 'text-gray-600 hover:text-blue-600' ?> transition-colors">
                        <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <?php foreach ($nav_items as $page => $label): ?>
                <a href="<?= $page ?>" class="block px-3 py-2 <?= $current_page === $page ? 'text-blue-600 font-semibold' : 'text-gray-600' ?>">
                    <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
    <?php
    return ob_get_clean();
}

function renderFooter() {
    $site_name = getSetting('site_name', 'SyntaxTrust');
    $site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
    
    ob_start();
    ?>
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company Info -->
                <div class="md:col-span-2">
                    <h3 class="text-2xl font-bold mb-4"><?= h($site_name) ?></h3>
                    <p class="text-gray-400 mb-6 leading-relaxed"><?= h($site_description) ?></p>
                    <div class="flex space-x-4">
                        <a href="<?= h(getSetting('social_media_facebook', '#')) ?>" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f text-xl"></i>
                        </a>
                        <a href="<?= h(getSetting('social_media_twitter', '#')) ?>" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="<?= h(getSetting('social_media_instagram', '#')) ?>" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                        <a href="<?= h(getSetting('social_media_linkedin', '#')) ?>" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-linkedin-in text-xl"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="services.php" class="text-gray-400 hover:text-white transition-colors">Layanan</a></li>
                        <li><a href="portfolio.php" class="text-gray-400 hover:text-white transition-colors">Portfolio</a></li>
                        <li><a href="team.php" class="text-gray-400 hover:text-white transition-colors">Tim</a></li>
                        <li><a href="blog.php" class="text-gray-400 hover:text-white transition-colors">Blog</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Kontak</h4>
                    <ul class="space-y-2 text-gray-400 break-words">
                        <li class="break-words"><i class="fas fa-envelope mr-2"></i><?= h(getSetting('contact_email')) ?></li>
                        <li class="break-words"><i class="fas fa-phone mr-2"></i><?= h(getSetting('contact_phone')) ?></li>
                        <li class="break-words"><i class="fas fa-map-marker-alt mr-2"></i><?= h(getSetting('address')) ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 mt-8 text-center">
                <p class="text-gray-400">&copy; <?= date('Y') ?> <?= h($site_name) ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    <?php
    return ob_get_clean();
}

function renderWhatsAppButton() {
    $whatsapp = getSetting('company_whatsapp', '6285156553226');
    $clean_number = str_replace(['+', '-', ' '], '', $whatsapp);
    
    ob_start();
    ?>
    <!-- Floating WhatsApp Button -->
    <div id="whatsapp-float" class="fixed bottom-6 right-6 z-50">
        <a href="https://wa.me/<?= $clean_number ?>?text=Halo, saya tertarik dengan layanan Anda" 
           target="_blank" 
           class="bg-green-500 hover:bg-green-600 text-white rounded-full p-4 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-110 flex items-center justify-center group animate-pulse-custom ring-4 ring-green-500/20">
            <i class="fab fa-whatsapp text-2xl"></i>
            <span class="ml-3 font-semibold opacity-100 transition-opacity duration-300 whitespace-nowrap">
                Chat Sekarang
            </span>
        </a>
    </div>
    <?php
    return ob_get_clean();
}

function renderBackToTop() {
    ob_start();
    ?>
    <!-- Back to Top Button -->
    <button id="back-to-top" class="fixed bottom-6 left-6 bg-blue-600 hover:bg-blue-700 text-white rounded-full p-3 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-110 opacity-0 invisible">
        <i class="fas fa-arrow-up"></i>
    </button>
    <?php
    return ob_get_clean();
}

function renderLoadingSpinner() {
    ob_start();
    ?>
    <!-- Loading Spinner -->
    <div id="loading-spinner" class="fixed inset-0 bg-white bg-opacity-90 flex items-center justify-center z-50 hidden">
        <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600"></div>
    </div>
    <?php
    return ob_get_clean();
}

function renderCommonScripts() {
    ob_start();
    ?>
    <script>
        // Common JavaScript functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
            
            // Back to top button
            const backToTopBtn = document.getElementById('back-to-top');
            if (backToTopBtn) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 300) {
                        backToTopBtn.classList.remove('opacity-0', 'invisible');
                        backToTopBtn.classList.add('opacity-100', 'visible');
                    } else {
                        backToTopBtn.classList.add('opacity-0', 'invisible');
                        backToTopBtn.classList.remove('opacity-100', 'visible');
                    }
                });
                
                backToTopBtn.addEventListener('click', function() {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
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
            
            // Lazy loading for images
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
            
            // Animation on scroll
            const animateOnScroll = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in');
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.animate-on-scroll').forEach(el => {
                animateOnScroll.observe(el);
            });
            
            // Form validation
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.classList.add('border-red-500');
                            isValid = false;
                        } else {
                            field.classList.remove('border-red-500');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        showNotification('Mohon lengkapi semua field yang wajib diisi', 'error');
                    }
                });
            });
        });
        
        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = {
                'success': 'bg-green-500',
                'error': 'bg-red-500',
                'warning': 'bg-yellow-500',
                'info': 'bg-blue-500'
            }[type] || 'bg-blue-500';
            
            notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);
            
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Loading state management
        function showLoading() {
            const spinner = document.getElementById('loading-spinner');
            if (spinner) spinner.classList.remove('hidden');
        }
        
        function hideLoading() {
            const spinner = document.getElementById('loading-spinner');
            if (spinner) spinner.classList.add('hidden');
        }
        
        // AJAX helper
        function makeRequest(url, options = {}) {
            showLoading();
            
            return fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                ...options
            })
            .then(response => {
                hideLoading();
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .catch(error => {
                hideLoading();
                showNotification('Terjadi kesalahan. Silakan coba lagi.', 'error');
                throw error;
            });
        }
    </script>
    <?php
    return ob_get_clean();
}

function renderCommonStyles() {
    ob_start();
    ?>
    <style>
        /* Common animations and styles */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% { transform: translateY(0); }
            40%, 43% { transform: translateY(-30px); }
            70% { transform: translateY(-15px); }
            90% { transform: translateY(-4px); }
        }
        
        .animate-fade-in { animation: fadeIn 0.6s ease-out; }
        .animate-slide-up { animation: slideUp 0.6s ease-out; }
        .animate-pulse-custom { animation: pulse 2s infinite; }
        .animate-bounce-custom { animation: bounce 2s infinite; }
        
        /* Utility classes */
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Lazy loading placeholder */
        img.lazy {
            filter: blur(5px);
            transition: filter 0.3s;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        
        /* Smooth transitions */
        * { transition: color 0.2s ease, background-color 0.2s ease, transform 0.2s ease; }
        
        /* Focus styles */
        .focus\:ring-custom:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
        }
    </style>
    <?php
    return ob_get_clean();
}
?>
