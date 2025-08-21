<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ml-auto">
        <?php
        // Prepare notifications data
        $notif_count = 0;
        $notifs = [];
        try {
            if (isset($pdo)) {
                $uid = $_SESSION['user_id'] ?? null;
                // Unread count: for this user or global (NULL)
                $csql = "SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND (user_id = :uid OR user_id IS NULL)";
                $cstmt = $pdo->prepare($csql);
                $cstmt->execute([':uid' => $uid]);
                $notif_count = (int)$cstmt->fetchColumn();

                // Latest 10 notifications (read and unread) prioritizing unread first
                $lsql = "SELECT id, title, message, type, related_url, is_read, created_at
                         FROM notifications
                         WHERE (user_id = :uid OR user_id IS NULL)
                         ORDER BY is_read ASC, created_at DESC
                         LIMIT 10";
                $lstmt = $pdo->prepare($lsql);
                $lstmt->execute([':uid' => $uid]);
                $notifs = $lstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Throwable $e) {
            // Fail silently in topbar; avoid breaking layout
            $notif_count = 0;
            $notifs = [];
        }
        // Helper to map type to bootstrap bg class and icon
        $typeToClass = function($t) {
            switch ($t) {
                case 'success': return ['bg' => 'bg-success', 'icon' => 'fas fa-check'];
                case 'warning': return ['bg' => 'bg-warning', 'icon' => 'fas fa-exclamation'];
                case 'error':   return ['bg' => 'bg-danger',  'icon' => 'fas fa-times'];
                default:        return ['bg' => 'bg-primary', 'icon' => 'fas fa-info'];
            }
        };
        ?>

        <!-- Nav Item - Alerts (Notifications) -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <?php if ($notif_count > 0): ?>
                    <span class="badge badge-danger badge-counter"><?php echo ($notif_count > 99 ? '99+' : (string)$notif_count); ?></span>
                <?php endif; ?>
            </a>
            <!-- Dropdown - Notifications -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="alertsDropdown" style="max-width: 420px;">
                <h6 class="dropdown-header">Notifications</h6>
                <?php if (empty($notifs)): ?>
                    <div class="dropdown-item text-center small text-gray-500">No notifications</div>
                <?php else: ?>
                    <?php foreach ($notifs as $n): $m = $typeToClass($n['type'] ?? 'info'); ?>
                        <a class="dropdown-item d-flex align-items-center" href="<?php echo htmlspecialchars($n['related_url'] ?: '#'); ?>">
                            <div class="mr-3">
                                <div class="icon-circle <?php echo $m['bg']; ?>">
                                    <i class="<?php echo $m['icon']; ?> text-white"></i>
                                </div>
                            </div>
                            <div>
                                <div class="small text-gray-500"><?php echo date('d M Y H:i', strtotime($n['created_at'])); ?><?php echo (!$n['is_read'] ? ' · <span class="badge badge-danger">New</span>' : ''); ?></div>
                                <div class="font-weight-bold"><?php echo htmlspecialchars($n['title'] ?? 'Notification'); ?></div>
                                <div class="text-truncate" style="max-width: 280px;"><?php echo htmlspecialchars($n['message'] ?? ''); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <div class="dropdown-item text-center small text-gray-500">
                        <a href="manage_notifications.php">Show All</a>
                    </div>
                <?php endif; ?>
            </div>
        </li>

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        <?php
        // Determine current user's avatar dynamically
        $avatarUrl = 'assets/img/undraw_profile.svg';
        try {
            if (isset($pdo) && isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare('SELECT profile_image, username FROM users WHERE id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($row['profile_image'])) {
                        $avatarUrl = assetUrlAdmin($row['profile_image']);
                    }
                    if (empty($_SESSION['user_name']) && !empty($row['username'])) {
                        $_SESSION['user_name'] = $row['username'];
                    }
                }
            }
        } catch (Throwable $e) { /* ignore errors in topbar */ }
        ?>
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                <img class="img-profile rounded-circle" src="<?php echo htmlspecialchars($avatarUrl); ?>">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="userDropdown">
                <a class="dropdown-item" href="profile.php">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                    Profile
                </a>
                <a class="dropdown-item" href="manage_settings.php">
                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                    Settings
                </a>
                <div class="dropdown-divider"></div>
                <?php
                require_once __DIR__ . '/../../config/app.php';
                $___base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
                if ($___base !== '' && $___base[0] !== '/') { $___base = '/' . $___base; }
                $___logout = rtrim($___base, '/') . '/admin/logout.php';
                ?>
                <a class="dropdown-item" href="<?php echo htmlspecialchars($___logout, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>

    </ul>

</nav>
<!-- End of Topbar -->
