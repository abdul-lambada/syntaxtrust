<?php
require_once __DIR__ . '/includes/layout.php';

// Get team members
try {
    $stmt = $pdo->prepare("SELECT * FROM team WHERE is_active = 1 ORDER BY sort_order ASC, created_at ASC");
    $stmt->execute();
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $team_members = [];
}

$site_name = getSetting('site_name', 'SyntaxTrust');
$site_description = getSetting('site_description', 'Layanan Pembuatan Website untuk Mahasiswa & UMKM');
echo renderPageStart('Tim Kami - ' . $site_name, 'Kenali tim profesional kami - ' . $site_description, 'team.php');
?>
    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        .team-card { transition: all 0.3s ease; }
        .team-card:hover { transform: translateY(-10px); }
        .skill-tag { transition: all 0.2s ease; }
        .skill-tag:hover { transform: scale(1.1); }
    </style>
    

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-blue-600 via-purple-600 to-indigo-700 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6">Tim Profesional</h1>
            <p class="text-xl md:text-2xl mb-8 text-blue-100">Berkenalan dengan para ahli di balik kesuksesan project Anda</p>
            <div class="flex justify-center items-center space-x-8 mt-12">
                <div class="text-center">
                    <div class="text-3xl font-bold"><span id="experts-count"><?= count($team_members) ?></span>+</div>
                    <div class="text-blue-100">Expert</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">50+</div>
                    <div class="text-blue-100">Project</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold">3+</div>
                    <div class="text-blue-100">Tahun</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Members -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Meet Our Team</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Tim berpengalaman yang siap membantu mewujudkan visi digital Anda</p>
            </div>
            
            <div id="team-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($team_members as $index => $member): ?>
                <div class="team-card bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100" style="animation: slideUp 0.6s ease-out <?= $index * 0.2 ?>s both;">
                    <!-- Profile Image -->
                    <div class="relative">
                        <?php if ($member['profile_image']): ?>
                        <img src="<?= h(assetUrl($member['profile_image'])) ?>" alt="<?= h($member['name']) ?>" class="w-full h-64 object-cover">
                        <?php else: ?>
                        <div class="w-full h-64 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-user text-white text-6xl"></i>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Social Links Overlay -->
                        <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-60 transition-all duration-300 flex items-center justify-center">
                            <div class="opacity-0 hover:opacity-100 transition-opacity duration-300 flex space-x-4">
                                <?php
                                $social_links = $member['social_links'] ? json_decode($member['social_links'], true) : [];
                                foreach ($social_links as $platform => $url):
                                    if (!empty($url)):
                                        $icon_map = [
                                            'linkedin' => 'fab fa-linkedin-in',
                                            'github' => 'fab fa-github',
                                            'twitter' => 'fab fa-twitter',
                                            'instagram' => 'fab fa-instagram',
                                            'facebook' => 'fab fa-facebook-f'
                                        ];
                                        $icon = $icon_map[$platform] ?? 'fas fa-link';
                                ?>
                                <a href="<?= h($url) ?>" target="_blank" class="bg-white text-gray-900 w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition-colors">
                                    <i class="<?= $icon ?>"></i>
                                </a>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Member Info -->
                    <div class="p-6">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= h($member['name']) ?></h3>
                        <p class="text-blue-600 font-semibold mb-4"><?= h($member['position']) ?></p>
                        
                        <?php if ($member['experience_years']): ?>
                        <div class="flex items-center mb-4 text-gray-600">
                            <i class="fas fa-briefcase mr-2"></i>
                            <span><?= $member['experience_years'] ?> tahun pengalaman</span>
                        </div>
                        <?php endif; ?>
                        
                        <p class="text-gray-600 mb-6 leading-relaxed"><?= h($member['bio']) ?></p>
                        
                        <!-- Skills -->
                        <?php if ($member['skills']): ?>
                        <div class="mb-6">
                            <h4 class="font-semibold text-gray-900 mb-3">Keahlian:</h4>
                            <div class="flex flex-wrap gap-2">
                                <?php
                                $skills = json_decode($member['skills'], true) ?: [];
                                foreach ($skills as $skill):
                                ?>
                                <span class="skill-tag bg-gradient-to-r from-blue-100 to-purple-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <?= h($skill) ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Contact -->
                        <div class="flex space-x-3">
                            <?php if ($member['email']): ?>
                            <a href="mailto:<?= h($member['email']) ?>" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg text-center font-semibold hover:bg-blue-700 transition-colors">
                                <i class="fas fa-envelope mr-2"></i>Email
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($member['phone']): ?>
                            <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', $member['phone']) ?>" target="_blank" class="bg-green-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($team_members)): ?>
            <div class="text-center py-20">
                <i class="fas fa-users text-6xl text-gray-300 mb-6"></i>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Tim Segera Hadir</h3>
                <p class="text-gray-600 mb-8">Kami sedang membangun tim terbaik untuk melayani Anda.</p>
                <a href="contact.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    Hubungi Kami
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Join Team CTA -->
    <section class="py-20 bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6">Bergabung dengan Tim Kami</h2>
            <p class="text-xl mb-8 text-blue-100">Kami selalu mencari talenta terbaik untuk bergabung dalam tim</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="contact.php" class="bg-white text-blue-600 px-8 py-4 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Kirim CV
                </a>
                <a href="https://wa.me/<?= str_replace(['+', '-', ' '], '', getSetting('company_whatsapp', '6285156553226')) ?>?text=Halo, saya tertarik untuk bergabung dengan tim" 
                   target="_blank" 
                   class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                    <i class="fab fa-whatsapp mr-2"></i>
                    Chat HR
                </a>
            </div>
        </div>
    </section>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu?.classList.toggle('hidden');
        });

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Add animation on scroll (re-bindable)
        function bindTeamAnimations() {
            const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => { if (entry.isIntersecting) { entry.target.style.animationPlayState = 'running'; } });
            }, observerOptions);
            document.querySelectorAll('.team-card').forEach(card => observer.observe(card));
        }
        // Initial bind for SSR content
        bindTeamAnimations();

        // Client-side hydration: load team from API
        (function hydrateTeam() {
            const grid = document.getElementById('team-grid');
            const expertsCount = document.getElementById('experts-count');
            if (!grid) return;
            fetch('api/team_list.php', { cache: 'no-store' })
                .then(r => r.json())
                .then(data => {
                    if (!data?.success || !Array.isArray(data.items)) return;
                    const items = data.items;
                    if (expertsCount) expertsCount.textContent = String(items.length);
                    grid.innerHTML = items.map((m, idx) => {
                        const skills = m.skills ? (() => { try { return JSON.parse(m.skills) || []; } catch { return []; } })() : [];
                        const social = m.social_links ? (() => { try { return JSON.parse(m.social_links) || {}; } catch { return {}; } })() : {};
                        const imgUrl = window.normalizeImageSrc(m.profile_image);
                        const img = imgUrl
                            ? `<img src="${imgUrl}" alt="${m.name}" class="w-full h-64 object-cover">`
                            : `<div class=\"w-full h-64 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center\"><i class=\"fas fa-user text-white text-6xl\"></i></div>`;
                        const socialIcons = { linkedin: 'fab fa-linkedin-in', github: 'fab fa-github', twitter: 'fab fa-twitter', instagram: 'fab fa-instagram', facebook: 'fab fa-facebook-f' };
                        const socialLinks = Object.entries(social)
                            .filter(([, url]) => !!url)
                            .map(([platform, url]) => `<a href="${url}" target="_blank" class="bg-white text-gray-900 w-10 h-10 rounded-full flex items-center justify-center hover:bg-blue-600 hover:text-white transition-colors"><i class="${socialIcons[platform] || 'fas fa-link'}"></i></a>`)
                            .join('');
                        const phoneBtn = m.phone ? `<a href=\"https://wa.me/${(m.phone || '').replace(/[+\-\s]/g,'')}\" target=\"_blank\" class=\"bg-green-600 text-white py-2 px-4 rounded-lg font-semibold hover:bg-green-700 transition-colors\"><i class=\"fab fa-whatsapp\"></i></a>` : '';
                        const emailBtn = m.email ? `<a href=\"mailto:${m.email}\" class=\"flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg text-center font-semibold hover:bg-blue-700 transition-colors\"><i class=\"fas fa-envelope mr-2\"></i>Email</a>` : '';
                        const experience = m.experience_years ? `<div class=\"flex items-center mb-4 text-gray-600\"><i class=\"fas fa-briefcase mr-2\"></i><span>${m.experience_years} tahun pengalaman</span></div>` : '';
                        const skillsHtml = skills.length ? `
                            <div class=\"mb-6\"> 
                                <h4 class=\"font-semibold text-gray-900 mb-3\">Keahlian:</h4>
                                <div class=\"flex flex-wrap gap-2\">
                                    ${skills.map(s => `<span class=\"skill-tag bg-gradient-to-r from-blue-100 to-purple-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium\">${s}</span>`).join('')}
                                </div>
                            </div>` : '';
                        return `
                        <div class=\"team-card bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100\" style=\"animation: slideUp 0.6s ease-out ${idx * 0.2}s both;\">
                            <div class=\"relative\">${img}
                                <div class=\"absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-60 transition-all duration-300 flex items-center justify-center\">
                                    <div class=\"opacity-0 hover:opacity-100 transition-opacity duration-300 flex space-x-4\">${socialLinks}</div>
                                </div>
                            </div>
                            <div class=\"p-6\">
                                <h3 class=\"text-2xl font-bold text-gray-900 mb-2\">${m.name}</h3>
                                <p class=\"text-blue-600 font-semibold mb-4\">${m.position || ''}</p>
                                ${experience}
                                <p class=\"text-gray-600 mb-6 leading-relaxed\">${m.bio || ''}</p>
                                ${skillsHtml}
                                <div class=\"flex space-x-3\">${emailBtn}${phoneBtn}</div>
                            </div>
                        </div>`;
                    }).join('');
                    // Rebind animations for new elements
                    bindTeamAnimations();
                })
                .catch(() => {});
        })();
    </script>
    <?php echo renderPageEnd(); ?>
