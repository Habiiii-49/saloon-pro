<?php
/**
 * Admin – Add User
 * Creates a new system user (admin / receptionist / stylist).
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'Add User';
$activeMenu = 'users';

$db = getDBConnection();
$errors = [];
$old = [
    'first_name' => '',
    'last_name'  => '',
    'username'   => '',
    'email'      => '',
    'phone'      => '',
    'role'       => 'receptionist',
    'is_active'  => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['first_name'] = trim($_POST['first_name'] ?? '');
        $old['last_name']  = trim($_POST['last_name'] ?? '');
        $old['username']   = strtolower(trim($_POST['username'] ?? ''));
        $old['email']      = strtolower(trim($_POST['email'] ?? ''));
        $old['phone']      = trim($_POST['phone'] ?? '');
        $old['role']       = $_POST['role'] ?? 'receptionist';
        $old['is_active']  = !empty($_POST['is_active']) ? 1 : 0;
        $password          = (string)($_POST['password'] ?? '');
        $confirm           = (string)($_POST['confirm_password'] ?? '');

        $allowedRoles = ['admin', 'receptionist', 'stylist'];

        /* ---- Validation ---- */
        if ($old['first_name'] === '' || $old['last_name'] === '') {
            $errors[] = 'First and last name are required.';
        }
        if ($old['username'] === '' || !preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $old['username'])) {
            $errors[] = 'Username must be 3-30 characters (letters, numbers, underscores, dots).';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!in_array($old['role'], $allowedRoles, true)) {
            $errors[] = 'Please choose a valid role.';
        }
        if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Password and confirmation do not match.';
        }

        /* ---- Uniqueness ---- */
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => $old['username']]);
        if ($stmt->fetch()) {
            $errors[] = 'That username is already taken.';
        }
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'That email address is already registered.';
        }

        if (empty($errors)) {
            $roleQuery = $db->prepare("SELECT role_id FROM roles WHERE role_name = :r LIMIT 1");
            $roleQuery->execute([':r' => $old['role']]);
            $roleId = (int)$roleQuery->fetchColumn();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                INSERT INTO users (role_id, first_name, last_name, username, email, phone, password, is_active)
                VALUES (:role_id, :f, :l, :u, :e, :p, :pw, :active)
            ");
            $stmt->execute([
                ':role_id' => $roleId,
                ':f'       => $old['first_name'],
                ':l'       => $old['last_name'],
                ':u'       => $old['username'],
                ':e'       => $old['email'],
                ':p'       => $old['phone'] !== '' ? $old['phone'] : null,
                ':pw'      => $hash,
                ':active'  => $old['is_active'],
            ]);
            $newUserId = (int)$db->lastInsertId();

            // Create a staff profile automatically for stylists.
            if ($old['role'] === 'stylist') {
                try {
                    $db->prepare("INSERT INTO staff (user_id, is_available) VALUES (:uid, 1)")->execute([':uid' => $newUserId]);
                } catch (PDOException $e) {
                    // Already exists or table-level constraint - ignore.
                }
            }

            setFlash('success', ucfirst($old['role']) . ' "' . $old['first_name'] . ' ' . $old['last_name'] . '" created successfully.');
            redirect('admin/users.php');
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-user-plus"></i> Add New User</h1>
        <p>Create an admin, receptionist or stylist account.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Users</a>
</div>

<div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10">
        <div class="admin-card">
            <div class="card-body-custom">
                <?php if (!empty($errors)): ?>
                <div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php echo csrfField(); ?>

                    <div class="form-section-title">Personal Information</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="req">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="form-control-admin" value="<?php echo sanitize($old['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="req">*</span></label>
                                <input type="text" id="last_name" name="last_name" class="form-control-admin" value="<?php echo sanitize($old['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="username">Username <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-at"></i>
                                    <input type="text" id="username" name="username" class="form-control-admin" value="<?php echo sanitize($old['username']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email Address <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" class="form-control-admin" value="<?php echo sanitize($old['email']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" id="phone" name="phone" class="form-control-admin" value="<?php echo sanitize($old['phone']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title">Account Access</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">Password <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="password" name="password" class="form-control-admin" required minlength="8">
                                    <i class="fas fa-eye toggle-pw" data-pw-toggle="password" title="Show password" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);cursor:pointer;z-index:2;"></i>
                                </div>
                                <div class="form-text-admin">Minimum 8 characters.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-check-double"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control-admin" required minlength="8">
                                    <i class="fas fa-eye toggle-pw" data-pw-toggle="confirm_password" title="Show password" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);cursor:pointer;z-index:2;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="role">Role <span class="req">*</span></label>
                                <select id="role" name="role" class="form-control-admin">
                                    <option value="receptionist" <?php echo $old['role'] === 'receptionist' ? 'selected' : ''; ?>>Receptionist (User)</option>
                                    <option value="stylist" <?php echo $old['role'] === 'stylist' ? 'selected' : ''; ?>>Stylist</option>
                                    <option value="admin" <?php echo $old['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <div style="display:flex;gap:1.2rem;align-items:center;padding-top:.45rem;">
                                    <label class="remember" style="color:#c3cfe0;font-size:.86rem;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;">
                                        <input type="checkbox" name="is_active" value="1" style="accent-color:#00C2D9;width:16px;height:16px;" <?php echo $old['is_active'] ? 'checked' : ''; ?>>
                                        Active immediately
                                    </label>
                                </div>
                                <div class="form-text-admin">Inactive users cannot sign in.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Create User</button>
                        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>