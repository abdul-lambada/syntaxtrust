<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to the public login using configured base path
    require_once __DIR__ . '/../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    if ($publicBase === '') { $publicBase = ''; }
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

// Get dashboard stats
try {
    $stats = [];

    // Count users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count services
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE is_active = 1");
    $stats['services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count portfolio
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM portfolio WHERE is_active = 1");
    $stats['portfolio'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count team members
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM team WHERE is_active = 1");
    $stats['team'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count clients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE is_active = 1");
    $stats['clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count testimonials (active)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM testimonials WHERE is_active = 1");
    $stats['testimonials'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count blog posts (published)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM blog_posts WHERE status = 'published'");
    $stats['blog_posts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count contact inquiries
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_inquiries");
    $stats['inquiries'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Count pending payments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE payment_status = 'unpaid' AND total_amount > 0");
    $stats['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Recent inquiries
    $stmt = $pdo->query("SELECT * FROM contact_inquiries ORDER BY created_at DESC LIMIT 5");
    $recent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent orders
    $stmt = $pdo->query("SELECT o.*, s.name as service_name FROM orders o LEFT JOIN services s ON o.service_id = s.id ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['users' => 0, 'services' => 0, 'portfolio' => 0, 'team' => 0, 'clients' => 0, 'testimonials' => 0, 'blog_posts' => 0, 'inquiries' => 0, 'orders' => 0, 'pending_payments' => 0];
    $recent_inquiries = [];
    $recent_orders = [];
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
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
                        </a>
                    </div>

                    <!-- Pending Payments Alert -->
                    <?php if (($stats['pending_payments'] ?? 0) > 0): ?>
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    <strong>Attention!</strong> You have <?= $stats['pending_payments'] ?> pending payment(s) that require confirmation.
                                    <a href="payment_confirmations.php" class="btn btn-sm btn-warning ml-2">
                                        <i class="fas fa-credit-card mr-1"></i>Review Payments
                                    </a>
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Users Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Users</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['users'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Services Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Total Services</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['services'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-cogs fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Portfolio Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Total Portfolio</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['portfolio'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Total Team</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['team'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-users fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Row (Blog & Testimonials) -->
                    <div class="row">
                        <!-- Blog Posts Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-dark shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Published Blog Posts</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['blog_posts'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-blog fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Testimonials Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-secondary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Active Testimonials</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['testimonials'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-comment-dots fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Orders Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Orders</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['orders'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Payments Card -->
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-danger shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Pending Payments</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_payments'] ?? 0; ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Content Row -->
                    <div class="row">
                        <!-- Recent Orders -->
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                                    <a href="manage_orders.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_orders)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-shopping-cart text-muted" style="font-size: 2rem;"></i>
                                            <p class="text-muted mt-2">No orders yet</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered" width="100%" cellspacing="0">
                                                <thead>
                                                    <tr>
                                                        <th>Order #</th>
                                                        <th>Customer</th>
                                                        <th>Service</th>
                                                        <th>Amount</th>
                                                        <th>Status</th>
                                                        <th>Payment</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_orders as $order): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                                            </td>
                                                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                                            <td><?= htmlspecialchars($order['service_name'] ?? 'N/A') ?></td>
                                                            <td>
                                                                <?php if ($order['total_amount'] > 0): ?>
                                                                    <strong>Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></strong>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Custom</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-<?php
                                                                                            echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'in_progress' ? 'info' : ($order['status'] === 'confirmed' ? 'primary' : 'warning'));
                                                                                            ?>">
                                                                    <?= ucfirst($order['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-<?php
                                                                                            echo $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'refunded' ? 'warning' : 'danger');
                                                                                            ?>">
                                                                    <?= ucfirst($order['payment_status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <small><?= date('d M Y', strtotime($order['created_at'])) ?></small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>

</body>
</html>