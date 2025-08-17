<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Define base constants (dynamic base URL for backend)
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Build base path up to and including the current directory (backend/)
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/backend/index.php')), '/');
    define('BASE_URL', $scheme . '://' . $host . $dir . '/');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/login.php');
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
    
    // Count contact inquiries
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_inquiries");
    $stats['inquiries'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent inquiries
    $stmt = $pdo->query("SELECT * FROM contact_inquiries ORDER BY created_at DESC LIMIT 5");
    $recent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $stats = ['users' => 0, 'services' => 0, 'portfolio' => 0, 'team' => 0, 'clients' => 0, 'inquiries' => 0];
    $recent_inquiries = [];
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

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Recent Inquiries -->
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Recent Contact Inquiries</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="inquiriesTable" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Subject</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                try {
                                                    $stmt = $pdo->query("SELECT * FROM contact_inquiries ORDER BY created_at DESC LIMIT 5");
                                                    $recent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    foreach ($recent_inquiries as $inquiry): 
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($inquiry['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($inquiry['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($inquiry['subject'] ?? 'No Subject'); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($inquiry['created_at'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $inquiry['status'] == 'new' ? 'primary' : ($inquiry['status'] == 'read' ? 'info' : 'success'); ?>">
                                                            <?php echo ucfirst($inquiry['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="manage_contact_inquiries.php?search=<?php echo urlencode($inquiry['email']); ?>" class="btn btn-sm btn-info">View</a>
                                                    </td>
                                                </tr>
                                                <?php 
                                                    endforeach; 
                                                } catch (PDOException $e) {
                                                    echo '<tr><td colspan="6">No inquiries found</td></tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="col-lg-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                                </div>
                                <div class="card-body">
                                    <div class="list-group">
                                        <a href="manage_users.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-users fa-fw mr-2"></i>Manage Users
                                        </a>
                                        <a href="manage_services.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-cogs fa-fw mr-2"></i>Manage Services
                                        </a>
                                        <a href="manage_portfolio.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-briefcase fa-fw mr-2"></i>Manage Portfolio
                                        </a>
                                        <a href="manage_team.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-users fa-fw mr-2"></i>Manage Team
                                        </a>
                                        <a href="manage_clients.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-handshake fa-fw mr-2"></i>Manage Clients
                                        </a>
                                        <a href="manage_settings.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-cog fa-fw mr-2"></i>Manage Settings
                                        </a>
                                        <a href="manage_contact_inquiries.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-envelope fa-fw mr-2"></i>Manage Contact Inquiries
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API Documentation -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">API Endpoints</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Authentication</h6>
                                            <ul>
                                                <li><code>POST /api/auth.php</code> - Login</li>
                                                <li><code>GET /api/auth.php</code> - Check auth status</li>
                                                <li><code>DELETE /api/auth.php</code> - Logout</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Users Management</h6>
                                            <ul>
                                                <li><code>GET /api/users.php</code> - Get all users</li>
                                                <li><code>POST /api/users.php</code> - Create user</li>
                                                <li><code>PUT /api/users.php?id=1</code> - Update user</li>
                                                <li><code>DELETE /api/users.php?id=1</code> - Delete user</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Services</h6>
                                            <ul>
                                                <li><code>GET /api/services.php</code> - Get all services</li>
                                                <li><code>POST /api/services.php</code> - Create service</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Portfolio</h6>
                                            <ul>
                                                <li><code>GET /api/portfolio.php</code> - Get all portfolio</li>
                                                <li><code>POST /api/portfolio.php</code> - Create portfolio</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Team</h6>
                                            <ul>
                                                <li><code>GET /api/team.php</code> - Get team members</li>
                                                <li><code>POST /api/team.php</code> - Create team member</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Clients</h6>
                                            <ul>
                                                <li><code>GET /api/clients.php</code> - Get clients</li>
                                                <li><code>POST /api/clients.php</code> - Create client</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

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

    

    <?php require_once 'includes/scripts.php'; ?>

</body>

</html>
