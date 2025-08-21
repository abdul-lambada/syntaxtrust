<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Define upload directory
define('UPLOAD_DIR', __DIR__ . '/../uploads/portofolio/');

// Helper to convert relative asset paths to absolute URLs under the site root
function assetUrlAdmin(string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if (preg_match('/^https?:\/\//i', $path)) return $path; // already absolute URL
    $path = ltrim($path, '/');
    return '/syntaxtrust/' . $path; // site runs under /syntaxtrust
}

// Function to handle file uploads (validate image type and size)
function handle_upload($file_input_name, $current_image_path = null) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_image_path; // no new file
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    $max_bytes = 2 * 1024 * 1024; // 2MB

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    $tmp = $_FILES[$file_input_name]['tmp_name'];
    $name = $_FILES[$file_input_name]['name'];
    $size = (int)$_FILES[$file_input_name]['size'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($size <= 0 || $size > $max_bytes) {
        throw new RuntimeException('Image too large or empty (max 2MB).');
    }
    if (!in_array($ext, $allowed_ext, true)) {
        throw new RuntimeException('Invalid image type. Allowed: jpg, jpeg, png, webp.');
    }
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload.');
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    if ($finfo) {
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_mimes, true)) {
            throw new RuntimeException('Invalid image MIME type.');
        }
    }

    $new_file_name = uniqid('portfolio_', true) . '_' . time() . '.' . $ext;
    $dest_path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $new_file_name;
    if (!move_uploaded_file($tmp, $dest_path)) {
        throw new RuntimeException('Failed to move uploaded image.');
    }

    // Safe delete old file if inside UPLOAD_DIR
    if ($current_image_path) {
        $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
        $oldReal = realpath($current_image_path);
        if ($base && $oldReal && strpos($oldReal, $base) === 0 && is_file($oldReal)) {
            @unlink($oldReal);
        }
    }
    // Return relative path for database storage
    return 'uploads/portofolio/' . $new_file_name;
}

// Function to handle multiple file uploads for gallery (with validation)
function handle_gallery_upload($file_input_name, $current_images_json = '[]') {
    $uploaded_paths = [];
    if (isset($_FILES[$file_input_name]) && is_array($_FILES[$file_input_name]['name'])) {
        $file_count = count($_FILES[$file_input_name]['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES[$file_input_name]['error'][$i] === UPLOAD_ERR_OK) {
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0777, true);
                }
                $tmp = $_FILES[$file_input_name]['tmp_name'][$i];
                $name = $_FILES[$file_input_name]['name'][$i];
                $size = (int)$_FILES[$file_input_name]['size'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                $max_bytes = 2 * 1024 * 1024; // 2MB

                if ($size <= 0 || $size > $max_bytes) {
                    continue; // skip invalid size
                }
                if (!in_array($ext, $allowed_ext, true)) {
                    continue; // skip invalid type
                }
                if (!is_uploaded_file($tmp)) {
                    continue;
                }
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
                if ($finfo) {
                    $mime = finfo_file($finfo, $tmp);
                    finfo_close($finfo);
                    if (!in_array($mime, $allowed_mimes, true)) {
                        continue;
                    }
                }

                $new_file_name = uniqid('portfolio_', true) . '_gallery_' . time() . '.' . $ext;
                $dest_path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $new_file_name;
                if (move_uploaded_file($tmp, $dest_path)) {
                    // Store relative path for database
                    $uploaded_paths[] = 'uploads/portofolio/' . $new_file_name;
                }
            }
        }
    }

    $current_images = json_decode($current_images_json, true) ?: [];
    $new_images = array_merge($current_images, $uploaded_paths);

    // Handle image deletion from gallery (safe within UPLOAD_DIR)
    if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
        foreach ($_POST['delete_images'] as $image_to_delete) {
            if (($key = array_search($image_to_delete, $new_images)) !== false) {
                $real = realpath($image_to_delete);
                if ($base && $real && strpos($real, $base) === 0 && is_file($real)) {
                    @unlink($real);
                }
                unset($new_images[$key]);
            }
        }
    }

    return json_encode(array_values($new_images)); // Re-index array
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
    header('Location: /syntaxtrust/login.php');
    exit();
}

// Handle CRUD operations
$message = '';
$message_type = '';

