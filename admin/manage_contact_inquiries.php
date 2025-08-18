<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// CSRF protection: generate token and helper
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /syntaxtrust/login.php');
    exit();
}

// Handle CRUD operations
$message = '';
$message_type = '';

// CSRF invalid feedback (show message if POST with action but invalid/missing token)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_actions = ['delete_inquiry', 'update_status', 'create_inquiry', 'update_inquiry'];
    $has_action = false;
    foreach ($csrf_actions as $act) {
        if (isset($_POST[$act])) { $has_action = true; break; }
    }
    if ($has_action && !verify_csrf()) {
        $message = 'Invalid CSRF token. Please refresh the page and try again.';
        $message_type = 'danger';
    }
}

// Delete inquiry
if (isset($_POST['delete_inquiry']) && isset($_POST['inquiry_id']) && verify_csrf()) {
    $inquiry_id = $_POST['inquiry_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM contact_inquiries WHERE id = ?");
        $stmt->execute([$inquiry_id]);
        $message = "Contact inquiry deleted successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error deleting contact inquiry: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update inquiry status
if (isset($_POST['update_status']) && isset($_POST['inquiry_id']) && isset($_POST['status']) && verify_csrf()) {
    $inquiry_id = $_POST['inquiry_id'];
    $status = $_POST['status'];
    try {
        // Fetch current status for message context
        $cur = $pdo->prepare("SELECT status, email, subject, name FROM contact_inquiries WHERE id = ?");
        $cur->execute([$inquiry_id]);
        $row = $cur->fetch(PDO::FETCH_ASSOC) ?: [];
        $old_status = $row['status'] ?? null;
        $email = $row['email'] ?? '';
        $subject = $row['subject'] ?? '';
        $name = $row['name'] ?? '';

        $stmt = $pdo->prepare("UPDATE contact_inquiries SET status = ? WHERE id = ?");
        $stmt->execute([$status, $inquiry_id]);

        // Notification
        try {
            $n = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)");
            $n_user = $_SESSION['user_id'] ?? null;
            $n_title = 'Inquiry status updated';
            $label = ($subject !== '' ? $subject : ($name !== '' ? ('From ' . $name) : ('#' . (string)$inquiry_id)));
            $n_msg = $label . ' status ' . ($old_status ?? '-') . ' -> ' . $status . '.';
            $n_type = ($status === 'closed' ? 'success' : 'info');
            $n_url = 'manage_contact_inquiries.php?search=' . urlencode($email);
            $n->execute([$n_user, $n_title, $n_msg, $n_type, $n_url]);
        } catch (Throwable $e2) { /* ignore notification failures */ }
        $message = "Inquiry status updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating inquiry status: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Create new inquiry (manual entry)
if (isset($_POST['create_inquiry']) && verify_csrf()) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $subject = $_POST['subject'];
    $message_text = $_POST['message'];
    $status = $_POST['status'];

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_inquiries (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $subject, $message_text, $status]);
        // Notification
        try {
            $n = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, related_url) VALUES (?, ?, ?, ?, ?)");
            $n_user = $_SESSION['user_id'] ?? null;
            $n_title = 'Inquiry created (admin)';
            $label = ($subject !== '' ? $subject : ($name !== '' ? ('From ' . $name) : 'New Inquiry'));
            $n_msg = $label . ' created with status ' . $status . '.';
            $n_type = 'info';
            $n_url = 'manage_contact_inquiries.php?search=' . urlencode($email);
            $n->execute([$n_user, $n_title, $n_msg, $n_type, $n_url]);
        } catch (Throwable $e2) { /* ignore notification failures */ }
        $message = "Contact inquiry created successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error creating contact inquiry: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update inquiry
if (isset($_POST['update_inquiry']) && verify_csrf()) {
    $inquiry_id = $_POST['inquiry_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $subject = $_POST['subject'];
    $message_text = $_POST['message'];
    $status = $_POST['status'];
    $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : null; // optional, column may not exist

    try {
        // Note: admin_notes column not present in DB; exclude from UPDATE
        $stmt = $pdo->prepare("UPDATE contact_inquiries SET name = ?, email = ?, phone = ?, subject = ?, message = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $subject, $message_text, $status, $inquiry_id]);
        $message = "Contact inquiry updated successfully!";
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error updating contact inquiry: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Search and pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;

// Build query (qualify columns with alias c)
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.subject LIKE ? OR c.message LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count (use same alias and join to avoid ambiguity)
$count_sql = "SELECT COUNT(*) as total FROM contact_inquiries c LEFT JOIN services s ON c.service_id = s.id $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit));

// Validate current page after knowing total pages
if ($page < 1) { $page = 1; }
if ($page > $total_pages) { $page = $total_pages; }
// Compute offset
$offset = ($page - 1) * $limit;

// Get inquiries with pagination
$sql = "SELECT c.*, s.name as service_name 
        FROM contact_inquiries c 
        LEFT JOIN services s ON c.service_id = s.id 
        $where_clause 
        ORDER BY c.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <h1 class="h3 mb-0 text-gray-800">Manage Contact Inquiries</h1>
                        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addInquiryModal">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Inquiry
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
                            <h6 class="m-0 font-weight-bold text-primary">Search and Filter Inquiries</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Search by name, email, subject..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group mx-sm-3 mb-2">
                                    <select class="form-control" name="status">
                                        <option value="">All Status</option>
                                        <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                                        <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                                        <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Search</button>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <a href="manage_contact_inquiries.php" class="btn btn-secondary mb-2 ml-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Inquiries Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Contact Inquiries List (<?php echo (int)$total_records; ?> total)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Contact Info</th>
                                            <th>Subject</th>
                                            <th>Message</th>
                                            <th>Service</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inquiries as $inquiry): ?>
                                            <tr>
                                                <td><?php echo $inquiry['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($inquiry['name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($inquiry['email']); ?></small>
                                                    <?php if (!empty($inquiry['phone'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($inquiry['phone']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($inquiry['subject'] ?? 'No Subject'); ?>
                                                    <?php if (!empty($inquiry['budget_range'])): ?>
                                                        <br><small class="text-info">Budget: <?php echo htmlspecialchars($inquiry['budget_range']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($inquiry['timeline'])): ?>
                                                        <br><small class="text-info">Timeline: <?php echo htmlspecialchars($inquiry['timeline']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($inquiry['message'], 0, 150) . '...'); ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($inquiry['service_name'])): ?>
                                                        <span class="badge badge-info"><?php echo htmlspecialchars($inquiry['service_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">General</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php
                                                                                echo $inquiry['status'] === 'new' ? 'danger' : ($inquiry['status'] === 'read' ? 'warning' : ($inquiry['status'] === 'replied' ? 'success' : 'secondary'));
                                                                                ?>">
                                                        <?php echo ucfirst($inquiry['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('d M Y H:i', strtotime($inquiry['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#viewInquiryModal<?php echo $inquiry['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#editInquiryModal<?php echo $inquiry['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-toggle="dropdown">
                                                                Status
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="status" value="new">
                                                                    <button type="submit" name="update_status" class="dropdown-item">New</button>
                                                                </form>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="status" value="read">
                                                                    <button type="submit" name="update_status" class="dropdown-item">Read</button>
                                                                </form>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="status" value="replied">
                                                                    <button type="submit" name="update_status" class="dropdown-item">Replied</button>
                                                                </form>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="status" value="closed">
                                                                    <button type="submit" name="update_status" class="dropdown-item">Closed</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this inquiry? This action cannot be undone.')">
                                                            <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <button type="submit" name="delete_inquiry" class="btn btn-sm btn-danger">
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
                            <?php // View Modals for each inquiry ?>
                            <?php foreach ($inquiries as $inquiry): ?>
                                <div class="modal fade" id="viewInquiryModal<?php echo $inquiry['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="viewInquiryModalLabel<?php echo $inquiry['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-info text-white">
                                                <h5 class="modal-title" id="viewInquiryModalLabel<?php echo $inquiry['id']; ?>">View Inquiry #<?php echo $inquiry['id']; ?></h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="mb-0 text-muted">Name</label>
                                                            <div><strong><?php echo htmlspecialchars($inquiry['name']); ?></strong></div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="mb-0 text-muted">Email</label>
                                                            <div><?php echo htmlspecialchars($inquiry['email']); ?></div>
                                                        </div>
                                                        <?php if (!empty($inquiry['phone'])): ?>
                                                            <div class="form-group">
                                                                <label class="mb-0 text-muted">Phone</label>
                                                                <div><?php echo htmlspecialchars($inquiry['phone']); ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="form-group">
                                                            <label class="mb-0 text-muted">Status</label>
                                                            <div><span class="badge badge-secondary"><?php echo ucfirst($inquiry['status']); ?></span></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label class="mb-0 text-muted">Subject</label>
                                                            <div><?php echo htmlspecialchars($inquiry['subject']); ?></div>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="mb-0 text-muted">Message</label>
                                                            <div class="border rounded p-2" style="white-space: pre-wrap;"><?php echo htmlspecialchars($inquiry['message']); ?></div>
                                                        </div>
                                                        <?php if (!empty($inquiry['service_name'])): ?>
                                                            <div class="form-group">
                                                                <label class="mb-0 text-muted">Service</label>
                                                                <div><span class="badge badge-info"><?php echo htmlspecialchars($inquiry['service_name']); ?></span></div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="form-group">
                                                            <label class="mb-0 text-muted">Created</label>
                                                            <div><?php echo date('d M Y H:i', strtotime($inquiry['created_at'])); ?></div>
                                                        </div>
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

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
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

    <!-- Add Inquiry Modal -->
    <div class="modal fade" id="addInquiryModal" tabindex="-1" role="dialog" aria-labelledby="addInquiryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addInquiryModalLabel">Add New Contact Inquiry</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject">Subject *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required>
                                </div>
                                <div class="form-group">
                                    <label for="message">Message *</label>
                                    <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="status">Status *</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="new" selected>New</option>
                                        <option value="read">Read</option>
                                        <option value="replied">Replied</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_inquiry" class="btn btn-primary">Create Inquiry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Edit Inquiry Modal -->
    <div class="modal fade" id="editInquiryModal<?php echo $inquiry['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editInquiryModalLabel<?php echo $inquiry['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="editInquiryModalLabel<?php echo $inquiry['id']; ?>">Edit Inquiry #<?php echo $inquiry['id']; ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name_edit<?php echo $inquiry['id']; ?>">Name *</label>
                                    <input type="text" class="form-control" id="name_edit<?php echo $inquiry['id']; ?>" name="name" value="<?php echo htmlspecialchars($inquiry['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email_edit<?php echo $inquiry['id']; ?>">Email *</label>
                                    <input type="email" class="form-control" id="email_edit<?php echo $inquiry['id']; ?>" name="email" value="<?php echo htmlspecialchars($inquiry['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone_edit<?php echo $inquiry['id']; ?>">Phone</label>
                                    <input type="tel" class="form-control" id="phone_edit<?php echo $inquiry['id']; ?>" name="phone" value="<?php echo htmlspecialchars($inquiry['phone']); ?>">
                                </div>
                                <div class="form-group">
                                    <!-- Inquiry Type edit field removed: not stored in DB -->
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="subject_edit<?php echo $inquiry['id']; ?>">Subject *</label>
                                    <input type="text" class="form-control" id="subject_edit<?php echo $inquiry['id']; ?>" name="subject" value="<?php echo htmlspecialchars($inquiry['subject']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="message_edit<?php echo $inquiry['id']; ?>">Message *</label>
                                    <textarea class="form-control" id="message_edit<?php echo $inquiry['id']; ?>" name="message" rows="4" required><?php echo htmlspecialchars($inquiry['message']); ?></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status_edit<?php echo $inquiry['id']; ?>">Status</label>
                                            <select class="form-control" id="status_edit<?php echo $inquiry['id']; ?>" name="status">
                                                <option value="new" <?php echo $inquiry['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                                <option value="read" <?php echo $inquiry['status'] == 'read' ? 'selected' : ''; ?>>Read</option>
                                                <option value="replied" <?php echo $inquiry['status'] == 'replied' ? 'selected' : ''; ?>>Replied</option>
                                                <option value="closed" <?php echo $inquiry['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <!-- Priority edit field removed: not stored in DB -->
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="admin_notes_edit<?php echo $inquiry['id']; ?>">Admin Notes</label>
                                    <textarea class="form-control" id="admin_notes_edit<?php echo $inquiry['id']; ?>" name="admin_notes" rows="2" placeholder="Add internal notes here (not visible to customer)"><?php echo isset($inquiry['admin_notes']) ? htmlspecialchars($inquiry['admin_notes']) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_inquiry" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


    <?php require_once 'includes/scripts.php'; ?>
</body>

</html>