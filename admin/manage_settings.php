<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/upload.php';

// Define upload directory
define('UPLOAD_DIR', 'uploads/settings/');

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Default: CSRF OK; if POST and invalid, mark false
$csrf_ok = true;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $csrf_ok = isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Function to handle file uploads
function handle_upload($file_input_name, $current_image_path = null) {
    return secure_upload(
        $file_input_name,
        UPLOAD_DIR,
        [
            'maxBytes' => 2 * 1024 * 1024,
            'allowedExt' => ['jpg','jpeg','png','webp','gif'],
            'allowedMime' => ['image/jpeg','image/png','image/webp','image/gif'],
            'prefix' => 'setting_'
        ],
        $current_image_path
    );
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle CRUD operations
$message = '';
$message_type = '';
// CSRF failure feedback
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$csrf_ok) {
    $message = 'Invalid CSRF token. Please refresh the page and try again.';
    $message_type = 'danger';
}

// Update setting
if ($csrf_ok && isset($_POST['update_setting']) && isset($_POST['setting_id'])) {
    $setting_id = $_POST['setting_id'];

    // Fetch the setting to check its type
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE id = ?");
    $stmt->execute([$setting_id]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($setting && $setting['setting_type'] === 'image') {
        $setting_value = handle_upload('setting_value', $setting['setting_value']);
    } else {
        $setting_value = $_POST['setting_value'];
    }
    try {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE id = ?");
        $stmt->execute([$setting_value, $setting_id]);
        $message = "Setting updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating setting: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Add new setting
if ($csrf_ok && isset($_POST['add_setting']) && isset($_POST['setting_key'])) {
    $setting_key = $_POST['setting_key'];
    $setting_type = $_POST['setting_type'] ?? 'text';
    $description = $_POST['description'] ?? '';
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Determine value based on type
    if ($setting_type === 'image') {
        // Accept file from input name 'setting_value'
        $setting_value = handle_upload('setting_value', null) ?: '';
    } else {
        $setting_value = $_POST['setting_value'] ?? '';
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$setting_key, $setting_value, $setting_type, $description, $is_public]);
        $message = "Setting added successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error adding setting: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Delete setting
if ($csrf_ok && isset($_POST['delete_setting']) && isset($_POST['setting_id'])) {
    $setting_id = $_POST['setting_id'];
    try {
        // Fetch first to check if it's an image and get the path
        $stmt = $pdo->prepare("SELECT setting_type, setting_value FROM settings WHERE id = ?");
        $stmt->execute([$setting_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $image_to_delete = null;
        if ($row && $row['setting_type'] === 'image') {
            $image_to_delete = $row['setting_value'];
        }

        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM settings WHERE id = ?");
        $stmt->execute([$setting_id]);

        // Safely delete file if applicable
        if ($stmt->rowCount() > 0 && $image_to_delete) {
            $base = realpath(rtrim(UPLOAD_DIR, '/\\'));
            $target = realpath($image_to_delete);
            if ($base && $target && strpos($target, $base) === 0 && is_file($target)) {
                @unlink($target);
            }
        }

        $message = "Setting deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting setting: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Seed recommended settings (hero keys, canonical company/site/social keys, analytics placeholders)
if ($csrf_ok && isset($_POST['seed_recommended'])) {
    $recommended = [
        // Hero keys
        ['key' => 'hero_students_count', 'value' => '200+', 'type' => 'text', 'desc' => 'Jumlah siswa/mahasiswa yang dilayani (teks tampilan).', 'public' => 1],
        ['key' => 'hero_businesses_count', 'value' => '80+', 'type' => 'text', 'desc' => 'Jumlah bisnis/UMKM yang dibantu (teks tampilan).', 'public' => 1],
        ['key' => 'hero_price_text', 'value' => 'Mulai Rp 299K', 'type' => 'text', 'desc' => 'Teks harga pada hero.', 'public' => 1],
        ['key' => 'hero_delivery_time', 'value' => '3-7 Hari Selesai', 'type' => 'text', 'desc' => 'Estimasi waktu pengerjaan pada hero.', 'public' => 1],

        // Company/site canonical keys
        ['key' => 'company_name', 'value' => 'SyntaxTrust', 'type' => 'text', 'desc' => 'Nama perusahaan yang ditampilkan di UI.', 'public' => 1],
        ['key' => 'company_address', 'value' => '', 'type' => 'textarea', 'desc' => 'Alamat perusahaan untuk footer/kontak.', 'public' => 1],
        ['key' => 'company_email', 'value' => '', 'type' => 'text', 'desc' => 'Email kontak perusahaan.', 'public' => 1],
        ['key' => 'company_phone', 'value' => '', 'type' => 'text', 'desc' => 'Nomor telepon/WA perusahaan.', 'public' => 1],
        ['key' => 'site_url', 'value' => 'http://localhost/company_profile_syntaxtrust', 'type' => 'text', 'desc' => 'Base URL situs untuk canonical/og:url/sitemap.', 'public' => 1],
        ['key' => 'site_logo_url', 'value' => '', 'type' => 'text', 'desc' => 'URL logo situs untuk header/SEO.', 'public' => 1],
        ['key' => 'site_description', 'value' => 'Jasa pembuatan website profesional, cepat, dan terjangkau.', 'type' => 'textarea', 'desc' => 'Deskripsi singkat situs untuk SEO.', 'public' => 1],

        // Social links
        ['key' => 'social_facebook', 'value' => '', 'type' => 'text', 'desc' => 'URL Facebook resmi.', 'public' => 1],
        ['key' => 'social_twitter', 'value' => '', 'type' => 'text', 'desc' => 'URL Twitter/X resmi.', 'public' => 1],
        ['key' => 'social_instagram', 'value' => '', 'type' => 'text', 'desc' => 'URL Instagram resmi.', 'public' => 1],
        ['key' => 'social_linkedin', 'value' => '', 'type' => 'text', 'desc' => 'URL LinkedIn resmi.', 'public' => 1],

        // Analytics toggles/placeholders
        ['key' => 'analytics_script_url', 'value' => '', 'type' => 'text', 'desc' => 'URL script analytics (contoh: Plausible/GA).', 'public' => 0],
        ['key' => 'analytics_script_inline', 'value' => '', 'type' => 'textarea', 'desc' => 'Script analytics inline (opsional).', 'public' => 0],
    ];

    try {
        foreach ($recommended as $row) {
            // Check if exists by setting_key
            $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$row['key']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update value/type/desc/public to ensure consistency
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ?, setting_type = ?, description = ?, is_public = ? WHERE id = ?");
                $stmt->execute([$row['value'], $row['type'], $row['desc'], $row['public'], $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description, is_public) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$row['key'], $row['value'], $row['type'], $row['desc'], $row['public']]);
            }
        }
        $message = "Recommended settings seeded successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error seeding settings: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Search and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(setting_key LIKE ? OR setting_value LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($type_filter)) {
    $where_conditions[] = "setting_type = ?";
    $params[] = $type_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM settings $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get settings with pagination
$sql = "SELECT * FROM settings $where_clause ORDER BY setting_key ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Settings</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addSettingModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Setting
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
                            <h6 class="m-0 font-weight-bold text-primary">Search and Filter Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by key, value, description..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group mx-sm-3 mb-2">
                                    <select class="form-control" name="type">
                                        <option value="">All Types</option>
                                        <option value="text" <?php echo $type_filter === 'text' ? 'selected' : ''; ?>>Text</option>
                                        <option value="number" <?php echo $type_filter === 'number' ? 'selected' : ''; ?>>Number</option>
                                        <option value="boolean" <?php echo $type_filter === 'boolean' ? 'selected' : ''; ?>>Boolean</option>
                                        <option value="json" <?php echo $type_filter === 'json' ? 'selected' : ''; ?>>JSON</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search) || !empty($type_filter)): ?>
                                    <a href="manage_settings.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Seed Recommended Settings -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-3 text-muted">Seed recommended settings (hero stats, company/site info, social links, analytics placeholders). This will create missing keys and update existing ones with safe defaults.</p>
                            <form method="POST" onsubmit="return confirm('Seed recommended settings now? Existing keys will be updated to defaults.');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <button type="submit" name="seed_recommended" class="btn btn-sm btn-primary">
                                    <i class="fas fa-magic mr-1"></i> Seed Recommended Settings
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Settings Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Settings List (<?php echo $total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Key</th>
                                            <th>Value</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Public</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($settings as $setting): ?>
                                            <tr>
                                                <td><?php echo $setting['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($setting['setting_key']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $value = $setting['setting_value'];
                                                    if ($setting['setting_type'] === 'boolean') {
                                                        echo $value ? '<span class="badge badge-success">True</span>' : '<span class="badge badge-secondary">False</span>';
                                                    } elseif ($setting['setting_type'] === 'json') {
                                                        echo '<code>' . htmlspecialchars(substr($value, 0, 100)) . (strlen($value) > 100 ? '...' : '') . '</code>';
                                                    } else {
                                                        echo htmlspecialchars(substr($value, 0, 100)) . (strlen($value) > 100 ? '...' : '');
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $setting['setting_type'] === 'text' ? 'info' : 
                                                            ($setting['setting_type'] === 'number' ? 'warning' : 
                                                            ($setting['setting_type'] === 'boolean' ? 'success' : 'primary')); 
                                                    ?>">
                                                        <?php echo ucfirst($setting['setting_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($setting['description'] ?? ''); ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $setting['is_public'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $setting['is_public'] ? 'Public' : 'Private'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editSettingModal<?php echo $setting['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this setting? This action cannot be undone.')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="setting_id" value="<?php echo $setting['id']; ?>">
                                                            <button type="submit" name="delete_setting" class="btn btn-sm btn-danger">
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
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>">Next</a>
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

    <!-- Add Setting Modal -->
    <div class="modal fade" id="addSettingModal" tabindex="-1" role="dialog" aria-labelledby="addSettingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addSettingModalLabel">Add New Setting</h5>
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
                                    <label for="setting_key">Setting Key *</label>
                                    <input type="text" class="form-control" id="setting_key" name="setting_key" required placeholder="e.g., site_name, contact_email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="setting_type">Setting Type *</label>
                                    <select class="form-control" id="setting_type" name="setting_type" required>
                                        <option value="text">Text</option>
                                        <option value="number">Number</option>
                                        <option value="boolean">Boolean</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="setting_value">Setting Value *</label>
                            <textarea class="form-control" id="setting_value" name="setting_value" rows="3" required placeholder="Enter the setting value"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" class="form-control" id="description" name="description" placeholder="Brief description of this setting">
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="is_public" name="is_public" value="1" checked>
                                <label class="custom-control-label" for="is_public">Public Setting (accessible via API)</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="add_setting" class="btn btn-primary">Add Setting</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Setting Modals -->
    <?php foreach ($settings as $setting): ?>
        <div class="modal fade" id="editSettingModal<?php echo $setting['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editSettingModalLabel<?php echo $setting['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSettingModalLabel<?php echo $setting['id']; ?>">Edit Setting</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="setting_id" value="<?php echo $setting['id']; ?>">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_setting_key_<?php echo $setting['id']; ?>">Setting Key</label>
                                        <input type="text" class="form-control" id="edit_setting_key_<?php echo $setting['id']; ?>" value="<?php echo htmlspecialchars($setting['setting_key']); ?>" readonly>
                                        <small class="form-text text-muted">Setting key cannot be changed</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit_setting_type_<?php echo $setting['id']; ?>">Setting Type</label>
                                        <input type="text" class="form-control" id="edit_setting_type_<?php echo $setting['id']; ?>" value="<?php echo ucfirst($setting['setting_type']); ?>" readonly>
                                        <small class="form-text text-muted">Setting type cannot be changed</small>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="edit_setting_value_<?php echo $setting['id']; ?>">Setting Value *</label>
                                <?php if ($setting['setting_type'] === 'image'): ?>
                                    <input type="file" class="form-control-file" id="edit_setting_value_<?php echo $setting['id']; ?>" name="setting_value" accept="image/*">
                                    <?php if (!empty($setting['setting_value'])): ?>
                                        <div class="mt-2">
                                            <small>Current Image:</small><br>
                                            <img src="<?php echo htmlspecialchars($setting['setting_value']); ?>" alt="Current Image" style="max-width: 200px; height: auto;">
                                            <a href="<?php echo htmlspecialchars($setting['setting_value']); ?>" target="_blank" class="ml-2">View</a>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($setting['setting_type'] === 'boolean'): ?>
                                    <select class="form-control" id="edit_setting_value_<?php echo $setting['id']; ?>" name="setting_value" required>
                                        <option value="1" <?php echo $setting['setting_value'] ? 'selected' : ''; ?>>True</option>
                                        <option value="0" <?php echo !$setting['setting_value'] ? 'selected' : ''; ?>>False</option>
                                    </select>
                                <?php else: ?>
                                    <textarea class="form-control" id="edit_setting_value_<?php echo $setting['id']; ?>" name="setting_value" rows="3" required><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="edit_description_<?php echo $setting['id']; ?>">Description</label>
                                <input type="text" class="form-control" id="edit_description_<?php echo $setting['id']; ?>" value="<?php echo htmlspecialchars($setting['description'] ?? ''); ?>" readonly>
                                <small class="form-text text-muted">Description cannot be changed</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" name="update_setting" class="btn btn-primary">Update Setting</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Scripts -->
    <?php require_once 'includes/scripts.php'; ?>

</body>

</html>
