<?php
$pageTitle = 'Terima Kasih | SyntaxTrust';
$pageDesc  = 'Konfirmasi pembuatan payment intent.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$canonicalUrl = $scheme . '://' . $host . '/syntaxtrust/public/payment_intent_thanks.php';

require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../config/database.php';

$intent = isset($_GET['intent']) ? trim((string)$_GET['intent']) : '';
$record = null;
if ($intent !== '') {
  try {
    $stmt = $pdo->prepare("SELECT intent_number, customer_name, customer_email, pricing_plan_id, status, created_at FROM payment_intents WHERE intent_number = :intent LIMIT 1");
    $stmt->execute([':intent' => $intent]);
    $record = $stmt->fetch();
  } catch (Exception $e) { $record = null; }
}
?>

<main class="min-h-screen py-16">
  <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900" data-reveal="up">
      <h1 class="text-2xl font-bold">Terima kasih!</h1>
      <p class="mt-3 text-slate-600 dark:text-slate-400">Pengajuan niat pembayaran Anda telah kami terima. Tim kami akan meninjau dan menghubungi Anda.</p>
      <?php if ($record): ?>
        <div class="mt-6 inline-block rounded-xl border border-slate-200 bg-slate-50 p-4 text-left text-sm dark:border-slate-700 dark:bg-slate-800/40">
          <p><span class="font-medium">Nomor Intent:</span> <?php echo htmlspecialchars($record['intent_number'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="mt-1"><span class="font-medium">Nama:</span> <?php echo htmlspecialchars($record['customer_name'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="mt-1"><span class="font-medium">Email:</span> <?php echo htmlspecialchars($record['customer_email'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="mt-1"><span class="font-medium">Status:</span> <?php echo htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      <?php elseif ($intent !== ''): ?>
        <p class="mt-6 text-sm text-slate-500">Nomor intent: <?php echo htmlspecialchars($intent, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <div class="mt-8 flex items-center justify-center gap-3">
        <a href="/syntaxtrust/public/index.php" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">Kembali ke Beranda</a>
        <a href="/syntaxtrust/public/pricing.php" class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-white shadow-soft hover:brightness-110">Lihat Paket</a>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
