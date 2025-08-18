<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Upload directory for testimonial images
define('TESTI_UPLOAD_DIR', 'uploads/testimonials/');

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/login.php');
    exit();
}

// Handle image upload
function upload_testimonial_image($input_name, $current_path = null) {
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_path;
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $max_bytes = 2 * 1024 * 1024; // 2MB

    if (!is_dir(TESTI_UPLOAD_DIR)) {
        mkdir(TESTI_UPLOAD_DIR, 0777, true);
    }

    $tmp = $_FILES[$input_name]['tmp_name'];
    $name = $_FILES[$input_name]['name'];
    $size = (int)$_FILES[$input_name]['size'];
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

    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($f, $tmp);
        finfo_close($f);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed_mimes, true)) {
            throw new RuntimeException('Invalid image MIME type.');
        }
    }

    $new_name = uniqid('testimonial_', true) . '_' . time() . '.' . $ext;
    $dest = rtrim(TESTI_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $new_name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Failed to move uploaded image.');
    }

    // delete old if within upload dir
    if ($current_path) {
        $base = realpath(rtrim(TESTI_UPLOAD_DIR, '/\\'));
        $old = realpath($current_path);
        if ($base && $old && strpos($old, $base) === 0 && is_file($old)) {
            @unlink($old);
        }
    }
    return $dest;
}

$message = '';
$message_type = '';

