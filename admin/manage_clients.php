<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Define upload directory for client logos
define('UPLOAD_DIR', __DIR__ . '/../uploads/clients/');

// Function to handle file uploads (validate type and size)
function handle_upload($file_input_name, $current_logo_path = null) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_logo_path;
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $max_bytes = 2 * 1024 * 1024; // 2 MB

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    $file_tmp_path = $_FILES[$file_input_name]['tmp_name'];
    $orig_name = $_FILES[$file_input_name]['name'];
    $size = (int)$_FILES[$file_input_name]['size'];
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

    if ($size <= 0 || $size > $max_bytes) {
        throw new RuntimeException('Logo file too large or empty (max 2MB).');
    }
    if (!in_array($ext, $allowed_ext, true)) {
        throw new RuntimeException('Invalid logo file type. Allowed: jpg, jpeg, png, webp.');
    }
    if (!is_uploaded_file($file_tmp_path)) {
        throw new RuntimeException('Invalid upload.');
    }

    // MIME check
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    if ($finfo) {
        $mime = finfo_file($finfo, $file_tmp_path);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed_mimes, true)) {
            throw new RuntimeException('Invalid logo MIME type.');
        }
    }

    $new_file_name = uniqid('client_', true) . '_' . time() . '.' . $ext;
    $dest_path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $new_file_name;

    if (!move_uploaded_file($file_tmp_path, $dest_path)) {
        throw new RuntimeException('Failed to move uploaded logo.');
    }

    // Delete previous file if it exists and is under UPLOAD_DIR
    if ($current_logo_path) {
        $old = $current_logo_path;
        $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
        $oldReal = realpath($old);
        if ($oldReal && $base && strpos($oldReal, $base) === 0 && is_file($oldReal)) {
            @unlink($oldReal);
        }
    }
    // Return relative path for database storage
    return 'uploads/clients/' . $new_file_name;
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

$message = '';
$message_type = '';

