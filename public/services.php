<?php
// Services page
$pageTitle = 'Layanan | SyntaxTrust';
$pageDesc  = 'Paket layanan pembuatan website untuk Mahasiswa & UMKM.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/services.php';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Fetch services
$services = [];
try {
  $stmt = $pdo->query("SELECT name, short_description, description, icon FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 50");
  $services = $stmt->fetchAll();
} catch (Exception $e) { $services = []; }
?>

<main class="min-h-screen py-16 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Layanan Kami</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Paket hemat untuk kebutuhan Mahasiswa, UMKM, dan bisnis kecil.</p>
    </div>

    <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php if (!empty($services)): foreach ($services as $s): ?>
        <div class="rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition dark:border-slate-700" data-reveal="up">
          <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18M3 12h12M3 17h18"/></svg>
          </div>
          <h2 class="font-semibold"><?php echo htmlspecialchars($s['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
          <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($s['short_description'] ?: ($s['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada layanan aktif.</div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
