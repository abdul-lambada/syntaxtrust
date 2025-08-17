<?php
$pageTitle = 'Kontak | SyntaxTrust';
$pageDesc  = 'Hubungi kami untuk konsultasi pembuatan website.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/contact.php';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Fetch public settings
$settings = [];
try {
  $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE is_public = 1");
  foreach ($stmt->fetchAll() as $row) { $settings[$row['setting_key']] = $row['setting_value']; }
} catch (Exception $e) { $settings = []; }

$contact_email = $settings['contact_email'] ?? 'support@syntaxtrust.com';
$contact_phone = $settings['contact_phone'] ?? '+1 (555) 123-4567';
$address = $settings['address'] ?? 'Silicon Valley, California';

// CSRF token for contact form (single-use)
if (empty($_SESSION['csrf_contact'])) {
  try { $_SESSION['csrf_contact'] = bin2hex(random_bytes(32)); } catch (Exception $e) { $_SESSION['csrf_contact'] = bin2hex(openssl_random_pseudo_bytes(32)); }
}
$csrf_contact = $_SESSION['csrf_contact'];
?>

<main class="min-h-screen py-16">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-2xl text-center">
      <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Hubungi Kami</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Tinggalkan pesan, tim kami akan menghubungi Anda.</p>
    </div>

    <div class="mt-10 grid gap-8 md:grid-cols-2">
      <?php if (!empty($_GET['sent']) && $_GET['sent'] === '1'): ?>
        <div class="md:col-span-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
          Pesan Anda telah terkirim. Kami akan menghubungi Anda segera.
        </div>
      <?php elseif (!empty($_GET['error'])): ?>
        <div class="md:col-span-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
          <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>
      <form action="/syntaxtrust/public/contact_submit.php" method="post" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="grid gap-4">
          <div>
            <label class="text-sm font-medium">Nama</label>
            <input name="name" required maxlength="100" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="Nama Anda"/>
          </div>
          <div>
            <label class="text-sm font-medium">Email</label>
            <input name="email" required type="email" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="nama@perusahaan.com"/>
          </div>
          <div>
            <label class="text-sm font-medium">Telepon (opsional)</label>
            <input name="phone" maxlength="20" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="08xxxxxxxxxx"/>
          </div>
          <div>
            <label class="text-sm font-medium">Subjek (opsional)</label>
            <input name="subject" maxlength="200" type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="Judul singkat pesan"/>
          </div>
          <div>
            <label class="text-sm font-medium">Pesan</label>
            <textarea name="message" required rows="4" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900" placeholder="Ceritakan kebutuhan Anda..."></textarea>
          </div>
          <!-- Honeypot anti-spam field: keep hidden from users -->
          <div class="hidden" aria-hidden="true">
            <label>Website</label>
            <input type="text" name="website" tabindex="-1" autocomplete="off" />
          </div>
          <input type="hidden" name="csrf_contact" value="<?php echo htmlspecialchars($csrf_contact, ENT_QUOTES, 'UTF-8'); ?>" />
          <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110">Kirim</button>
        </div>
      </form>
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h2 class="font-semibold">Kantor</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="mt-4 text-sm">
          <p>Email: <?php echo htmlspecialchars($contact_email, ENT_QUOTES, 'UTF-8'); ?></p>
          <p>Tel: <?php echo htmlspecialchars($contact_phone, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div class="mt-6 h-52 w-full overflow-hidden rounded-xl">
          <img src="https://images.unsplash.com/photo-1521737604893-d14cc237f11d?q=80&w=1200&auto=format&fit=crop" alt="Office" class="h-full w-full object-cover" loading="lazy" decoding="async" />
        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
