<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle payment confirmation
if (isset($_POST['confirm_payment']) && verify_csrf()) {
    $order_id = (int)$_POST['order_id'];
    $payment_method = $_POST['payment_method'] ?? '';
    $payment_notes = $_POST['payment_notes'] ?? '';
    
    // Validate required fields
    if (empty($payment_method)) {
        $message = "Payment method is required.";
        $message_type = "danger";
    } else {
        try {
            // Get order details first
            $stmt = $pdo->prepare("SELECT order_number, customer_name, customer_email, total_amount, payment_status FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                // Check if admin_notes column exists, if not use notes column
                $admin_notes_query = "
                    UPDATE orders 
                    SET payment_status = 'paid', 
                        payment_method = ?, 
                        notes = CONCAT(COALESCE(notes, ''), '\\n[Payment Confirmed] ', NOW(), ' - Method: ', ?, COALESCE(CONCAT(' - Notes: ', ?), '')),
                        updated_at = NOW()
                    WHERE id = ?
                ";
                
                // Try to use admin_notes column first
                try {
                    $admin_notes_query = "
                        UPDATE orders 
                        SET payment_status = 'paid', 
                            payment_method = ?, 
                            admin_notes = CONCAT(COALESCE(admin_notes, ''), '\\n[Payment Confirmed] ', NOW(), ' - Method: ', ?, COALESCE(CONCAT(' - Notes: ', ?), '')),
                            updated_at = NOW()
                        WHERE id = ?
                    ";
                    $update_stmt = $pdo->prepare($admin_notes_query);
                } catch (PDOException $e) {
                    // Fallback to notes column if admin_notes doesn't exist
                    $admin_notes_query = "
                        UPDATE orders 
                        SET payment_status = 'paid', 
                            payment_method = ?, 
                            notes = CONCAT(COALESCE(notes, ''), '\\n[Payment Confirmed] ', NOW(), ' - Method: ', ?, COALESCE(CONCAT(' - Notes: ', ?), '')),
                            updated_at = NOW()
                        WHERE id = ?
                    ";
                    $update_stmt = $pdo->prepare($admin_notes_query);
                }
                
                $update_stmt->execute([$payment_method, $payment_method, $payment_notes, $order_id]);
                
                // Create notification
                try {
                    $n_stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)");
                    $n_title = 'Payment Confirmed';
                    $n_msg = 'Payment confirmed for order ' . $order['order_number'] . ' - ' . $order['customer_name'] . ' (Rp ' . number_format($order['total_amount'], 0, ',', '.') . ')';
                    $n_url = 'manage_orders.php?search=' . urlencode($order['order_number']);
                    $n_stmt->execute([$_SESSION['user_id'], $n_title, $n_msg, 'success', $n_url]);
                } catch (Throwable $e) { 
                    error_log("Notification creation failed: " . $e->getMessage());
                }
                
                $message = "Payment confirmed successfully for order " . $order['order_number'] . "!";
                $message_type = "success";
            } else {
                $message = "Order not found.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            error_log("Payment confirmation error: " . $e->getMessage());
            $message = "Error confirming payment. Please try again.";
            $message_type = "danger";
        }
    }
}

// Handle payment rejection
if (isset($_POST['reject_payment']) && verify_csrf()) {
    $order_id = (int)$_POST['order_id'];
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    // Validate required fields
    if (empty($rejection_reason)) {
        $message = "Rejection reason is required.";
        $message_type = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT order_number, customer_name FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                // Try to use admin_notes column first, fallback to notes
                try {
                    $update_stmt = $pdo->prepare("
                        UPDATE orders 
                        SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\\n[Payment Rejected] ', NOW(), ' - Reason: ', ?),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                } catch (PDOException $e) {
                    // Fallback to notes column if admin_notes doesn't exist
                    $update_stmt = $pdo->prepare("
                        UPDATE orders 
                        SET notes = CONCAT(COALESCE(notes, ''), '\\n[Payment Rejected] ', NOW(), ' - Reason: ', ?),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                }
                
                $update_stmt->execute([$rejection_reason, $order_id]);
                
                $message = "Payment rejection noted for order " . $order['order_number'] . ".";
                $message_type = "warning";
            } else {
                $message = "Order not found.";
                $message_type = "danger";
            }
        } catch (PDOException $e) {
            error_log("Payment rejection error: " . $e->getMessage());
            $message = "Error processing rejection. Please try again.";
            $message_type = "danger";
        }
    }
}

// Get pending payments (orders with unpaid status)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$where_conditions = ["payment_status = 'unpaid'", "total_amount > 0"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM orders $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = max(1, (int)ceil($total_records / $limit));