// Delete Client
if (isset($_POST['delete_client']) && verify_csrf()) {
    $client_id = $_POST['client_id'];
    try {
        $stmt = $pdo->prepare("SELECT logo FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($client && !empty($client['logo'])) {
            $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
            $target = realpath($client['logo']);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $message = "Client deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting client: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create New Client
if (isset($_POST['create_client']) && verify_csrf()) {
    $name = trim($_POST['name']);
    $website_url = trim($_POST['website_url']);
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $testimonial = isset($_POST['testimonial']) ? $_POST['testimonial'] : '';
    $rating = (isset($_POST['rating']) && $_POST['rating'] !== '') ? (float)$_POST['rating'] : null;
    $sort_order = (isset($_POST['sort_order']) && $_POST['sort_order'] !== '') ? max(0, (int)$_POST['sort_order']) : 0;

    // Basic validation
    if ($name === '') {
        $message = 'Name is required.';
        $message_type = 'danger';
    } elseif ($website_url !== '' && !filter_var($website_url, FILTER_VALIDATE_URL)) {
        $message = 'Website URL is invalid.';
        $message_type = 'danger';
    } elseif ($rating !== null && ($rating < 0 || $rating > 5)) {
        $message = 'Rating must be between 0 and 5.';
        $message_type = 'danger';
    } else {
        try {
            $logo = handle_upload('logo');
            $stmt = $pdo->prepare("INSERT INTO clients (name, logo, website_url, description, testimonial, rating, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $logo, $website_url, $description, $testimonial, $rating, $sort_order]);
            $message = "Client created successfully!";
            $message_type = "success";
        } catch (Throwable $e) {
            $message = "Error creating client: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Update Client
if (isset($_POST['update_client']) && verify_csrf()) {
    $client_id = (int)$_POST['client_id'];
    $name = trim($_POST['name']);
    $website_url = trim($_POST['website_url']);
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $testimonial = isset($_POST['testimonial']) ? $_POST['testimonial'] : '';
    $rating = (isset($_POST['rating']) && $_POST['rating'] !== '') ? (float)$_POST['rating'] : null;
    $sort_order = (isset($_POST['sort_order']) && $_POST['sort_order'] !== '') ? max(0, (int)$_POST['sort_order']) : 0;

    if ($name === '') {
        $message = 'Name is required.';
        $message_type = 'danger';
    } elseif ($website_url !== '' && !filter_var($website_url, FILTER_VALIDATE_URL)) {
        $message = 'Website URL is invalid.';
        $message_type = 'danger';
    } elseif ($rating !== null && ($rating < 0 || $rating > 5)) {
        $message = 'Rating must be between 0 and 5.';
        $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT logo FROM clients WHERE id = ?");
            $stmt->execute([$client_id]);
            $current_client = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_logo_path = $current_client ? $current_client['logo'] : null;

            $logo = handle_upload('logo', $current_logo_path);

            $stmt = $pdo->prepare("UPDATE clients SET name = ?, logo = ?, website_url = ?, description = ?, testimonial = ?, rating = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$name, $logo, $website_url, $description, $testimonial, $rating, $sort_order, $client_id]);
            $message = "Client updated successfully!";
            $message_type = "success";
        } catch (Throwable $e) {
            $message = "Error updating client: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Search and Pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$allowed_limits = [10, 25, 50, 100];
if (!in_array($limit, $allowed_limits, true)) {
    $limit = 10;
}

$where_clause = "";
$params = [];
if (!empty($search)) {
    $where_clause = "WHERE name LIKE ? OR website_url LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
}

$count_sql = "SELECT COUNT(*) as total FROM clients $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit));

// Clamp page and compute offset
if ($page < 1) { $page = 1; }
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $limit;

// For UI text like "Showing X to Y of Z entries"
$showing_start = $total_records > 0 ? ($offset + 1) : 0;
$showing_end = min($offset + $limit, $total_records);

$sql = "SELECT * FROM clients $where_clause ORDER BY sort_order ASC, name ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Clients</h1>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addClientModal"><i class="fas fa-plus fa-sm text-white-50"></i> Add New Client</button>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Clients List Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Clients List (<?php echo (int)$total_records; ?> total)</h6>
                            <!-- Right aligned quick search could go here if desired -->
                        </div>
                        <div class="card-body">
                            <!-- Top toolbar: Show entries (left) and Search (right) -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
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
                                <form method="GET" action="" class="form-inline m-0">
                                    <input type="hidden" name="page" value="1">
                                    <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                                    <label class="mb-0 mr-2">Search:</label>
                                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                </form>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="clientsTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Logo</th>
                                            <th>Name</th>
                                            <th>Website</th>
                                            <th>Rating</th>
                                            <th>Sort Order</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td><img src="<?php echo htmlspecialchars(assetUrlAdmin(!empty($client['logo']) ? $client['logo'] : 'assets/img/placeholder.png')); ?>" alt="<?php echo htmlspecialchars($client['name']); ?>" width="50"></td>
                                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                                            <td><a href="<?php echo htmlspecialchars($client['website_url']); ?>" target="_blank"><?php echo htmlspecialchars($client['website_url']); ?></a></td>
                                            <td><?php echo htmlspecialchars($client['rating']); ?></td>
                                            <td><?php echo htmlspecialchars($client['sort_order']); ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#viewClientModal<?php echo $client['id']; ?>"><i class="fas fa-eye"></i></button>
                                                <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editClientModal<?php echo $client['id']; ?>"><i class="fas fa-edit"></i></button>
                                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this client?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                    <button type="submit" name="delete_client" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    Showing <?php echo (int)$showing_start; ?> to <?php echo (int)$showing_end; ?> of <?php echo (int)$total_records; ?> entries
                                </div>
                                <!-- Pagination -->
                                <nav>
                                    <ul class="pagination mb-0">
                                        <?php
                                        $prev = max(1, $page - 1);
                                        $next = min($total_pages, $page + 1);
                                        ?>
                                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $prev; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>">Previous</a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $next; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo (int)$limit; ?>">Next</a>
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

    <!-- Add Client Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Client</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" required>
                            <div class="invalid-feedback">Name is required.</div>
                        </div>
                        <div class="form-group">
                            <label>Logo</label>
                            <input type="file" name="logo" class="form-control-file" accept="image/png,image/jpeg,image/webp">
                            <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
                        </div>
                        <div class="form-group">
                            <label>Website URL</label>
                            <input type="url" name="website_url" class="form-control">
                            <div class="invalid-feedback">Please enter a valid URL (or leave blank).</div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control summernote"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Testimonial</label>
                            <textarea name="testimonial" class="form-control summernote"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Rating</label>
                            <input type="number" name="rating" class="form-control" step="0.1" min="0" max="5">
                            <div class="invalid-feedback">Rating must be between 0 and 5.</div>
                        </div>
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <button type="submit" name="create_client" class="btn btn-primary">Create Client</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($clients as $client): ?>
    <!-- Edit Client Modal -->
    <div class="modal fade" id="editClientModal<?php echo $client['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Client</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            <div class="invalid-feedback">Name is required.</div>
                        </div>
                        <div class="form-group">
                            <label>Current Logo</label>
                            <div><img src="<?php echo htmlspecialchars(assetUrlAdmin(!empty($client['logo']) ? $client['logo'] : 'assets/img/placeholder.png')); ?>" width="100"></div>
                            <label class="mt-2">New Logo</label>
                            <input type="file" name="logo" class="form-control-file" accept="image/png,image/jpeg,image/webp">
                            <small class="form-text text-muted">Allowed: JPG, PNG, WEBP. Max 2MB.</small>
                        </div>
                        <div class="form-group">
                            <label>Website URL</label>
                            <input type="url" name="website_url" class="form-control" value="<?php echo htmlspecialchars($client['website_url']); ?>">
                            <div class="invalid-feedback">Please enter a valid URL (or leave blank).</div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control summernote"><?php echo htmlspecialchars($client['description']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Testimonial</label>
                            <textarea name="testimonial" class="form-control summernote"><?php echo htmlspecialchars($client['testimonial']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Rating</label>
                            <input type="number" name="rating" class="form-control" step="0.1" min="0" max="5" value="<?php echo htmlspecialchars($client['rating']); ?>">
                            <div class="invalid-feedback">Rating must be between 0 and 5.</div>
                        </div>
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0" value="<?php echo htmlspecialchars($client['sort_order']); ?>">
                        </div>
                        <button type="submit" name="update_client" class="btn btn-primary">Update Client</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Client Modal -->
    <div class="modal fade" id="viewClientModal<?php echo $client['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Client</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <img src="<?php echo htmlspecialchars(assetUrlAdmin(!empty($client['logo']) ? $client['logo'] : 'assets/img/placeholder.png')); ?>" alt="<?php echo htmlspecialchars($client['name']); ?>" style="max-height: 120px; width: auto;" />
                    </div>
                    <h5><?php echo htmlspecialchars($client['name']); ?></h5>
                    <p><strong>Website:</strong> <a href="<?php echo htmlspecialchars($client['website_url']); ?>" target="_blank"><?php echo htmlspecialchars($client['website_url']); ?></a></p>
                    <p><strong>Rating:</strong> <?php echo htmlspecialchars($client['rating']); ?></p>
                    <div><strong>Description:</strong> <?php echo $client['description']; ?></div>
                    <hr>
                    <div><strong>Testimonial:</strong> <?php echo $client['testimonial']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php require_once 'includes/scripts.php'; ?>
    <script>
    $(document).ready(function() {
        $('.summernote').summernote({
            height: 150,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']]
            ]
        });

        // Bootstrap validation
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