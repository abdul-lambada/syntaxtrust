<?php
$pageTitle = 'Pesan Layanan | SyntaxTrust';
$pageDesc  = 'Form pemesanan untuk membuat payment intent.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/payment_intent_new.php';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

// CSRF token for payment intent form (single-use)
if (empty($_SESSION['csrf_pi'])) {
  try { $_SESSION['csrf_pi'] = bin2hex(random_bytes(32)); } catch (Exception $e) { $_SESSION['csrf_pi'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}
$csrf_pi = $_SESSION['csrf_pi'];

// Fetch lists for selects
$plans = [];
$services = [];
try {
  $stmt = $pdo->query("SELECT id, name, price, currency, billing_period FROM pricing_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 100");
  $plans = $stmt->fetchAll();
} catch (Exception $e) { $plans = []; }
try {
  $s = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 100");
  $services = $s->fetchAll();
} catch (Exception $e) { $services = []; }

// Preselect plan name for display
$selectedPlan = null;
foreach ($plans as $p) { if ((int)$p['id'] === $planId) { $selectedPlan = $p; break; } }
?>

<main class="min-h-screen py-16 bg-slate-50 dark:bg-slate-900/30">
  <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center" data-reveal="down">
      <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Form Pemesanan</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Isi data berikut untuk membuat niat pembayaran (payment intent). Tim kami akan meninjau dan menghubungi Anda.</p>
    </div>

    <?php if (!empty($_GET['error'])): ?>
      <div class="mt-8 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
        <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <form action="/syntaxtrust/public/payment_intent_submit.php" method="post" enctype="multipart/form-data" class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <div class="grid gap-4 md:grid-cols-2">
        <div class="md:col-span-2">
          <label class="text-sm font-medium">Nama</label>
          <input name="customer_name" required maxlength="100" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="Nama lengkap"/>
        </div>
        <div>
          <label class="text-sm font-medium">Email</label>
          <input name="customer_email" required type="email" maxlength="100" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="nama@contoh.com"/>
        </div>
        <div>
          <label class="text-sm font-medium">Telepon (opsional)</label>
          <input name="customer_phone" type="text" maxlength="20" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="08xxxxxxxxxx"/>
        </div>
        <div>
          <label class="text-sm font-medium">Paket Harga</label>
          <select name="pricing_plan_id" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900">
            <option value="">Pilih paket</option>
            <?php foreach ($plans as $pp): $sel = ((int)$pp['id'] === $planId) ? 'selected' : ''; ?>
              <option value="<?php echo (int)$pp['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($pp['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium">Layanan (opsional)</label>
          <select name="service_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900">
            <option value="">Pilih layanan</option>
            <?php foreach ($services as $sv): ?>
              <option value="<?php echo (int)$sv['id']; ?>"><?php echo htmlspecialchars($sv['name'], ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-sm font-medium">Perkiraan Nominal (opsional)</label>
          <input name="amount" type="number" step="0.01" min="0" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="cth: 500000"/>
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-medium">Catatan (opsional)</label>
          <textarea name="notes" rows="4" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="Tambahkan detail kebutuhan, tenggat waktu, dll."></textarea>
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-medium">Upload Bukti (opsional)</label>
          <input name="payment_proof" type="file" accept="image/png,image/jpeg,image/webp,application/pdf" class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200" />
          <p class="mt-1 text-xs text-slate-500">Maks 2MB. Format: JPG, PNG, WEBP, PDF.</p>
        </div>
        <!-- Honeypot anti-spam field: keep hidden from users -->
        <div class="hidden" aria-hidden="true">
          <label>Website</label>
          <input type="text" name="website" tabindex="-1" autocomplete="off" />
        </div>
        <input type="hidden" name="csrf_pi" value="<?php echo htmlspecialchars($csrf_pi, ENT_QUOTES, 'UTF-8'); ?>" />
        <div class="md:col-span-2">
          <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110">Kirim Pemesanan</button>
        </div>
      </div>

      <?php if ($selectedPlan): ?>
        <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-700 dark:bg-slate-800/40">
          <p>Anda memilih paket: <strong><?php echo htmlspecialchars($selectedPlan['name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </div>
      <?php endif; ?>
    </form>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
