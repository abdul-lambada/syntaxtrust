<?php
require_once __DIR__ . '/../config/session.php';
// config/session.php already includes database.php

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function verify_csrf(): bool {
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Auth check
if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/app.php';
    $publicBase = defined('PUBLIC_BASE_PATH') ? PUBLIC_BASE_PATH : '';
    header('Location: ' . rtrim($publicBase, '/') . '/login.php');
    exit();
}

$uid = $_SESSION['user_id'];

// Feedback message
$message = '';
$message_type = '';

// Helpers
function clamp_int($v, $min, $max) {
    $v = (int)$v;
    if ($v < $min) return $min;
    if ($v > $max) return $max;
    return $v;
}

// Handle single item actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    try {
        // Mark read
        if (isset($_POST['mark_read']) && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND (user_id = :uid OR user_id IS NULL)");
            $stmt->execute([':id' => $id, ':uid' => $uid]);
            if ($stmt->rowCount() > 0) {
                $message = 'Notification marked as read.';
                $message_type = 'success';
            } else {
                $message = 'No notification updated.';
                $message_type = 'secondary';
            }
        }
        // Mark unread
        if (isset($_POST['mark_unread']) && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 0 WHERE id = :id AND (user_id = :uid OR user_id IS NULL)");
            $stmt->execute([':id' => $id, ':uid' => $uid]);
            if ($stmt->rowCount() > 0) {
                $message = 'Notification marked as unread.';
                $message_type = 'success';
            } else {
                $message = 'No notification updated.';
                $message_type = 'secondary';
            }
        }
        // Delete (only user's own notifications can be deleted; global notifications are preserved)
        if (isset($_POST['delete']) && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :uid");
            $stmt->execute([':id' => $id, ':uid' => $uid]);
            if ($stmt->rowCount() > 0) {
                $message = 'Notification deleted.';
                $message_type = 'success';
            } else {
                $message = 'No notification deleted. Only personal notifications can be deleted.';
                $message_type = 'secondary';
            }
        }
        // Bulk actions
        if (isset($_POST['bulk_action']) && isset($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = array_map('intval', $_POST['ids']);
            $ids = array_values(array_unique(array_filter($ids))); // clean
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                if ($_POST['bulk_action'] === 'mark_read') {
                    $sql = "UPDATE notifications SET is_read = 1 WHERE id IN ($in) AND (user_id = ? OR user_id IS NULL)";
                    $params = array_merge($ids, [$uid]);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = 'Selected notifications marked as read.';
                    $message_type = 'success';
                } elseif ($_POST['bulk_action'] === 'mark_unread') {
                    $sql = "UPDATE notifications SET is_read = 0 WHERE id IN ($in) AND (user_id = ? OR user_id IS NULL)";
                    $params = array_merge($ids, [$uid]);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = 'Selected notifications marked as unread.';
                    $message_type = 'success';
                } elseif ($_POST['bulk_action'] === 'delete') {
                    $sql = "DELETE FROM notifications WHERE id IN ($in) AND user_id = ?";
                    $params = array_merge($ids, [$uid]);
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = 'Selected notifications deleted (only personal ones).';
                    $message_type = 'success';
                }
            }
        }
        // Mark all as read (in current filter scope optional, here we do for all visible to user)
        if (isset($_POST['mark_all_read'])) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = :uid OR user_id IS NULL)");
            $stmt->execute([':uid' => $uid]);
            $message = 'All notifications marked as read.';
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        // Avoid leaking sensitive DB error details to the UI
        $message = 'An error occurred while processing your request.';
        $message_type = 'danger';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF invalid
    $message = 'Invalid CSRF token. Please refresh the page and try again.';
    $message_type = 'danger';
}

// Filters and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$read_filter = isset($_GET['read']) ? trim($_GET['read']) : ''; // '', 'read', 'unread'
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page > 0 ? $page : 1;
$limit = 15;

$where = ['(user_id = :uid OR user_id IS NULL)'];
$params = [':uid' => $uid];

if ($search !== '') {
    $where[] = '(title LIKE :q OR message LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
if ($type_filter !== '') {
    $where[] = 'type = :type';
    $params[':type'] = $type_filter;
}
if ($read_filter === 'read') {
    $where[] = 'is_read = 1';
} elseif ($read_filter === 'unread') {
    $where[] = 'is_read = 0';
}
$where_clause = 'WHERE ' . implode(' AND ', $where);

// Count
$count_sql = "SELECT COUNT(*) AS total FROM notifications $where_clause";
$cstmt = $pdo->prepare($count_sql);
$cstmt->execute($params);
$total_records = (int)($cstmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_records / $limit));
if ($page > $total_pages) { $page = $total_pages; }
$offset = ($page - 1) * $limit;

// Fetch
$list_sql = "SELECT id, title, message, type, related_url, is_read, created_at
            FROM notifications
            $where_clause
            ORDER BY created_at DESC
            LIMIT $limit OFFSET $offset";
$lstmt = $pdo->prepare($list_sql);
$lstmt->execute($params);
$rows = $lstmt->fetchAll(PDO::FETCH_ASSOC);

