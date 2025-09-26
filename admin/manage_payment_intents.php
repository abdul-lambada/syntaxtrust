<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

// Flash message helper
$flash_msg = '';
$flash_type = '';

// Handle quick actions (mark paid/failed)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'], $_POST['intent_number'])) {
    $csrf_ok = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
    if (!$csrf_ok) {
        $flash_msg = 'Invalid CSRF token.';
        $flash_type = 'danger';
    } else {
        $intent = trim((string)$_POST['intent_number']);
        $act = $_POST['action'];
        if ($intent !== '' && in_array($act, ['mark_paid','mark_failed'], true)) {
            try {
                $newStatus = $act === 'mark_paid' ? 'paid' : 'failed';
                $u = $pdo->prepare('UPDATE payment_intents SET status = ?, updated_at = NOW() WHERE intent_number = ? LIMIT 1');
                $u->execute([$newStatus, $intent]);
                $flash_msg = 'Intent ' . htmlspecialchars($intent) . ' updated to ' . $newStatus . '.';
                $flash_type = 'success';
            } catch (Throwable $e) {
                $flash_msg = 'Failed to update intent: ' . $e->getMessage();
                $flash_type = 'danger';
            }
        }
    }
}

// CSRF token for filters/actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Filters
$q_order = trim($_GET['order'] ?? '');
$q_status = trim($_GET['status'] ?? '');
$q_from = trim($_GET['from'] ?? '');
$q_to = trim($_GET['to'] ?? '');

$where = [];
$params = [];

if ($q_order !== '') {
    $where[] = "JSON_EXTRACT(pi.notes, '$.order_number') = ?";
    $params[] = $q_order;
}
if ($q_status !== '' && in_array($q_status, ['submitted','paid','failed','expired','refunded'], true)) {
    $where[] = "pi.status = ?";
    $params[] = $q_status;
}
if ($q_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q_from)) {
    $where[] = "pi.created_at >= ?";
    $params[] = $q_from . ' 00:00:00';
}
if ($q_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q_to)) {
    $where[] = "pi.created_at <= ?";
    $params[] = $q_to . ' 23:59:59';
}

$sql = "SELECT pi.*, JSON_UNQUOTE(JSON_EXTRACT(pi.notes, '$.order_number')) AS ord, 
                s.name AS service_name, pp.name AS plan_name
        FROM payment_intents pi
        LEFT JOIN services s ON pi.service_id = s.id
        LEFT JOIN pricing_plans pp ON pi.pricing_plan_id = pp.id";
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY pi.created_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Compute aggregates per order
$agg = [];
foreach ($list as $row) {
    $ord = $row['ord'] ?: 'N/A';
    if (!isset($agg[$ord])) { $agg[$ord] = ['sum' => 0.0, 'count' => 0]; }
    if ($row['status'] === 'paid') { $agg[$ord]['sum'] += (float)$row['amount']; }
    $agg[$ord]['count']++;
}

