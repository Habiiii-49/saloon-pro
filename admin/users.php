<?php
/**
 * Admin – User Management
 * View / activate / deactivate / delete system users.
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'Users';
$activeMenu = 'users';

$db = getDBConnection();
$errors = [];

/* ---------- POST actions (destructive ops use POST only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['id'] ?? 0);

    if ($userId <= 0) {
        setFlash('error', 'Invalid user selected.');
        redirect('admin/users.php');
    }

    if ($action === 'toggle_status') {
        if ($userId === currentUserId()) {
            setFlash('error', 'You cannot deactivate your own account.');
        } else {
            $stmt = $db->prepare("UPDATE users SET is_active = IF(is_active = 1, 0, 1) WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            setFlash('success', 'User status updated.');
        }
    } elseif ($action === 'delete') {
        if ($userId === currentUserId()) {
            setFlash('error', 'You cannot delete your own account.');
        } else {
            // Remove related staff record first (FK-safe).
            try {
                $db->prepare("DELETE FROM staff WHERE user_id = :id")->execute([':id' => $userId]);
            } catch (PDOException $e) {
                // staff table row may not exist.
            }
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
            setFlash('success', 'User deleted successfully.');
        }
    }

    redirect('admin/users.php');
}

/* ---------- List users ---------- */
$roleFilter = $_GET['role'] ?? '';
if (in_array($roleFilter, ['', 'admin', 'receptionist', 'stylist', 'client'], true) === false) {
    $roleFilter = '';
}

$sql = "
    SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.phone,
           u.role_id, u.is_active, u.last_login, u.created_at, r.role_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
";
$params = [];
if ($roleFilter !== '') {
    $sql .= " WHERE r.role_name = :role";
    $params[':role'] = $roleFilter;
}
$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roleTotal = $db->query("SELECT COUNT(*) FROM users JOIN roles r ON r.role_id = users.role_id WHERE r.role_name = 'admin'")->fetchColumn();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-user-gear"></i> User Management</h1>
        <p>Manage admins, receptionists and stylists with full access control.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/user-add.php" class="btn-admin btn-cyan"><i class="fas fa-user-plus"></i> Add User</a>
</div>

<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-users"></i> All Users <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($users); ?></span></h5>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn-admin btn-outline btn-xs <?php echo $roleFilter === '' ? 'bg-opacity-25' : ''; ?>">All</a>
            <a href="<?php echo SITE_URL; ?>/admin/users.php?role=admin" class="btn-admin btn-outline btn-xs">Admin</a>
            <a href="<?php echo SITE_URL; ?>/admin/users.php?role=receptionist" class="btn-admin btn-outline btn-xs">Receptionist</a>
            <a href="<?php echo SITE_URL; ?>/admin/users.php?role=stylist" class="btn-admin btn-outline btn-xs">Stylist</a>
        </div>
    </div>

    <?php if (empty($users)): ?>
    <div class="empty-state">
        <i class="fas fa-user-slash"></i>
        <h5>No users found</h5>
        <p>Create your first team member to get started.</p>
        <a href="<?php echo SITE_URL; ?>/admin/user-add.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> Add User</a>
    </div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <?php
                $isSelf = (int)$user['user_id'] === currentUserId();
                $badgeRole = $user['role_name'] === 'admin' ? 'confirmed' : ($user['role_name'] === 'stylist' ? 'paid' : 'pending');
                $roleIcon  = $user['role_name'] === 'admin' ? 'fa-shield-halved' : ($user['role_name'] === 'stylist' ? 'fa-scissors' : 'fa-headset');
                ?>
                <tr>
                    <td>
                        <span class="table-avatar"><?php echo strtoupper(mb_substr($user['first_name'], 0, 1)); ?></span>
                        <span class="cell-strong"><?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        <?php if ($isSelf): ?><span class="status-badge confirmed" style="margin-left:.35rem;">You</span><?php endif; ?>
                    </td>
                    <td><?php echo sanitize($user['username'] ?? '—'); ?></td>
                    <td class="cell-sub"><?php echo sanitize($user['email']); ?></td>
                    <td><span class="status-badge <?php echo $badgeRole; ?>"><i class="fas <?php echo $roleIcon; ?>"></i> <?php echo ucfirst(roleLabel($user['role_name'])); ?></span></td>
                    <td>
                        <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><span class="cell-sub"><?php echo !empty($user['last_login']) ? formatDate($user['last_login'], 'M d, Y') : 'Never'; ?></span></td>
                    <td>
                        <div class="action-btns">
                            <a href="<?php echo SITE_URL; ?>/admin/user-edit.php?id=<?php echo (int)$user['user_id']; ?>" class="btn-admin btn-outline btn-xs" title="Edit"><i class="fas fa-pen"></i></a>

                            <?php if (!$isSelf): ?>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo (int)$user['user_id']; ?>">
                                <button type="submit"
                                        data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                            'title'       => $user['is_active'] ? 'Deactivate user?' : 'Activate user?',
                                            'message'     => ($user['is_active'] ? 'Deactivating' : 'Activating') . ' this user will ' . ($user['is_active'] ? 'block them from signing in.' : 'allow them to sign in again.'),
                                            'confirmText' => $user['is_active'] ? 'Deactivate' : 'Activate',
                                        ]), ENT_QUOTES); ?>'
                                        class="btn-admin btn-outline btn-xs"
                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $user['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                </button>
                            </form>

                            <?php endif; ?>

                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$user['user_id']; ?>">
                                <button type="submit"
                                        data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                            'title'       => 'Delete user?',
                                            'message'     => 'This will permanently remove ' . $user['first_name'] . ' ' . $user['last_name'] . '. This action cannot be undone.',
                                            'confirmText' => 'Delete',
                                        ]), ENT_QUOTES); ?>'
                                        class="btn-admin btn-danger-outline btn-xs"
                                        title="Delete"
                                        <?php echo $isSelf ? 'disabled' : ''; ?>>
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>