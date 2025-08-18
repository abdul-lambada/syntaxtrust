<?php require __DIR__ . '/includes/header.php'; ?>
<?php
  // Load DB and fetch dynamic content
  require_once __DIR__ . '/../config/database.php';

  // Helper: safe json decode to array
  function decode_json_array($json) {
    $arr = json_decode($json ?? '[]', true);
    return is_array($arr) ? $arr : [];
  }

  // Fetch clients
  $clients = [];
  try {
    $stmt = $pdo->query("SELECT name, logo, website_url FROM clients WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 12");
    $clients = $stmt->fetchAll();
  } catch (Exception $e) { $clients = []; }

  // Fetch team
  $team = [];
  try {
    $stmt = $pdo->query("SELECT name, position, profile_image FROM team WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 12");
    $team = $stmt->fetchAll();
  } catch (Exception $e) { $team = []; }

  // Fetch services
  $services = [];
  try {
    $stmt = $pdo->query("SELECT name, short_description, description, icon FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 12");
    $services = $stmt->fetchAll();
  } catch (Exception $e) { $services = []; }

  // Fetch portfolio (active only)
  $portfolioItems = [];
  try {
    $stmt = $pdo->query("SELECT id, title, short_description, category, project_url, image_main FROM portfolio WHERE is_active = 1 ORDER BY id DESC LIMIT 9");
    $portfolioItems = $stmt->fetchAll();
  } catch (Exception $e) { $portfolioItems = []; }

  // Fetch latest published blog posts
  $blogPosts = [];
  try {
    $stmt = $pdo->query("SELECT title, slug, excerpt, featured_image, published_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC, id DESC LIMIT 3");
    $blogPosts = $stmt->fetchAll();
  } catch (Exception $e) { $blogPosts = []; }

  // Fetch pricing plans
  $plans = [];
  try {
    $stmt = $pdo->query("SELECT name, subtitle, price, currency, billing_period, features, is_popular FROM pricing_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 6");
    $plans = $stmt->fetchAll();
  } catch (Exception $e) { $plans = []; }

  // Fetch testimonials
  $testimonials = [];
  try {
    $stmt = $pdo->query("SELECT client_name, client_company, content FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 6");
    $testimonials = $stmt->fetchAll();
  } catch (Exception $e) { $testimonials = []; }

  // Settings for contact info
  $settings = [];
  try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE is_public = 1");
    foreach ($stmt->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
  } catch (Exception $e) { $settings = []; }

  $contact_email = $settings['contact_email'] ?? 'support@syntaxtrust.com';
  $contact_phone = $settings['contact_phone'] ?? '+1 (555) 123-4567';
  $address = $settings['address'] ?? 'Silicon Valley, California';
  
?>

