<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Auth guard
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_ok = true;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf_ok = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Helper: read setting by key
function mpref_get(PDO $pdo, string $key, $default = '') {
    try {
        $s = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $s->execute([$key]);
        $v = $s->fetchColumn();
        if ($v !== false && $v !== null) return $v;
    } catch (Throwable $e) { /* ignore */ }
    return $default;
}

$message = '';
$message_type = '';

// Handle save
if ($csrf_ok && isset($_POST['save_prefs'])) {
    // Sanitize inputs
    $full = (int)($_POST['payment_due_hours_full'] ?? 24);
    $inst = (int)($_POST['payment_due_hours_installment'] ?? 24);
    $days = (int)($_POST['installment_total_days'] ?? 30);
    $auto = isset($_POST['enable_auto_whatsapp_payment_notice']) ? '1' : '0';

    $items = [
        ['payment_due_hours_full', (string)max(1, $full), 'number', 'Jatuh tempo pembayaran penuh (jam).', 1],
        ['payment_due_hours_installment', (string)max(1, $inst), 'number', 'Jatuh tempo cicilan pertama (jam).', 1],
        ['installment_total_days', (string)max(1, $days), 'number', 'Jarak pelunasan dari cicilan pertama (hari).', 1],
        ['enable_auto_whatsapp_payment_notice', $auto, 'boolean', 'Aktifkan kirim WhatsApp otomatis setelah membuat intent pembayaran.', 0],
    ];
    try {
        $pdo->beginTransaction();
        foreach ($items as [$k,$v,$t,$d,$pub]) {
            $q = $pdo->prepare('SELECT id FROM settings WHERE setting_key = ? LIMIT 1');
            $q->execute([$k]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $u = $pdo->prepare('UPDATE settings SET setting_value = ?, setting_type = ?, description = ?, is_public = ?, updated_at = NOW() WHERE id = ?');
                $u->execute([$v, $t, $d, $pub, $row['id']]);
            } else {
                $i = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                $i->execute([$k, $v, $t, $d, $pub]);
            }
        }
        $pdo->commit();
        $message = 'Preferensi pembayaran berhasil disimpan.';
        $message_type = 'success';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = 'Gagal menyimpan: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Load current values
$payment_due_hours_full = (int)mpref_get($pdo, 'payment_due_hours_full', 24);
$payment_due_hours_installment = (int)mpref_get($pdo, 'payment_due_hours_installment', 24);
$installment_total_days = (int)mpref_get($pdo, 'installment_total_days', 30);
$enable_auto_whatsapp_payment_notice = mpref_get($pdo, 'enable_auto_whatsapp_payment_notice', '1') === '1';

require_once 'includes/header.php';
?>

<body id="page-top">
<div id="wrapper">
  <?php require_once 'includes/sidebar.php'; ?>
  <div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
      <?php require_once 'includes/topbar.php'; ?>
      <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
          <h1 class="h3 mb-0 text-gray-800">Payment Preferences</h1>
        </div>

        <?php if (!empty($message)): ?>
          <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Konfigurasi</h6>
          </div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

              <div class="form-row">
                <div class="form-group col-md-4">
                  <label>Jatuh Tempo Pembayaran Penuh (jam)</label>
                  <input type="number" min="1" class="form-control" name="payment_due_hours_full" value="<?= htmlspecialchars($payment_due_hours_full) ?>">
                  <small class="form-text text-muted">Default 24 jam.</small>
                </div>
                <div class="form-group col-md-4">
                  <label>Jatuh Tempo Cicilan Pertama (jam)</label>
                  <input type="number" min="1" class="form-control" name="payment_due_hours_installment" value="<?= htmlspecialchars($payment_due_hours_installment) ?>">
                  <small class="form-text text-muted">Default 24 jam.</small>
                </div>
                <div class="form-group col-md-4">
                  <label>Jarak Pelunasan dari Cicilan Pertama (hari)</label>
                  <input type="number" min="1" class="form-control" name="installment_total_days" value="<?= htmlspecialchars($installment_total_days) ?>">
                  <small class="form-text text-muted">Default 30 hari.</small>
                </div>
              </div>

              <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="auto_wa" name="enable_auto_whatsapp_payment_notice" value="1" <?= $enable_auto_whatsapp_payment_notice ? 'checked' : '' ?>>
                <label class="form-check-label" for="auto_wa">Aktifkan WhatsApp otomatis setelah intent pembayaran dibuat</label>
                <div><small class="text-muted">Pastikan token Fonnte terisi di <em>Fonnte Integration</em> dan nomor telepon pelanggan tersedia.</small></div>
              </div>

              <button type="submit" name="save_prefs" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php require_once 'includes/footer.php'; ?>
  </div>
</div>
<?php require_once 'includes/scripts.php'; ?>
</body>
</html>
