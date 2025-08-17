<?php
$pageTitle = 'Harga | SyntaxTrust';
$pageDesc  = 'Harga ramah Mahasiswa & UMKM. Pembayaran bisa bertahap (DP 50%).';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/pricing.php';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Fetch pricing plans
$plans = [];
try {
  $stmt = $pdo->query("SELECT id, name, subtitle, price, currency, billing_period, features, is_popular FROM pricing_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 50");
  $plans = $stmt->fetchAll();
} catch (Exception $e) { $plans = []; }

function decode_json_array($json) {
  $arr = json_decode($json ?? '[]', true);
  return is_array($arr) ? $arr : [];
}
?>

<main class="min-h-screen py-16 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Harga Ramah Mahasiswa & UMKM</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Pembayaran bisa bertahap (DP 50%). Harga dapat disesuaikan kebutuhan.</p>
    </div>

    <div class="mt-12 grid gap-6 md:grid-cols-3">
      <?php if (!empty($plans)): foreach ($plans as $p): ?>
        <?php
          $isPopular = !empty($p['is_popular']);
          $features = decode_json_array($p['features'] ?? '[]');
          $currency = strtoupper($p['currency'] ?? 'IDR');
          $amount = (float)($p['price'] ?? 0);
          $formatted = $currency === 'IDR' ? 'Rp ' . number_format($amount, 0, ',', '.') : ($currency . ' ' . number_format($amount, 2));
        ?>
        <div class="<?php echo $isPopular ? 'relative rounded-2xl border-2 border-primary bg-white p-6 shadow-soft dark:border-sky-500 dark:bg-slate-900' : 'rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900'; ?>" data-reveal="up">
          <?php if ($isPopular): ?><span class="absolute -top-3 right-4 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-white">Populer</span><?php endif; ?>
          <h2 class="text-lg font-semibold"><?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h2>
          <?php if (!empty($p['subtitle'])): ?><p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($p['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
          <div class="mt-4 text-3xl font-extrabold"><?php echo $formatted; ?><span class="text-base font-medium text-slate-500"> <?php echo htmlspecialchars($p['billing_period'] ?? 'one-time', ENT_QUOTES, 'UTF-8'); ?></span></div>
          <?php if (!empty($features)): ?>
            <ul class="mt-4 space-y-2 text-sm">
              <?php foreach ($features as $f): ?>
                <li>• <?php echo htmlspecialchars((string)$f, ENT_QUOTES, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a href="/syntaxtrust/public/contact.php" class="inline-flex w-full items-center justify-center rounded-lg <?php echo $isPopular ? 'border border-primary px-4 py-2 text-primary hover:bg-primary/5' : 'border border-slate-300 px-4 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'; ?>">Hubungi</a>
            <a href="/syntaxtrust/public/payment_intent_new.php?plan_id=<?php echo urlencode((string)($p['id'] ?? '')); ?>" class="inline-flex w-full items-center justify-center rounded-lg <?php echo $isPopular ? 'bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110' : 'bg-slate-900 px-4 py-2 text-white hover:brightness-110 dark:bg-slate-700'; ?>">Pesan</a>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="col-span-3 text-center text-slate-400 text-sm">Belum ada paket harga.</div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
