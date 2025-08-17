<?php
$pageTitle = 'Testimoni | SyntaxTrust';
$pageDesc  = 'Apa kata klien tentang layanan kami.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/testimonials.php';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$testimonials = [];
try {
  $stmt = $pdo->query("SELECT client_name, client_company, content FROM testimonials WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 100");
  $testimonials = $stmt->fetchAll();
} catch (Exception $e) { $testimonials = []; }
?>

<main class="min-h-screen py-16">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Apa Kata Klien</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Dipercaya Berbagai Mahasiswa dan Industri.</p>
    </div>

    <?php $count = is_array($testimonials) ? count($testimonials) : 0; ?>
    <?php if ($count > 0): ?>
      <div class="relative mt-12" x-data="{ idx: 0, count: <?php echo (int)$count; ?>, autoplay: true }" x-init="if (count>1) { setInterval(()=>{ if(!autoplay) return; idx = (idx + 1) % count }, 5000) }">
        <!-- Slider viewport -->
        <div class="overflow-hidden rounded-2xl" data-reveal="up">
          <div class="flex transition-transform duration-500 ease-out" :style="'transform: translateX(-' + (idx * 100) + '%)'">
            <?php foreach ($testimonials as $t): ?>
              <figure class="w-full shrink-0 px-2 sm:px-4">
                <div class="h-full rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                  <blockquote class="text-sm text-slate-700 dark:text-slate-300">“<?php echo htmlspecialchars($t['content'] ?? '', ENT_QUOTES, 'UTF-8'); ?>”</blockquote>
                  <figcaption class="mt-4 text-xs text-slate-500"><?php echo htmlspecialchars($t['client_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($t['client_company']) ? ', ' . htmlspecialchars($t['client_company'], ENT_QUOTES, 'UTF-8') : ''; ?></figcaption>
                </div>
              </figure>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($count > 1): ?>
        <!-- Controls -->
        <button type="button" class="absolute left-2 top-1/2 -translate-y-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 ring-1 ring-slate-200 shadow hover:bg-white dark:bg-slate-800/90 dark:ring-slate-700" @click="idx = (idx - 1 + count) % count; autoplay=false" aria-label="Sebelumnya">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/90 ring-1 ring-slate-200 shadow hover:bg-white dark:bg-slate-800/90 dark:ring-slate-700" @click="idx = (idx + 1) % count; autoplay=false" aria-label="Berikutnya">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>

        <!-- Dots -->
        <div class="mt-4 flex justify-center gap-2">
          <template x-for="i in count" :key="i">
            <button class="h-2.5 w-2.5 rounded-full ring-1 ring-slate-300 dark:ring-slate-700" :class="idx === (i-1) ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-700'" @click="idx = i-1; autoplay=false" aria-label="Pilih slide"></button>
          </template>
        </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="mt-12 text-center text-slate-400 text-sm">Belum ada testimoni.</div>
    <?php endif; ?>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
