<?php
$pageTitle = 'Tim | SyntaxTrust';
$pageDesc  = 'Tim kecil yang fokus pada kualitas dan komunikasi.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/team.php';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$team = [];
try {
  $stmt = $pdo->query("SELECT name, position, profile_image FROM team WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 100");
  $team = $stmt->fetchAll();
} catch (Exception $e) { $team = []; }
?>

<main class="min-h-screen py-16">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Tim Kami</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Tim kecil yang fokus pada kualitas dan komunikasi.</p>
    </div>

    <div class="mt-12" x-data="{ open:false, m:{ name:'', position:'', img:'' } }">
      <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php if (!empty($team)): foreach ($team as $m): ?>
        <?php
          $img = $m['profile_image'] ?? '';
          $fallback = 'https://ui-avatars.com/api/?background=0ea5e9&color=fff&name=' . urlencode($m['name'] ?? 'Member');
        ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary" data-reveal="up" role="button" tabindex="0" @click="open=true; m={ name:'<?php echo htmlspecialchars($m['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', position:'<?php echo htmlspecialchars($m['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', img:'<?php echo htmlspecialchars($img ?: $fallback, ENT_QUOTES, 'UTF-8'); ?>' }" @keydown.enter.prevent="open=true; m={ name:'<?php echo htmlspecialchars($m['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', position:'<?php echo htmlspecialchars($m['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', img:'<?php echo htmlspecialchars($img ?: $fallback, ENT_QUOTES, 'UTF-8'); ?>' }">
          <img class="mx-auto h-20 w-20 rounded-full object-cover" src="<?php echo htmlspecialchars($img ?: $fallback, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($m['name'] ?? 'Member', ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async" width="80" height="80"/>
          <h2 class="mt-4 font-semibold"><?php echo htmlspecialchars($m['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
          <p class="text-sm text-slate-500"><?php echo htmlspecialchars($m['position'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada anggota tim.</div>
      <?php endif; ?>
      </div>
      <!-- Detail Modal -->
      <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @keydown.escape.window="open=false" @click.self="open=false" style="display:none">
        <div class="relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-slate-900">
          <button type="button" class="absolute right-3 top-3 rounded-md bg-slate-100 px-2 py-1 text-xs hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700" @click="open=false">Tutup</button>
          <img :src="m.img" alt="Foto" class="mx-auto h-28 w-28 rounded-full object-cover" loading="lazy" decoding="async" width="112" height="112" />
          <h3 class="mt-4 text-center text-lg font-semibold text-slate-800 dark:text-slate-100" x-text="m.name"></h3>
          <p class="text-center text-sm text-slate-500" x-text="m.position"></p>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
