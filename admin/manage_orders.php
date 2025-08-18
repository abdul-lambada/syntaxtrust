<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// CSRF protection: generate token and helper (consistent with other modules)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle CRUD operations
$message = '';
$message_type = '';

// CSRF invalid feedback (show message if POST with action but invalid/missing token)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_actions = ['delete_order', 'update_status', 'update_payment', 'create_order', 'update_order'];
    $has_action = false;
    foreach ($csrf_actions as $act) {
        if (isset($_POST[$act])) { $has_action = true; break; }
    }
    if ($has_action && !verify_csrf()) {
        $message = 'Invalid CSRF token. Please refresh the page and try again.';
        $message_type = 'danger';
    }
}

// Delete order
if (isset($_POST['delete_order']) && isset($_POST['order_id']) && verify_csrf()) {
    $order_id = $_POST['order_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $message = "Order deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting order: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update order status
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status']) && verify_csrf()) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    try {
        // Fetch current status and order number for audit/notification
        $cur = $pdo->prepare("SELECT order_number, status FROM orders WHERE id = ?");
        $cur->execute([$order_id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
        $old_status = $row['status'] ?? null;
        $order_number = $row['order_number'] ?? ('#' . (string)$order_id);

        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);

        // Notification
        try {
            $n = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)");
            $n_user = $_SESSION['user_id'] ?? null;
            $n_title = 'Order status updated';
            $n_msg = 'Order ' . $order_number . ' status ' . ($old_status ?? '-') . ' -> ' . $status . '.';
            $n_type = ($status === 'completed' ? 'success' : ($status === 'cancelled' ? 'warning' : 'info'));
            $n_url = 'manage_orders.php?search=' . urlencode($order_number);
            $n->execute([$n_user, $n_title, $n_msg, $n_type, $n_url]);
        } catch (Throwable $e2) { /* ignore notification failures */ }

        $message = "Order status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating order status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update payment status
if (isset($_POST['update_payment']) && isset($_POST['order_id']) && isset($_POST['payment_status']) && verify_csrf()) {
    $order_id = $_POST['order_id'];
    $payment_status = $_POST['payment_status'];
    try {
        // Fetch current payment_status and order number
        $cur = $pdo->prepare("SELECT order_number, payment_status FROM orders WHERE id = ?");
        $cur->execute([$order_id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
        $old_pay = $row['payment_status'] ?? null;
        $order_number = $row['order_number'] ?? ('#' . (string)$order_id);

        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
        $stmt->execute([$payment_status, $order_id]);

        // Notification
        try {
            $n = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)");
            $n_user = $_SESSION['user_id'] ?? null;
            $n_title = 'Order payment updated';
            $n_msg = 'Order ' . $order_number . ' payment ' . ($old_pay ?? '-') . ' -> ' . $payment_status . '.';
            $n_type = ($payment_status === 'paid' ? 'success' : ($payment_status === 'refunded' ? 'warning' : 'info'));
            $n_url = 'manage_orders.php?search=' . urlencode($order_number);
            $n->execute([$n_user, $n_title, $n_msg, $n_type, $n_url]);
        } catch (Throwable $e2) { /* ignore notification failures */ }

        $message = "Payment status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating payment status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create new order
if (isset($_POST['create_order']) && verify_csrf()) {
    $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? $_POST['user_id'] : null;
    $customer_name = $_POST['customer_name'];
    $customer_email = $_POST['customer_email'];
    $customer_phone = $_POST['customer_phone'];
    $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
    $pricing_plan_id = isset($_POST['pricing_plan_id']) && $_POST['pricing_plan_id'] !== '' ? (int)$_POST['pricing_plan_id'] : null;
    $project_description = $_POST['project_description'];
    $total_amount = floatval($_POST['total_amount']);
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    // Ensure requirements is valid JSON for DB column (JSON type or CHECK JSON_VALID)
    $requirements_input = isset($_POST['requirements']) ? trim($_POST['requirements']) : '';
    if ($requirements_input === '') {
        $requirements = '[]';
    } else {
        $firstChar = substr($requirements_input, 0, 1);
        // If it looks like JSON object/array, pass through; otherwise wrap as JSON string
        if ($firstChar === '{' || $firstChar === '[') {
            $requirements = $requirements_input;
        } else {
            $requirements = json_encode($requirements_input, JSON_UNESCAPED_UNICODE);
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO orders (order_number, user_id, service_id, pricing_plan_id, customer_name, customer_email, customer_phone, project_description, total_amount, status, payment_status, requirements) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$order_number, $user_id, $service_id, $pricing_plan_id, $customer_name, $customer_email, $customer_phone, $project_description, $total_amount, $status, $payment_status, $requirements]);
        $message = "Order created successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error creating order: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update order
if (isset($_POST['update_order']) && verify_csrf()) {
    $order_id = $_POST['order_id'];
    $user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? $_POST['user_id'] : null;
    $customer_name = $_POST['customer_name'];
    $customer_email = $_POST['customer_email'];
    $customer_phone = $_POST['customer_phone'];
    $service_id = isset($_POST['service_id']) && $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
    $pricing_plan_id = isset($_POST['pricing_plan_id']) && $_POST['pricing_plan_id'] !== '' ? (int)$_POST['pricing_plan_id'] : null;
    $project_description = $_POST['project_description'];
    $total_amount = floatval($_POST['total_amount']);
    $status = $_POST['status'];
    $payment_status = $_POST['payment_status'];
    // Ensure requirements is valid JSON
    $requirements_input = isset($_POST['requirements']) ? trim($_POST['requirements']) : '';
    if ($requirements_input === '') {
        $requirements = '[]';
    } else {
        $firstChar = substr($requirements_input, 0, 1);
        if ($firstChar === '{' || $firstChar === '[') {
            $requirements = $requirements_input;
        } else {
            $requirements = json_encode($requirements_input, JSON_UNESCAPED_UNICODE);
        }
    }
    $admin_notes = $_POST['admin_notes'];
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET user_id = ?, service_id = ?, pricing_plan_id = ?, customer_name = ?, customer_email = ?, customer_phone = ?, project_description = ?, total_amount = ?, status = ?, payment_status = ?, requirements = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id, $service_id, $pricing_plan_id, $customer_name, $customer_email, $customer_phone, $project_description, $total_amount, $status, $payment_status, $requirements, $admin_notes, $order_id]);
        $message = "Order updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating order: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Search and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR project_description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $where_conditions[] = "payment_status = ?";
    $params[] = $payment_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM orders $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = (int)ceil($total_records / $limit);
// Clamp pagination: ensure at least 1 page, clamp current page within [1, total_pages], then recalc offset
$total_pages = max(1, $total_pages);
if ($page < 1) { $page = 1; }
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $limit;

// Get orders with pagination
$sql = "SELECT o.*, s.name as service_name, p.name as plan_name, u.full_name as user_name 
        FROM orders o 
        LEFT JOIN services s ON o.service_id = s.id 
        LEFT JOIN pricing_plans p ON o.pricing_plan_id = p.id 
        LEFT JOIN users u ON o.user_id = u.id 
        $where_clause 
        ORDER BY o.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch services for selects
try {
    $services_stmt = $pdo->prepare("SELECT id, name FROM services ORDER BY name ASC");
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $services = [];
}

// Include header
require_once 'includes/header.php';
?>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <?php require_once 'includes/topbar.php'; ?>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Manage Orders</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addOrderModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Order
                        </a>
                    </div>

                    <!-- Alert Messages -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filter Bar -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Search and Filter Orders</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by order number, customer..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group mx-sm-3 mb-2">
                                    <select class="form-control" name="status">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="form-group mx-sm-3 mb-2">
                                    <select class="form-control" name="payment_status">
                                        <option value="">All Payment Status</option>
                                        <option value="unpaid" <?php echo $payment_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                        <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search) || !empty($status_filter) || !empty($payment_filter)): ?>
                                    <a href="manage_orders.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Orders List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Service/Plan</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                    <?php if (!empty($order['user_name'])): ?>
                                                        <br><small class="text-muted">User: <?php echo htmlspecialchars($order['user_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                                    <?php if (!empty($order['customer_phone'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($order['service_name'])): ?>
                                                        <span class="badge badge-info"><?php echo htmlspecialchars($order['service_name']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($order['plan_name'])): ?>
                                                        <br><span class="badge badge-secondary"><?php echo htmlspecialchars($order['plan_name']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong>
                                                    <?php if (!empty($order['payment_method'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($order['payment_method']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $order['status'] === 'completed' ? 'success' : 
                                                            ($order['status'] === 'in_progress' ? 'warning' : 
                                                            ($order['status'] === 'confirmed' ? 'info' : 
                                                            ($order['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $order['payment_status'] === 'paid' ? 'success' : 
                                                            ($order['payment_status'] === 'refunded' ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y', strtotime($order['created_at'])); ?>
                                                    <?php if ($order['estimated_completion']): ?>
                                                        <br><small class="text-muted">Est: <?php echo date('d M Y', strtotime($order['estimated_completion'])); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewOrderModal<?php echo $order['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editOrderModal<?php echo $order['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php 
                                                        $intentRef = null; 
                                                        if (!empty($order['project_description'])) {
                                                            if (preg_match('/payment intent\s+([A-Za-z0-9\-]+)/i', $order['project_description'], $m)) {
                                                                $intentRef = $m[1];
                                                            }
                                                        }
                                                        if ($intentRef): ?>
                                                            <a class="btn btn-sm btn-outline-secondary" href="manage_payment_intents.php?q=<?php echo urlencode($intentRef); ?>" title="View Payment Intent">PI</a>
                                                        <?php endif; ?>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-toggle="dropdown">
                                                                Status
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="status" value="pending">
                                                                    <button type="submit" name="update_status" class="dropdown-item">Pending</button>
                                                                </form>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="status" value="confirmed">
                                                                    <button type="submit" name="update_status" class="dropdown-item">Confirmed</button>
                                                                </form>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="status" value="in_progress">
                                                                    <button type="submit" name="update_status" class="dropdown-item">In Progress</button>
                                                                </form>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="status" value="completed">
                                                                    <button type="submit" name="update_status" class="dropdown-item">Completed</button>
                                                                </form>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <input type="hidden" name="status" value="cancelled">
                                                                    <button type="submit" name="update_status" class="dropdown-item">Cancelled</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <button type="submit" name="delete_order" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&payment_status=<?php echo urlencode($payment_filter); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&payment_status=<?php echo urlencode($payment_filter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&payment_status=<?php echo urlencode($payment_filter); ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <?php require_once 'includes/footer.php'; ?>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>

    <!-- Add Order Modal -->
    <div class="modal fade" id="addOrderModal" tabindex="-1" role="dialog" aria-labelledby="addOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Add New Order</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="customer_name">Customer Name *</label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="customer_email">Email *</label>
                                    <input type="email" class="form-control" id="customer_email" name="customer_email" required>
                                </div>
                                <div class="form-group">
                                    <label for="customer_phone">Phone</label>
                                    <input type="tel" class="form-control" id="customer_phone" name="customer_phone">
                                </div>
                                <div class="form-group">
                                    <label for="service_id">Service *</label>
                                    <select class="form-control" id="service_id" name="service_id" required>
                                        <option value="">Select Service</option>
                                        <?php foreach ($services as $svc): ?>
                                            <option value="<?php echo (int)$svc['id']; ?>"><?php echo htmlspecialchars($svc['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Status *</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="payment_status">Payment Status *</label>
                                    <select class="form-control" id="payment_status" name="payment_status" required>
                                        <option value="unpaid">Unpaid</option>
                                        <option value="paid">Paid</option>
                                        <option value="refunded">Refunded</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="total_amount">Total Amount *</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="number" class="form-control" id="total_amount" name="total_amount" min="0" step="1000" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="project_description">Project Description *</label>
                            <textarea class="form-control" id="project_description" name="project_description" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="requirements">Requirements</label>
                            <textarea class="form-control" id="requirements" name="requirements" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_order" class="btn btn-primary">Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($orders as $order): ?>
    <!-- View Order Modal -->
    <div class="modal fade" id="viewOrderModal<?php echo $order['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Customer Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th style="width: 40%;">Name:</th>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo !empty($order['customer_phone']) ? htmlspecialchars($order['customer_phone']) : '-'; ?></td>
                                </tr>
                                <?php if (!empty($order['user_name'])): ?>
                                <tr>
                                    <th>Registered User:</th>
                                    <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>

                            <h6 class="text-primary mt-4">Order Details</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th style="width: 40%;">Order Number:</th>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                </tr>
                                <tr>
                                    <th>Service:</th>
                                    <td><?php echo !empty($order['service_name']) ? htmlspecialchars($order['service_name']) : '-'; ?></td>
                                </tr>
                                <?php if (!empty($order['plan_name'])): ?>
                                <tr>
                                    <th>Pricing Plan:</th>
                                    <td><?php echo htmlspecialchars($order['plan_name']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td><strong>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Payment Status:</th>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $order['payment_status'] === 'paid' ? 'success' : 
                                                ($order['payment_status'] === 'refunded' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if (!empty($order['payment_method'])): ?>
                                <tr>
                                    <th>Payment Method:</th>
                                    <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Order Status</h6>
                            <div class="timeline">
                                <?php
                                $statuses = [
                                    'pending' => 'Pending',
                                    'confirmed' => 'Confirmed',
                                    'in_progress' => 'In Progress',
                                    'completed' => 'Completed',
                                    'cancelled' => 'Cancelled'
                                ];
                                $current_status = $order['status'];
                                $found = false;
                                
                                foreach ($statuses as $status => $label):
                                    $is_current = $status === $current_status;
                                    $is_past = $found;
                                    $found = $found || $is_current;
                                    $icon = $is_current ? 'circle' : ($is_past ? 'check-circle' : 'circle');
                                    $color = $is_current ? 'primary' : ($is_past ? 'success' : 'secondary');
                                ?>
                                    <div class="timeline-item">
                                        <div class="timeline-icon bg-<?php echo $color; ?> text-white">
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-0"><?php echo $label; ?></h6>
                                            <?php if ($is_current): ?>
                                                <small class="text-<?php echo $color; ?>">Current Status</small>
                                            <?php elseif ($is_past): ?>
                                                <small class="text-muted">Completed on <?php echo date('d M Y', strtotime($order['updated_at'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            
                        </div>
                    </div>

                    <?php if (!empty($order['project_description'])): ?>
                    <div class="mt-4">
                        <h6 class="text-primary">Project Description</h6>
                        <div class="card">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($order['project_description'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($order['requirements'])): ?>
                    <div class="mt-3">
                        <h6 class="text-primary">Requirements</h6>
                        <div class="card">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($order['requirements'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($order['admin_notes'])): ?>
                    <div class="mt-3">
                        <h6 class="text-primary">Admin Notes</h6>
                        <div class="card bg-light">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($order['admin_notes'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" data-dismiss="modal" data-toggle="modal" data-target="#editOrderModal<?php echo $order['id']; ?>">
                        <i class="fas fa-edit"></i> Edit Order
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php foreach ($orders as $order): ?>
    <!-- Edit Order Modal -->
    <div class="modal fade" id="editOrderModal<?php echo $order['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">Edit Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <ul class="nav nav-tabs" id="orderTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="details-tab" data-toggle="tab" href="#details-<?php echo $order['id']; ?>" role="tab">Order Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="status-tab" data-toggle="tab" href="#status-<?php echo $order['id']; ?>" role="tab">Status & Tracking</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="notes-tab" data-toggle="tab" href="#notes-<?php echo $order['id']; ?>" role="tab">Notes & Files</a>
                            </li>
                        </ul>
                        
                        <div class="tab-content mt-3" id="orderTabsContent">
                            <!-- Order Details Tab -->
                            <div class="tab-pane fade show active" id="details-<?php echo $order['id']; ?>" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_customer_name_<?php echo $order['id']; ?>">Customer Name *</label>
                                            <input type="text" class="form-control" id="edit_customer_name_<?php echo $order['id']; ?>" name="customer_name" value="<?php echo htmlspecialchars($order['customer_name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_customer_email_<?php echo $order['id']; ?>">Email *</label>
                                            <input type="email" class="form-control" id="edit_customer_email_<?php echo $order['id']; ?>" name="customer_email" value="<?php echo htmlspecialchars($order['customer_email']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_customer_phone_<?php echo $order['id']; ?>">Phone</label>
                                            <input type="tel" class="form-control" id="edit_customer_phone_<?php echo $order['id']; ?>" name="customer_phone" value="<?php echo htmlspecialchars($order['customer_phone']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_service_id_<?php echo $order['id']; ?>">Service *</label>
                                            <select class="form-control" id="edit_service_id_<?php echo $order['id']; ?>" name="service_id" required>
                                                <option value="">Select Service</option>
                                                <?php foreach ($services as $svc): ?>
                                                    <option value="<?php echo (int)$svc['id']; ?>" <?php echo (int)$order['service_id'] === (int)$svc['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($svc['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_total_amount_<?php echo $order['id']; ?>">Total Amount *</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">Rp</span>
                                                </div>
                                                <input type="number" class="form-control" id="edit_total_amount_<?php echo $order['id']; ?>" name="total_amount" value="<?php echo htmlspecialchars($order['total_amount']); ?>" min="0" step="1000" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="edit_project_description_<?php echo $order['id']; ?>">Project Description *</label>
                                    <textarea class="form-control" id="edit_project_description_<?php echo $order['id']; ?>" name="project_description" rows="3" required><?php echo htmlspecialchars($order['project_description']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="edit_requirements_<?php echo $order['id']; ?>">Requirements</label>
                                    <textarea class="form-control" id="edit_requirements_<?php echo $order['id']; ?>" name="requirements" rows="3"><?php echo htmlspecialchars($order['requirements']); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Status & Tracking Tab -->
                            <div class="tab-pane fade" id="status-<?php echo $order['id']; ?>" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_status_<?php echo $order['id']; ?>">Order Status *</label>
                                            <select class="form-control" id="edit_status_<?php echo $order['id']; ?>" name="status" required>
                                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="in_progress" <?php echo $order['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_payment_status_<?php echo $order['id']; ?>">Payment Status *</label>
                                            <select class="form-control" id="edit_payment_status_<?php echo $order['id']; ?>" name="payment_status" required>
                                                <option value="unpaid" <?php echo $order['payment_status'] === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                                <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="refunded" <?php echo $order['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="edit_payment_method_<?php echo $order['id']; ?>">Payment Method</label>
                                            <select class="form-control" id="edit_payment_method_<?php echo $order['id']; ?>" name="payment_method">
                                                <option value="">Select Payment Method</option>
                                                <option value="bank_transfer" <?php echo isset($order['payment_method']) && $order['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                                <option value="credit_card" <?php echo isset($order['payment_method']) && $order['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                                <option value="paypal" <?php echo isset($order['payment_method']) && $order['payment_method'] === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                                <option value="other" <?php echo isset($order['payment_method']) && !in_array($order['payment_method'], ['bank_transfer', 'credit_card', 'paypal', '']) ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <?php if (!empty($order['payment_reference'])): ?>
                                        <div class="form-group">
                                            <label for="edit_payment_reference_<?php echo $order['id']; ?>">Payment Reference</label>
                                            <input type="text" class="form-control" id="edit_payment_reference_<?php echo $order['id']; ?>" name="payment_reference" value="<?php echo htmlspecialchars($order['payment_reference']); ?>">
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Order Progress</label>
                                    <div class="progress mb-3">
                                        <?php
                                        $progress = 0;
                                        switch($order['status']) {
                                            case 'pending': $progress = 10; break;
                                            case 'confirmed': $progress = 30; break;
                                            case 'in_progress': $progress = 65; break;
                                            case 'completed': $progress = 100; break;
                                            case 'cancelled': $progress = 100; break;
                                            default: $progress = 0;
                                        }
                                        $progress_class = $order['status'] === 'cancelled' ? 'bg-danger' : 'bg-success';
                                        ?>
                                        <div class="progress-bar <?php echo $progress_class; ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $progress; ?>%</div>
                                    </div>
                                    <div class="text-center">
                                        <span class="badge badge-<?php 
                                            echo $order['status'] === 'completed' ? 'success' : 
                                                ($order['status'] === 'in_progress' ? 'warning' : 
                                                ($order['status'] === 'confirmed' ? 'info' : 
                                                ($order['status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                        <span class="mx-2"><i class="fas fa-arrow-right"></i></span>
                                        <span class="text-muted">
                                            <?php 
                                            $next_status = '';
                                            switch($order['status']) {
                                                case 'pending': $next_status = 'Confirmed'; break;
                                                case 'confirmed': $next_status = 'In Progress'; break;
                                                case 'in_progress': $next_status = 'Completed'; break;
                                                default: $next_status = 'Completed';
                                            }
                                            echo $next_status;
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notes & Files Tab -->
                            <div class="tab-pane fade" id="notes-<?php echo $order['id']; ?>" role="tabpanel">
                                <div class="form-group">
                                    <label for="edit_admin_notes_<?php echo $order['id']; ?>">Admin Notes</label>
                                    <textarea class="form-control" id="edit_admin_notes_<?php echo $order['id']; ?>" name="admin_notes" rows="5" placeholder="Add internal notes here (not visible to customer)"><?php echo !empty($order['admin_notes']) ? htmlspecialchars($order['admin_notes']) : ''; ?></textarea>
                                    <small class="form-text text-muted">These notes are for internal use only and won't be visible to the customer.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label>Attachments</label>
                                    <div class="border rounded p-3">
                                        <?php if (!empty($order['attachments'])): ?>
                                            <div class="mb-2">
                                                <i class="fas fa-paperclip mr-2"></i>
                                                <a href="#" target="_blank"><?php echo basename($order['attachments']); ?></a>
                                                <button type="button" class="btn btn-sm btn-outline-danger ml-2">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted mb-2">No attachments</p>
                                        <?php endif; ?>
                                        
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="order_attachments_<?php echo $order['id']; ?>" name="attachments[]" multiple>
                                            <label class="custom-file-label" for="order_attachments_<?php echo $order['id']; ?>">Choose files</label>
                                        </div>
                                        <small class="form-text text-muted">Upload project files, designs, or other documents (max 10MB)</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Order History</label>
                                    <div class="list-group">
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small>Order created</small>
                                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></small>
                                            </div>
                                        </div>
                                        <?php if (!empty($order['updated_at']) && $order['updated_at'] !== $order['created_at']): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small>Last updated</small>
                                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['updated_at'])); ?></small>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_order" class="btn btn-warning">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</body>

</html>
