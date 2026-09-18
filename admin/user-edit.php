<?php
/**
 * Admin – Edit User
 * Updates name, username, email, phone, role and status.
 * Password is intentionally NOT changed here (use change-password flow / admin reset).
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'Edit User';
$activeMenu = 'users';

$db = getDBConnection();
$errors = [];

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    setFlash('error', 'Invalid user selected.');
    redirect('admin/users.php');
}

$stmt = $db->prepare("
    SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.phone,
           u.role_id, u.is_active, u.created_at, r.role_name
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = :id LIMIT 1
");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();
if (!$user) {
    setFlash('error', 'User not found.');
    redirect('admin/users.php');
}

$isSelf = (int)$user['user_id'] === currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $username  = strtolower(trim($_POST['username'] ?? ''));
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = trim($_POST['phone'] ?? '');
        $role      = $_POST['role'] ?? '';
        $isActive  = !empty($_POST['is_active']) ? 1 : 0;

        $allowedRoles = ['admin', 'receptionist', 'stylist'];

        /* ---- Validation ---- */
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First and last name are required.';
        }
        if ($username === '' || !preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
            $errors[] = 'Username must be 3-30 characters (letters, numbers, underscores, dots).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Please choose a valid role.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if ($isSelf && $isActive !== 1) {
            $errors[] = 'You cannot deactivate your own account.'; // self-protection stays enforced server-side
        }
        if ($isSelf && $role !== 'admin') {
            $errors[] = 'You cannot change your own role.'; // prevent locking yourself out
        }

        /* ---- Uniqueness (excluding self) ---- */
        $s = $db->prepare("SELECT user_id FROM users WHERE username = :u AND user_id <> :id LIMIT 1");
        $s->execute([':u' => $username, ':id' => $userId]);
        if ($s->fetch()) {
            $errors[] = 'That username is already taken.';
        }
        $s = $db->prepare("SELECT user_id FROM users WHERE email = :e AND user_id <> :id LIMIT 1");
        $s->execute([':e' => $email, ':id' => $userId]);
        if ($s->fetch()) {
            $errors[] = 'That email address is already registered.';
        }

        if (empty($errors)) {
            $roleQuery = $db->prepare("SELECT role_id FROM roles WHERE role_name = :r LIMIT 1");
            $roleQuery->execute([':r' => $role]);
            $roleId = (int)$roleQuery->fetchColumn();

            $stmt = $db->prepare("
                UPDATE users
                SET first_name = :f, last_name = :l, username = :u, email = :e,
                    phone = :p, role_id = :rid, is_active = :active
                WHERE user_id = :id
            ");
            $stmt->execute([
                ':f'      => $firstName,
                ':l'      => $lastName,
                ':u'      => $username,
                ':e'      => $email,
                ':p'      => $phone !== '' ? $phone : null,
                ':rid'    => $roleId,
                ':active' => $isActive,
                ':id'     => $userId,
            ]);

            /* Keep a stylist's staff profile in sync. */
            $staffExists = $db->prepare("SELECT staff_id FROM staff WHERE user_id = :id LIMIT 1");
            $staffExists->execute([':id' => $userId]);
            if ($role === 'stylist') {
                if (!$staffExists->fetch()) {
                    $db->prepare("INSERT INTO staff (user_id, is_available) VALUES (:id, 1)")->execute([':id' => $userId]);
                }
            } elseif ($staffExists->fetch()) {
                // No longer a stylist → remove from staff roster (kept safe).
                $db->prepare("DELETE FROM staff WHERE user_id = :id")->execute([':id' => $userId]);
            }

            // If the current admin edited themselves, refresh session name.
            if ($isSelf) {
                $_SESSION['name']       = trim($firstName . ' ' . $lastName);
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name']  = $lastName;
                $_SESSION['username']   = $username;
                $_SESSION['email']      = $email;
                $_SESSION['role']       = 'admin';
            }

            setFlash('success', 'User updated successfully.');
            redirect('admin/users.php');
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-user-pen"></i> Edit User</h1>
        <p>Update account details for <?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?>.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Users</a>
</div>

<div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-pen"></i> Account Information</h5>
                <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                    &nbsp;·&nbsp; <span class="status-badge <?php echo $user['role_name'] === 'admin' ? 'confirmed' : 'pending'; ?>" style="font-size:.68rem;"><?php echo ucfirst(roleLabel($user['role_name'])); ?></span>
                </span>
            </div>
            <div class="card-body-custom">
                <div class="alert-admin info" style="margin-bottom:1.4rem;">
                    <i class="fas fa-circle-info"></i>
                    <div>Passwords are not changed here. To reset a password securely, use the dedicated password change flow.</div>
                    <button type="button" class="alert-close"><i class="fas fa-xmark"></i></button>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php echo csrfField(); ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="req">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="form-control-admin" value="<?php echo sanitize($user['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="req">*</span></label>
                                <input type="text" id="last_name" name="last_name" class="form-control-admin" value="<?php echo sanitize($user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="username">Username <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-at"></i>
                                    <input type="text" id="username" name="username" class="form-control-admin" value="<?php echo sanitize($user['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email Address <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" class="form-control-admin" value="<?php echo sanitize($user['email']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" id="phone" name="phone" class="form-control-admin" value="<?php echo sanitize($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="role">Role <span class="req">*</span></label>
                                <select id="role" name="role" class="form-control-admin" <?php echo $isSelf ? 'disabled' : ''; ?>>
                                    <option value="receptionist" <?php echo $user['role_name'] === 'receptionist' ? 'selected' : ''; ?>>Receptionist (User)</option>
                                    <option value="stylist" <?php echo $user['role_name'] === 'stylist' ? 'selected' : ''; ?>>Stylist</option>
                                    <option value="admin" <?php echo $user['role_name'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <?php if ($isSelf): ?><div class="form-text-admin">You cannot change your own role.</div><?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <label class="remember" style="color:#c3cfe0;font-size:.86rem;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;padding-top:.45rem;">
                                    <input type="checkbox" name="is_active" value="1" style="accent-color:#00C2D9;width:16px;height:16px;" <?php echo $user['is_active'] ? 'checked' : ''; ?> <?php echo $isSelf ? 'disabled' : ''; ?>>
                                    Active account
                                </label>
                                <?php if ($isSelf): ?><div class="form-text-admin">You cannot deactivate your own account.</div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Save Changes</button>
                        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>