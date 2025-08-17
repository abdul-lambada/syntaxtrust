<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle convert to order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    try {
        // Fetch intent
        $stmt = $pdo->prepare("SELECT * FROM payment_intents WHERE id = ?");
        $stmt->execute([$id]);
        $pi = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pi) {
            $_SESSION['flash_error'] = 'Payment intent not found.';
        } else {
            // Map to order fields
            $order_number = 'ORD-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $service_id = !empty($pi['service_id']) ? (int)$pi['service_id'] : null;
            $pricing_plan_id = (int)$pi['pricing_plan_id'];
            $customer_name = $pi['customer_name'];
            $customer_email = $pi['customer_email'];
            $customer_phone = $pi['customer_phone'] ?? null;
            $override_amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
            $total_amount = $override_amount !== null ? $override_amount : (isset($pi['amount']) && $pi['amount'] !== null ? (float)$pi['amount'] : 0.00);
            // Put intent number into project_description so Orders search can find it
            $project_description = 'Created from payment intent ' . $pi['intent_number'];
            $requirements = '[]';
            $status_order = 'pending';
            $payment_status = 'paid'; // proof provided
            $payment_method = 'bank_transfer';
            $extra_notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
            $notes = trim(($pi['notes'] ?? '') . (\strlen($pi['notes'] ?? '') ? "\n\n" : '') . $extra_notes . "\n\nCreated from payment intent " . $pi['intent_number']);

            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, service_id, pricing_plan_id, customer_name, customer_email, customer_phone, project_description, requirements, total_amount, status, payment_status, payment_method, notes) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $order_number,
                $service_id,
                $pricing_plan_id,
                $customer_name,
                $customer_email,
                $customer_phone,
                $project_description,
                $requirements,
                $total_amount,
                $status_order,
                $payment_status,
                $payment_method,
                $notes,
            ]);
            $new_order_id = $pdo->lastInsertId();

            // Try storing created order_id back to payment_intents if the column exists (ignore failure)
            try {
                $stmt = $pdo->prepare("UPDATE payment_intents SET order_id = ? WHERE id = ?");
                $stmt->execute([$new_order_id, $id]);
            } catch (Throwable $e2) { /* column may not exist; ignore */ }

            // Mark intent approved and append audit trail to notes
            $ts = date('Y-m-d H:i:s');
            $actor = isset($_SESSION['user_id']) ? ('user ' . (string)$_SESSION['user_id']) : 'system';
            $trail = "\n[{$ts}] converted to order {$order_number} by {$actor}";
            $stmt = $pdo->prepare("UPDATE payment_intents SET status = 'approved', notes = CONCAT(COALESCE(notes,''), ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$trail, $id]);

            $orders_link = 'manage_orders.php?search=' . urlencode($order_number);
            $_SESSION['flash_success'] = 'Order created: <a href="' . $orders_link . '"><strong>' . htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8') . '</strong></a>';
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Error converting: ' . $e->getMessage();
    }
    header('Location: manage_payment_intents.php');
    exit();
}

// Filters
$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

