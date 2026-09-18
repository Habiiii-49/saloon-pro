<?php
/**
 * Elegance Salon - User / Receptionist Panel - Header / Topbar
 * Requires receptionist authentication (reuses PART 2 auth system).
 */
require_once __DIR__ . '/../../includes/functions.php';

requireRole('receptionist');

$receptionist = getUserById(currentUserId());
if (!$receptionist) {
    performLogout();
    redirect('login.php');
}

$userName      = trim($receptionist['first_name'] . ' ' . $receptionist['last_name']) ?: 'Receptionist';
$userRoleLabel = roleLabel($receptionist['role_name']);
$userInitial   = strtoupper(mb_substr(trim($receptionist['first_name']), 0, 1)) ?: 'R';
$pageTitle     = $pageTitle ?? 'Receptionist Dashboard';
$activeMenu    = $activeMenu ?? basename($_SERVER['PHP_SELF'], '.php');

// Unread notifications (for this user or broadcast)
$unreadCount = 0;
$latestNotifications = [];
try {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM notifications WHERE is_read = 0 AND (user_id = :uid OR user_id IS NULL)");
    $stmt->execute([':uid' => $receptionist['user_id']]);
    $unreadCount = (int)$stmt->fetch()['c'];

    $stmt = $db->prepare("SELECT notification_id, title, message, type, created_at FROM notifications WHERE (user_id = :uid OR user_id IS NULL) ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([':uid' => $receptionist['user_id']]);
    $latestNotifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $latestNotifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo sanitize($pageTitle); ?> | Elegance Salon - Reception</title>

    <link rel="icon" href="<?php echo SITE_URL; ?>/assets/images/favicon.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/user/assets/css/user.css">
</head>
<body class="user-body">
<div class="user-layout">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="sidebar-overlay" data-user-close></div>

    <div class="user-main">

        <!-- TOPBAR -->
        <header class="user-topbar">
            <div class="topbar-left">
                <button class="topbar-toggle" data-user-toggle aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="topbar-search" id="globalSearchBox">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="globalSearchInput" placeholder="Search clients, appointments, invoices..."
                           autocomplete="off" aria-label="Global search">
                    <i class="fas fa-spinner fa-spin search-spinner d-none"></i>
                    <div class="topbar-search-results" id="globalSearchResults"></div>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="topbar-icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="notif-dot" id="notifDotTop"><?php echo min($unreadCount, 99); ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                        <div class="notif-head">
                            <span><i class="fas fa-bell"></i> Notifications</span>
                            <?php if ($unreadCount > 0): ?>
                            <button type="button" class="link-btn" data-mark-all="top" data-csrf="<?php echo generateCSRFToken(); ?>">Mark all read</button>
                            <?php endif; ?>
                        </div>
                        <div class="notif-list" id="notifListTop">
                            <?php if (empty($latestNotifications)): ?>
                            <div class="notif-empty"><i class="fas fa-bell-slash"></i> No notifications yet.</div>
                            <?php else: ?>
                            <?php foreach ($latestNotifications as $notif): ?>
                            <div class="notif-item <?php echo (int)$notif['is_read'] === 0 ? 'unread' : ''; ?>" data-nid="<?php echo (int)$notif['notification_id']; ?>">
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
                        <a class="notif-view-all" href="<?php echo SITE_URL; ?>/user/notifications.php">
                            View all notifications <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Profile -->
                <div class="dropdown">
                    <button class="profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="profile-avatar"><?php echo $userInitial; ?></span>
                        <span class="profile-meta">
                            <b><?php echo sanitize($userName); ?></b>
                            <span><?php echo sanitize($userRoleLabel); ?></span>
                        </span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <div class="dropdown-header">
                            <b><?php echo sanitize($userName); ?></b>
                            <span><?php echo sanitize($receptionist['email']); ?></span>
                        </div>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/change-password.php"><i class="fas fa-key"></i> Change Password</a>
                        <a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/index.php"><i class="fas fa-gauge-high"></i> Dashboard</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item dropdown-danger" href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <main class="user-content">
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