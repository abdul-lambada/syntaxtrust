<!-- Topbar -->
<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Topbar Search -->
    <form
        class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group">
            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..."
                aria-label="Search" aria-describedby="basic-addon2">
            <div class="input-group-append">
                <button class="btn btn-primary" type="button">
                    <i class="fas fa-search fa-sm"></i>
                </button>
            </div>
        </div>
    </form>

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

        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
        <li class="nav-item dropdown no-arrow d-sm-none">
            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-search fa-fw"></i>
            </a>
        </li>

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

        <!-- Nav Item - Messages -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-envelope fa-fw"></i>
                <!-- Counter - Messages -->
                <span class="badge badge-danger badge-counter">7</span>
            </a>
            <!-- Dropdown - Messages -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">
                    Message Center
                </h6>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="assets/img/undraw_profile_1.svg"
                            alt="...">
                        <div class="status-indicator bg-success"></div>
                    </div>
                    <div class="font-weight-bold">
                        <div class="text-truncate">Hi there! I am wondering if you can help me with a
                            problem I've been having.</div>
                        <div class="small text-gray-500">Emily Fowler · 58m</div>
                    </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="assets/img/undraw_profile_2.svg"
                            alt="...">
                        <div class="status-indicator"></div>
                    </div>
                    <div>
                        <div class="text-truncate">I have the photos that you ordered last month, how
                            would you like them sent to you?</div>
                        <div class="small text-gray-500">Jae Chun · 1d</div>
                    </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="assets/img/undraw_profile_3.svg"
                            alt="...">
                        <div class="status-indicator bg-warning"></div>
                    </div>
                    <div>
                        <div class="text-truncate">Last month's report looks great, I am very happy with
                            the progress so far, keep up the great work!</div>
                        <div class="small text-gray-500">Morgan Alvarez · 2d</div>
                    </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="https://source.unsplash.com/Mv9hjnEUHR4/60x60"
                            alt="...">
                        <div class="status-indicator bg-success"></div>
                    </div>
                    <div>
                        <div class="text-truncate">Am I a good boy? The reason I ask is because someone
                            told me that people say this to all dogs, even if they aren't good...</div>
                        <div class="small text-gray-500">Chicken the Dog · 2w</div>
                    </div>
                </a>
                <a class="dropdown-item text-center small text-gray-500" href="#">Read More Messages</a>
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
                <a class="dropdown-item" href="/syntaxtrust/admin/logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>

    </ul>

</nav>
<!-- End of Topbar -->