// Create
if (isset($_POST['create_testimonial']) && verify_csrf()) {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_position = trim($_POST['client_position'] ?? '');
    $client_company = trim($_POST['client_company'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $rating = ($_POST['rating'] !== '' ? (float)$_POST['rating'] : null);
    $project_name = trim($_POST['project_name'] ?? '');
    $service_id = ($_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = ($_POST['sort_order'] !== '' ? max(0, (int)$_POST['sort_order']) : 0);

    if ($client_name === '' || $content === '') {
        $message = 'Client name and content are required.';
        $message_type = 'danger';
    } elseif ($rating !== null && ($rating < 0 || $rating > 5)) {
        $message = 'Rating must be between 0 and 5.';
        $message_type = 'danger';
    } else {
        try {
            $client_image = upload_testimonial_image('client_image');
            $stmt = $pdo->prepare("INSERT INTO testimonials (client_name, client_position, client_company, client_image, content, rating, project_name, service_id, is_featured, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$client_name, $client_position, $client_company, $client_image, $content, $rating, $project_name, $service_id, $is_featured, $is_active, $sort_order]);
            $message = 'Testimonial created successfully!';
            $message_type = 'success';
        } catch (Throwable $e) {
            $message = 'Error creating testimonial: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Update
if (isset($_POST['update_testimonial']) && verify_csrf()) {
    $id = (int)($_POST['testimonial_id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $client_position = trim($_POST['client_position'] ?? '');
    $client_company = trim($_POST['client_company'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $rating = ($_POST['rating'] !== '' ? (float)$_POST['rating'] : null);
    $project_name = trim($_POST['project_name'] ?? '');
    $service_id = ($_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = ($_POST['sort_order'] !== '' ? max(0, (int)$_POST['sort_order']) : 0);

    if ($client_name === '' || $content === '') {
        $message = 'Client name and content are required.';
        $message_type = 'danger';
    } elseif ($rating !== null && ($rating < 0 || $rating > 5)) {
        $message = 'Rating must be between 0 and 5.';
        $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT client_image FROM testimonials WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_image = $row['client_image'] ?? null;

            $client_image = upload_testimonial_image('client_image', $current_image);

            $stmt = $pdo->prepare('UPDATE testimonials SET client_name=?, client_position=?, client_company=?, client_image=?, content=?, rating=?, project_name=?, service_id=?, is_featured=?, is_active=?, sort_order=? WHERE id=?');
            $stmt->execute([$client_name, $client_position, $client_company, $client_image, $content, $rating, $project_name, $service_id, $is_featured, $is_active, $sort_order, $id]);
            $message = 'Testimonial updated successfully!';
            $message_type = 'success';
        } catch (Throwable $e) {
            $message = 'Error updating testimonial: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Delete
if (isset($_POST['delete_testimonial']) && verify_csrf()) {
    $id = (int)($_POST['testimonial_id'] ?? 0);
    try {
        $stmt = $pdo->prepare('SELECT client_image FROM testimonials WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['client_image'])) {
            $base = realpath(rtrim(TESTI_UPLOAD_DIR, '/\\'));
            $target = realpath($row['client_image']);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }
        $stmt = $pdo->prepare('DELETE FROM testimonials WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Testimonial deleted successfully!';
        $message_type = 'success';
    } catch (Throwable $e) {
        $message = 'Error deleting testimonial: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Toggle active/featured
if (isset($_POST['toggle_active']) && verify_csrf()) {
    $id = (int)$_POST['testimonial_id'];
    $to = (int)$_POST['to'];
    $stmt = $pdo->prepare('UPDATE testimonials SET is_active=? WHERE id=?');
    $stmt->execute([$to, $id]);
}
if (isset($_POST['toggle_featured']) && verify_csrf()) {
    $id = (int)$_POST['testimonial_id'];
    $to = (int)$_POST['to'];
    $stmt = $pdo->prepare('UPDATE testimonials SET is_featured=? WHERE id=?');
    $stmt->execute([$to, $id]);
}

// Filters & pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_active = isset($_GET['active']) && $_GET['active'] !== '' ? (int)$_GET['active'] : '';
$filter_featured = isset($_GET['featured']) && $_GET['featured'] !== '' ? (int)$_GET['featured'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$allowed_limits = [10, 25, 50, 100];
if (!in_array($limit, $allowed_limits, true)) { $limit = 10; }

$w = [];
$params = [];
if ($search !== '') {
    $w[] = '(client_name LIKE ? OR client_company LIKE ? OR content LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($filter_active !== '') {
    $w[] = 'is_active = ?';
    $params[] = $filter_active;
}
if ($filter_featured !== '') {
    $w[] = 'is_featured = ?';
    $params[] = $filter_featured;
}
$where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM testimonials $where");
$stmt->execute($params);
$total_records = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;
$showing_start = $total_records > 0 ? ($offset + 1) : 0;
$showing_end = min($offset + $limit, $total_records);

$sql = "SELECT * FROM testimonials $where ORDER BY sort_order ASC, id ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Testimonials</h1>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addTestimonialModal"><i class="fas fa-plus fa-sm text-white-50"></i> Add New Testimonial</button>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Testimonials List (<?php echo (int)$total_records; ?> total)</h6>
                            <form method="GET" action="" class="form-inline m-0">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <input type="hidden" name="page" value="1">
                                <label class="mr-2 mb-0">Show</label>
                                <select name="limit" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <?php foreach ($allowed_limits as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($limit === $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="ml-2">entries</span>
                            </form>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="" class="form-inline mb-3">
                                <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                                <label class="mb-0 mr-2">Search:</label>
                                <input type="text" name="search" class="form-control form-control-sm mr-3" placeholder="Name/Company/Content" value="<?php echo htmlspecialchars($search); ?>">
                                <label class="mb-0 mr-2">Active:</label>
                                <select name="active" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="1" <?php echo ($filter_active === 1 ? 'selected' : ''); ?>>Active</option>
                                    <option value="0" <?php echo ($filter_active === 0 ? 'selected' : ''); ?>>Inactive</option>
                                </select>
                                <label class="mb-0 mr-2">Featured:</label>
                                <select name="featured" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="1" <?php echo ($filter_featured === 1 ? 'selected' : ''); ?>>Featured</option>
                                    <option value="0" <?php echo ($filter_featured === 0 ? 'selected' : ''); ?>>Not Featured</option>
                                </select>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Client</th>
                                            <th>Company</th>
                                            <th>Rating</th>
                                            <th>Featured</th>
                                            <th>Active</th>
                                            <th>Sort</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($testimonials): foreach ($testimonials as $t): ?>
                                        <tr>
                                            <td style="width:70px"><img src="<?php echo !empty($t['client_image']) ? htmlspecialchars($t['client_image']) : 'assets/img/placeholder.png'; ?>" alt="img" width="60"></td>
                                            <td>
                                                <div class="font-weight-bold"><?php echo htmlspecialchars($t['client_name']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars($t['client_position'] ?? ''); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($t['client_company'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($t['rating'] ?? ''); ?></td>
                                            <td>
                                                <form method="POST" action="" style="display:inline-block;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="testimonial_id" value="<?php echo (int)$t['id']; ?>">
                                                    <input type="hidden" name="to" value="<?php echo (int)!$t['is_featured']; ?>">
                                                    <button class="btn btn-sm <?php echo $t['is_featured'] ? 'btn-success' : 'btn-outline-secondary'; ?>" name="toggle_featured" title="Toggle Featured">
                                                        <i class="fas fa-star"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="POST" action="" style="display:inline-block;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="testimonial_id" value="<?php echo (int)$t['id']; ?>">
                                                    <input type="hidden" name="to" value="<?php echo (int)!$t['is_active']; ?>">
                                                    <button class="btn btn-sm <?php echo $t['is_active'] ? 'btn-success' : 'btn-outline-secondary'; ?>" name="toggle_active" title="Toggle Active">
                                                        <i class="fas fa-power-off"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td><?php echo (int)$t['sort_order']; ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#viewTestimonialModal<?php echo (int)$t['id']; ?>"><i class="fas fa-eye"></i></button>
                                                <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editTestimonialModal<?php echo (int)$t['id']; ?>"><i class="fas fa-edit"></i></button>
                                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Delete this testimonial?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="testimonial_id" value="<?php echo (int)$t['id']; ?>">
                                                    <button type="submit" name="delete_testimonial" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="8" class="text-center text-muted">No testimonials found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    Showing <?php echo (int)$showing_start; ?> to <?php echo (int)$showing_end; ?> of <?php echo (int)$total_records; ?> entries
                                </div>
                                <nav>
                                    <ul class="pagination mb-0">
                                        <?php $prev = max(1, $page-1); $next = min($total_pages, $page+1); ?>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $prev; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>&featured=<?php echo urlencode((string)$filter_featured); ?>">Previous</a>
                                        </li>
                                        <?php for ($i=1;$i<=$total_pages;$i++): ?>
                                        <li class="page-item <?php echo ($i==$page)?'active':''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>&featured=<?php echo urlencode((string)$filter_featured); ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $next; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>&active=<?php echo urlencode((string)$filter_active); ?>&featured=<?php echo urlencode((string)$filter_featured); ?>">Next</a>
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

    <!-- Add Testimonial Modal -->
    <div class="modal fade" id="addTestimonialModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Testimonial</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Client Name</label>
                                <input type="text" name="client_name" class="form-control" required>
                                <div class="invalid-feedback">Client name is required.</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Client Position</label>
                                <input type="text" name="client_position" class="form-control">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Client Company</label>
                                <input type="text" name="client_company" class="form-control">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Client Image</label>
                                <input type="file" name="client_image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
                                <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" class="form-control summernote" required></textarea>
                            <div class="invalid-feedback">Content is required.</div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Rating</label>
                                <input type="number" name="rating" class="form-control" step="0.1" min="0" max="5">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="0" min="0">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="d-block">Featured</label>
                                <div class="form-check"><input type="checkbox" name="is_featured" class="form-check-input" id="add_is_featured"><label class="form-check-label" for="add_is_featured">Featured</label></div>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="d-block">Active</label>
                                <div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="add_is_active" checked><label class="form-check-label" for="add_is_active">Active</label></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Project Name</label>
                                <input type="text" name="project_name" class="form-control">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Service ID (optional)</label>
                                <input type="number" name="service_id" class="form-control" min="0">
                            </div>
                        </div>
                        <button type="submit" name="create_testimonial" class="btn btn-primary">Create Testimonial</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($testimonials)): foreach ($testimonials as $t): ?>
    <!-- Edit Modal -->
    <div class="modal fade" id="editTestimonialModal<?php echo (int)$t['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Testimonial</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="testimonial_id" value="<?php echo (int)$t['id']; ?>">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Client Name</label>
                                <input type="text" name="client_name" class="form-control" value="<?php echo htmlspecialchars($t['client_name']); ?>" required>
                                <div class="invalid-feedback">Client name is required.</div>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Client Position</label>
                                <input type="text" name="client_position" class="form-control" value="<?php echo htmlspecialchars($t['client_position']); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Client Company</label>
                                <input type="text" name="client_company" class="form-control" value="<?php echo htmlspecialchars($t['client_company']); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Current Image</label>
                                <div><img src="<?php echo !empty($t['client_image']) ? htmlspecialchars($t['client_image']) : 'assets/img/placeholder.png'; ?>" width="100"></div>
                                <label class="mt-2">New Image</label>
                                <input type="file" name="client_image" class="form-control-file" accept="image/png,image/jpeg,image/webp">
                                <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Content</label>
                            <textarea name="content" class="form-control summernote" required><?php echo htmlspecialchars($t['content']); ?></textarea>
                            <div class="invalid-feedback">Content is required.</div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Rating</label>
                                <input type="number" name="rating" class="form-control" step="0.1" min="0" max="5" value="<?php echo htmlspecialchars($t['rating']); ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" min="0" value="<?php echo (int)$t['sort_order']; ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label class="d-block">Featured</label>
                                <div class="form-check"><input type="checkbox" name="is_featured" class="form-check-input" id="edit_is_featured_<?php echo (int)$t['id']; ?>" <?php echo $t['is_featured'] ? 'checked' : ''; ?>><label class="form-check-label" for="edit_is_featured_<?php echo (int)$t['id']; ?>">Featured</label></div>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="d-block">Active</label>
                                <div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" id="edit_is_active_<?php echo (int)$t['id']; ?>" <?php echo $t['is_active'] ? 'checked' : ''; ?>><label class="form-check-label" for="edit_is_active_<?php echo (int)$t['id']; ?>">Active</label></div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Project Name</label>
                                <input type="text" name="project_name" class="form-control" value="<?php echo htmlspecialchars($t['project_name']); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Service ID (optional)</label>
                                <input type="number" name="service_id" class="form-control" min="0" value="<?php echo htmlspecialchars($t['service_id']); ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_testimonial" class="btn btn-primary">Update Testimonial</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewTestimonialModal<?php echo (int)$t['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">View Testimonial</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <img src="<?php echo !empty($t['client_image']) ? htmlspecialchars($t['client_image']) : 'assets/img/placeholder.png'; ?>" alt="<?php echo htmlspecialchars($t['client_name']); ?>" style="max-height: 120px; width: auto;" />
                    </div>
                    <h5><?php echo htmlspecialchars($t['client_name']); ?></h5>
                    <p class="mb-1"><strong>Position:</strong> <?php echo htmlspecialchars($t['client_position']); ?></p>
                    <p class="mb-1"><strong>Company:</strong> <?php echo htmlspecialchars($t['client_company']); ?></p>
                    <p class="mb-1"><strong>Rating:</strong> <?php echo htmlspecialchars($t['rating']); ?></p>
                    <p class="mb-1"><strong>Featured:</strong> <?php echo $t['is_featured'] ? 'Yes' : 'No'; ?></p>
                    <p class="mb-1"><strong>Active:</strong> <?php echo $t['is_active'] ? 'Yes' : 'No'; ?></p>
                    <p class="mb-1"><strong>Project:</strong> <?php echo htmlspecialchars($t['project_name']); ?></p>
                    <p class="mb-3"><strong>Service ID:</strong> <?php echo htmlspecialchars($t['service_id']); ?></p>
                    <div><strong>Content:</strong> <?php echo $t['content']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>

    <?php require_once 'includes/scripts.php'; ?>
    <script>
    $(document).ready(function(){
        $('.summernote').summernote({
            height: 150,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']]
            ]
        });

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
    </script>
</body>
</html>
