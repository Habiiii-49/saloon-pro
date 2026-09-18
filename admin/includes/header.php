<?php
/**
 * Admin Panel - Header / Topbar
 * Requires admin authentication.
 */
require_once __DIR__ . '/../../includes/functions.php';

requireRole('admin');

$adminUser = getUserById(currentUserId());
if (!$adminUser) {
    performLogout();
    redirect('login.php');
}

$adminName  = trim($adminUser['first_name'] . ' ' . $adminUser['last_name']) ?: 'Admin';
$adminRoleLabel = roleLabel($adminUser['role_name']);
$adminInitial = strtoupper(mb_substr(trim($adminUser['first_name']), 0, 1)) ?: 'A';
$pageTitle   = $pageTitle ?? 'Dashboard';

// Active menu (overridable from page files before including header)
$activeMenu = $activeMenu ?? basename($_SERVER['PHP_SELF'], '.php');

// Unread notifications
$unreadNotifications = [];
$unreadCount = 0;
try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0 AND (user_id = :uid OR user_id IS NULL)");
    $stmt->execute([':uid' => $adminUser['user_id']]);
    $unreadCount = (int)$stmt->fetch()['c'];

    $stmt = $db->prepare("SELECT notification_id, title, message, type, created_at FROM notifications WHERE (user_id = :uid OR user_id IS NULL) ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([':uid' => $adminUser['user_id']]);
    $unreadNotifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $unreadNotifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo sanitize($pageTitle); ?> | Elegance Salon Admin</title>

    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg" type="image/svg+xml">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 + Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Admin Styles -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/assets/css/admin.css">
    <?php if (!empty($extraCss)): ?>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/<?php echo sanitize($extraCss); ?>">
    <?php endif; ?>

    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
</head>
<body class="admin-body">
<div class="admin-layout">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="admin-overlay-placeholder">
        <div class="sidebar-overlay"></div>
    </div>

    <div class="admin-main">

        <!-- TOPBAR -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="topbar-toggle" data-admin-toggle aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="topbar-search">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" data-table-search=".admin-table" placeholder="Search in tables...">
                </div>
            </div>

            <div class="topbar-right">
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="topbar-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="notif-dot"><?php echo min($unreadCount, 99); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                        <div class="notif-head">
                            <span><i class="fas fa-bell"></i> Notifications</span>
                            <?php if ($unreadCount > 0): ?>
                            <span class="status-badge pending"><?php echo $unreadCount; ?> new</span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-list">
                            <?php if (empty($unreadNotifications)): ?>
                            <div class="notif-empty">
                                <i class="fas fa-bell-slash"></i>
                                No notifications yet.
                            </div>
                            <?php else: ?>
                            <?php foreach ($unreadNotifications as $notif): ?>
                            <div class="notif-item">
                                <span class="notif-ic <?php echo sanitize($notif['type'] ?? 'info'); ?>">
                                    <i class="fas <?php
                                        switch ($notif['type']) {
                                            case 'success': echo 'fa-circle-check'; break;
                                            case 'warning': echo 'fa-triangle-exclamation'; break;
                                            case 'danger':  echo 'fa-circle-xmark'; break;
                                            default:        echo 'fa-circle-info';
                                        }
                                    ?>"></i>
                                </span>
                                <div>
                                    <b><?php echo sanitize($notif['title']); ?></b>
                                    <p><?php echo sanitize($notif['message']); ?></p>
                                    <small><i class="far fa-clock"></i> <?php echo formatDate($notif['created_at'], 'M d, Y g:i A'); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile -->
                <div class="dropdown">
                    <button class="profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="profile-avatar"><?php echo $adminInitial; ?></span>
                        <span class="profile-meta">
                            <b><?php echo sanitize($adminName); ?></b>
                            <span><?php echo sanitize($adminRoleLabel); ?></span>
                        </span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <div class="dropdown-header">
                            <b><?php echo sanitize($adminName); ?></b>
                            <span><?php echo sanitize($adminUser['email']); ?></span>
                        </div>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/change-password.php"><i class="fas fa-key"></i> Change Password</a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/index.php"><i class="fas fa-chart-simple"></i> Dashboard</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item dropdown-danger" href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <main class="admin-content">
            <?php
            $flashes = getFlash();
            if (!empty($flashes)):
                foreach ($flashes as $type => $message):
            ?>
            <div class="alert-admin <?php echo sanitize($type); ?>">
                <i class="fas <?php
                    switch ($type) {
                        case 'success': echo 'fa-circle-check'; break;
                        case 'error':   echo 'fa-circle-xmark'; break;
                        case 'warning': echo 'fa-triangle-exclamation'; break;
                        default:        echo 'fa-circle-info';
                    }
                ?>"></i>
                <div><?php echo sanitize($message); ?></div>
                <button type="button" class="alert-close" aria-label="close"><i class="fas fa-xmark"></i></button>
            </div>
            <?php endforeach; endif; ?>