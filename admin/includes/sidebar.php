<?php
// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Define menu structures
$menu_groups = [
    'content' => [
        'title' => 'Content Management',
        'icon' => 'fas fa-fw fa-edit',
        'pages' => [
            'manage_services.php' => 'Services',
            'manage_pricing_plans.php' => 'Pricing Plans',
            'manage_portfolio.php' => 'Portfolio',
            'manage_blog_posts.php' => 'Blog Posts',
            'manage_testimonials.php' => 'Testimonials',
        ]
    ],
    'users' => [
        'title' => 'User Management',
        'icon' => 'fas fa-fw fa-users',
        'pages' => [
            'manage_team.php' => 'Team',
            'manage_clients.php' => 'Clients',
            'manage_users.php' => 'Users',
        ]
    ],
    'site' => [
        'title' => 'Site Management',
        'icon' => 'fas fa-fw fa-desktop',
        'pages' => [
            'manage_orders.php' => 'Orders',
            'manage_payment_intents.php' => 'Payment Intents',
            'payment_confirmations.php' => 'Payment Confirmations',
            'manage_contact_inquiries.php' => 'Contact Inquiries',
            'manage_notifications.php' => 'Notifications',
            'manage_fonnte.php' => 'Fonnte Integration',
            'manage_settings.php' => 'Settings',
        ]
    ]
];

?><!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
        <div class="sidebar-brand-icon rotate-n-15">
            <i class="fas fa-laugh-wink"></i>
        </div>
        <div class="sidebar-brand-text mx-3">SyntaxTrust <sup>Admin</sup></div>
    </a>

    <!-- Divider -->
    <hr class="sidebar-divider my-0">

    <!-- Nav Item - Dashboard -->
    <li class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
        <a class="nav-link" href="index.php">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span></a>
    </li>

        <!-- Loop through menu groups -->
    <?php foreach ($menu_groups as $group_key => $group): ?>
        <?php 
            // Determine if the current group should be active/expanded
            $is_active_group = false;
            foreach ($group['pages'] as $page_file => $page_name) {
                if ($current_page == $page_file) {
                    $is_active_group = true;
                    break;
                }
            }
        ?>
        <!-- Nav Item - Collapsible Menu -->
        <li class="nav-item <?php echo $is_active_group ? 'active' : ''; ?>">
            <a class="nav-link <?php echo $is_active_group ? '' : 'collapsed'; ?>" href="#" data-toggle="collapse" data-target="#collapse-<?php echo $group_key; ?>" aria-expanded="<?php echo $is_active_group ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $group_key; ?>">
                <i class="<?php echo $group['icon']; ?>"></i>
                <span><?php echo $group['title']; ?></span>
            </a>
            <div id="collapse-<?php echo $group_key; ?>" class="collapse <?php echo $is_active_group ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $group_key; ?>" data-parent="#accordionSidebar">
                <div class="bg-white py-2 collapse-inner rounded">
                    <?php foreach ($group['pages'] as $page_file => $page_name): ?>
                        <a class="collapse-item <?php echo ($current_page == $page_file) ? 'active' : ''; ?>" href="<?php echo $page_file; ?>"><?php echo $page_name; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </li>
    <?php endforeach; ?>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>

</ul>
<!-- End of Sidebar -->