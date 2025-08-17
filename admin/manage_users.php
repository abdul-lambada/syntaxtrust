<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Define upload directory
define('UPLOAD_DIR', 'uploads/users/');

// Function to handle file uploads
function handle_upload($file_input_name, $current_image_path = null) {
    // No new upload
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_image_path;
    }

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

    $new_file_name = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $dest_path = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $new_file_name;

    if (!move_uploaded_file($tmp, $dest_path)) {
        return $current_image_path;
    }

    // Safely delete previous file within UPLOAD_DIR
    if ($current_image_path) {
        $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
        $target = realpath($current_image_path);
        if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
            @unlink($target);
        }
    }

    return $dest_path;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle CRUD operations
$message = '';
$message_type = '';

// Delete user
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    try {
        // Prevent deleting self already enforced in SQL; fetch image path first
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $image_to_delete = $row ? $row['profile_image'] : null;

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        $stmt->execute([$user_id, $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0 && $image_to_delete) {
            $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
            $target = realpath($image_to_delete);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }

        $message = "User deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting user: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Toggle user status
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$user_id]);
        $message = "User status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating user status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create new user
if (isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $user_type = $_POST['user_type'];
    $profile_image = handle_upload('profile_image');
    $bio = $_POST['bio'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, user_type, profile_image, bio, is_active, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $full_name, $phone, $user_type, $profile_image, $bio, $is_active, $email_verified]);
        $message = "User created successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error creating user: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update user
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];

    // Fetch current image path
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_image = $current_user ? $current_user['profile_image'] : null;

    $username = $_POST['username'];
    $email = $_POST['email'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $user_type = $_POST['user_type'];
    $profile_image = handle_upload('profile_image', $current_image);
    $bio = $_POST['bio'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $email_verified = isset($_POST['email_verified']) ? 1 : 0;
    
    // Update password only if provided
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, full_name = ?, phone = ?, user_type = ?, profile_image = ?, bio = ?, is_active = ?, email_verified = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$username, $email, $password, $full_name, $phone, $user_type, $profile_image, $bio, $is_active, $email_verified, $user_id]);
        } catch (PDOException $e) {
            $message = "Error updating user: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, user_type = ?, profile_image = ?, bio = ?, is_active = ?, email_verified = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$username, $email, $full_name, $phone, $user_type, $profile_image, $bio, $is_active, $email_verified, $user_id]);
        } catch (PDOException $e) {
            $message = "Error updating user: " . $e->getMessage();
            $message_type = "danger";
        }
    }
    
    if ($message_type !== 'danger') {
        $message = "User updated successfully!";
        $message_type = "success";
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
    $where_clause = "WHERE username LIKE ? OR email LIKE ? OR full_name LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get users with pagination
$sql = "SELECT * FROM users $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Users</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addUserModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New User
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
                            <h6 class="m-0 font-weight-bold text-primary">Search Users</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by username, email, or name..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="manage_users.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Users List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Profile</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Full Name</th>
                                            <th>User Type</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($user['profile_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle" width="40" height="40">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                            <i class="fas fa-user"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $user['user_type'] === 'admin' ? 'danger' : ($user['user_type'] === 'bisnis' ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst($user['user_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewUserModal<?php echo $user['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editUserModal<?php echo $user['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to toggle this user\'s status?')">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" name="toggle_status" class="btn btn-sm btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>">
                                                                    <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
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

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="manage_users.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="password">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="form-text text-muted">Minimum 8 characters</small>
                                </div>
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="user_type">User Type *</label>
                                    <select class="form-control" id="user_type" name="user_type" required>
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                        <option value="bisnis">Bisnis</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                                <div class="form-group">
                                    <label for="profile_image">Profile Image</label>
                                    <input type="file" class="form-control-file" id="profile_image" name="profile_image" accept="image/*">
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_verified" name="email_verified">
                                        <label class="form-check-label" for="email_verified">Email Verified</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($users as $user): ?>
    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">View User: <?php echo htmlspecialchars($user['username']); ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-3 text-center">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="img-fluid rounded-circle mb-3" style="max-width: 150px;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 150px; height: 150px; font-size: 60px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <h5 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                            <span class="badge badge-<?php echo $user['user_type'] === 'admin' ? 'danger' : ($user['user_type'] === 'bisnis' ? 'warning' : 'info'); ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                            <div class="mt-2">
                                <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                                <?php if ($user['email_verified']): ?>
                                    <span class="badge badge-success">Email Verified</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%;">Username:</th>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Member Since:</th>
                                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Updated:</th>
                                        <td><?php echo !empty($user['updated_at']) ? date('d M Y H:i', strtotime($user['updated_at'])) : 'Never'; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <?php if (!empty($user['bio'])): ?>
                                <div class="mt-3">
                                    <h6>Bio:</h6>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
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
    <?php endforeach; ?>

    <?php foreach ($users as $user): ?>
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="manage_users.php" enctype="multipart/form-data">
                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_username_<?php echo $user['id']; ?>">Username *</label>
                                    <input type="text" class="form-control" id="edit_username_<?php echo $user['id']; ?>" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_email_<?php echo $user['id']; ?>">Email *</label>
                                    <input type="email" class="form-control" id="edit_email_<?php echo $user['id']; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="edit_password_<?php echo $user['id']; ?>">New Password</label>
                                    <input type="password" class="form-control" id="edit_password_<?php echo $user['id']; ?>" name="password">
                                    <small class="form-text text-muted">Leave blank to keep current password</small>
                                </div>
                                <div class="form-group">
                                    <label for="edit_full_name_<?php echo $user['id']; ?>">Full Name *</label>
                                    <input type="text" class="form-control" id="edit_full_name_<?php echo $user['id']; ?>" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_user_type_<?php echo $user['id']; ?>">User Type *</label>
                                    <select class="form-control" id="edit_user_type_<?php echo $user['id']; ?>" name="user_type" required>
                                        <option value="user" <?php echo $user['user_type'] === 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo $user['user_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="bisnis" <?php echo $user['user_type'] === 'bisnis' ? 'selected' : ''; ?>>Bisnis</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="edit_phone_<?php echo $user['id']; ?>">Phone</label>
                                    <input type="tel" class="form-control" id="edit_phone_<?php echo $user['id']; ?>" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="edit_profile_image_<?php echo $user['id']; ?>">New Profile Image (optional)</label>
                                    <input type="file" class="form-control-file" id="edit_profile_image_<?php echo $user['id']; ?>" name="profile_image" accept="image/*">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <div class="mt-2">
                                            <small>Current Image:</small><br>
                                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Current Profile Image" style="max-width: 100px; height: auto; border-radius: 50%;">
                                            <a href="<?php echo htmlspecialchars($user['profile_image']); ?>" target="_blank" class="ml-2">View</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_is_active_<?php echo $user['id']; ?>" name="is_active" <?php echo $user['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="edit_is_active_<?php echo $user['id']; ?>">Active</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_email_verified_<?php echo $user['id']; ?>" name="email_verified" <?php echo $user['email_verified'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="edit_email_verified_<?php echo $user['id']; ?>">Email Verified</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="edit_bio_<?php echo $user['id']; ?>">Bio</label>
                            <textarea class="form-control" id="edit_bio_<?php echo $user['id']; ?>" name="bio" rows="3"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-warning">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

</body>

</html>