// If a specific order is filtered, fetch order total to show remaining
$order_total = null;
if ($q_order !== '') {
    try {
        $os = $pdo->prepare('SELECT total_amount FROM orders WHERE order_number = ? LIMIT 1');
        $os->execute([$q_order]);
        $order_total = $os->fetchColumn();
    } catch (Throwable $e) { /* ignore */ }
}

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
            <h1 class="h3 mb-0 text-gray-800">Payment Intents</h1>
          </div>

          <?php if ($flash_msg): ?>
            <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show" role="alert">
              <?= $flash_msg ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>

          <div class="card shadow mb-4">
            <div class="card-body">
              <form class="form-inline" method="GET">
                <div class="form-group mr-2">
                  <label for="order" class="mr-2">Order #</label>
                  <input type="text" class="form-control" id="order" name="order" value="<?= htmlspecialchars($q_order) ?>" placeholder="ORD-...">
                </div>
                <div class="form-group mr-2">
                  <label for="status" class="mr-2">Status</label>
                  <select name="status" id="status" class="form-control">
                    <option value="">All</option>
                    <?php foreach (['submitted','paid','failed','expired','refunded'] as $st): ?>
                      <option value="<?= $st ?>" <?= $q_status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group mr-2">
                  <label for="from" class="mr-2">From</label>
                  <input type="date" class="form-control" id="from" name="from" value="<?= htmlspecialchars($q_from) ?>">
                </div>
                <div class="form-group mr-2">
                  <label for="to" class="mr-2">To</label>
                  <input type="date" class="form-control" id="to" name="to" value="<?= htmlspecialchars($q_to) ?>">
                </div>
                <button class="btn btn-primary">Filter</button>
              </form>
            </div>
          </div>

          <?php if ($q_order !== '' && $order_total !== null): ?>
          <div class="row">
            <div class="col-lg-6">
              <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Ringkasan Order <?= htmlspecialchars($q_order) ?></h6></div>
                <div class="card-body">
                  <?php $paid = isset($agg[$q_order]) ? (float)$agg[$q_order]['sum'] : 0.0; $remain = max(0.0, (float)$order_total - $paid); ?>
                  <div class="d-flex justify-content-between mb-2"><span>Total Order</span><strong>Rp <?= number_format((float)$order_total, 0, ',', '.') ?></strong></div>
                  <div class="d-flex justify-content-between mb-2"><span>Sudah Dibayar</span><strong class="text-success">Rp <?= number_format($paid, 0, ',', '.') ?></strong></div>
                  <div class="d-flex justify-content-between"><span>Sisa</span><strong class="text-danger">Rp <?= number_format($remain, 0, ',', '.') ?></strong></div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Daftar Payment Intents (max 200)</h6>
            </div>
            <div class="card-body table-responsive">
              <table class="table table-bordered table-hover small">
                <thead class="thead-light">
                  <tr>
                    <th>Created</th>
                    <th>Intent #</th>
                    <th>Order #</th>
                    <th>Service</th>
                    <th>Plan</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($list)): ?>
                    <tr><td colspan="9" class="text-center text-muted">No data</td></tr>
                  <?php else: ?>
                    <?php foreach ($list as $r): ?>
                      <tr>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td><?= htmlspecialchars($r['intent_number']) ?></td>
                        <td><a href="manage_orders.php?search=<?= urlencode($r['ord'] ?: '') ?>"><?= htmlspecialchars($r['ord'] ?: '-') ?></a></td>
                        <td><?= htmlspecialchars($r['service_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($r['plan_name'] ?: '-') ?></td>
                        <td class="text-right">Rp <?= number_format((float)$r['amount'], 0, ',', '.') ?></td>
                        <td>
                          <?php if ($r['status']==='paid'): ?>
                            <span class="badge badge-success">Paid</span>
                          <?php elseif ($r['status']==='submitted'): ?>
                            <span class="badge badge-secondary">Submitted</span>
                          <?php elseif ($r['status']==='failed'): ?>
                            <span class="badge badge-danger">Failed</span>
                          <?php elseif ($r['status']==='expired'): ?>
                            <span class="badge badge-warning">Expired</span>
                          <?php else: ?>
                            <span class="badge badge-info"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <form method="POST" class="form-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="intent_number" value="<?= htmlspecialchars($r['intent_number']) ?>">
                            <div class="btn-group btn-group-sm" role="group">
                              <button name="action" value="mark_paid" class="btn btn-success" <?= $r['status']==='paid'?'disabled':'' ?>>Mark Paid</button>
                              <button name="action" value="mark_failed" class="btn btn-danger" <?= $r['status']==='failed'?'disabled':'' ?>>Mark Failed</button>
                            </div>
                          </form>
                        </td>
                        <td><code><?= htmlspecialchars($r['notes']) ?></code></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
      <?php require_once 'includes/footer.php'; ?>
    </div>
  </div>
  <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>
  <?php require_once 'includes/scripts.php'; ?>
</body>
</html>
