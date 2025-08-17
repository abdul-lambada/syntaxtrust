<?php
  // Start session early for header login state
  require_once __DIR__ . '/../../config/session.php';
  // DB for public settings used in meta/JSON-LD
  require_once __DIR__ . '/../../config/database.php';
  // Determine current page to set active state
  $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
  $isHome = ($script === 'index.php');
  $isBlog = ($script === 'blog.php');
  $isPortfolio = ($script === 'portfolio.php');
  $isServices = ($script === 'services.php');
  $isPricing = ($script === 'pricing.php');
  $isTeam = ($script === 'team.php');
  $isTestimonials = ($script === 'testimonials.php');
  $isContact = ($script === 'contact.php');
  $isFaq = ($script === 'faq.php');
  // Helper to build section link that works across pages
  function sectionHref($id, $isHome) {
    $id = ltrim((string)$id, '#');
    return $isHome ? "#{$id}" : "/syntaxtrust/public/index.php#{$id}";
  }
?>
<!DOCTYPE html>
<html lang="id" x-data="{ mobileOpen: false, userMenu:false }" class="scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <?php
    $defaultTitle = 'Jasa Pembuatan Website | SyntaxTrust';
    $defaultDesc = 'SyntaxTrust - Jasa Pembuatan Website Mahasiswa & UMKM';
    $metaTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle : $defaultTitle;
    $metaDesc = isset($pageDesc) && $pageDesc !== '' ? $pageDesc : $defaultDesc;
  ?>
  <meta name="description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta name="theme-color" content="#0ea5e9" />
  <title><?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <?php if (!empty($canonicalUrl)): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endif; ?>
  <?php if (!empty($relPrevUrl)): ?>
    <link rel="prev" href="<?php echo htmlspecialchars($relPrevUrl, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endif; ?>
  <?php if (!empty($relNextUrl)): ?>
    <link rel="next" href="<?php echo htmlspecialchars($relNextUrl, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endif; ?>
  <!-- Open Graph -->
  <meta property="og:title" content="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php if (!empty($canonicalUrl)): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endif; ?>
  <meta property="og:type" content="website" />
  <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endif; ?>

  <!-- Resource Hints -->
  <link rel="dns-prefetch" href="//fonts.googleapis.com">
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <link rel="dns-prefetch" href="//cdn.tailwindcss.com">
  <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
  <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="preconnect" href="https://images.unsplash.com" crossorigin>

  <?php
    // Build dynamic base URL for JSON-LD and canonical fallbacks
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $publicBase = $scheme . '://' . $host . '/syntaxtrust/public/';

    // Fetch public settings
    $settings = [];
    try {
      $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE is_public = 1");
      foreach ($stmt->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
    } catch (Exception $e) { $settings = []; }

    $contactEmail = $settings['contact_email'] ?? null;
    $contactPhone = $settings['contact_phone'] ?? null;
    $orgAddress   = $settings['address'] ?? null;
    $logoUrl      = $settings['logo_url'] ?? null;
    // Social profiles if present in settings
    // Support both legacy keys (*_url) and new keys (social_media_*)
    $socialCandidatesRaw = [
      $settings['twitter_url'] ?? null,
      $settings['github_url'] ?? null,
      $settings['facebook_url'] ?? null,
      $settings['instagram_url'] ?? null,
      $settings['linkedin_url'] ?? null,
      $settings['youtube_url'] ?? null,
      $settings['social_media_twitter'] ?? null,
      $settings['social_media_facebook'] ?? null,
      $settings['social_media_instagram'] ?? null,
      $settings['social_media_linkedin'] ?? null,
    ];
    $sameAs = array_values(array_filter($socialCandidatesRaw, function($u){ return is_string($u) && preg_match('/^https?:\\/\\//i', $u); }));

    $siteName = 'SyntaxTrust';
    $websiteJson = [
      '@context' => 'https://schema.org',
      '@type' => 'WebSite',
      'name' => $siteName,
      'url' => $publicBase,
    ];
    $orgJson = [
      '@context' => 'https://schema.org',
      '@type' => 'Organization',
      'name' => $siteName,
      'url' => $publicBase,
    ];

    if (!empty($logoUrl)) {
      $orgJson['logo'] = $logoUrl;
    }
    if (!empty($sameAs)) {
      $orgJson['sameAs'] = $sameAs;
    }
    $contactPoint = [];
    if (!empty($contactPhone) || !empty($contactEmail)) {
      $cp = [
        '@type' => 'ContactPoint',
        'contactType' => 'customer support',
      ];
      if (!empty($contactPhone)) $cp['telephone'] = $contactPhone;
      if (!empty($contactEmail)) $cp['email'] = $contactEmail;
      $contactPoint[] = $cp;
    }
    if (!empty($contactPoint)) {
      $orgJson['contactPoint'] = $contactPoint;
    }
  ?>
  <script type="application/ld+json">
    <?php echo json_encode($websiteJson, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>
  </script>

  <!-- Sitemap -->
  <link rel="sitemap" type="application/xml" href="<?php echo htmlspecialchars($publicBase . 'sitemap.xml', ENT_QUOTES, 'UTF-8'); ?>" />
  <script type="application/ld+json">
    <?php echo json_encode($orgJson, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>
  </script>
  <?php if (!empty($isFaq) && $isFaq): ?>
  <script type="application/ld+json">
    <?php
      // Static FAQ items matching the content in faq.php
      $faqJson = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
          [
            '@type' => 'Question',
            'name' => 'Berapa lama proses pembuatan?',
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => 'Umumnya 7–14 hari kerja tergantung paket dan kelengkapan konten.'
            ]
          ],
          [
            '@type' => 'Question',
            'name' => 'Apakah termasuk revisi?',
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => 'Iya, paket sudah termasuk revisi (lihat detail tiap paket: 1–3x revisi).'
            ]
          ],
          [
            '@type' => 'Question',
            'name' => 'Bagaimana skema pembayaran?',
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => 'DP 50% saat mulai, pelunasan ketika website selesai dan disetujui.'
            ]
          ]
        ]
      ];
      echo json_encode($faqJson, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    ?>
  </script>
  <?php endif; ?>
  <?php
    // BreadcrumbList JSON-LD for non-home pages
    if (!$isHome) {
      // Map current page name
      $pageMap = [
        'blog.php' => 'Blog',
        'services.php' => 'Layanan',
        'portfolio.php' => 'Portofolio',
        'pricing.php' => 'Harga',
        'team.php' => 'Tim',
        'testimonials.php' => 'Testimoni',
        'faq.php' => 'FAQ',
        'contact.php' => 'Kontak',
      ];
      $currentName = $pageMap[$script] ?? ($metaTitle ?? 'Halaman');
      // Build URLs
      $homeUrl = $publicBase . 'index.php';
      $currentUrl = !empty($canonicalUrl) ? $canonicalUrl : ($publicBase . $script);
      $breadcrumbs = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
          [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Beranda',
            'item' => $homeUrl
          ],
          [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => $currentName,
            'item' => $currentUrl
          ]
        ]
      ];
  ?>
  <script type="application/ld+json">
    <?php echo json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>
  </script>
  <?php } ?>
  
  <!-- Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" onload="this.onload=null;this.rel='stylesheet'">
  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  </noscript>

  <?php if (!empty($isHome) && $isHome): ?>
    <!-- Preload homepage hero image for better LCP -->
    <link
      rel="preload"
      as="image"
      href="https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=1600&auto=format&fit=crop"
      imagesrcset="https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=800&auto=format&fit=crop 800w, https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=1200&auto=format&fit=crop 1200w, https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=1600&auto=format&fit=crop 1600w"
      imagesizes="100vw"
      fetchpriority="high">
  <?php endif; ?>

  <!-- TailwindCSS CDN + Config -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Set theme before paint to avoid FOUC
    (function() {
      try {
        const ls = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const dark = ls ? (ls === 'dark') : prefersDark;
        document.documentElement.classList.toggle('dark', dark);
      } catch (e) {}
    })();
  </script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
          colors: {
            brand: {
              50: '#ecfeff', 100: '#cffafe', 200: '#a5f3fc', 300: '#67e8f9',
              400: '#22d3ee', 500: '#06b6d4', 600: '#0891b2', 700: '#0e7490',
              800: '#155e75', 900: '#164e63'
            },
            primary: '#0ea5e9',
            dark: '#0b1220',
          },
          boxShadow: {
            soft: '0 10px 25px -5px rgba(2,132,199,0.15), 0 8px 10px -6px rgba(2,132,199,0.12)'
          }
        }
      }
    }
  </script>

  <!-- Alpine.js for interactivity -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
    :root { color-scheme: light dark; }
    html, body { font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; }
    /* Respect reduced motion preferences */
    @media (prefers-reduced-motion: reduce) {
      html.scroll-smooth { scroll-behavior: auto !important; }
      *, *::before, *::after { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
      [data-reveal] { opacity: 1 !important; transform: none !important; }
    }
  </style>
</head>
<body class="bg-white text-slate-800 dark:bg-dark dark:text-slate-100">
  <!-- Skip to content for accessibility -->
  <a href="#content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-slate-900 shadow dark:focus:bg-slate-800 dark:focus:text-white">Lewati ke konten</a>
  <!-- Navbar -->
  <header class="sticky top-0 z-50 border-b border-slate-100/70 bg-white/80 backdrop-blur dark:bg-slate-900/70">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <a href="/syntaxtrust/public/index.php" class="flex items-center gap-2 group">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/20 shadow-soft">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 3v18M3 12h18" stroke-linecap="round"/>
            </svg>
          </span>
          <span class="text-lg font-semibold tracking-tight group-hover:text-primary transition">SyntaxTrust</span>
        </a>

        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-6 text-sm">
          <a href="/syntaxtrust/public/index.php" class="hover:text-primary <?php echo $isHome ? 'text-primary font-semibold' : ''; ?>" data-section="home">Beranda</a>
          <a href="/syntaxtrust/public/services.php" class="hover:text-primary <?php echo $isServices ? 'text-primary font-semibold' : ''; ?>" data-section="layanan">Layanan</a>
          <a href="/syntaxtrust/public/portfolio.php" class="hover:text-primary <?php echo $isPortfolio ? 'text-primary font-semibold' : ''; ?>" data-section="portofolio">Portofolio</a>
          <a href="/syntaxtrust/public/pricing.php" class="hover:text-primary <?php echo $isPricing ? 'text-primary font-semibold' : ''; ?>" data-section="pricing">Harga</a>
          <a href="/syntaxtrust/public/team.php" class="hover:text-primary <?php echo $isTeam ? 'text-primary font-semibold' : ''; ?>" data-section="tim">Tim</a>
          <a href="<?php echo $isHome ? sectionHref('blog', $isHome) : '/syntaxtrust/public/blog.php'; ?>" class="hover:text-primary <?php echo $isBlog ? 'text-primary font-semibold' : ''; ?>" data-section="blog">Blog</a>
          <a href="/syntaxtrust/public/testimonials.php" class="hover:text-primary <?php echo $isTestimonials ? 'text-primary font-semibold' : ''; ?>" data-section="testimoni">Testimoni</a>
          <a href="/syntaxtrust/public/faq.php" class="hover:text-primary <?php echo $isFaq ? 'text-primary font-semibold' : ''; ?>" data-section="faq">FAQ</a>
          <a href="/syntaxtrust/public/contact.php" class="hover:text-primary <?php echo $isContact ? 'text-primary font-semibold' : ''; ?>" data-section="kontak">Kontak</a>
          <button id="themeToggle" type="button" class="ml-2 inline-flex items-center rounded-lg border border-slate-300 px-2.5 py-1.5 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Toggle theme">
            <svg class="h-4 w-4 block dark:hidden" viewBox="0 0 24 24" fill="currentColor"><path d="M6.76 4.84l-1.8-1.79L3.17 4.84l1.79 1.8 1.8-1.8zM1 13h3v-2H1v2zm10 10h2v-3h-2v3zM4.22 19.78l1.8 1.79 1.79-1.79-1.79-1.8-1.8 1.8zM20 11V9h-3v2h3zm-4.76-6.16l1.8-1.79L18.83 4.84l-1.79 1.8-1.8-1.8zM12 5a7 7 0 100 14 7 7 0 000-14z"/></svg>
            <svg class="h-4 w-4 hidden dark:block" viewBox="0 0 24 24" fill="currentColor"><path d="M21.64 13a1 1 0 00-1.05-.14 8 8 0 01-10.45-10.5 1 1 0 00-1.19-1.33A10 10 0 1022 14.19a1 1 0 00-.36-1.19z"/></svg>
          </button>
          <?php if (!empty($_SESSION['user_id'])): ?>
            <div class="relative" x-data="{ open:false }">
              <button @click="open=!open" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-primary">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zm0 2c-4.418 0-8 2.239-8 5v1h16v-1c0-2.761-3.582-5-8-5z"/></svg>
                </span>
                <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd"/></svg>
              </button>
              <div x-cloak x-show="open" @click.outside="open=false" x-transition.opacity class="absolute right-0 mt-2 w-44 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <a href="/syntaxtrust/public/logout.php" class="block px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">Logout</a>
              </div>
            </div>
          <?php else: ?>
            <a href="/syntaxtrust/login.php" class="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110">Masuk</a>
          <?php endif; ?>
        </nav>

        <!-- Mobile button -->
        <button class="md:hidden inline-flex items-center justify-center rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="mobileOpen = !mobileOpen" aria-label="Toggle Menu">
          <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Mobile Menu -->
      <div x-show="mobileOpen" x-transition.opacity x-collapse class="md:hidden border-t border-slate-100/70 py-3 dark:border-slate-700/50">
        <div class="flex flex-col gap-2 text-sm">
          <a href="/syntaxtrust/public/index.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isHome ? 'text-primary font-semibold' : ''; ?>" data-section="home" @click="mobileOpen=false">Beranda</a>
          <a href="/syntaxtrust/public/services.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isServices ? 'text-primary font-semibold' : ''; ?>" data-section="layanan" @click="mobileOpen=false">Layanan</a>
          <a href="/syntaxtrust/public/portfolio.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isPortfolio ? 'text-primary font-semibold' : ''; ?>" data-section="portofolio" @click="mobileOpen=false">Portofolio</a>
          <a href="/syntaxtrust/public/pricing.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isPricing ? 'text-primary font-semibold' : ''; ?>" data-section="pricing" @click="mobileOpen=false">Harga</a>
          <a href="/syntaxtrust/public/team.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isTeam ? 'text-primary font-semibold' : ''; ?>" data-section="tim" @click="mobileOpen=false">Tim</a>
          <a href="<?php echo $isHome ? sectionHref('blog', $isHome) : '/syntaxtrust/public/blog.php'; ?>" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isBlog ? 'text-primary font-semibold' : ''; ?>" data-section="blog" @click="mobileOpen=false">Blog</a>
          <a href="/syntaxtrust/public/testimonials.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isTestimonials ? 'text-primary font-semibold' : ''; ?>" data-section="testimoni" @click="mobileOpen=false">Testimoni</a>
          <a href="/syntaxtrust/public/faq.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isFaq ? 'text-primary font-semibold' : ''; ?>" data-section="faq" @click="mobileOpen=false">FAQ</a>
          <a href="/syntaxtrust/public/contact.php" class="rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 <?php echo $isContact ? 'text-primary font-semibold' : ''; ?>" data-section="kontak" @click="mobileOpen=false">Kontak</a>
          <button id="themeToggleMobile" type="button" class="mx-3 mt-1 rounded-lg border border-slate-300 px-3 py-2 text-left hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" @click="mobileOpen=false" aria-label="Toggle theme">
            <span class="inline-flex items-center gap-2">
              <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <svg class="h-4 w-4 block dark:hidden" viewBox="0 0 24 24" fill="currentColor"><path d="M6.76 4.84l-1.8-1.79L3.17 4.84l1.79 1.8 1.8-1.8zM1 13h3v-2H1v2zm10 10h2v-3h-2v3zM4.22 19.78l1.8 1.79 1.79-1.79-1.79-1.8-1.8 1.8zM20 11V9h-3v2h3zm-4.76-6.16l1.8-1.79L18.83 4.84l-1.79 1.8-1.8-1.8zM12 5a7 7 0 100 14 7 7 0 000-14z"/></svg>
                <svg class="h-4 w-4 hidden dark:block" viewBox="0 0 24 24" fill="currentColor"><path d="M21.64 13a1 1 0 00-1.05-.14 8 8 0 01-10.45-10.5 1 1 0 00-1.19-1.33A10 10 0 1022 14.19a1 1 0 00-.36-1.19z"/></svg>
              </span>
              <span>Toggle Tema</span>
            </span>
          </button>
          <?php if (!empty($_SESSION['user_id'])): ?>
            <div class="grid grid-cols-2 gap-2 px-3">
              <div class="col-span-2 text-slate-500 flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary/10 text-primary">
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 12c2.761 0 5-2.239 5-5s-2.239-5-5-5-5 2.239-5 5 2.239 5 5 5zm0 2c-4.418 0-8 2.239-8 5v1h16v-1c0-2.761-3.582-5-8-5z"/></svg>
                </span>
                <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <a href="/syntaxtrust/public/logout.php" class="col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-center hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" @click="mobileOpen=false">Logout</a>
            </div>
          <?php else: ?>
            <a href="/syntaxtrust/login.php" class="rounded-lg bg-primary px-3 py-2 text-center text-white" @click="mobileOpen=false">Masuk</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <main id="content">
    <?php
      // Visible breadcrumb UI for non-home, non-blog pages
      if (!$isHome && !$isBlog) {
        $pageMapUi = [
          'blog.php' => 'Blog',
          'services.php' => 'Layanan',
          'portfolio.php' => 'Portofolio',
          'pricing.php' => 'Harga',
          'team.php' => 'Tim',
          'testimonials.php' => 'Testimoni',
          'faq.php' => 'FAQ',
          'contact.php' => 'Kontak',
        ];
        $currentNameUi = $pageMapUi[$script] ?? ($metaTitle ?? 'Halaman');
        $homeUrlUi = $publicBase . 'index.php';
        $currentUrlUi = !empty($canonicalUrl) ? $canonicalUrl : ($publicBase . $script);
    ?>
      <nav class="border-b border-slate-100/70 bg-white/60 backdrop-blur py-2 dark:border-slate-800/60 dark:bg-slate-900/50" aria-label="Breadcrumb" data-reveal="down">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <ol class="flex items-center gap-2 text-sm text-slate-500">
            <li>
              <a href="<?php echo htmlspecialchars($homeUrlUi, ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-slate-700 dark:hover:text-slate-300">Beranda</a>
            </li>
            <li aria-hidden="true" class="text-slate-400">/</li>
            <li class="text-slate-700 dark:text-slate-300" aria-current="page">
              <a href="<?php echo htmlspecialchars($currentUrlUi, ENT_QUOTES, 'UTF-8'); ?>" class="pointer-events-none cursor-default"><?php echo htmlspecialchars($currentNameUi, ENT_QUOTES, 'UTF-8'); ?></a>
            </li>
          </ol>
        </div>
      </nav>
    <?php } ?>

  <script>
    // Scrollspy: highlight nav link based on visible section
    (function() {
      const linkSelector = 'a[data-section]';
      const links = Array.from(document.querySelectorAll(linkSelector));
      if (!links.length) return;

      const bySection = {};
      links.forEach(l => {
        const key = l.getAttribute('data-section');
        if (!bySection[key]) bySection[key] = [];
        bySection[key].push(l);
      });

      function setActive(key) {
        // Clear all
        links.forEach(l => l.classList.remove('text-primary','font-semibold'));
        // Set active for all links of the current key
        (bySection[key] || []).forEach(l => l.classList.add('text-primary','font-semibold'));
      }

      // Observe sections
      const sectionIds = ['layanan','portofolio','pricing','tim','blog','testimoni','faq','kontak'];
      const sections = sectionIds
        .map(id => document.getElementById(id))
        .filter(Boolean);

      // If no section in view, mark home
      let lastActive = 'home';
      const observer = new IntersectionObserver(entries => {
        let best = null;
        for (const e of entries) {
          if (e.isIntersecting) {
            const ratio = e.intersectionRatio;
            if (!best || ratio > best.ratio) best = { id: e.target.id, ratio };
          }
        }
        if (best && best.id) {
          lastActive = best.id;
          setActive(best.id);
        } else {
          // If scrolled near top (no sections intersecting), set home
          const nearTop = window.scrollY < 100;
          if (nearTop && lastActive !== 'home') {
            lastActive = 'home';
            setActive('home');
          }
        }
      }, { threshold: [0.5] });

      sections.forEach(sec => observer.observe(sec));

      // On hash navigation
      window.addEventListener('hashchange', () => {
        const hash = (location.hash || '').replace('#','');
        if (hash && bySection[hash]) setActive(hash);
      });

      // Initial state
      if (location.hash) {
        const hash = location.hash.replace('#','');
        if (bySection[hash]) setActive(hash);
      } else if (<?php echo $isHome ? 'true' : 'false'; ?>) {
        setActive('home');
      }
    })();
  </script>
