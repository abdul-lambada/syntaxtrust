<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Define upload directory
define('UPLOAD_DIR', 'uploads/services/');

// Function to handle file uploads
function handle_upload($file_input_name, $current_image_path = null) {
    // No new upload
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_image_path;
    }

    // Ensure upload directory exists
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    $tmp = $_FILES[$file_input_name]['tmp_name'];
    $orig = $_FILES[$file_input_name]['name'];
    $size = (int)$_FILES[$file_input_name]['size'];

    // Validate size (max 2MB)
    $maxBytes = 2 * 1024 * 1024;
    if ($size <= 0 || $size > $maxBytes) {
        return $current_image_path;
    }

    // Validate extension and MIME
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExt, true)) {
        return $current_image_path;
    }
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime = $finfo ? finfo_file($finfo, $tmp) : (function_exists('mime_content_type') ? mime_content_type($tmp) : null);
    if ($finfo) { finfo_close($finfo); }
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!$mime || !in_array($mime, $allowedMime, true)) {
        return $current_image_path;
    }

    // Build destination path
    $new_file_name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $dest_path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $new_file_name;

    if (!move_uploaded_file($tmp, $dest_path)) {
        return $current_image_path;
    }

    // Safely delete old file if it resides within UPLOAD_DIR
    if ($current_image_path) {
        $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
        $target = realpath($current_image_path);
        if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
            @unlink($target);
        }
    }

    return $dest_path;
}

// CSRF protection: generate token and helper
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

