<?php
/**
 * Elegance Salon - Receptionist - Notifications
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$userId = currentUserId();

try {
    $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = :u");
    $stmt->execute([':u' => $userId]);
} catch (PDOException $e) { /* ignore */ }

/* POST: mark all read / delete one */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect('user/notifications.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0")->execute([':u' => $userId]);
        setFlash('success', 'All notifications marked as read.');
        redirect('user/notifications.php');
    }
}

/* DELETE: remove a single notification */
$delId = (int)($_GET['delete'] ?? 0);
if ($delId > 0) {
    $db->prepare("DELETE FROM notifications WHERE notification_id = :id AND user_id = :u")->execute([':id' => $delId, ':u' => $userId]);
    setFlash('success', 'Notification removed.');
    redirect('user/notifications.php');
}

$filter = $_GET['filter'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$where  = ['(n.user_id = :u OR n.user_id IS NULL)'];
$params = [':u' => $userId];
if ($filter === 'unread') { $where[] = 'n.is_read = 0'; }
if ($typeFilter !== '' && in_array($typeFilter, ['info', 'success', 'warning', 'danger'], true)) { $where[] = 'n.type = :t'; $params[':t'] = $typeFilter; }

$sql = "SELECT n.*, u.first_name AS actor_first, u.last_name AS actor_last
        FROM notifications n
        LEFT JOIN users u ON u.user_id = n.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY n.created_at DESC LIMIT 150";

$notifs = $db->prepare($sql);
$notifs->execute($params);

$countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (user_id = :u OR user_id IS NULL) AND is_read = 0");
$countStmt->execute([':u' => $userId]);
$unreadCount = (int)$countStmt->fetchColumn();

$typeLabels = [
    'info' => '', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger',
];
$typeIcons = [
    'info' => 'fa-circle-info', 'success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation', 'danger' => 'fa-circle-xmark',
];

$pageTitle = "Notifications";
$activeMenu = 'notifications';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-bell"></i> Notifications</h1>
        <p class="page-subtitle"><?php echo $unreadCount > 0 ? $unreadCount . ' unread notification(s)' : 'You are all caught up.'; ?></p>
    </div>
    <div class="page-head-right">
        <form method="post" action="" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn-user btn-outline"><i class="fas fa-check-double"></i> Mark All Read</button>
        </form>
    </div>
</div>

<div class="panel">
    <form method="get" action="" class="filter-bar" style="padding:1rem 1.3rem;margin:0">
        <select name="filter" class="form-control-salon">
            <option value="">All notifications</option>
            <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread only</option>
        </select>
        <select name="type" class="form-control-salon">
            <option value="">All types</option>
            <?php foreach (['info', 'success', 'warning', 'danger'] as $t): ?>
            <option value="<?php echo $t; ?>" <?php echo $typeFilter === $t ? 'selected' : ''; ?>><?php echo $typeLabels[$t]; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-user btn-outline"><i class="fas fa-filter"></i></button>
    </form>

    <div class="notif-list">
        <?php if ($notifs->rowCount() === 0): ?>
        <div class="empty-block">
            <i class="fas fa-bell-slash"></i>
            <h6>No notifications</h6>
            <p>Notifications about appointments, payments and system events will appear here.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifs as $n): ?>
        <div class="notif-item<?php echo (int)$n['is_read'] === 0 ? ' unread' : ''; ?>">
            <span class="notif-icon <?php echo $n['type']; ?>"><i class="fas <?php echo $typeIcons[$n['type']] ?? 'fa-circle-info'; ?>"></i></span>
            <div class="notif-body">
                <div class="notif-title"><?php echo sanitize($n['title']); ?></div>
                <div class="notif-msg"><?php echo sanitize($n['message']); ?></div>
                <div class="notif-time">
                    <i class="fas fa-clock"></i> <?php echo formatDate($n['created_at'], 'M d, Y g:i A'); ?>
                    <?php if ($n['actor_first']): ?> &middot; by <?php echo sanitize(trim($n['actor_first'] . ' ' . $n['actor_last'])); ?><?php endif; ?>
                    <?php if ($n['user_id'] === null): ?> &middot; <span class="status-badge info" style="padding:.1rem .45rem;font-size:.66rem">Broadcast</span><?php endif; ?>
                </div>
            </div>
            <div class="notif-actions">
                <a href="<?php echo SITE_URL; ?>/user/notifications.php?delete=<?php echo (int)$n['notification_id']; ?>" class="btn-user btn-outline btn-sm" title="Delete" data-confirm="Delete this notification?"><i class="fas fa-trash"></i></a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>