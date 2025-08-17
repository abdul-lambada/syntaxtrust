<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/upload.php';

// Define upload directory
define('UPLOAD_DIR', 'uploads/testimonials/');

// Function to handle file uploads
function handle_upload($file_input_name, $current_image_path = null) {
    return secure_upload(
        $file_input_name,
        UPLOAD_DIR,
        [
            'maxBytes' => 2 * 1024 * 1024,
            'allowedExt' => ['jpg','jpeg','png','webp','gif'],
            'allowedMime' => ['image/jpeg','image/png','image/webp','image/gif'],
            'prefix' => 'testimonial_'
        ],
        $current_image_path
    );
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

// Delete testimonial
if (isset($_POST['delete_testimonial']) && isset($_POST['testimonial_id']) && verify_csrf()) {
    $testimonial_id = $_POST['testimonial_id'];
    try {
        // First, get the image path to delete the file
        $stmt = $pdo->prepare("SELECT client_image FROM testimonials WHERE id = ?");
        $stmt->execute([$testimonial_id]);
        $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);
        $image_to_delete = $testimonial ? $testimonial['client_image'] : null;

        // Then, delete the database record
        $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
        $stmt->execute([$testimonial_id]);

        // If the record was deleted and an image exists, delete the file safely
        if ($stmt->rowCount() > 0 && $image_to_delete) {
            $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
            $target = realpath($image_to_delete);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }

        $message = "Testimonial deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting testimonial: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle testimonial status
if (isset($_POST['toggle_status']) && isset($_POST['testimonial_id']) && verify_csrf()) {
    $testimonial_id = $_POST['testimonial_id'];
    try {
        $stmt = $pdo->prepare("UPDATE testimonials SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$testimonial_id]);
        $message = "Testimonial status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating testimonial status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle featured status
if (isset($_POST['toggle_featured']) && isset($_POST['testimonial_id']) && verify_csrf()) {
    $testimonial_id = $_POST['testimonial_id'];
    try {
        $stmt = $pdo->prepare("UPDATE testimonials SET is_featured = NOT is_featured WHERE id = ?");
        $stmt->execute([$testimonial_id]);
        $message = "Featured status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating featured status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create new testimonial
if (isset($_POST['create_testimonial']) && verify_csrf()) {
    $client_name = $_POST['client_name'];
    $client_position = $_POST['client_position'];
    $client_company = $_POST['client_company'];
    $client_image = handle_upload('client_image');
    $content = $_POST['content'];
    $rating = intval($_POST['rating']);
    $project_name = $_POST['project_name'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    
    try {
                $stmt = $pdo->prepare("INSERT INTO testimonials (client_name, client_position, client_company, client_image, content, rating, project_name, is_featured, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_name, $client_position, $client_company, $client_image, $content, $rating, $project_name, $is_featured, $is_active, $sort_order]);
        $message = "Testimonial created successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error creating testimonial: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update testimonial
if (isset($_POST['update_testimonial']) && verify_csrf()) {
    $testimonial_id = $_POST['testimonial_id'];

    // Fetch current image path
    $stmt = $pdo->prepare("SELECT client_image FROM testimonials WHERE id = ?");
    $stmt->execute([$testimonial_id]);
    $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_image = $current_item ? $current_item['client_image'] : null;

    $client_name = $_POST['client_name'];
    $client_position = $_POST['client_position'];
    $client_company = $_POST['client_company'];
    $client_image = handle_upload('client_image', $current_image);
    $content = $_POST['content'];
    $rating = intval($_POST['rating']);
    $project_name = $_POST['project_name'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = intval($_POST['sort_order']);
    
    try {
                $stmt = $pdo->prepare("UPDATE testimonials SET client_name = ?, client_position = ?, client_company = ?, client_image = ?, content = ?, rating = ?, project_name = ?, is_featured = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$client_name, $client_position, $client_company, $client_image, $content, $rating, $project_name, $is_featured, $is_active, $sort_order, $testimonial_id]);
        $message = "Testimonial updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating testimonial: " . $e->getMessage();
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
    $where_clause = "WHERE client_name LIKE ? OR client_position LIKE ? OR client_company LIKE ? OR content LIKE ? OR project_name LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param];
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM testimonials $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get testimonials with pagination
$sql = "SELECT t.*, s.name as service_name FROM testimonials t LEFT JOIN services s ON t.service_id = s.id $where_clause ORDER BY t.is_featured DESC, t.sort_order ASC, t.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Testimonials</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addTestimonialModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Testimonial
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
                            <h6 class="m-0 font-weight-bold text-primary">Search Testimonials</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by client name, company, project..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_testimonials.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Testimonials Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Testimonials List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Client Photo</th>
                                            <th>Client Info</th>
                                            <th>Content</th>
                                            <th>Rating</th>
                                            <th>Service</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($testimonials as $testimonial): ?>
                                            <tr>
                                                <td><?php echo $testimonial['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($testimonial['client_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($testimonial['client_image']); ?>" alt="Client" class="rounded-circle" width="60" height="60" style="object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($testimonial['client_name']); ?></strong>
                                                    <?php if (!empty($testimonial['client_position'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($testimonial['client_position']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($testimonial['client_company'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($testimonial['client_company']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($testimonial['content'], 0, 150) . '...'); ?>
                                                    <?php if (!empty($testimonial['project_name'])): ?>
                                                        <br><small class="text-info">Project: <?php echo htmlspecialchars($testimonial['project_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($testimonial['rating']): ?>
                                                        <div class="text-warning">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star<?php echo $i <= $testimonial['rating'] ? '' : '-o'; ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <small class="text-muted"><?php echo $testimonial['rating']; ?>/5</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No rating</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($testimonial['service_name'])): ?>
                                                        <span class="badge badge-info"><?php echo htmlspecialchars($testimonial['service_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $testimonial['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $testimonial['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $testimonial['is_featured'] ? 'warning' : 'light'; ?>">
                                                        <?php echo $testimonial['is_featured'] ? 'Featured' : 'Regular'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewTestimonialModal<?php echo $testimonial['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editTestimonialModal<?php echo $testimonial['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this testimonial\'s status?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $testimonial['is_active'] ? 'warning' : 'success'; ?>">
                                                                <i class="fas fa-<?php echo $testimonial['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle featured status?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                                            <button type="submit" name="toggle_featured" class="btn btn-sm btn-<?php echo $testimonial['is_featured'] ? 'secondary' : 'warning'; ?>">
                                                                <i class="fas fa-star"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this testimonial? This action cannot be undone.')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                                                            <button type="submit" name="delete_testimonial" class="btn btn-sm btn-danger">
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

    <!-- Add Testimonial Modal -->
    <div class="modal fade" id="addTestimonialModal" tabindex="-1" role="dialog" aria-labelledby="addTestimonialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addTestimonialModalLabel">Add New Testimonial</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="manage_testimonials.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="client_name">Client Name *</label>
                                    <input type="text" class="form-control" id="client_name" name="client_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="client_position">Client Position</label>
                                    <input type="text" class="form-control" id="client_position" name="client_position">
                                </div>
                                <div class="form-group">
                                    <label for="client_company">Company</label>
                                    <input type="text" class="form-control" id="client_company" name="client_company">
                                </div>
                                <div class="form-group">
                                    <label for="client_image_create">Client Image</label>
                                    <input type="file" class="form-control-file" id="client_image_create" name="client_image" accept="image/*">
                                </div>
                                <div class="form-group">
                                    <label for="project_name">Project Type</label>
                                    <input type="text" class="form-control" id="project_name" name="project_name" placeholder="e.g., Web Development, Mobile App">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rating">Rating (1-5)</label>
                                    <select class="form-control" id="rating" name="rating">
                                        <option value="5">★★★★★ (5/5)</option>
                                        <option value="4">★★★★☆ (4/5)</option>
                                        <option value="3">★★★☆☆ (3/5)</option>
                                        <option value="2">★★☆☆☆ (2/5)</option>
                                        <option value="1">★☆☆☆☆ (1/5)</option>
                                        <option value="0" selected>No Rating</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="content">Testimonial Text *</label>
                                    <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1">
                                            <label class="form-check-label" for="is_featured">
                                                Mark as Featured
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                            <label class="form-check-label" for="is_active">
                                                Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mt-3">
                                    <label for="sort_order">Sort Order</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" value="0" min="0">
                                    <small class="form-text text-muted">Lower numbers appear first</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_testimonial" class="btn btn-primary">Save Testimonial</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>
    
    <script>
    // Image preview functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Preview image when URL changes
        document.querySelectorAll('[id^=client_image_edit]').forEach(function(input) {
            input.addEventListener('input', function() {
                const previewId = this.id.replace('client_image_edit', 'imagePreviewEdit');
                const previewDiv = document.getElementById(previewId);
                if (this.value) {
                    previewDiv.innerHTML = '<img src="' + this.value + '" class="img-fluid rounded-circle" style="max-width: 150px; max-height: 150px;">';
                } else {
                    previewDiv.innerHTML = '';
                }
            });
        });
        
        // Preview button click handler
        document.querySelectorAll('[id^=previewImageEdit]').forEach(function(button) {
            button.addEventListener('click', function() {
                const modalId = this.closest('.modal').id;
                const testimonialId = modalId.replace('editTestimonialModal', '');
                const input = document.getElementById('client_image_edit' + testimonialId);
                const previewDiv = document.getElementById('imagePreviewEdit' + testimonialId);
                if (input.value) {
                    previewDiv.innerHTML = '<img src="' + input.value + '" class="img-fluid rounded-circle" style="max-width: 150px; max-height: 150px;">';
                }
            });
        });
    });
    </script>

    <!-- View Testimonial Modals -->
    <?php foreach ($testimonials as $testimonial): ?>
    <div class="modal fade" id="viewTestimonialModal<?php echo $testimonial['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewTestimonialModalLabel<?php echo $testimonial['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewTestimonialModalLabel<?php echo $testimonial['id']; ?>">Testimonial from <?php echo htmlspecialchars($testimonial['client_name']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <?php if (!empty($testimonial['client_image'])): ?>
                                <img src="<?php echo htmlspecialchars($testimonial['client_image']); ?>" alt="<?php echo htmlspecialchars($testimonial['client_name']); ?>" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mb-3 mx-auto" style="width: 150px; height: 150px;">
                                    <i class="fas fa-user fa-4x"></i>
                                </div>
                            <?php endif; ?>
                            
                            <h4 class="mb-1"><?php echo htmlspecialchars($testimonial['client_name']); ?></h4>
                            
                            <?php if (!empty($testimonial['client_position']) || !empty($testimonial['client_company'])): ?>
                                <p class="text-muted mb-2">
                                    <?php 
                                    echo htmlspecialchars($testimonial['client_position']); 
                                    echo !empty($testimonial['client_company']) ? ' at ' . htmlspecialchars($testimonial['client_company']) : '';
                                    ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($testimonial['project_name'])): ?>
                                <p class="mb-2">
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($testimonial['project_name']); ?></span>
                                </p>
                            <?php endif; ?>
                            
                            <div class="rating mb-3">
                                <?php 
                                $rating = intval($testimonial['rating']);
                                for ($i = 1; $i <= 5; $i++): 
                                    $starClass = $i <= $rating ? 'text-warning' : 'text-muted';
                                ?>
                                    <i class="fas fa-star <?php echo $starClass; ?>"></i>
                                <?php endfor; ?>
                                <span class="ml-1">(<?php echo $rating; ?>/5)</span>
                            </div>
                            
                            <div class="mt-3">
                                <span class="badge badge-<?php echo $testimonial['is_active'] ? 'success' : 'secondary'; ?> mr-2">
                                    <?php echo $testimonial['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <?php if ($testimonial['is_featured']): ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-star"></i> Featured
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($testimonial['created_at'])): ?>
                                <div class="mt-3 text-muted small">
                                    <div>Created: <?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?></div>
                                    <?php if (!empty($testimonial['updated_at']) && $testimonial['updated_at'] !== $testimonial['created_at']): ?>
                                        <div>Updated: <?php echo date('M d, Y', strtotime($testimonial['updated_at'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="testimonial-content p-4 bg-light rounded">
                                <div class="quote-icon mb-3">
                                    <i class="fas fa-quote-left fa-2x text-primary opacity-50"></i>
                                </div>
                                
                                <div class="testimonial-text mb-4">
                                    <?php echo nl2br(htmlspecialchars($testimonial['content'])); ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="testimonial-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): 
                                            $starClass = $i <= $rating ? 'text-warning' : 'text-muted';
                                        ?>
                                            <i class="fas fa-star <?php echo $starClass; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="testimonial-date text-muted">
                                        <?php echo !empty($testimonial['created_at']) ? date('F j, Y', strtotime($testimonial['created_at'])) : ''; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($testimonial['client_company'])): ?>
                                <div class="mt-4">
                                    <h6>Company</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($testimonial['client_company']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($testimonial['project_name'])): ?>
                                <div class="mt-3">
                                    <h6>Project Type</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($testimonial['project_name']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <h6>Status</h6>
                                <p class="mb-0">
                                    <span class="badge badge-<?php echo $testimonial['is_active'] ? 'success' : 'secondary'; ?> mr-2">
                                        <?php echo $testimonial['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    <?php if ($testimonial['is_featured']): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-star"></i> Featured
                                        </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-warning" data-dismiss="modal" data-toggle="modal" data-target="#editTestimonialModal<?php echo $testimonial['id']; ?>">
                        <i class="fas fa-edit"></i> Edit Testimonial
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Edit Testimonial Modals -->
    <?php foreach ($testimonials as $testimonial): ?>
    <div class="modal fade" id="editTestimonialModal<?php echo $testimonial['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editTestimonialModalLabel<?php echo $testimonial['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="editTestimonialModalLabel<?php echo $testimonial['id']; ?>">Edit Testimonial: <?php echo htmlspecialchars($testimonial['client_name']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="manage_testimonials.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="testimonial_id" value="<?php echo $testimonial['id']; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="client_name_edit<?php echo $testimonial['id']; ?>">Client Name *</label>
                                    <input type="text" class="form-control" id="client_name_edit<?php echo $testimonial['id']; ?>" name="client_name" value="<?php echo htmlspecialchars($testimonial['client_name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="client_position_edit<?php echo $testimonial['id']; ?>">Client Position</label>
                                    <input type="text" class="form-control" id="client_position_edit<?php echo $testimonial['id']; ?>" name="client_position" value="<?php echo htmlspecialchars($testimonial['client_position'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="client_company_edit<?php echo $testimonial['id']; ?>">Company</label>
                                    <input type="text" class="form-control" id="client_company_edit<?php echo $testimonial['id']; ?>" name="client_company" value="<?php echo htmlspecialchars($testimonial['client_company'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="client_image_edit<?php echo $testimonial['id']; ?>">New Client Image (optional)</label>
                                    <input type="file" class="form-control-file" id="client_image_edit<?php echo $testimonial['id']; ?>" name="client_image" accept="image/*">
                                    <?php if (!empty($testimonial['client_image'])):
                                        $image_path = htmlspecialchars($testimonial['client_image']);
                                    ?>
                                        <div class="mt-2">
                                            <small>Current Image:</small><br>
                                            <img src="<?php echo $image_path; ?>" alt="Current Client Image" style="max-width: 100px; height: auto;">
                                            <a href="<?php echo $image_path; ?>" target="_blank" class="ml-2">View</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="project_name_edit<?php echo $testimonial['id']; ?>">Project Type</label>
                                    <input type="text" class="form-control" id="project_name_edit<?php echo $testimonial['id']; ?>" name="project_name" value="<?php echo htmlspecialchars($testimonial['project_name'] ?? ''); ?>" placeholder="e.g., Web Development, Mobile App">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rating_edit<?php echo $testimonial['id']; ?>">Rating (1-5)</label>
                                    <select class="form-control" id="rating_edit<?php echo $testimonial['id']; ?>" name="rating">
                                        <option value="5" <?php echo ($testimonial['rating'] ?? 0) == 5 ? 'selected' : ''; ?>>★★★★★ (5/5)</option>
                                        <option value="4" <?php echo ($testimonial['rating'] ?? 0) == 4 ? 'selected' : ''; ?>>★★★★☆ (4/5)</option>
                                        <option value="3" <?php echo ($testimonial['rating'] ?? 0) == 3 ? 'selected' : ''; ?>>★★★☆☆ (3/5)</option>
                                        <option value="2" <?php echo ($testimonial['rating'] ?? 0) == 2 ? 'selected' : ''; ?>>★★☆☆☆ (2/5)</option>
                                        <option value="1" <?php echo ($testimonial['rating'] ?? 0) == 1 ? 'selected' : ''; ?>>★☆☆☆☆ (1/5)</option>
                                        <option value="0" <?php echo empty($testimonial['rating']) ? 'selected' : ''; ?>>No Rating</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="content_edit<?php echo $testimonial['id']; ?>">Testimonial Text *</label>
                                    <textarea class="form-control" id="content_edit<?php echo $testimonial['id']; ?>" name="content" rows="5" required><?php echo htmlspecialchars($testimonial['content'] ?? ''); ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_featured_edit<?php echo $testimonial['id']; ?>" name="is_featured" value="1" <?php echo !empty($testimonial['is_featured']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_featured_edit<?php echo $testimonial['id']; ?>">
                                                Mark as Featured
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_active_edit<?php echo $testimonial['id']; ?>" name="is_active" value="1" <?php echo !empty($testimonial['is_active']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_active_edit<?php echo $testimonial['id']; ?>">
                                                Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mt-3">
                                    <label for="sort_order_edit<?php echo $testimonial['id']; ?>">Sort Order</label>
                                    <input type="number" class="form-control" id="sort_order_edit<?php echo $testimonial['id']; ?>" name="sort_order" value="<?php echo intval($testimonial['sort_order'] ?? 0); ?>" min="0">
                                    <small class="form-text text-muted">Lower numbers appear first</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_testimonial" class="btn btn-primary">Update Testimonial</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</body>

</html>