<!-- Hero -->
<section class="relative overflow-hidden">
  <div class="absolute inset-0 -z-10 bg-gradient-to-b from-sky-50 to-white dark:from-slate-900 dark:to-slate-950"></div>
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-16 pb-24">
    <div class="grid items-center gap-10 md:grid-cols-2">
      <div data-reveal="up">
        <span class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-medium text-sky-700 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300">
          Layanan • Pembuatan Website untuk Mahasiswa & UMKM
        </span>
        <h1 class="mt-4 text-4xl font-extrabold tracking-tight sm:text-5xl">
          Bangun Website Profesional untuk Tumbuh Lebih Cepat
        </h1>
        <p class="mt-4 text-slate-600 dark:text-slate-400">
          Kami membantu Anda hadir online secara efektif: landing page, company profile, hingga toko online. Fokus pada desain modern, performa, dan kemudahan dikelola.
        </p>
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
          <a href="/syntaxtrust/public/pricing.php" class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-3 text-white shadow-soft hover:brightness-110">Lihat Harga</a>
          <a href="/syntaxtrust/public/services.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-5 py-3 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Layanan</a>
        </div>
        <div class="mt-6 grid grid-cols-3 gap-6 text-center">
          <div>
            <div class="text-3xl font-bold">99.9%</div>
            <div class="text-xs text-slate-500">Uptime</div>
          </div>
          <div>
            <div class="text-3xl font-bold">7-14 hr</div>
            <div class="text-xs text-slate-500">Estimasi</div>
          </div>
          <div>
            <div class="text-3xl font-bold">SEO</div>
            <div class="text-xs text-slate-500">Basic</div>
          </div>
        </div>
      </div>
      <div class="relative" data-reveal="left">
        <div class="relative rounded-2xl border border-slate-200 bg-white p-4 shadow-xl ring-1 ring-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:ring-slate-800">
          <img
            src="https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=1600&auto=format&fit=crop"
            srcset="https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=800&auto=format&fit=crop 800w, https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=1200&auto=format&fit=crop 1200w, https://images.unsplash.com/photo-1556157382-97eda2d62296?q=80&w=1600&auto=format&fit=crop 1600w"
            sizes="(min-width: 1024px) 50vw, 100vw"
            class="rounded-lg"
            alt="Website preview"
            decoding="async"
            width="1600"
            height="900"
            fetchpriority="high" />
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Proses Kerja -->
<section class="py-16 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Proses Kerja</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Alur sederhana yang memastikan proyek berjalan efektif dari awal hingga rilis.</p>
    </div>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="text-xs font-medium text-slate-500">01</div>
        <h3 class="mt-2 font-semibold">Konsultasi</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Diskusi kebutuhan & tujuan bisnis, menentukan ruang lingkup.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="text-xs font-medium text-slate-500">02</div>
        <h3 class="mt-2 font-semibold">Perencanaan</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Sitemap, konten, dan timeline disepakati sebelum mulai desain/dev.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="text-xs font-medium text-slate-500">03</div>
        <h3 class="mt-2 font-semibold">Pengembangan</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Implementasi desain responsif, integrasi fitur, dan QA internal.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="text-xs font-medium text-slate-500">04</div>
        <h3 class="mt-2 font-semibold">Launch & Support</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Go-live, handover, dan dukungan pasca-rilis sesuai kebutuhan.</p>
      </div>
    </div>
  </div>
</section>

<!-- Keunggulan -->
<section class="py-16">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Keunggulan Kami</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Alasan klien memilih SyntaxTrust untuk membangun kehadiran digital.</p>
    </div>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </div>
        <h3 class="font-semibold">Proses Cepat</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Estimasi 7–14 hari kerja dengan tahapan jelas dan komunikatif.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 7h7l-5.5 4 2.5 7-7-4.5L5 20l2.5-7L2 9h7z"/></svg>
        </div>
        <h3 class="font-semibold">Desain Modern</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Responsif, rapi, dan konsisten dengan praktik UI/UX terkini.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M4 8h16M6 4h12M6 16h12M4 20h16"/></svg>
        </div>
        <h3 class="font-semibold">SEO Dasar</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Struktur markup, meta tag, dan sitemap untuk visibilitas awal.</p>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <div class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 17l4 4 4-4M12 3v18"/></svg>
        </div>
        <h3 class="font-semibold">Support Responsif</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Bantuan cepat via email/telepon. Garansi perbaikan minor.</p>
      </div>
    </div>
  </div>
</section>

<!-- Clients -->
<section class="py-14">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <p class="text-center text-sm font-medium text-slate-500" data-reveal="down">Dipercaya oleh klien dari berbagai industri.</p>
    <div class="mt-6 grid grid-cols-2 items-center justify-center gap-6 opacity-80 sm:grid-cols-3 md:grid-cols-6">
      <?php if (!empty($clients)): foreach ($clients as $c): ?>
        <?php $logo = $c['logo'] ?? ''; $name = $c['name'] ?? 'Client'; ?>
        <?php
          // Fallback: if DB stores only a filename (no slash), assume clients folder under admin/uploads
          $logoPath = $logo;
          if ($logo && strpos($logo, '/') === false) {
            $c1 = 'admin/uploads/clients/' . ltrim($logo, '/');
            $c2 = 'admin/uploads/' . ltrim($logo, '/'); // fallback: root uploads
            $a1 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $c1);
            $a2 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $c2);
            if (is_file($a1)) { $logoPath = $c1; }
            elseif (is_file($a2)) { $logoPath = $c2; }
            else { $logoPath = $c1; }
          }
        ?>
        <div data-reveal="up">
          <?php if ($logoPath): ?>
            <!-- debug: <?php echo htmlspecialchars(mediaUrl($logoPath), ENT_QUOTES, 'UTF-8'); ?> -->
            <img class="h-8 mx-auto" src="<?php echo htmlspecialchars(mediaUrl($logoPath), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async" />
          <?php else: ?>
            <div class="h-8 flex items-center justify-center text-xs text-slate-500 mx-auto"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; else: ?>
        <div class="col-span-6 text-center text-slate-400 text-sm">Belum ada data klien.</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Tim -->