// Handle status update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = $_POST['id'] ?? null;
    $newStatus = $_POST['status'] ?? '';
    $allowed = ['submitted','reviewed','approved','rejected'];
    if ($id && in_array($newStatus, $allowed, true)) {
        try {
            // Fetch current status for audit trail
            $stmt = $pdo->prepare("SELECT status FROM payment_intents WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $oldStatus = $row ? $row['status'] : null;

            $ts = date('Y-m-d H:i:s');
            $actor = isset($_SESSION['user_id']) ? ('user ' . (string)$_SESSION['user_id']) : 'system';
            $trail = "\n[{$ts}] status " . ($oldStatus ?? '-') . " -> {$newStatus} by {$actor}";

            $stmt = $pdo->prepare("UPDATE payment_intents SET status = ?, notes = CONCAT(COALESCE(notes,''), ?), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newStatus, $trail, $id]);
            $_SESSION['flash_success'] = 'Status updated.';
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = 'DB error: ' . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = 'Invalid request';
    }
    header('Location: manage_payment_intents.php');
    exit();
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
    $id = $_POST['id'];
    try {
        // remove file if exists
        $stmt = $pdo->prepare("SELECT payment_proof_path FROM payment_intents WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['payment_proof_path'])) {
            $rel = ltrim($row['payment_proof_path'], '/\\');
            $candidates = [];
            // path relative to project root (../ from backend/ to web root)
            $candidates[] = realpath(__DIR__ . '/../' . $rel) ?: (__DIR__ . '/../' . $rel);
            // path relative to backend/
            $candidates[] = realpath(__DIR__ . '/' . $rel) ?: (__DIR__ . '/' . $rel);
            foreach ($candidates as $p) {
                if ($p && file_exists($p)) { @unlink($p); break; }
            }
        }
        $stmt = $pdo->prepare("DELETE FROM payment_intents WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = 'Payment intent deleted.';
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = 'DB error: ' . $e->getMessage();
    }
    header('Location: manage_payment_intents.php');
    exit();
}

// Query list with pagination
$where = [];
$params = [];
if ($status !== '') { $where[] = 'pi.status = ?'; $params[] = $status; }
if ($search !== '') { $where[] = '(pi.customer_name LIKE ? OR pi.customer_email LIKE ? OR pi.intent_number LIKE ?)'; $like = "%$search%"; array_push($params, $like, $like, $like); }

// Count total
$countSql = 'SELECT COUNT(*) AS cnt FROM payment_intents pi';
if (!empty($where)) { $countSql .= ' WHERE ' . implode(' AND ', $where); }
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// Pagination params
$perPage = max(1, min(100, (int)($_GET['per_page'] ?? 10)));
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;

// Fetch rows
$sql = "SELECT pi.*, s.name AS service_name, pp.name AS plan_name,
        NULL AS order_number_fk,
        (SELECT o.id FROM orders o WHERE o.project_description COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', (pi.intent_number COLLATE utf8mb4_unicode_ci), '%') ORDER BY o.created_at DESC LIMIT 1) AS order_id_guess,
        (SELECT o.order_number FROM orders o WHERE o.project_description COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', (pi.intent_number COLLATE utf8mb4_unicode_ci), '%') ORDER BY o.created_at DESC LIMIT 1) AS order_number_guess
        FROM payment_intents pi
        LEFT JOIN services s ON s.id = pi.service_id
        LEFT JOIN pricing_plans pp ON pp.id = pi.pricing_plan_id";
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY pi.created_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
require_once 'includes/header.php';
?>
<body id="page-top">
    <div id="wrapper">
        <?php require_once 'includes/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php require_once 'includes/topbar.php'; ?>
                <div class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Payment Intents</h1>

                    <?php if (!empty($_SESSION['flash_success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['flash_error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-body">
                            <form class="form-inline mb-3" method="get" action="">
                                <div class="form-group mr-2">
                                    <label for="status" class="mr-2">Status</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="">All</option>
                                        <?php foreach (['submitted','reviewed','approved','rejected'] as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo ($status===$st?'selected':''); ?>><?php echo ucfirst($st); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mr-2">
                                    <label for="q" class="mr-2">Search</label>
                                    <input type="text" name="q" id="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name/Email/Intent No">
                                </div>
                                <div class="form-group mr-2">
                                    <label for="per_page" class="mr-2">Per Page</label>
                                    <select name="per_page" id="per_page" class="form-control">
                                        <?php foreach ([10,25,50,100] as $pp): ?>
                                            <option value="<?php echo $pp; ?>" <?php echo ($perPage===$pp?'selected':''); ?>><?php echo $pp; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Intent No</th>
                                            <th>Customer</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Plan</th>
                                            <th>Service</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Proof</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($rows)): ?>
                                            <tr><td colspan="12" class="text-center">No data</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($rows as $i => $r): ?>
                                            <tr>
                                                <td><?php echo $i+1; ?></td>
                                                <td><code><?php echo htmlspecialchars($r['intent_number']); ?></code></td>
                                                <td><?php echo htmlspecialchars($r['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($r['customer_email']); ?></td>
                                                <td><?php echo htmlspecialchars($r['customer_phone'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($r['plan_name'] ?? ($r['pricing_plan_id'] ?? '')); ?></td>
                                                <td><?php echo htmlspecialchars($r['service_name'] ?? ($r['service_id'] ?? '')); ?></td>
                                                <td><?php echo $r['amount'] !== null ? number_format((float)$r['amount'], 2) : '-'; ?></td>
                                                <td><span class="badge badge-info"><?php echo htmlspecialchars($r['status']); ?></span></td>
                                                <td>
                                                    <?php if (!empty($r['payment_proof_path'])): ?>
                                                        <?php $proofUrl = '../' . ltrim($r['payment_proof_path'], '/');
                                                            $ext = strtolower(pathinfo($r['payment_proof_path'], PATHINFO_EXTENSION));
                                                            $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                                                        ?>
                                                        <?php if ($isImg): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#proofModal" data-img="<?php echo htmlspecialchars($proofUrl); ?>">Preview</button>
                                                        <?php else: ?>
                                                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo htmlspecialchars($proofUrl); ?>">View</a>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                                                <td>
                                                    <form method="post" class="d-inline-block">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                                        <select name="status" class="form-control form-control-sm d-inline-block" style="width:auto;">
                                                            <?php foreach (['submitted','reviewed','approved','rejected'] as $st): ?>
                                                                <option value="<?php echo $st; ?>" <?php echo ($r['status']===$st?'selected':''); ?>><?php echo ucfirst($st); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-info ml-1" data-toggle="modal" data-target="#intentDetailModal"
                                                        data-intent="<?php echo htmlspecialchars($r['intent_number']); ?>"
                                                        data-name="<?php echo htmlspecialchars($r['customer_name']); ?>"
                                                        data-email="<?php echo htmlspecialchars($r['customer_email']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($r['customer_phone'] ?? ''); ?>"
                                                        data-plan="<?php echo htmlspecialchars($r['plan_name'] ?? ($r['pricing_plan_id'] ?? '')); ?>"
                                                        data-service="<?php echo htmlspecialchars($r['service_name'] ?? ($r['service_id'] ?? '')); ?>"
                                                        data-amount="<?php echo htmlspecialchars($r['amount'] !== null ? number_format((float)$r['amount'], 2) : '-'); ?>"
                                                        data-status="<?php echo htmlspecialchars($r['status']); ?>"
                                                        data-created="<?php echo htmlspecialchars($r['created_at']); ?>"
                                                        data-ip="<?php echo htmlspecialchars($r['ip_address'] ?? ''); ?>"
                                                        data-ua="<?php echo htmlspecialchars($r['user_agent'] ?? ''); ?>"
                                                        data-notes="<?php echo htmlspecialchars($r['notes'] ?? ''); ?>"
                                                        data-proof="<?php echo !empty($r['payment_proof_path']) ? htmlspecialchars('../' . ltrim($r['payment_proof_path'],'/')) : ''; ?>">
                                                        View
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-success ml-1" data-toggle="modal" data-target="#convertModal" 
                                                        data-id="<?php echo (int)$r['id']; ?>"
                                                        data-amount="<?php echo htmlspecialchars($r['amount'] ?? ''); ?>"
                                                        data-notes="<?php echo htmlspecialchars($r['notes'] ?? ''); ?>"
                                                        data-intent="<?php echo htmlspecialchars($r['intent_number']); ?>"
                                                        data-customer="<?php echo htmlspecialchars($r['customer_name']); ?>"
                                                        <?php echo (in_array($r['status'], ['approved','rejected']) ? 'disabled' : ''); ?>>Convert to Order</button>
                                                    <?php if (!empty($r['order_number_fk'])): ?>
                                                        <a class="btn btn-sm btn-outline-secondary ml-1" href="manage_orders.php?search=<?php echo urlencode($r['order_number_fk']); ?>">View Order</a>
                                                    <?php elseif (!empty($r['order_number_guess'])): ?>
                                                        <a class="btn btn-sm btn-outline-secondary ml-1" href="manage_orders.php?search=<?php echo urlencode($r['order_number_guess']); ?>">View Order</a>
                                                    <?php elseif ($r['status'] === 'approved'): ?>
                                                        <a class="btn btn-sm btn-outline-secondary ml-1" href="manage_orders.php?search=<?php echo urlencode($r['intent_number']); ?>">Find Order</a>
                                                    <?php endif; ?>
                                                    <form method="post" class="d-inline-block" onsubmit="return confirm('Delete this intent?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small>Total: <?php echo $total; ?> rows. Page <?php echo $page; ?> of <?php echo $totalPages; ?>.</small>
                                </div>
                                <nav>
                                    <ul class="pagination mb-0">
                                        <?php
                                            // helper to build query
                                            function qp($overrides = []) {
                                                $q = array_merge([
                                                    'status' => $_GET['status'] ?? '',
                                                    'q' => $_GET['q'] ?? '',
                                                    'per_page' => $_GET['per_page'] ?? 10,
                                                    'page' => $_GET['page'] ?? 1,
                                                ], $overrides);
                                                return 'manage_payment_intents.php?' . http_build_query($q);
                                            }
                                        ?>
                                        <li class="page-item <?php echo ($page<=1?'disabled':''); ?>">
                                            <a class="page-link" href="<?php echo qp(['page'=>1]); ?>">First</a>
                                        </li>
                                        <li class="page-item <?php echo ($page<=1?'disabled':''); ?>">
                                            <a class="page-link" href="<?php echo qp(['page'=>max(1,$page-1)]); ?>">Prev</a>
                                        </li>
                                        <li class="page-item disabled"><span class="page-link"><?php echo $page; ?></span></li>
                                        <li class="page-item <?php echo ($page>=$totalPages?'disabled':''); ?>">
                                            <a class="page-link" href="<?php echo qp(['page'=>min($totalPages,$page+1)]); ?>">Next</a>
                                        </li>
                                        <li class="page-item <?php echo ($page>=$totalPages?'disabled':''); ?>">
                                            <a class="page-link" href="<?php echo qp(['page'=>$totalPages]); ?>">Last</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once 'includes/footer.php'; ?>
        </div>
    </div>

    <?php require_once 'includes/scripts.php'; ?>

    <!-- Proof Preview Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1" role="dialog" aria-labelledby="proofModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="proofModalLabel">Payment Proof Preview</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body text-center">
            <img id="proofModalImg" src="" alt="Proof" class="img-fluid" style="max-height:70vh;" />
          </div>
        </div>
      </div>
    </div>
    <script>
    $('#proofModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var img = button.data('img');
        $(this).find('#proofModalImg').attr('src', img);
    });
    </script>

    <!-- Intent Details Modal -->
    <div class="modal fade" id="intentDetailModal" tabindex="-1" role="dialog" aria-labelledby="intentDetailModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="intentDetailModalLabel">Payment Intent Details</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6">
                <dl class="row mb-0">
                  <dt class="col-sm-4">Intent No</dt>
                  <dd class="col-sm-8" id="detail_intent"></dd>
                  <dt class="col-sm-4">Customer</dt>
                  <dd class="col-sm-8" id="detail_customer"></dd>
                  <dt class="col-sm-4">Email</dt>
                  <dd class="col-sm-8" id="detail_email"></dd>
                  <dt class="col-sm-4">Phone</dt>
                  <dd class="col-sm-8" id="detail_phone"></dd>
                  <dt class="col-sm-4">Plan</dt>
                  <dd class="col-sm-8" id="detail_plan"></dd>
                  <dt class="col-sm-4">Service</dt>
                  <dd class="col-sm-8" id="detail_service"></dd>
                  <dt class="col-sm-4">Amount</dt>
                  <dd class="col-sm-8" id="detail_amount"></dd>
                  <dt class="col-sm-4">Status</dt>
                  <dd class="col-sm-8" id="detail_status"></dd>
                  <dt class="col-sm-4">Created</dt>
                  <dd class="col-sm-8" id="detail_created"></dd>
                  <dt class="col-sm-4">IP</dt>
                  <dd class="col-sm-8" id="detail_ip"></dd>
                </dl>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <strong>Notes</strong>
                  <div id="detail_notes" class="border rounded p-2" style="min-height:80px; white-space:pre-wrap;"></div>
                </div>
                <div>
                  <strong>User Agent</strong>
                  <pre id="detail_ua" class="border rounded p-2" style="max-height:160px; overflow:auto;"></pre>
                </div>
              </div>
            </div>
            <hr/>
            <div id="detail_proof_wrap" class="text-center">
              <img id="detail_proof_img" src="" alt="Proof" class="img-fluid d-none" style="max-height:60vh;" />
              <a id="detail_proof_link" href="#" target="_blank" class="btn btn-outline-secondary d-none">Open Proof</a>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
    <script>
    $('#intentDetailModal').on('show.bs.modal', function (event) {
        var b = $(event.relatedTarget);
        $('#detail_intent').text(b.data('intent'));
        $('#detail_customer').text(b.data('name'));
        $('#detail_email').text(b.data('email'));
        $('#detail_phone').text(b.data('phone'));
        $('#detail_plan').text(b.data('plan'));
        $('#detail_service').text(b.data('service'));
        $('#detail_amount').text(b.data('amount'));
        $('#detail_status').text(b.data('status'));
        $('#detail_created').text(b.data('created'));
        $('#detail_ip').text(b.data('ip'));
        $('#detail_notes').text(b.data('notes'));
        $('#detail_ua').text(b.data('ua'));

        var proof = b.data('proof') || '';
        var $img = $('#detail_proof_img');
        var $link = $('#detail_proof_link');
        if (proof) {
            var ext = proof.split('.').pop().toLowerCase();
            var isImg = ['jpg','jpeg','png','gif','webp'].indexOf(ext) !== -1;
            if (isImg) {
                $img.attr('src', proof).removeClass('d-none');
                $link.addClass('d-none');
            } else {
                $img.addClass('d-none');
                $link.attr('href', proof).removeClass('d-none');
            }
        } else {
            $img.addClass('d-none');
            $link.addClass('d-none');
        }
    });
    </script>

    <!-- Convert to Order Modal -->
    <div class="modal fade" id="convertModal" tabindex="-1" role="dialog" aria-labelledby="convertModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form method="post">
            <div class="modal-header">
              <h5 class="modal-title" id="convertModalLabel">Convert to Order</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="convert">
              <input type="hidden" name="id" id="convert_id" value="">
              <div class="form-group">
                <label>Intent</label>
                <input type="text" class="form-control" id="convert_intent" readonly>
              </div>
              <div class="form-group">
                <label>Customer</label>
                <input type="text" class="form-control" id="convert_customer" readonly>
              </div>
              <div class="form-group">
                <label for="convert_amount">Amount (override)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="convert_amount" name="amount">
              </div>
              <div class="form-group">
                <label for="convert_notes">Notes (append)</label>
                <textarea class="form-control" id="convert_notes" name="notes" rows="3"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success">Create Order</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <script>
    $('#convertModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        $('#convert_id').val(button.data('id'));
        $('#convert_amount').val(button.data('amount'));
        $('#convert_notes').val(button.data('notes'));
        $('#convert_intent').val(button.data('intent'));
        $('#convert_customer').val(button.data('customer'));
    });
    </script>
</body>
</html>