// Delete service
if (isset($_POST['delete_service']) && isset($_POST['service_id']) && verify_csrf()) {
    $service_id = $_POST['service_id'];
    try {
        // Fetch image path
        $stmt = $pdo->prepare("SELECT image FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $image_to_delete = $row ? $row['image'] : null;

        // Delete record
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$service_id]);

        // Safely delete file in UPLOAD_DIR
        if ($stmt->rowCount() > 0 && $image_to_delete) {
            $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
            $target = realpath($image_to_delete);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }

        $message = "Service deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting service: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle service status
if (isset($_POST['toggle_status']) && isset($_POST['service_id']) && verify_csrf()) {
    $service_id = $_POST['service_id'];
    try {
        $stmt = $pdo->prepare("UPDATE services SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$service_id]);
        $message = "Service status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating service status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create new service
if (isset($_POST['create_service']) && verify_csrf()) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $short_description = $_POST['short_description'];
    $icon = $_POST['icon'];
    $image = handle_upload('image');
    $price = $_POST['price'] ? floatval($_POST['price']) : null;
    $duration = $_POST['duration'];
    // Normalize features from either array inputs (features[]) or a comma-separated string
    $rawFeatures = $_POST['features'] ?? null;
    $featuresArr = [];
    if (is_array($rawFeatures)) {
        foreach ($rawFeatures as $f) {
            $t = trim((string)$f);
            if ($t !== '') { $featuresArr[] = $t; }
        }
    } elseif (is_string($rawFeatures)) {
        foreach (explode(',', $rawFeatures) as $f) {
            $t = trim($f);
            if ($t !== '') { $featuresArr[] = $t; }
        }
    }
    $features = !empty($featuresArr) ? json_encode($featuresArr) : null;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO services (name, description, short_description, icon, image, price, duration, features, is_featured, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $short_description, $icon, $image, $price, $duration, $features, $is_featured, $is_active, $sort_order]);
        $message = "Service created successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error creating service: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update service
if (isset($_POST['update_service']) && verify_csrf()) {
    $service_id = $_POST['service_id'];

    // Fetch the current image path to pass to the handler for deletion
    $stmt = $pdo->prepare("SELECT image FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $current_service = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_image_path = $current_service ? $current_service['image'] : null;

    $name = $_POST['name'];
    $description = $_POST['description'];
    $short_description = $_POST['short_description'];
    $icon = $_POST['icon'];
    $image = handle_upload('image', $current_image_path);
    $price = $_POST['price'] ? floatval($_POST['price']) : null;
    $duration = $_POST['duration'];
    // Normalize features from either array inputs (features[]) or a comma-separated string
    $rawFeatures = $_POST['features'] ?? null;
    $featuresArr = [];
    if (is_array($rawFeatures)) {
        foreach ($rawFeatures as $f) {
            $t = trim((string)$f);
            if ($t !== '') { $featuresArr[] = $t; }
        }
    } elseif (is_string($rawFeatures)) {
        foreach (explode(',', $rawFeatures) as $f) {
            $t = trim($f);
            if ($t !== '') { $featuresArr[] = $t; }
        }
    }
    $features = !empty($featuresArr) ? json_encode($featuresArr) : null;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    
    try {
        $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, short_description = ?, icon = ?, image = ?, price = ?, duration = ?, features = ?, is_featured = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $description, $short_description, $icon, $image, $price, $duration, $features, $is_featured, $is_active, $sort_order, $service_id]);
        $message = "Service updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating service: " . $e->getMessage();
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
    $where_clause = "WHERE name LIKE ? OR description LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM services $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get services with pagination
$sql = "SELECT * FROM services $where_clause ORDER BY sort_order ASC, created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Services</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addServiceModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Service
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
                            <h6 class="m-0 font-weight-bold text-primary">Search Services</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by name, description..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_services.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Services Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Services List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($services as $service): ?>
                                            <tr>
                                                <td><?php echo $service['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                                    <?php if (!empty($service['icon'])): ?>
                                                        <br><small class="text-muted"><i class="fas fa-<?php echo htmlspecialchars($service['icon']); ?>"></i></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($service['description'], 0, 100) . '...'); ?></td>
                                                <td>
                                                    <?php if ($service['price'] > 0): ?>
                                                        Rp <?php echo number_format($service['price'], 0, ',', '.'); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Custom</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $service['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewServiceModal<?php echo $service['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editServiceModal<?php echo $service['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this service\'s status?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $service['is_active'] ? 'warning' : 'success'; ?>">
                                                                <i class="fas fa-<?php echo $service['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this service? This action cannot be undone.')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                            <button type="submit" name="delete_service" class="btn btn-sm btn-danger">
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

    <!-- Add Service Modal -->
    <div class="modal fade" id="addServiceModal" tabindex="-1" role="dialog" aria-labelledby="addServiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addServiceModalLabel">Add Service</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Service Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="icon">Icon Class</label>
                                    <input type="text" class="form-control" id="icon" name="icon" placeholder="fas fa-code">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="short_description">Short Description</label>
                            <textarea class="form-control" id="short_description" name="short_description" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="description">Full Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="image">Image</label>
                                    <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price">Price (IDR)</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="duration">Duration</label>
                                    <input type="text" class="form-control" id="duration" name="duration" placeholder="3-7 days">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sort_order">Sort Order</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="features">Features (one per line)</label>
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured">
                                    <label class="form-check-label" for="is_featured">Featured Service</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_service" class="btn btn-primary">Create Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Service Modals -->
    <?php foreach ($services as $service): 
        // Safely decode features
        $features = [];
        if (!empty($service['features'])) {
            $decoded = json_decode($service['features'], true);
            $features = is_array($decoded) ? $decoded : [];
        }
    ?>
    <div class="modal fade" id="viewServiceModal<?php echo $service['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewServiceModalLabel<?php echo $service['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewServiceModalLabel<?php echo $service['id']; ?>">
                        <i class="fas <?php echo !empty($service['icon']) ? $service['icon'] : 'fa-cog'; ?> mr-2"></i>
                        <?php echo htmlspecialchars($service['name']); ?>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <?php if (!empty($service['image'])): ?>
                                <img src="<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" class="img-fluid rounded mb-3">
                            <?php endif; ?>
                            <p class="text-muted">
                                <i class="fas fa-clock mr-2"></i> 
                                <?php echo !empty($service['duration']) ? htmlspecialchars($service['duration']) : 'N/A'; ?>
                            </p>
                            <?php if ($service['price'] > 0): ?>
                                <h4 class="text-primary">Rp <?php echo number_format($service['price'], 0, ',', '.'); ?></h4>
                            <?php else: ?>
                                <h4 class="text-primary">Custom Quote</h4>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                            
                            <?php if (!empty($features)): ?>
                                <h5 class="mt-4">Features</h5>
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
                    <div class="row">
                        <div class="col-12
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge badge-<?php echo $service['is_active'] ? 'success' : 'secondary'; ?> mr-2">
                                        <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <?php if ($service['is_featured']): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-star"></i> Featured
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    Last updated: <?php echo date('M d, Y', strtotime($service['updated_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <a href="#editServiceModal<?php echo $service['id']; ?>" class="btn btn-primary" data-toggle="modal" data-dismiss="modal" data-target="#editServiceModal<?php echo $service['id']; ?>">
                        <i class="fas fa-edit"></i> Edit Service
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Edit Service Modals -->
    <?php foreach ($services as $service): ?>
    <div class="modal fade" id="editServiceModal<?php echo $service['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editServiceModalLabel<?php echo $service['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editServiceModalLabel<?php echo $service['id']; ?>">Edit Service</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_name<?php echo $service['id']; ?>">Service Name</label>
                                    <input type="text" class="form-control" id="edit_name<?php echo $service['id']; ?>" name="name" value="<?php echo htmlspecialchars($service['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_icon<?php echo $service['id']; ?>">Icon Class</label>
                                    <input type="text" class="form-control" id="edit_icon<?php echo $service['id']; ?>" name="icon" value="<?php echo htmlspecialchars($service['icon']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_short_description<?php echo $service['id']; ?>">Short Description</label>
                            <textarea class="form-control" id="edit_short_description<?php echo $service['id']; ?>" name="short_description" rows="2"><?php echo htmlspecialchars($service['short_description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_description<?php echo $service['id']; ?>">Full Description</label>
                            <textarea class="form-control" id="edit_description<?php echo $service['id']; ?>" name="description" rows="4" required><?php echo htmlspecialchars($service['description']); ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_image_<?php echo $service['id']; ?>">New Image (optional)</label>
                                    <input type="file" class="form-control-file" id="edit_image_<?php echo $service['id']; ?>" name="image" accept="image/*">
                                    <?php if ($service['image']): ?>
                                        <small class="form-text text-muted">Current: <a href="<?php echo htmlspecialchars($service['image']); ?>" target="_blank">View Image</a></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_price<?php echo $service['id']; ?>">Price (IDR)</label>
                                    <input type="number" class="form-control" id="edit_price<?php echo $service['id']; ?>" name="price" value="<?php echo $service['price']; ?>" step="0.01">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_duration<?php echo $service['id']; ?>">Duration</label>
                                    <input type="text" class="form-control" id="edit_duration<?php echo $service['id']; ?>" name="duration" value="<?php echo htmlspecialchars($service['duration']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_sort_order<?php echo $service['id']; ?>">Sort Order</label>
                                    <input type="number" class="form-control" id="edit_sort_order<?php echo $service['id']; ?>" name="sort_order" value="<?php echo $service['sort_order']; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Features</label>
                            <div id="edit_features_container_<?php echo $service['id']; ?>">
                                <?php if (!empty($features)): ?>
                                    <?php foreach ($features as $index => $feature): ?>
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control feature-input" name="features[]" value="<?php echo htmlspecialchars(trim($feature)); ?>" placeholder="Enter a feature">
                                            <div class="input-group-append">
                                                <button class="btn btn-outline-danger remove-feature" type="button">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php if ($index === count($features) - 1): ?>
                                                    <button class="btn btn-outline-success add-feature" type="button" data-target="edit_features_container_<?php echo $service['id']; ?>">
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
                                            <button class="btn btn-outline-success add-feature" type="button" data-target="edit_features_container_<?php echo $service['id']; ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <small class="form-text text-muted">Click + to add more features</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_is_featured<?php echo $service['id']; ?>" name="is_featured" <?php echo $service['is_featured'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_is_featured<?php echo $service['id']; ?>">Featured Service</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="edit_is_active<?php echo $service['id']; ?>" name="is_active" <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="edit_is_active<?php echo $service['id']; ?>">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_service" class="btn btn-primary">Update Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($services)): ?>
        <div class="container-fluid">
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-box-open fa-4x text-muted"></i>
                </div>
                <h4 class="text-muted mb-4">No Services Found</h4>
                <p class="text-muted mb-4">Get started by adding your first service</p>
                <button class="btn btn-primary" data-toggle="modal" data-target="#addServiceModal">
                    <i class="fas fa-plus mr-2"></i> Add New Service
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>
    
    <script>
    // Client-side validation
    document.addEventListener('DOMContentLoaded', function() {
        // Add required attribute to required fields
        const requiredFields = ['name', 'description'];
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