// Map type to badge/icon (similar to topbar)
function notif_map($t) {
    switch ($t) {
        case 'success': return ['bg' => 'badge-success', 'icon' => 'fas fa-check'];
        case 'warning': return ['bg' => 'badge-warning', 'icon' => 'fas fa-exclamation'];
        case 'error':   return ['bg' => 'badge-danger',  'icon' => 'fas fa-times'];
        default:        return ['bg' => 'badge-primary', 'icon' => 'fas fa-info'];
    }
}

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
                        <h1 class="h3 mb-0 text-gray-800">Kelola Notifikasi</h1>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Tandai SEMUA notifikasi sebagai sudah dibaca?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <button type="submit" name="mark_all_read" class="btn btn-sm btn-primary">
                                <i class="fas fa-check mr-1"></i> Tandai Semua Dibaca
                            </button>
                        </form>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Pencarian dan Filter</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mx-sm-3 mb-2">
                                    <input type="text" class="form-control" name="search" placeholder="Cari judul atau pesan..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group mx-sm-3 mb-2">
                                    <select class="form-control" name="type">
                                        <option value="">Semua Tipe</option>
                                        <option value="info" <?php echo $type_filter==='info'?'selected':''; ?>>Info</option>
                                        <option value="success" <?php echo $type_filter==='success'?'selected':''; ?>>Success</option>
                                        <option value="warning" <?php echo $type_filter==='warning'?'selected':''; ?>>Warning</option>
                                        <option value="error" <?php echo $type_filter==='error'?'selected':''; ?>>Error</option>
                                    </select>
                                </div>
                                <div class="form-group mx-sm-3 mb-2">
                                    <select class="form-control" name="read">
                                        <option value="">Semua</option>
                                        <option value="unread" <?php echo $read_filter==='unread'?'selected':''; ?>>Belum Dibaca</option>
                                        <option value="read" <?php echo $read_filter==='read'?'selected':''; ?>>Sudah Dibaca</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary mb-2">Cari</button>
                                <?php if ($search!=='' || $type_filter!=='' || $read_filter!==''): ?>
                                    <a href="manage_notifications.php" class="btn btn-secondary mb-2 ml-2">Reset</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Notifications List (<?php echo $total_records; ?> total)</h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkSet('mark_read')">Tandai Dibaca</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="bulkSet('mark_unread')">Tandai Belum Dibaca</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="bulkSet('delete')">Hapus</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="bulkForm" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="bulk_action" id="bulk_action" value="">
                                <div class="table-responsive">
                                    <table class="table table-bordered" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th style="width: 32px;"><input type="checkbox" id="chk_all" onclick="toggleAll(this)"></th>
                                                <th>Type</th>
                                                <th>Title & Message</th>
                                                <th>Link</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($rows)): ?>
                                                <tr><td colspan="7" class="text-center text-muted">No notifications found.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($rows as $n): $m = notif_map($n['type'] ?? 'info'); ?>
                                                    <tr>
                                                        <td><input type="checkbox" name="ids[]" value="<?php echo (int)$n['id']; ?>"></td>
                                                        <td>
                                                            <span class="badge <?php echo $m['bg']; ?>"><i class="<?php echo $m['icon']; ?>"></i></span>
                                                            <span class="ml-1 text-capitalize"><?php echo htmlspecialchars($n['type'] ?: 'info'); ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="font-weight-bold">
                                                                <?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?>
                                                                <?php if (!$n['is_read']): ?><span class="badge badge-danger ml-1">Baru</span><?php endif; ?>
                                                            </div>
                                                            <div class="text-muted" style="white-space: pre-line;"><?php echo htmlspecialchars($n['message'] ?? ''); ?></div>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($n['related_url'])): ?>
                                                                <a href="<?php echo htmlspecialchars($n['related_url']); ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener noreferrer">Open</a>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($n['is_read']): ?>
                                                                <span class="badge badge-success">Sudah Dibaca</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary">Belum Dibaca</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo date('d M Y H:i', strtotime($n['created_at'])); ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group" role="group">
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                                                    <?php if ($n['is_read']): ?>
                                                                        <button type="submit" name="mark_unread" class="btn btn-sm btn-outline-secondary">Tandai Belum Dibaca</button>
                                                                    <?php else: ?>
                                                                        <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">Tandai Dibaca</button>
                                                                    <?php endif; ?>
                                                                </form>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus notifikasi ini?');">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                    <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                                                                    <button type="submit" name="delete" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>

                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Navigasi halaman">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&read=<?php echo urlencode($read_filter); ?>">Sebelumnya</a></li>
                                        <?php endif; ?>
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&read=<?php echo urlencode($read_filter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&read=<?php echo urlencode($read_filter); ?>">Next</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

            <?php require_once 'includes/footer.php'; ?>
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <?php require_once 'includes/scripts.php'; ?>

    <script>
        function toggleAll(src) {
            const boxes = document.querySelectorAll('input[name="ids[]"]');
            boxes.forEach(b => b.checked = src.checked);
        }
        function bulkSet(action) {
            const form = document.getElementById('bulkForm');
            const anyChecked = form.querySelectorAll('input[name="ids[]"]:checked').length > 0;
            if (!anyChecked) {
                alert('Please select at least one notification.');
                return;
            }
            if (action === 'delete' && !confirm('Delete selected notifications?')) {
                return;
            }
            document.getElementById('bulk_action').value = action;
            form.submit();
        }
    </script>

</body>
</html>
