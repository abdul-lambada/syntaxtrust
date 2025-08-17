<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle CRUD operations
$message = '';
$message_type = '';

// Delete pricing plan
if (isset($_POST['delete_plan']) && isset($_POST['plan_id'])) {
    $plan_id = $_POST['plan_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM pricing_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $message = "Pricing plan deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting pricing plan: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle plan status
if (isset($_POST['toggle_status']) && isset($_POST['plan_id'])) {
    $plan_id = $_POST['plan_id'];
    try {
        $stmt = $pdo->prepare("UPDATE pricing_plans SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$plan_id]);
        $message = "Pricing plan status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating pricing plan status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle popular status
if (isset($_POST['toggle_popular']) && isset($_POST['plan_id'])) {
    $plan_id = $_POST['plan_id'];
    try {
        $stmt = $pdo->prepare("UPDATE pricing_plans SET is_popular = NOT is_popular WHERE id = ?");
        $stmt->execute([$plan_id]);
        $message = "Popular status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating popular status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create new pricing plan
if (isset($_POST['create_plan'])) {
    $name = $_POST['name'];
    $subtitle = $_POST['subtitle'];
    $price = floatval($_POST['price']);
    $currency = $_POST['currency'];
    $billing_period = $_POST['billing_period'];
    $description = $_POST['description'];
    $features = $_POST['features'] ? json_encode(explode(',', $_POST['features'])) : null;
    $delivery_time = $_POST['delivery_time'];
    $technologies = $_POST['technologies'] ? json_encode(explode(',', $_POST['technologies'])) : null;
    $color = $_POST['color'];
    $icon = $_POST['icon'];
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO pricing_plans (name, subtitle, price, currency, billing_period, description, features, delivery_time, technologies, color, icon, is_popular, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $subtitle, $price, $currency, $billing_period, $description, $features, $delivery_time, $technologies, $color, $icon, $is_popular, $is_active, $sort_order]);
        $message = "Pricing plan created successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error creating pricing plan: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update pricing plan
if (isset($_POST['update_plan'])) {
    $plan_id = $_POST['plan_id'];
    $name = $_POST['name'];
    $subtitle = $_POST['subtitle'];
    $price = floatval($_POST['price']);
    $currency = $_POST['currency'];
    $billing_period = $_POST['billing_period'];
    $description = $_POST['description'];
    $features = $_POST['features'] ? json_encode(explode(',', $_POST['features'])) : null;
    $delivery_time = $_POST['delivery_time'];
    $technologies = $_POST['technologies'] ? json_encode(explode(',', $_POST['technologies'])) : null;
    $color = $_POST['color'];
    $icon = $_POST['icon'];
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    
    try {
        $stmt = $pdo->prepare("UPDATE pricing_plans SET name = ?, subtitle = ?, price = ?, currency = ?, billing_period = ?, description = ?, features = ?, delivery_time = ?, technologies = ?, color = ?, icon = ?, is_popular = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $subtitle, $price, $currency, $billing_period, $description, $features, $delivery_time, $technologies, $color, $icon, $is_popular, $is_active, $sort_order, $plan_id]);
        $message = "Pricing plan updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating pricing plan: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Search and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE name LIKE ? OR subtitle LIKE ? OR description LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM pricing_plans $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get pricing plans with pagination
$sql = "SELECT * FROM pricing_plans $where_clause ORDER BY sort_order ASC, created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pricing_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Pricing Plans</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addPlanModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Plan
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

                    <!-- Search Bar -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Search Pricing Plans</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by name, subtitle..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_pricing_plans.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Pricing Plans Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Pricing Plans List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Subtitle</th>
                                            <th>Price</th>
                                            <th>Billing Period</th>
                                            <th>Status</th>
                                            <th>Popular</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pricing_plans as $plan): ?>
                                            <tr>
                                                <td><?php echo $plan['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                                                    <?php if (!empty($plan['icon'])): ?>
                                                        <br><small class="text-muted"><i class="fas fa-<?php echo htmlspecialchars($plan['icon']); ?>"></i></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($plan['subtitle'] ?? ''); ?></td>
                                                <td>
                                                    <?php if ($plan['price'] > 0): ?>
                                                        <?php echo $plan['currency']; ?> <?php echo number_format($plan['price'], 0, ',', '.'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Custom</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $plan['billing_period'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $plan['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $plan['is_popular'] ? 'warning' : 'light'; ?>">
                                                        <?php echo $plan['is_popular'] ? 'Popular' : 'Regular'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewPlanModal<?php echo $plan['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editPlanModal<?php echo $plan['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this plan\'s status?')">
                                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $plan['is_active'] ? 'warning' : 'success'; ?>">
                                                                <i class="fas fa-<?php echo $plan['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle popular status?')">
                                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                                            <button type="submit" name="toggle_popular" class="btn btn-sm btn-<?php echo $plan['is_popular'] ? 'secondary' : 'warning'; ?>">
                                                                <i class="fas fa-star"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this pricing plan? This action cannot be undone.')">
                                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                                            <button type="submit" name="delete_plan" class="btn btn-sm btn-danger">
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
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
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

    <!-- Add Plan Modal -->
    <div class="modal fade" id="addPlanModal" tabindex="-1" role="dialog" aria-labelledby="addPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPlanModalLabel">Add New Pricing Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" id="addPlanForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Plan Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subtitle">Subtitle</label>
                                    <input type="text" class="form-control" id="subtitle" name="subtitle">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="price">Price *</label>
                                    <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="currency">Currency</label>
                                    <select class="form-control" id="currency" name="currency">
                                        <option value="IDR" selected>IDR</option>
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="billing_period">Billing Period *</label>
                                    <select class="form-control" id="billing_period" name="billing_period" required>
                                        <option value="one_time">One Time</option>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>Features (one per line)</label>
                            <div id="features-container">
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control feature-input" name="features[]" placeholder="Enter a feature">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-success add-feature" type="button">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <small class="form-text text-muted">Click + to add more features</small>
                        </div>
                        <div class="form-group">
                            <label for="delivery_time">Delivery Time</label>
                            <input type="text" class="form-control" id="delivery_time" name="delivery_time" placeholder="e.g., 7-14 business days">
                        </div>
                        <div class="form-group">
                            <label>Technologies (comma separated)</label>
                            <input type="text" class="form-control" id="technologies" name="technologies" placeholder="e.g., PHP, MySQL, JavaScript">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="color">Color</label>
                                    <input type="color" class="form-control" id="color" name="color" value="#4e73df">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="icon">Icon (Font Awesome class)</label>
                                    <input type="text" class="form-control" id="icon" name="icon" placeholder="e.g., fa-rocket">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_popular" name="is_popular">
                                    <label class="form-check-label" for="is_popular">Mark as Popular</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="sort_order">Sort Order</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_plan" class="btn btn-primary">Create Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View/Edit Plan Modals -->
    <?php foreach ($pricing_plans as $plan): 
        // Safely decode features and technologies
        $features = [];
        if (!empty($plan['features'])) {
            $decoded = json_decode($plan['features'], true);
            $features = is_array($decoded) ? $decoded : [];
        }
        
        $technologies = [];
        if (!empty($plan['technologies'])) {
            $decoded = json_decode($plan['technologies'], true);
            $technologies = is_array($decoded) ? $decoded : [];
        }
    ?>
    <!-- View Plan Modal -->
    <div class="modal fade" id="viewPlanModal<?php echo $plan['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewPlanModalLabel<?php echo $plan['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background-color: <?php echo htmlspecialchars($plan['color']); ?>; color: white;">
                    <h5 class="modal-title" id="viewPlanModalLabel<?php echo $plan['id']; ?>">
                        <?php if (!empty($plan['icon'])): ?>
                            <i class="fas <?php echo htmlspecialchars($plan['icon']); ?> mr-2"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($plan['name']); ?>
                        <?php if ($plan['is_popular']): ?>
                            <span class="badge badge-warning ml-2">Popular</span>
                        <?php endif; ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h3 class="text-primary">
                                <?php echo $plan['currency']; ?> 
                                <?php echo number_format($plan['price'], 0, ',', '.'); ?>
                                <small class="text-muted">
                                    / <?php echo ucfirst(str_replace('_', ' ', $plan['billing_period'])); ?>
                                </small>
                            </h3>
                            <p class="lead"><?php echo htmlspecialchars($plan['subtitle']); ?></p>
                            
                            <?php if (!empty($plan['delivery_time'])): ?>
                                <p class="text-muted">
                                    <i class="fas fa-clock mr-2"></i> 
                                    Delivery: <?php echo htmlspecialchars($plan['delivery_time']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($technologies)): ?>
                                <div class="mt-3">
                                    <h6>Technologies:</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($technologies as $tech): ?>
                                            <span class="badge badge-secondary"><?php echo htmlspecialchars(trim($tech)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if (!empty($features)): ?>
                                <h5>Features</h5>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($features as $feature): ?>
                                        <li class="list-group-item">
                                            <i class="fas fa-check text-success mr-2"></i>
                                            <?php echo htmlspecialchars(trim($feature)); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($plan['description'])): ?>
                        <div class="border-top pt-3">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <div>
                            <span class="badge badge-<?php echo $plan['is_active'] ? 'success' : 'secondary'; ?> mr-2">
                                <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <small class="text-muted">
                                Last updated: <?php echo date('M d, Y', strtotime($plan['updated_at'])); ?>
                            </small>
                        </div>
                        <div>
                            <a href="#editPlanModal<?php echo $plan['id']; ?>" class="btn btn-sm btn-primary" data-toggle="modal" data-dismiss="modal" data-target="#editPlanModal<?php echo $plan['id']; ?>">
                                <i class="fas fa-edit"></i> Edit Plan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div class="modal fade" id="editPlanModal<?php echo $plan['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editPlanModalLabel<?php echo $plan['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPlanModalLabel<?php echo $plan['id']; ?>">Edit Pricing Plan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_name_<?php echo $plan['id']; ?>">Plan Name *</label>
                                    <input type="text" class="form-control" id="edit_name_<?php echo $plan['id']; ?>" name="name" value="<?php echo htmlspecialchars($plan['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_subtitle_<?php echo $plan['id']; ?>">Subtitle</label>
                                    <input type="text" class="form-control" id="edit_subtitle_<?php echo $plan['id']; ?>" name="subtitle" value="<?php echo htmlspecialchars($plan['subtitle']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_price_<?php echo $plan['id']; ?>">Price *</label>
                                    <input type="number" class="form-control" id="edit_price_<?php echo $plan['id']; ?>" name="price" value="<?php echo $plan['price']; ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_currency_<?php echo $plan['id']; ?>">Currency</label>
                                    <select class="form-control" id="edit_currency_<?php echo $plan['id']; ?>" name="currency">
                                        <option value="IDR" <?php echo $plan['currency'] === 'IDR' ? 'selected' : ''; ?>>IDR</option>
                                        <option value="USD" <?php echo $plan['currency'] === 'USD' ? 'selected' : ''; ?>>USD</option>
                                        <option value="EUR" <?php echo $plan['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_billing_period_<?php echo $plan['id']; ?>">Billing Period *</label>
                                    <select class="form-control" id="edit_billing_period_<?php echo $plan['id']; ?>" name="billing_period" required>
                                        <option value="one_time" <?php echo $plan['billing_period'] === 'one_time' ? 'selected' : ''; ?>>One Time</option>
                                        <option value="monthly" <?php echo $plan['billing_period'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="quarterly" <?php echo $plan['billing_period'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                        <option value="yearly" <?php echo $plan['billing_period'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_description_<?php echo $plan['id']; ?>">Description *</label>
                            <textarea class="form-control" id="edit_description_<?php echo $plan['id']; ?>" name="description" rows="3" required><?php echo htmlspecialchars($plan['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Features (one per line)</label>
                            <div id="edit_features_<?php echo $plan['id']; ?>">
                                <?php if (!empty($features)): ?>
                                    <?php foreach ($features as $index => $feature): ?>
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control feature-input" name="features[]" value="<?php echo htmlspecialchars(trim($feature)); ?>" placeholder="Enter a feature">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-danger remove-feature" type="button">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php if ($index === count($features) - 1): ?>
                                                    <button class="btn btn-outline-success add-feature" type="button" data-target="edit_features_<?php echo $plan['id']; ?>">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control feature-input" name="features[]" placeholder="Enter a feature">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-success add-feature" type="button" data-target="edit_features_<?php echo $plan['id']; ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <small class="form-text text-muted">Click + to add more features</small>
                        </div>
                        <div class="form-group">
                            <label for="edit_delivery_time_<?php echo $plan['id']; ?>">Delivery Time</label>
                            <input type="text" class="form-control" id="edit_delivery_time_<?php echo $plan['id']; ?>" name="delivery_time" value="<?php echo htmlspecialchars($plan['delivery_time']); ?>" placeholder="e.g., 7-14 business days">
                        </div>
                        <div class="form-group">
                            <label for="edit_technologies_<?php echo $plan['id']; ?>">Technologies (comma separated)</label>
                            <input type="text" class="form-control" id="edit_technologies_<?php echo $plan['id']; ?>" name="technologies" value="<?php echo !empty($technologies) ? htmlspecialchars(implode(', ', $technologies)) : ''; ?>" placeholder="e.g., PHP, MySQL, JavaScript">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_color_<?php echo $plan['id']; ?>">Color</label>
                                    <input type="color" class="form-control" id="edit_color_<?php echo $plan['id']; ?>" name="color" value="<?php echo !empty($plan['color']) ? htmlspecialchars($plan['color']) : '#4e73df'; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_icon_<?php echo $plan['id']; ?>">Icon (Font Awesome class)</label>
                                    <input type="text" class="form-control" id="edit_icon_<?php echo $plan['id']; ?>" name="icon" value="<?php echo htmlspecialchars($plan['icon']); ?>" placeholder="e.g., fa-rocket">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_is_popular_<?php echo $plan['id']; ?>" name="is_popular" <?php echo $plan['is_popular'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_is_popular_<?php echo $plan['id']; ?>">Mark as Popular</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_is_active_<?php echo $plan['id']; ?>" name="is_active" <?php echo $plan['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_is_active_<?php echo $plan['id']; ?>">Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_sort_order_<?php echo $plan['id']; ?>">Sort Order</label>
                                    <input type="number" class="form-control" id="edit_sort_order_<?php echo $plan['id']; ?>" name="sort_order" value="<?php echo $plan['sort_order']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_plan" class="btn btn-primary">Update Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($pricing_plans)): ?>
        <div class="container-fluid">
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-tags fa-4x text-muted"></i>
                </div>
                <h4 class="text-muted mb-4">No Pricing Plans Found</h4>
                <p class="text-muted mb-4">Get started by adding your first pricing plan</p>
                <button class="btn btn-primary" data-toggle="modal" data-target="#addPlanModal">
                    <i class="fas fa-plus mr-2"></i> Add New Plan
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Client-side validation for required fields
        const requiredFields = ['name', 'price', 'billing_period', 'description'];
        requiredFields.forEach(field => {
            const el = document.getElementById(field);
            if (el) el.required = true;
            
            // Also add to edit modals
            document.querySelectorAll(`[id^='edit_${field}']`).forEach(editEl => {
                editEl.required = true;
            });
        });

        // Validate price is non-negative
        const priceInputs = document.querySelectorAll('input[name="price"]');
        priceInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });

        // Feature management for add form
        const featuresContainer = document.getElementById('features-container');
        
        function addFeatureInput(container, value = '') {
            const newInputGroup = document.createElement('div');
            newInputGroup.className = 'input-group mb-2';
            newInputGroup.innerHTML = `
                <input type="text" class="form-control feature-input" name="features[]" value="${value}" placeholder="Enter a feature">
                <div class="input-group-append">
                    <button class="btn btn-outline-danger remove-feature" type="button">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Add event listener to remove button
            const removeBtn = newInputGroup.querySelector('.remove-feature');
            removeBtn.addEventListener('click', function() {
                newInputGroup.remove();
            });
            
            container.appendChild(newInputGroup);
            
            // Focus the new input
            const newInput = newInputGroup.querySelector('input');
            if (newInput) newInput.focus();
        }

        // Add feature button in add form
        document.querySelector('.add-feature')?.addEventListener('click', function() {
            addFeatureInput(featuresContainer);
        });

        // Add feature button in edit forms (delegated)
        document.addEventListener('click', function(e) {
            if (e.target.closest('.add-feature')) {
                const button = e.target.closest('.add-feature');
                const targetId = button.getAttribute('data-target');
                const targetContainer = document.getElementById(targetId) || featuresContainer;
                
                // Add new input group
                const newInputGroup = document.createElement('div');
                newInputGroup.className = 'input-group mb-2';
                newInputGroup.innerHTML = `
                    <input type="text" class="form-control feature-input" name="features[]" placeholder="Enter a feature">
                    <div class="input-group-append">
                        <button class="btn btn-outline-danger remove-feature" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                        <button class="btn btn-outline-success add-feature" type="button" data-target="${targetId}">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                `;
                
                // Replace the clicked button with the new input group
                button.closest('.input-group').parentNode.insertBefore(newInputGroup, button.closest('.input-group').nextSibling);
                
                // Add event listener to remove button
                const removeBtn = newInputGroup.querySelector('.remove-feature');
                removeBtn.addEventListener('click', function() {
                    newInputGroup.remove();
                });
                
                // Focus the new input
                const newInput = newInputGroup.querySelector('input');
                if (newInput) newInput.focus();
            }
            
            // Remove feature button (delegated)
            if (e.target.closest('.remove-feature')) {
                const button = e.target.closest('.remove-feature');
                const inputGroup = button.closest('.input-group');
                if (inputGroup) {
                    inputGroup.remove();
                }
            }
        });

        // Initialize features in add form if needed
        if (featuresContainer && featuresContainer.querySelectorAll('.feature-input').length === 0) {
            addFeatureInput(featuresContainer);
        }
    });
    </script>
</body>

</html>
