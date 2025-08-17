<?php
$pageTitle = 'FAQ | SyntaxTrust';
$pageDesc  = 'Pertanyaan umum seputar proses pembuatan website.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/faq.php';

require __DIR__ . '/includes/header.php';
?>

<main class="min-h-screen py-16 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Pertanyaan Umum</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Seputar proses pembuatan website.</p>
    </div>

    <div class="mt-10 space-y-4" x-data="{ open: null }">
      <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <button class="flex w-full items-center justify-between text-left" @click="open === 1 ? open = null : open = 1">
          <span class="font-medium">Berapa lama proses pembuatan?</span>
          <svg class="h-5 w-5 transition" :class="open===1?'rotate-180':''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd"/></svg>
        </button>
        <div class="mt-2 text-sm text-slate-600 dark:text-slate-400" x-show="open===1" x-collapse>
          Umumnya 7–14 hari kerja tergantung paket dan kelengkapan konten.
        </div>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <button class="flex w-full items-center justify-between text-left" @click="open === 2 ? open = null : open = 2">
          <span class="font-medium">Apakah termasuk revisi?</span>
          <svg class="h-5 w-5 transition" :class="open===2?'rotate-180':''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd"/></svg>
        </button>
        <div class="mt-2 text-sm text-slate-600 dark:text-slate-400" x-show="open===2" x-collapse>
          Iya, paket sudah termasuk revisi (lihat detail tiap paket: 1–3x revisi).
        </div>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
        <button class="flex w-full items-center justify-between text-left" @click="open === 3 ? open = null : open = 3">
          <span class="font-medium">Bagaimana skema pembayaran?</span>
          <svg class="h-5 w-5 transition" :class="open===3?'rotate-180':''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.25 8.27a.75.75 0 01-.02-1.06z" clip-rule="evenodd"/></svg>
        </button>
        <div class="mt-2 text-sm text-slate-600 dark:text-slate-400" x-show="open===3" x-collapse>
          DP 50% saat mulai, pelunasan ketika website selesai dan disetujui.
        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