// Get pending orders
$sql = "SELECT o.*, s.name as service_name, p.name as plan_name 
        FROM orders o 
        LEFT JOIN services s ON o.service_id = s.id 
        LEFT JOIN pricing_plans p ON o.pricing_plan_id = p.id 
        $where_clause 
        ORDER BY o.created_at ASC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pending_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<body id="page-top">
    <div id="wrapper">
        <?php require_once 'includes/sidebar.php'; ?>
        
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php require_once 'includes/topbar.php'; ?>
                
                <div class="container-fluid">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-credit-card text-primary"></i> Konfirmasi Pembayaran
                        </h1>
                        <div class="d-flex">
                            <span class="badge badge-warning badge-pill mr-2 p-2">
                                <?= $total_records ?> Pembayaran Tertunda
                            </span>
                        </div>
                    </div>

                    <!-- Alert Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                            <?= $message ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Search -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Cari Pembayaran Tertunda</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <input type="text" name="search" class="form-control mr-2" 
                                       placeholder="Cari nomor pesanan, nama pelanggan, atau email..." 
                                       value="<?= htmlspecialchars($search) ?>" style="min-width: 300px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="payment_confirmations.php" class="btn btn-secondary ml-2">
                                        <i class="fas fa-times"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Pending Payments Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Daftar Pembayaran Tertunda</h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pending_orders)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3 text-muted">Tidak Ada Pembayaran Tertunda</h5>
                                    <p class="text-muted">Semua pembayaran telah diproses.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Info Pesanan</th>
                                                <th>Pelanggan</th>
                                                <th>Layanan</th>
                                                <th>Nominal</th>
                                                <th>Tanggal</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_orders as $order): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($order['order_number']) ?></strong><br>
                                                        <small class="text-muted">ID: #<?= $order['id'] ?></small><br>
                                                        <span class="badge badge-<?= $order['status'] === 'pending' ? 'warning' : 'info' ?>">
                                                            <?= ucfirst($order['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                                                        <small class="text-muted"><?= htmlspecialchars($order['customer_email']) ?></small>
                                                        <?php if (!empty($order['customer_phone'])): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($order['service_name'] ?? 'N/A') ?>
                                                        <?php if (!empty($order['plan_name'])): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($order['plan_name']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong class="text-success">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></strong>
                                                    </td>
                                                    <td>
                                                        <small><?= date('d M Y', strtotime($order['created_at'])) ?></small><br>
                                                        <small class="text-muted"><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group-vertical btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-success btn-sm" 
                                                                    data-toggle="modal" 
                                                                    data-target="#confirmPaymentModal<?= $order['id'] ?>">
                                                                <i class="fas fa-check"></i> Konfirmasi
                                                            </button>
                                                            <button type="button" class="btn btn-warning btn-sm" 
                                                                    data-toggle="modal" 
                                                                    data-target="#rejectPaymentModal<?= $order['id'] ?>">
                                                                <i class="fas fa-times"></i> Tolak
                                                            </button>
                                                            <a href="manage_orders.php?search=<?= urlencode($order['order_number']) ?>" 
                                                               class="btn btn-info btn-sm">
                                                                <i class="fas fa-eye"></i> Lihat
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Navigasi halaman">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Sebelumnya</a>
                                            </li>
                                        <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Berikutnya</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
        </div>
    </div>

    <!-- Payment Confirmation Modals -->
    <?php foreach ($pending_orders as $order): ?>
        <!-- Confirm Payment Modal -->
        <div class="modal fade" id="confirmPaymentModal<?= $order['id'] ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                            </h5>
                            <button type="button" class="close text-white" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>Pesanan:</strong> <?= htmlspecialchars($order['order_number']) ?><br>
                                <strong>Pelanggan:</strong> <?= htmlspecialchars($order['customer_name']) ?><br>
                                <strong>Nominal:</strong> Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_method<?= $order['id'] ?>">Metode Pembayaran *</label>
                                <select class="form-control" name="payment_method" id="payment_method<?= $order['id'] ?>" required>
                                    <option value="">Pilih Metode Pembayaran</option>
                                    <option value="Bank Transfer - BCA">Bank Transfer - BCA</option>
                                    <option value="Bank Transfer - Mandiri">Bank Transfer - Mandiri</option>
                                    <option value="E-Wallet - GoPay">E-Wallet - GoPay</option>
                                    <option value="E-Wallet - OVO">E-Wallet - OVO</option>
                                    <option value="E-Wallet - DANA">E-Wallet - DANA</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_notes<?= $order['id'] ?>">Catatan Pembayaran</label>
                                <textarea class="form-control" name="payment_notes" id="payment_notes<?= $order['id'] ?>" 
                                          rows="3" placeholder="Additional notes about the payment..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" name="confirm_payment" class="btn btn-success">
                                <i class="fas fa-check"></i> Konfirmasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reject Payment Modal -->
        <div class="modal fade" id="rejectPaymentModal<?= $order['id'] ?>" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle"></i> Tolak Pembayaran
                            </h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <strong>Order:</strong> <?= htmlspecialchars($order['order_number']) ?><br>
                                <strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?><br>
                                <strong>Amount:</strong> Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="rejection_reason<?= $order['id'] ?>">Alasan Penolakan *</label>
                                <textarea class="form-control" name="rejection_reason" id="rejection_reason<?= $order['id'] ?>" 
                                          rows="3" required placeholder="Please specify why the payment is being rejected..."></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <small><i class="fas fa-info-circle"></i> Ini akan menambahkan catatan pada pesanan namun tidak mengubah status pembayaran. Anda mungkin perlu menghubungi pelanggan secara terpisah.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="submit" name="reject_payment" class="btn btn-warning">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                        </div>
                    </form>
                </div>
                <?php require_once 'includes/footer.php'; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php require_once 'includes/scripts.php'; ?>
</body>
</html>
