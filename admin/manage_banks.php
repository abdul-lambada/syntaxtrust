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
    $csrf_ok = isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Load current JSON setting
$current_json = '[]';
try {
    $st = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $st->execute(['payment_banks_json']);
    $val = $st->fetchColumn();
    if ($val !== false && $val !== null && trim((string)$val) !== '') {
        $current_json = (string)$val;
    }
} catch (Throwable $e) { $current_json = '[]'; }

$message = '';
$message_type = '';

// Handle save banks
if ($csrf_ok && isset($_POST['save_banks'])) {
    // Build array from form fields
    $labels = $_POST['label'] ?? [];
    $numbers = $_POST['number'] ?? [];
    $names = $_POST['name'] ?? [];
    $banks = [];
    $count = max(count($labels), count($numbers), count($names));
    for ($i = 0; $i < $count; $i++) {
        $label = trim((string)($labels[$i] ?? ''));
        $number = preg_replace('/\s+/', '', (string)($numbers[$i] ?? ''));
        $name = trim((string)($names[$i] ?? ''));
        if ($number !== '') {
            $banks[] = [
                'label' => $label !== '' ? $label : 'Bank',
                'account_number' => $number,
                'account_name' => $name,
            ];
        }
    }
    $json = json_encode($banks, JSON_UNESCAPED_UNICODE);

    try {
        // Upsert into settings
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT id FROM settings WHERE setting_key = ? LIMIT 1');
        $st->execute(['payment_banks_json']);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $u = $pdo->prepare('UPDATE settings SET setting_value = ?, setting_type = ?, description = ?, is_public = ?, updated_at = NOW() WHERE id = ?');
            $u->execute([$json, 'json', 'Daftar rekening transfer (JSON).', 1, $row['id']]);
        } else {
            $i = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            $i->execute(['payment_banks_json', $json, 'json', 'Daftar rekening transfer (JSON).', 1]);
        }
        $pdo->commit();
        $current_json = $json;
        $message = 'Daftar bank berhasil disimpan.';
        $message_type = 'success';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $message = 'Gagal menyimpan: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Parse JSON to array for display
$banks_display = [];
$decoded = json_decode($current_json, true);
if (is_array($decoded)) { $banks_display = $decoded; }

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
          <h1 class="h3 mb-0 text-gray-800">Kelola Rekening Bank</h1>
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
            <h6 class="m-0 font-weight-bold text-primary">Daftar Bank</h6>
          </div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <div id="bank-list">
                <?php if (empty($banks_display)): ?>
                  <div class="form-row bank-item mb-2">
                    <div class="col-md-3"><input type="text" class="form-control" name="label[]" placeholder="Label Bank (mis. Seabank)"></div>
                    <div class="col-md-4"><input type="text" class="form-control" name="number[]" placeholder="No. Rekening"></div>
                    <div class="col-md-5"><input type="text" class="form-control" name="name[]" placeholder="Nama Pemilik"></div>
                  </div>
                <?php else: ?>
                  <?php foreach ($banks_display as $b): ?>
                    <div class="form-row bank-item mb-2">
                      <div class="col-md-3"><input type="text" class="form-control" name="label[]" value="<?= htmlspecialchars($b['label'] ?? '') ?>" placeholder="Label Bank"></div>
                      <div class="col-md-4"><input type="text" class="form-control" name="number[]" value="<?= htmlspecialchars($b['account_number'] ?? '') ?>" placeholder="No. Rekening"></div>
                      <div class="col-md-5"><input type="text" class="form-control" name="name[]" value="<?= htmlspecialchars($b['account_name'] ?? '') ?>" placeholder="Nama Pemilik"></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <button type="button" class="btn btn-sm btn-secondary" id="add-row"><i class="fas fa-plus"></i> Tambah Baris</button>
              <button type="submit" name="save_banks" class="btn btn-sm btn-primary ml-2"><i class="fas fa-save"></i> Simpan</button>
            </form>
            <hr>
            <div>
              <small class="text-muted">Data disimpan sebagai JSON di setting key <code>payment_banks_json</code>.</small>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php require_once 'includes/footer.php'; ?>
  </div>
</div>
<?php require_once 'includes/scripts.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var addBtn = document.getElementById('add-row');
  var list = document.getElementById('bank-list');
  addBtn.addEventListener('click', function(){
    var wrap = document.createElement('div');
    wrap.className = 'form-row bank-item mb-2';
    wrap.innerHTML = '\
      <div class="col-md-3"><input type="text" class="form-control" name="label[]" placeholder="Label Bank (mis. Seabank)"></div>\
      <div class="col-md-4"><input type="text" class="form-control" name="number[]" placeholder="No. Rekening"></div>\
      <div class="col-md-5"><input type="text" class="form-control" name="name[]" placeholder="Nama Pemilik"></div>\
    ';
    list.appendChild(wrap);
  });
});
</script>
</body>
</html>