<section id="tim" class="py-20">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Tim Kami</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Tim kecil yang fokus pada kualitas dan komunikasi.</p>
    </div>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php if (!empty($team)): foreach ($team as $m): ?>
        <?php
          $img = $m['profile_image'] ?? '';
          // Fallback: if only filename, assume stored under admin/uploads/team
          $imgPath = $img;
          if ($img && strpos($img, '/') === false) {
            $t1 = 'admin/uploads/team/' . ltrim($img, '/');
            $t2 = 'admin/uploads/' . ltrim($img, '/'); // fallback: root uploads
            $ta1 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $t1);
            $ta2 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $t2);
            if (is_file($ta1)) { $imgPath = $t1; }
            elseif (is_file($ta2)) { $imgPath = $t2; }
            else { $imgPath = $t1; }
          }
          $fallback = 'https://ui-avatars.com/api/?background=0ea5e9&color=fff&name=' . urlencode($m['name'] ?? 'Member');
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
          <img class="mx-auto h-20 w-20 rounded-full object-cover" src="<?php echo htmlspecialchars($img ? mediaUrl($imgPath) : $fallback, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? 'Member', ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async" width="80" height="80" />
          <h3 class="mt-4 font-semibold"><?php echo htmlspecialchars($m['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
          <p class="text-sm text-slate-500"><?php echo htmlspecialchars($m['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada anggota tim.</div>
      <?php endif; ?>
    </div>
    <div class="mt-8 text-center">
      <a href="/syntaxtrust/public/team.php" class="text-sm text-blue-600 hover:underline">Lihat semua tim →</a>
    </div>
  </div>
</section>

<!-- Blog -->
<section id="blog" class="py-20 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Blog Terbaru</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Tips website, SEO, dan go-digital untuk UMKM.</p>
    </div>
    <div class="mt-12 grid gap-6 md:grid-cols-3">
      <?php if (!empty($blogPosts)): foreach ($blogPosts as $bp): ?>
        <?php
          $img = $bp['featured_image'] ?? '';
          // Fallback: if only filename, assume stored under admin/uploads/blog
          $imgPath = $img;
          if ($img && strpos($img, '/') === false) {
            $b1 = 'admin/uploads/blog/' . ltrim($img, '/');
            $b2 = 'admin/uploads/' . ltrim($img, '/'); // fallback: root uploads
            $ba1 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $b1);
            $ba2 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $b2);
            if (is_file($ba1)) { $imgPath = $b1; }
            elseif (is_file($ba2)) { $imgPath = $b2; }
            else { $imgPath = $b1; }
          }
          $title = $bp['title'] ?? 'Untitled';
          $excerpt = $bp['excerpt'] ?? '';
          $slug = $bp['slug'] ?? '';
          $url = $slug ? '/syntaxtrust/public/blog.php?slug=' . urlencode($slug) : '#';
        ?>
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
          <img
            class="h-40 w-full object-cover"
            src="<?php echo htmlspecialchars($img ? mediaUrl($imgPath) : 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=1200&auto=format&fit=crop', ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
            loading="lazy"
            decoding="async"
            width="1200"
            height="675" />
          <div class="p-4">
            <h3 class="font-semibold"><a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></a></h3>
            <?php if (!empty($excerpt)): ?><p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada artikel yang dipublikasikan.</div>
      <?php endif; ?>
    </div>
    <div class="mt-8 text-center">
      <a href="/syntaxtrust/public/blog.php" class="text-sm text-blue-600 hover:underline">Lihat semua artikel →</a>
    </div>
  </div>
</section>
<!-- Layanan -->
<section id="layanan" class="py-20">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Layanan Kami</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Paket hemat untuk kebutuhan Mahasiswa, UMKM, dan bisnis kecil.</p>
    </div>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php if (!empty($services)): foreach ($services as $s): ?>
        <div class="rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition dark:border-slate-700" data-reveal="up">
          <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18M3 12h12M3 17h18"/></svg>
          </div>
          <h3 class="font-semibold"><?php echo htmlspecialchars($s['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
          <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($s['short_description'] ?: ($s['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada layanan aktif.</div>
      <?php endif; ?>
    </div>
    <div class="mt-8 text-center">
      <a href="/syntaxtrust/public/services.php" class="text-sm text-blue-600 hover:underline">Lihat semua layanan →</a>
    </div>
  </div>
</section>

<!-- Portofolio -->
<section id="portofolio" class="py-20 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Contoh Pekerjaan</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Beberapa proyek yang pernah kami kerjakan.</p>
    </div>
    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php if (!empty($portfolioItems)): foreach ($portfolioItems as $pf): ?>
        <?php
          $img = $pf['image_main'] ?? '';
          // Fallback: if only filename, map to admin/uploads folder structure (portfolio/portofolio)
          $imgPath = $img;
          if ($img && strpos($img, '/') === false) {
            // Prefer the English 'portfolio' folder, fallback to Indonesian 'portofolio'
            $try1 = 'admin/uploads/portfolio/' . ltrim($img, '/');
            $try2 = 'admin/uploads/portofolio/' . ltrim($img, '/');
            $try3 = 'admin/uploads/' . ltrim($img, '/'); // fallback: root uploads
            // Resolve to an existing file if possible (server-side check)
            $abs1 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try1);
            $abs2 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try2);
            $abs3 = __DIR__ . '/../' . str_replace(['..', chr(92)], ['', '/'], $try3);
            if (is_file($abs1)) {
              $imgPath = $try1;
            } elseif (is_file($abs2)) {
              $imgPath = $try2;
            } elseif (is_file($abs3)) {
              $imgPath = $try3;
            } else {
              // Default to portfolio path if neither exists
              $imgPath = $try1;
            }
          }
          $title = $pf['title'] ?? 'Project';
          $desc = $pf['short_description'] ?? '';
          $url = !empty($pf['project_url']) ? $pf['project_url'] : ('/syntaxtrust/public/portfolio.php?id=' . (int)($pf['id'] ?? 0));
        ?>
        <?php $isExternal = !empty($pf['project_url']); ?>
        <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isExternal ? 'target="_blank" rel="noopener"' : ''; ?> class="group block overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
          <img
            class="h-44 w-full object-cover transition group-hover:scale-[1.02]"
            src="<?php echo htmlspecialchars($img ? mediaUrl($imgPath) : 'https://images.unsplash.com/photo-1557800636-894a64c1696f?q=80&w=1200&auto=format&fit=crop', ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
            loading="lazy"
            decoding="async"
            width="1200"
            height="675" />
          <div class="p-4">
            <h3 class="font-semibold"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
            <?php if (!empty($desc)): ?><p class="mt-1 text-xs text-slate-500"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
          </div>
        </a>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada portofolio aktif.</div>
      <?php endif; ?>
    </div>
    <div class="mt-8 text-center">
      <a href="/syntaxtrust/public/portfolio.php" class="text-sm text-blue-600 hover:underline">Lihat semua portofolio →</a>
    </div>
  </div>
</section>

<!-- Pricing -->
<section id="pricing" class="py-20 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Harga Ramah Mahasiswa & UMKM</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Pembayaran bisa bertahap (DP 50%). Harga dapat disesuaikan kebutuhan.</p>
    </div>
    <div class="mt-12 grid gap-6 md:grid-cols-3">
      <?php if (!empty($plans)): foreach ($plans as $p): ?>
        <?php
          $isPopular = !empty($p['is_popular']);
          $features = decode_json_array($p['features'] ?? '[]');
          $currency = strtoupper($p['currency'] ?? 'IDR');
          // Simple IDR formatting
          $amount = (float)($p['price'] ?? 0);
          $formatted = $currency === 'IDR' ? 'Rp ' . number_format($amount, 0, ',', '.') : ($currency . ' ' . number_format($amount, 2));
        ?>
        <div class="<?php echo $isPopular ? 'relative rounded-2xl border-2 border-primary bg-white p-6 shadow-soft dark:border-sky-500 dark:bg-slate-900' : 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900'; ?>" data-reveal="up">
          <?php if ($isPopular): ?><span class="absolute -top-3 right-4 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-white">Populer</span><?php endif; ?>
          <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
          <?php if (!empty($p['subtitle'])): ?><p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($p['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
          <div class="mt-4 text-3xl font-extrabold"><?php echo $formatted; ?><span class="text-base font-medium text-slate-500"> <?php echo htmlspecialchars($p['billing_period'] ?? 'one-time', ENT_QUOTES, 'UTF-8'); ?></span></div>
          <?php if (!empty($features)): ?>
            <ul class="mt-4 space-y-2 text-sm">
              <?php foreach ($features as $f): ?>
                <li>• <?php echo htmlspecialchars((string)$f, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <a href="/syntaxtrust/public/contact.php" class="mt-6 inline-flex w-full items-center justify-center rounded-lg <?php echo $isPopular ? 'bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110' : 'border border-slate-300 px-4 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'; ?>">Hubungi</a>
        </div>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada paket harga.</div>
      <?php endif; ?>
    </div>
    <div class="mt-8 text-center">
      <a href="/syntaxtrust/public/pricing.php" class="text-sm text-blue-600 hover:underline">Lihat detail harga →</a>
    </div>
  </div>
</section>

<!-- Testimoni -->
<section id="testimoni" class="py-20">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Apa Kata Klien</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Dipercaya berbagai industri.</p>
    </div>
    <div class="mt-12 grid gap-6 md:grid-cols-3">
      <?php if (!empty($testimonials)): foreach ($testimonials as $t): ?>
        <figure class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
          <blockquote class="text-sm text-slate-700 dark:text-slate-300">“<?php echo htmlspecialchars($t['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?>”</blockquote>
          <figcaption class="mt-4 text-xs text-slate-500"><?php echo htmlspecialchars($t['client_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($t['client_company']) ? ', ' . htmlspecialchars($t['client_company'], ENT_QUOTES, 'UTF-8') : ''; ?></figcaption>
        </figure>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada testimoni.</div>
      <?php endif; ?>
    </div>
    <div class="mt-8 text-center">
      <a href="/syntaxtrust/public/testimonials.php" class="text-sm text-blue-600 hover:underline">Lihat semua testimoni →</a>
    </div>
  </div>
</section>

<!-- FAQ (preview) -->
<section id="faq" class="py-20 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Pertanyaan Umum</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Temukan jawaban cepat tentang proses, revisi, dan pembayaran.</p>
    </div>
    <div class="mt-8 text-center">
      <a href="/syntaxtrust/public/faq.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-5 py-2.5 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Buka halaman FAQ</a>
    </div>
  </div>
  </section>

<!-- Kontak (CTA) -->
<section id="kontak" class="py-20">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Siap Diskusi Proyek?</h2>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Hubungi tim kami untuk konsultasi gratis dan penawaran terbaik.</p>
    </div>
    <div class="mt-10 grid gap-8 md:grid-cols-2">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <h3 class="font-semibold">Kontak</h3>
        <div class="mt-4 text-sm">
          <p>Email: <?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></p>
          <p>Tel: <?php echo htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="mt-2 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <a href="/syntaxtrust/public/contact.php" class="mt-6 inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-white shadow-soft hover:brightness-110">Buka Halaman Kontak</a>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-white p-0 overflow-hidden shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="left">
        <img
          src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?q=80&w=1200&auto=format&fit=crop"
          srcset="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?q=80&w=800&auto=format&fit=crop 800w, https://images.unsplash.com/photo-1521737604893-d14cc237f11d?q=80&w=1200&auto=format&fit=crop 1200w, https://images.unsplash.com/photo-1521737604893-d14cc237f11d?q=80&w=1600&auto=format&fit=crop 1600w"
          sizes="(min-width: 768px) 50vw, 100vw"
          alt="Office"
          class="h-full w-full object-cover"
          loading="lazy"
          decoding="async"
          width="1200"
          height="675" />
      </div>
    </div>
  </div>
</section>

<!-- CTA Akhir -->
<section class="py-16">
  <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 text-center" data-reveal="down">
    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Mulai Sekarang</h2>
    <p class="mt-3 text-slate-600 dark:text-slate-400">Dapatkan website profesional yang membantu bisnis Anda tumbuh. Konsultasi gratis hari ini.</p>
    <div class="mt-6">
      <a href="/syntaxtrust/public/contact.php" class="inline-flex items-center justify-center rounded-lg bg-primary px-6 py-3 text-white shadow-soft hover:brightness-110">Hubungi Kami</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