// Delete portfolio item
if (isset($_POST['delete_portfolio']) && isset($_POST['portfolio_id']) && verify_csrf()) {
    $portfolio_id = $_POST['portfolio_id'];
    try {
        // Fetch current file paths before deletion
        $stmt = $pdo->prepare("SELECT image_main, images FROM portfolio WHERE id = ?");
        $stmt->execute([$portfolio_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = ?");
        $stmt->execute([$portfolio_id]);

        // Safely delete files if deletion succeeded
        if ($stmt->rowCount() > 0 && $item) {
            $base = realpath(rtrim(UPLOAD_DIR, '/\\'));

            // Delete main image
            if (!empty($item['image_main'])) {
                $mainReal = realpath($item['image_main']);
                if ($base && $mainReal && strpos($mainReal, $base) === 0 && is_file($mainReal)) {
                    @unlink($mainReal);
                }
            }

            // Delete gallery images
            if (!empty($item['images'])) {
                $gallery = json_decode($item['images'], true) ?: [];
                foreach ($gallery as $imgPath) {
                    $imgReal = realpath($imgPath);
                    if ($base && $imgReal && strpos($imgReal, $base) === 0 && is_file($imgReal)) {
                        @unlink($imgReal);
                    }
                }
            }
        }

        $message = "Portfolio item deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting portfolio item: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle portfolio status
if (isset($_POST['toggle_status']) && isset($_POST['portfolio_id']) && verify_csrf()) {
    $portfolio_id = $_POST['portfolio_id'];
    try {
        $stmt = $pdo->prepare("UPDATE portfolio SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$portfolio_id]);
        $message = "Portfolio status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating portfolio status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle featured status
if (isset($_POST['toggle_featured']) && isset($_POST['portfolio_id']) && verify_csrf()) {
    $portfolio_id = $_POST['portfolio_id'];
    try {
        $stmt = $pdo->prepare("UPDATE portfolio SET is_featured = NOT is_featured WHERE id = ?");
        $stmt->execute([$portfolio_id]);
        $message = "Featured status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating featured status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create new portfolio item
if (isset($_POST['create_portfolio']) && verify_csrf()) {
    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $short_description = isset($_POST['short_description']) ? trim($_POST['short_description']) : '';
    $client_name = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
    $category = trim($_POST['category']);
    $technologies = isset($_POST['technologies']) && $_POST['technologies'] !== '' ? json_encode(array_map('trim', explode(',', $_POST['technologies']))) : null;
    $project_url = isset($_POST['project_url']) ? trim($_POST['project_url']) : '';
    $github_url = isset($_POST['github_url']) ? trim($_POST['github_url']) : '';
    $start_date = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? $_POST['start_date'] : null;
    $end_date = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? $_POST['end_date'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : 'completed';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Basic validation
    $valid_status = ['completed','ongoing','upcoming'];
    if ($title === '') {
        $message = 'Title is required.';
        $message_type = 'danger';
    } elseif ($category === '') {
        $message = 'Category is required.';
        $message_type = 'danger';
    } elseif (!in_array($status, $valid_status, true)) {
        $message = 'Invalid status value.';
        $message_type = 'danger';
    } elseif ($project_url !== '' && !filter_var($project_url, FILTER_VALIDATE_URL)) {
        $message = 'Invalid Project URL.';
        $message_type = 'danger';
    } elseif ($github_url !== '' && !filter_var($github_url, FILTER_VALIDATE_URL)) {
        $message = 'Invalid GitHub URL.';
        $message_type = 'danger';
    } elseif ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
        $message = 'End date cannot be earlier than start date.';
        $message_type = 'danger';
    } else {
        try {
            $image_main = handle_upload('image_main');
            $images = handle_gallery_upload('images');

            $stmt = $pdo->prepare("INSERT INTO portfolio (title, description, short_description, client_name, category, technologies, project_url, github_url, image_main, images, start_date, end_date, status, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $short_description, $client_name, $category, $technologies, $project_url, $github_url, $image_main, $images, $start_date, $end_date, $status, $is_featured, $is_active]);
            $message = "Portfolio item created successfully!";
            $message_type = "success";
        } catch (Throwable $e) {
            $message = "Error creating portfolio item: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Update portfolio item
if (isset($_POST['update_portfolio']) && verify_csrf()) {
    $portfolio_id = $_POST['portfolio_id'];

    $stmt = $pdo->prepare("SELECT image_main, images FROM portfolio WHERE id = ?");
    $stmt->execute([$portfolio_id]);
    $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_main_image = $current_item['image_main'] ?? null;
    $current_gallery_images = $current_item['images'] ?? '[]';

    $title = trim($_POST['title']);
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $short_description = isset($_POST['short_description']) ? trim($_POST['short_description']) : '';
    $client_name = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
    $category = trim($_POST['category']);
    $technologies = isset($_POST['technologies']) && $_POST['technologies'] !== '' ? json_encode(array_map('trim', explode(',', $_POST['technologies']))) : null;
    $project_url = isset($_POST['project_url']) ? trim($_POST['project_url']) : '';
    $github_url = isset($_POST['github_url']) ? trim($_POST['github_url']) : '';
    $start_date = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? $_POST['start_date'] : null;
    $end_date = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? $_POST['end_date'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : 'completed';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $valid_status = ['completed','ongoing','upcoming'];
    if ($title === '') {
        $message = 'Title is required.';
        $message_type = 'danger';
    } elseif ($category === '') {
        $message = 'Category is required.';
        $message_type = 'danger';
    } elseif (!in_array($status, $valid_status, true)) {
        $message = 'Invalid status value.';
        $message_type = 'danger';
    } elseif ($project_url !== '' && !filter_var($project_url, FILTER_VALIDATE_URL)) {
        $message = 'Invalid Project URL.';
        $message_type = 'danger';
    } elseif ($github_url !== '' && !filter_var($github_url, FILTER_VALIDATE_URL)) {
        $message = 'Invalid GitHub URL.';
        $message_type = 'danger';
    } elseif ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
        $message = 'End date cannot be earlier than start date.';
        $message_type = 'danger';
    } else {
        try {
            $image_main = handle_upload('image_main', $current_main_image);
            $images = handle_gallery_upload('images', $current_gallery_images);

            $stmt = $pdo->prepare("UPDATE portfolio SET title = ?, description = ?, short_description = ?, client_name = ?, category = ?, technologies = ?, project_url = ?, github_url = ?, image_main = ?, images = ?, start_date = ?, end_date = ?, status = ?, is_featured = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $description, $short_description, $client_name, $category, $technologies, $project_url, $github_url, $image_main, $images, $start_date, $end_date, $status, $is_featured, $is_active, $portfolio_id]);
            $message = "Portfolio item updated successfully!";
            $message_type = "success";
        } catch (Throwable $e) {
            $message = "Error updating portfolio item: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Search and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;

// Build query
$where_clause = "";
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE title LIKE ? OR description LIKE ? OR client_name LIKE ? OR category LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM portfolio $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit));

// Validate current page and compute offset
if ($page < 1) { $page = 1; }
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $limit;

// Get portfolio items with pagination
$sql = "SELECT * FROM portfolio $where_clause ORDER BY is_featured DESC, created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$portfolio_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Portfolio</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addPortfolioModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Portfolio Item
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
                            <h6 class="m-0 font-weight-bold text-primary">Search Portfolio</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by title, client, category..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_portfolio.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Portfolio Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Portfolio List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Image</th>
                                            <th>Title</th>
                                            <th>Client</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($portfolio_items as $item): ?>
                                            <tr>
                                                <td><?php echo $item['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($item['image_main'])): ?>
                                                        <img src="<?php echo htmlspecialchars(assetUrlAdmin($item['image_main'])); ?>" alt="Portfolio" class="img-thumbnail" width="80" height="60" style="object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 80px; height: 60px;">
                                                            <i class="fas fa-image"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                    <?php if (!empty($item['short_description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['short_description']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['client_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $item['status'] === 'completed' ? 'success' : ($item['status'] === 'ongoing' ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $item['is_featured'] ? 'warning' : 'light'; ?>">
                                                        <?php echo $item['is_featured'] ? 'Featured' : 'Regular'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewPortfolioModal<?php echo $item['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editPortfolioModal<?php echo $item['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this portfolio item\'s status?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="portfolio_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $item['is_active'] ? 'warning' : 'success'; ?>">
                                                                <i class="fas fa-<?php echo $item['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle featured status?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="portfolio_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" name="toggle_featured" class="btn btn-sm btn-<?php echo $item['is_featured'] ? 'secondary' : 'warning'; ?>">
                                                                <i class="fas fa-star"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this portfolio item? This action cannot be undone.')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="portfolio_id" value="<?php echo $item['id']; ?>">
                                                            <button type="submit" name="delete_portfolio" class="btn btn-sm btn-danger">
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

    <!-- Add New Portfolio Modal -->
    <div class="modal fade" id="addPortfolioModal" tabindex="-1" role="dialog" aria-labelledby="addPortfolioModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="manage_portfolio.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPortfolioModalLabel">Add New Portfolio Item</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="title">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                                <div class="invalid-feedback">Title is required.</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="client_name">Client Name</label>
                                <input type="text" class="form-control" id="client_name" name="client_name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="short_description">Short Description</label>
                            <input type="text" class="form-control" id="short_description" name="short_description" maxlength="255">
                        </div>
                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            <div class="invalid-feedback">Description is required.</div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="category">Category *</label>
                                <input type="text" class="form-control" id="category" name="category" required>
                                <div class="invalid-feedback">Category is required.</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="status">Status *</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="completed">Completed</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="upcoming">Upcoming</option>
                                </select>
                                <div class="invalid-feedback">Please select a status.</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="start_date">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="end_date">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="technologies">Technologies (comma separated)</label>
                            <input type="text" class="form-control" id="technologies" name="technologies" placeholder="e.g., HTML, CSS, JavaScript, PHP">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="project_url">Project URL</label>
                                <input type="url" class="form-control" id="project_url" name="project_url">
                                <div class="invalid-feedback">Please provide a valid URL.</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="github_url">GitHub URL</label>
                                <input type="url" class="form-control" id="github_url" name="github_url">
                                <div class="invalid-feedback">Please provide a valid URL.</div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="image_main">Main Image</label>
                            <input type="file" class="form-control-file" id="image_main" name="image_main" accept="image/png,image/jpeg,image/webp">
                            <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
                        </div>
                        <div class="form-group">
                            <label for="images">Gallery Images (can select multiple)</label>
                            <input type="file" class="form-control-file" id="images" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>
                            <small class="form-text text-muted">Each max 2MB.</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" value="1">
                                    <label class="custom-control-label" for="is_featured">Featured Project</label>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" checked>
                                    <label class="custom-control-label" for="is_active">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_portfolio" class="btn btn-primary">Save Portfolio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($portfolio_items as $item): 
        $technologies = !empty($item['technologies']) ? json_decode($item['technologies'], true) : [];
        $technologies_str = is_array($technologies) ? implode(', ', $technologies) : '';
        $images = !empty($item['images']) ? json_decode($item['images'], true) : [];
        $images_str = is_array($images) ? implode(',', $images) : '';
    ?>
    <!-- View Portfolio Modal -->
    <div class="modal fade" id="viewPortfolioModal<?php echo $item['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewPortfolioModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewPortfolioModalLabel<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['title']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <?php if (!empty($item['image_main'])): ?>
                                <img src="<?php echo htmlspecialchars(assetUrlAdmin($item['image_main'])); ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <h5>Project Details</h5>
                                <p><strong>Client:</strong> <?php echo htmlspecialchars($item['client_name'] ?? 'N/A'); ?></p>
                                <p><strong>Category:</strong> <span class="badge badge-primary"><?php echo htmlspecialchars($item['category']); ?></span></p>
                                <p><strong>Status:</strong> <span class="badge badge-<?php echo $item['status'] === 'completed' ? 'success' : ($item['status'] === 'ongoing' ? 'warning' : 'info'); ?>"><?php echo ucfirst($item['status']); ?></span></p>
                                <p><strong>Featured:</strong> <span class="badge badge-<?php echo $item['is_featured'] ? 'warning' : 'secondary'; ?>"><?php echo $item['is_featured'] ? 'Yes' : 'No'; ?></span></p>
                                <p><strong>Active:</strong> <span class="badge badge-<?php echo $item['is_active'] ? 'success' : 'danger'; ?>"><?php echo $item['is_active'] ? 'Yes' : 'No'; ?></span></p>
                            </div>
                            <?php if (!empty($item['project_url']) || !empty($item['github_url'])): ?>
                                <div class="mb-3">
                                    <h5>Links</h5>
                                    <?php if (!empty($item['project_url'])): ?>
                                        <p><a href="<?php echo htmlspecialchars($item['project_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt"></i> View Project</a></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['github_url'])): ?>
                                        <p><a href="<?php echo htmlspecialchars($item['github_url']); ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fab fa-github"></i> View on GitHub</a></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                            
                            <?php if (!empty($technologies)): ?>
                                <h5 class="mt-4">Technologies Used</h5>
                                <div class="d-flex flex-wrap">
                                    <?php foreach ($technologies as $tech): ?>
                                        <span class="badge badge-secondary mr-2 mb-2"><?php echo htmlspecialchars(trim($tech)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($images)): ?>
                                <h5 class="mt-4">Gallery</h5>
                                <div class="row">
                                    <?php foreach ($images as $img): ?>
                                        <div class="col-6 col-md-4 mb-3">
                                            <img src="<?php echo htmlspecialchars(assetUrlAdmin($img)); ?>" class="img-thumbnail" style="height: 100px; width: 100%; object-fit: cover;" alt="Project Image">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Portfolio Modal -->
    <div class="modal fade" id="editPortfolioModal<?php echo $item['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editPortfolioModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="manage_portfolio.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="portfolio_id" value="<?php echo $item['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPortfolioModalLabel<?php echo $item['id']; ?>">Edit Portfolio Item: <?php echo htmlspecialchars($item['title']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="edit_title_<?php echo $item['id']; ?>">Title *</label>
                                <input type="text" class="form-control" id="edit_title_<?php echo $item['id']; ?>" name="title" value="<?php echo htmlspecialchars($item['title']); ?>" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="edit_client_name_<?php echo $item['id']; ?>">Client Name</label>
                                <input type="text" class="form-control" id="edit_client_name_<?php echo $item['id']; ?>" name="client_name" value="<?php echo htmlspecialchars($item['client_name']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_short_description_<?php echo $item['id']; ?>">Short Description</label>
                            <input type="text" class="form-control" id="edit_short_description_<?php echo $item['id']; ?>" name="short_description" value="<?php echo htmlspecialchars($item['short_description']); ?>" maxlength="255">
                        </div>
                        <div class="form-group">
                            <label for="edit_description_<?php echo $item['id']; ?>">Description *</label>
                            <textarea class="form-control" id="edit_description_<?php echo $item['id']; ?>" name="description" rows="3" required><?php echo htmlspecialchars($item['description']); ?></textarea>
                            <div class="invalid-feedback">Description is required.</div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="edit_category_<?php echo $item['id']; ?>">Category *</label>
                                <input type="text" class="form-control" id="edit_category_<?php echo $item['id']; ?>" name="category" value="<?php echo htmlspecialchars($item['category']); ?>" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="edit_status_<?php echo $item['id']; ?>">Status *</label>
                                <select class="form-control" id="edit_status_<?php echo $item['id']; ?>" name="status" required>
                                    <option value="completed" <?php echo $item['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="ongoing" <?php echo $item['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="upcoming" <?php echo $item['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="edit_start_date_<?php echo $item['id']; ?>">Start Date</label>
                                <input type="date" class="form-control" id="edit_start_date_<?php echo $item['id']; ?>" name="start_date" value="<?php echo htmlspecialchars($item['start_date']); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="edit_end_date_<?php echo $item['id']; ?>">End Date</label>
                                <input type="date" class="form-control" id="edit_end_date_<?php echo $item['id']; ?>" name="end_date" value="<?php echo htmlspecialchars($item['end_date']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_technologies_<?php echo $item['id']; ?>">Technologies (comma separated)</label>
                            <input type="text" class="form-control" id="edit_technologies_<?php echo $item['id']; ?>" name="technologies" value="<?php echo htmlspecialchars(implode(',', json_decode($item['technologies'] ?? '[]', true))); ?>" placeholder="e.g., HTML, CSS, JavaScript, PHP">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="edit_project_url_<?php echo $item['id']; ?>">Project URL</label>
                                <input type="url" class="form-control" id="edit_project_url_<?php echo $item['id']; ?>" name="project_url" value="<?php echo htmlspecialchars($item['project_url']); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="edit_github_url_<?php echo $item['id']; ?>">GitHub URL</label>
                                <input type="url" class="form-control" id="edit_github_url_<?php echo $item['id']; ?>" name="github_url" value="<?php echo htmlspecialchars($item['github_url']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_image_main_<?php echo $item['id']; ?>">New Main Image (optional)</label>
                            <input type="file" class="form-control-file" id="edit_image_main_<?php echo $item['id']; ?>" name="image_main" accept="image/png,image/jpeg,image/webp">
                            <?php if ($item['image_main']): ?>
                                <small class="form-text text-muted">Current: <a href="<?php echo htmlspecialchars(assetUrlAdmin($item['image_main'])); ?>" target="_blank">View Main Image</a></small>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="edit_images_<?php echo $item['id']; ?>">Add/Remove Gallery Images</label>
                            <input type="file" class="form-control-file" id="edit_images_<?php echo $item['id']; ?>" name="images[]" accept="image/png,image/jpeg,image/webp" multiple>
                            <div class="mt-2">
                                <small>Current Gallery Images (check to delete)</small>
                                <div class="row">
                                    <?php 
                                    $gallery_images = json_decode($item['images'] ?? '[]', true);
                                    if (!empty($gallery_images)):
                                        foreach ($gallery_images as $img):
                                            if (empty($img)) continue; ?>
                                            <div class="col-md-3 mt-2">
                                                <div class="img-thumbnail position-relative">
                                                    <img src="<?php echo htmlspecialchars(assetUrlAdmin($img)); ?>" class="img-fluid">
                                                    <div class="position-absolute top-0 right-0 p-1 bg-white" style="line-height: 1;">
                                                        <input type="checkbox" name="delete_images[]" value="<?php echo htmlspecialchars($img); ?>" title="Mark to delete">
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach; 
                                    else: ?>
                                        <p class="col-12 text-muted">No gallery images.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_featured_<?php echo $item['id']; ?>" name="is_featured" value="1" <?php echo $item['is_featured'] ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="edit_is_featured_<?php echo $item['id']; ?>">Featured Project</label>
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="edit_is_active_<?php echo $item['id']; ?>" name="is_active" value="1" <?php echo $item['is_active'] ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="edit_is_active_<?php echo $item['id']; ?>">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_portfolio" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Image Upload Modal -->
    <div class="modal fade" id="imageUploadModal" tabindex="-1" role="dialog" aria-labelledby="imageUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageUploadModalLabel">Upload Image</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="imageUploadForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="imageFile">Choose Image</label>
                            <input type="file" class="form-control-file" id="imageFile" accept="image/*" required>
                        </div>
                        <div class="form-group">
                            <div class="progress d-none">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </form>
                    <div id="uploadPreview" class="text-center mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="uploadImageBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>
    
    <script>
    // Initialize tooltips
    $(function () {
        $('[data-toggle="tooltip"]').tooltip();
        
        // Initialize Summernote for rich text editing
        $('textarea[name="description"], textarea[name^="edit_description"]').summernote({
            height: 200,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['height', ['height']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
        
        // Handle image upload button click
        $('#uploadMainImage').on('click', function() {
            $('#imageUploadModal').modal('show');
            currentImageField = 'image_main';
        });
        
        // Handle upload button click in the modal
        $('#uploadImageBtn').on('click', function() {
            const fileInput = document.getElementById('imageFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select an image to upload');
                return;
            }
            
            const formData = new FormData();
            formData.append('image', file);
            
            // Show progress bar
            const progressBar = $('.progress');
            const progressBarInner = $('.progress-bar');
            progressBar.removeClass('d-none');
            
            // Upload the file
            $.ajax({
                url: 'upload.php', // You'll need to create this file
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            progressBarInner.css('width', percentComplete + '%').attr('aria-valuenow', percentComplete);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            // Set the image URL to the main image field
                            $('#' + currentImageField).val(data.url);
                            $('#uploadPreview').html('<div class="alert alert-success">Image uploaded successfully!</div>');
                            setTimeout(function() {
                                $('#imageUploadModal').modal('hide');
                                $('#uploadPreview').html('');
                                progressBar.addClass('d-none');
                                progressBarInner.css('width', '0%').attr('aria-valuenow', 0);
                                $('#imageUploadForm')[0].reset();
                            }, 1500);
                        } else {
                            $('#uploadPreview').html('<div class="alert alert-danger">' + (data.message || 'Upload failed') + '</div>');
                        }
                    } catch (e) {
                        $('#uploadPreview').html('<div class="alert alert-danger">Error processing upload: ' + e.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#uploadPreview').html('<div class="alert alert-danger">Upload error: ' + error + '</div>');
                }
            });
        });

        // Bootstrap validation for forms
        Array.prototype.slice.call(document.querySelectorAll('form.needs-validation')).forEach(function(form){
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
    
    // Global variable to track which image field is being edited
    let currentImageField = '';
    
    // Function to open image upload for edit modals
    function openImageUpload(fieldId) {
        currentImageField = fieldId;
        $('#imageUploadModal').modal('show');
    }
    
    // Add click handlers for all edit image buttons
    $(document).ready(function() {
        $('[id^=uploadEditImage]').on('click', function() {
            const fieldId = $(this).data('target');
            currentImageField = fieldId;
            $('#imageUploadModal').modal('show');
        });
    });
    </script>
</body>

</html>
