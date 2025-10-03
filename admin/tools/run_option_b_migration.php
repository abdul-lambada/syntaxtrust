<?php
require_once __DIR__ . '/../../config/database.php';

// CLI mode support
$isCli = (php_sapi_name() === 'cli');

// For web mode, load session and require login
if (!$isCli) {
    require_once __DIR__ . '/../../config/session.php';
}
if (!$isCli && !isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

// CSRF token (web only)
if (!$isCli) {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf = $_SESSION['csrf_token'];
}

// Helpers to check column/index existence (idempotent migration)
function column_exists(PDO $pdo, string $dbName, string $table, string $column): bool {
    $sql = 'SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col';
    $st = $pdo->prepare($sql);
    $st->execute([':db' => $dbName, ':tbl' => $table, ':col' => $column]);
    return ((int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
}
function index_exists(PDO $pdo, string $dbName, string $table, string $index): bool {
    $sql = 'SELECT COUNT(*) AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND INDEX_NAME = :idx';
    $st = $pdo->prepare($sql);
    $st->execute([':db' => $dbName, ':tbl' => $table, ':idx' => $index]);
    return ((int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)) > 0;
}

$dbName = app_db('name');
$errors = [];
$applied = [];

$planned = [
    'services' => [
        ['type' => 'column', 'name' => 'audience_enabled',  'sql' => "ALTER TABLE services ADD COLUMN audience_enabled TINYINT(1) DEFAULT 0 AFTER sort_order"],
        ['type' => 'column', 'name' => 'audience_slug',     'sql' => "ALTER TABLE services ADD COLUMN audience_slug VARCHAR(100) NULL UNIQUE AFTER audience_enabled"],
        ['type' => 'column', 'name' => 'audience_subtitle', 'sql' => "ALTER TABLE services ADD COLUMN audience_subtitle VARCHAR(255) NULL AFTER audience_slug"],
        ['type' => 'column', 'name' => 'audience_features', 'sql' => "ALTER TABLE services ADD COLUMN audience_features LONGTEXT NULL AFTER audience_subtitle"],
        ['type' => 'column', 'name' => 'audience_wa_text',  'sql' => "ALTER TABLE services ADD COLUMN audience_wa_text VARCHAR(255) NULL AFTER audience_features"],
        ['type' => 'index',  'name' => 'idx_services_audience', 'sql' => "CREATE INDEX idx_services_audience ON services (is_active, audience_enabled, sort_order)"],
    ],
    'pricing_plans' => [
        ['type' => 'column', 'name' => 'is_starting_plan', 'sql' => "ALTER TABLE pricing_plans ADD COLUMN is_starting_plan TINYINT(1) DEFAULT 0 AFTER is_popular"],
        ['type' => 'index',  'name' => 'idx_pricing_starting', 'sql' => "CREATE INDEX idx_pricing_starting ON pricing_plans (service_id, is_active, is_starting_plan, price)"],
    ],
];

$canRun = ($pdo instanceof PDO);

// Shared runner
function apply_migration(PDO $pdo, string $dbName, array $planned, array &$applied, array &$errors): void {
    try {
        $pdo->beginTransaction();

        // SERVICES changes
        if (!column_exists($pdo, $dbName, 'services', 'audience_enabled')) { $pdo->exec($planned['services'][0]['sql']); $applied[] = $planned['services'][0]['sql']; }
        if (!column_exists($pdo, $dbName, 'services', 'audience_slug'))     { $pdo->exec($planned['services'][1]['sql']); $applied[] = $planned['services'][1]['sql']; }
        if (!column_exists($pdo, $dbName, 'services', 'audience_subtitle')) { $pdo->exec($planned['services'][2]['sql']); $applied[] = $planned['services'][2]['sql']; }
        if (!column_exists($pdo, $dbName, 'services', 'audience_features')) { $pdo->exec($planned['services'][3]['sql']); $applied[] = $planned['services'][3]['sql']; }
        if (!column_exists($pdo, $dbName, 'services', 'audience_wa_text'))  { $pdo->exec($planned['services'][4]['sql']); $applied[] = $planned['services'][4]['sql']; }
        if (!index_exists($pdo, $dbName, 'services', 'idx_services_audience')) { $pdo->exec($planned['services'][5]['sql']); $applied[] = $planned['services'][5]['sql']; }

        // PRICING_PLANS changes
        if (!column_exists($pdo, $dbName, 'pricing_plans', 'is_starting_plan')) { $pdo->exec($planned['pricing_plans'][0]['sql']); $applied[] = $planned['pricing_plans'][0]['sql']; }
        if (!index_exists($pdo, $dbName, 'pricing_plans', 'idx_pricing_starting')) { $pdo->exec($planned['pricing_plans'][1]['sql']); $applied[] = $planned['pricing_plans'][1]['sql']; }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $errors[] = $e->getMessage();
    }
}

// CLI execution
if ($isCli) {
    $apply = in_array('--apply', $argv ?? [], true);
    if (!$canRun) {
        fwrite(STDERR, "Database connection is not available.\n");
        exit(1);
    }
    if ($apply) {
        apply_migration($pdo, $dbName, $planned, $applied, $errors);
        if (!empty($applied)) {
            echo "Applied statements:\n";
            foreach ($applied as $s) { echo $s, ";\n"; }
        } else {
            echo "No changes were necessary.\n";
        }
        if (!empty($errors)) {
            echo "Errors:\n";
            foreach ($errors as $err) { echo "- ", $err, "\n"; }
            exit(2);
        }
        exit(0);
    } else {
        echo "Planned statements:\n";
        foreach ($planned as $tbl => $items) {
            echo "-- $tbl\n";
            foreach ($items as $i) { echo $i['sql'], ";\n"; }
            echo "\n";
        }
        echo "Run with --apply to execute.\n";
        exit(0);
    }
}

// Web execution
if (!$isCli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canRun) {
        http_response_code(500);
        echo 'Database connection is not available.';
        exit;
    }
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }
    apply_migration($pdo, $dbName, $planned, $applied, $errors);
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Option B Migration</title>
      <link rel="stylesheet" href="../vendor/bootstrap.min.css">
      <style>
        body { padding: 20px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 6px; }
      </style>
    </head>
    <body>
      <h3>Database Migration: Option B (Services Audience + Starting Plan)</h3>
      <p>Script ini akan menambahkan kolom-kolom baru pada tabel <code>services</code> dan <code>pricing_plans</code>. Aman untuk dijalankan berulang (idempoten).</p>

      <?php if (!empty($applied)): ?>
        <div class="alert alert-success"><strong>Berhasil menerapkan perubahan berikut:</strong>
          <ul>
            <?php foreach ($applied as $s): ?>
              <li><code><?= htmlspecialchars($s) ?></code></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><strong>Terjadi error saat migrasi:</strong>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <h5>Perubahan yang direncanakan</h5>
      <pre><?php
    foreach ($planned as $tbl => $items) {
        echo "-- $tbl\n";
        foreach ($items as $i) { echo $i['sql'] . ";\n"; }
        echo "\n";
    }
    ?></pre>

      <?php if ($canRun): ?>
        <form method="post" onsubmit="return confirm('Jalankan migrasi sekarang? Pastikan Anda punya backup database.');">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <button type="submit" class="btn btn-primary">Jalankan Migrasi</button>
        </form>
      <?php else: ?>
        <div class="alert alert-warning">Koneksi database tidak tersedia. Cek konfigurasi di <code>config/database.php</code>.</div>
      <?php endif; ?>

      <hr>
      <p class="text-muted">File: <code>admin/tools/run_option_b_migration.php</code></p>
    </body>
    </html>
    <?php
}
