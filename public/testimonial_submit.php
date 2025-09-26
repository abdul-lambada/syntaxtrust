<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/layout.php';

$pageTitle = 'Kirim Testimoni';
$currentPage = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$order_number = isset($_GET['order_number']) ? trim((string)$_GET['order_number']) : '';
$order = null;
if ($order_number !== '') {
    try {
        $s = $pdo->prepare('SELECT o.*, s.name AS service_name, s.id AS service_id FROM orders o LEFT JOIN services s ON o.service_id = s.id WHERE o.order_number = ? LIMIT 1');
        $s->execute([$order_number]);
        $order = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { $order = null; }
}

$msg = '';
$msg_type = '';
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $msg = 'Sesi kadaluarsa. Silakan refresh halaman.';
        $msg_type = 'danger';
    } else {
        $client_name = trim((string)($_POST['client_name'] ?? ''));
        $client_position = trim((string)($_POST['client_position'] ?? ''));
        $client_company = trim((string)($_POST['client_company'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $rating = isset($_POST['rating']) ? (float)$_POST['rating'] : null;
        $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;

        if ($client_name === '' || $content === '' || !$rating) {
            $msg = 'Nama, testimoni, dan rating wajib diisi.';
            $msg_type = 'danger';
        } else {
            // Auto-approve toggle from settings (default: moderation queue)
            $autoApprove = 0;
            try {
                $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
                $st->execute(['testimonials_auto_approve']);
                $autoApprove = trim((string)($st->fetchColumn() ?: '0')) === '1' ? 1 : 0;
            } catch (Throwable $e) { $autoApprove = 0; }
            try {
                $ins = $pdo->prepare('INSERT INTO testimonials (client_name, client_position, client_company, client_image, content, rating, project_name, service_id, is_featured, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 0, ?, 0, NOW(), NOW())');
                $project_name = $order ? ($order['service_name'] ?: 'Project') : null;
                $ins->execute([
                    $client_name,
                    $client_position !== '' ? $client_position : null,
                    $client_company !== '' ? $client_company : null,
                    $content,
                    $rating,
                    $project_name,
                    $service_id,
                    $autoApprove,
                ]);

                // Notify admin
                try {
                    $n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)');
                    $n->execute([
                        null,
                        'Testimonial baru',
                        'Testimonial dari ' . $client_name . ' untuk order ' . ($order_number ?: '-'),
                        'info',
                        'admin/manage_testimonials.php'
                    ]);
                } catch (Throwable $e2) { /* ignore */ }

                $done = true;
                $msg = $autoApprove ? 'Terima kasih! Testimoni Anda telah dipublikasikan.' : 'Terima kasih! Testimoni Anda telah dikirim dan menunggu persetujuan admin.';
                $msg_type = 'success';
            } catch (Throwable $e) {
                $msg = 'Gagal menyimpan testimoni. Silakan coba lagi.';
                $msg_type = 'danger';
            }
        }
    }
}

echo renderPageStart($pageTitle, 'Bagikan pengalaman Anda bersama kami.', $currentPage);
?>
<main class="max-w-2xl mx-auto px-4 py-12">
  <div class="bg-white shadow rounded-lg p-6">
    <h1 class="text-2xl font-bold mb-2">Kirim Testimoni</h1>
    <p class="text-gray-600 mb-6">Kami sangat menghargai masukan Anda untuk meningkatkan layanan kami.</p>

    <?php if ($msg): ?>
      <div class="mb-4 p-3 rounded border <?= $msg_type==='success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <?php if (!$done): ?>
    <form method="post" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <?php if ($order): ?>
        <div class="p-3 bg-gray-50 rounded">
          <div class="text-sm text-gray-700"><strong>Order:</strong> <?= htmlspecialchars($order_number) ?></div>
          <div class="text-sm text-gray-700"><strong>Layanan:</strong> <?= htmlspecialchars($order['service_name'] ?: '-') ?></div>
        </div>
      <?php endif; ?>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nama *</label>
        <input type="text" name="client_name" required class="w-full border rounded px-3 py-2" value="<?= htmlspecialchars($order['customer_name'] ?? '') ?>">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Posisi/Jabatan</label>
          <input type="text" name="client_position" class="w-full border rounded px-3 py-2" placeholder="Owner, CEO, Mahasiswa, dll">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Perusahaan/Instansi</label>
          <input type="text" name="client_company" class="w-full border rounded px-3 py-2" placeholder="Opsional">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Rating (1-5) *</label>
        <select name="rating" required class="w-full border rounded px-3 py-2">
          <option value="">Pilih rating</option>
          <?php for ($i=5;$i>=1;$i--): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Testimoni *</label>
        <textarea name="content" required rows="5" class="w-full border rounded px-3 py-2" placeholder="Ceritakan pengalaman Anda..."></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Layanan Terkait</label>
        <select name="service_id" class="w-full border rounded px-3 py-2">
          <option value="">Pilih layanan</option>
          <?php
            try {
              $sv = $pdo->query('SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC');
              foreach ($sv as $row) {
                $sel = ($order && (int)$order['service_id'] === (int)$row['id']) ? 'selected' : '';
                echo '<option value="'.(int)$row['id'].'" '.$sel.'>'.htmlspecialchars($row['name']).'</option>';
              }
            } catch (Throwable $e) {}
          ?>
        </select>
      </div>

      <div class="pt-2">
        <button class="inline-flex items-center bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700">
          <i class="fas fa-paper-plane mr-2"></i> Kirim Testimoni
        </button>
      </div>
    </form>
    <?php else: ?>
      <div class="mt-4">
        <a href="index.php" class="text-blue-600 hover:underline">Kembali ke Beranda</a>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php echo renderPageEnd(); ?>
