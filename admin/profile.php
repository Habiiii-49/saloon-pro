<?php
/**
 * Admin Profile – view & update own account details.
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'My Profile';
$activeMenu = 'profile';

$db = getDBConnection();
$user = getUserById(currentUserId());
if (!$user) {
    setFlash('error', 'Account not found.');
    redirect('index.php');
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First and last name are required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if (empty($errors)) {
            // Ensure the email is not already used by another account.
            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email AND user_id <> :id LIMIT 1");
            $stmt->execute([':email' => $email, ':id' => $user['user_id']]);
            if ($stmt->fetch()) {
                $errors[] = 'That email address is already in use by another account.';
            } else {
                $stmt = $db->prepare("UPDATE users SET first_name = :f, last_name = :l, email = :e, phone = :p WHERE user_id = :id");
                $stmt->execute([
                    ':f' => $firstName,
                    ':l' => $lastName,
                    ':e' => $email,
                    ':p' => $phone !== '' ? $phone : null,
                    ':id' => $user['user_id'],
                ]);

                // Keep session display name in sync.
                $_SESSION['name']       = trim($firstName . ' ' . $lastName);
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name']  = $lastName;
                $_SESSION['email']      = $email;

                setFlash('success', 'Profile updated successfully.');
                redirect('admin/profile.php');
            }
        }
    }
}

$user = getUserById(currentUserId());

$joinDate  = isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—';
$lastLogin = !empty($user['last_login']) ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'Never';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-user-circle"></i> My Profile</h1>
        <p>View and update your personal information.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/change-password.php" class="btn-admin btn-outline"><i class="fas fa-key"></i> Change Password</a>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="admin-card">
            <div class="card-body-custom">
                <div class="profile-hero mb-4">
                    <div class="profile-avatar-lg"><?php echo strtoupper(mb_substr($user['first_name'], 0, 1)); ?></div>
                    <div>
                        <h2><?php echo sanitize($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                        <span class="role-chip"><i class="fas fa-shield-halved"></i> <?php echo roleLabel($user['role_name']); ?></span>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-item"><div class="lbl">Username</div><div class="val"><?php echo sanitize($user['username'] ?? '—'); ?></div></div>
                    <div class="info-item"><div class="lbl">Role</div><div class="val"><?php echo roleLabel($user['role_name']); ?></div></div>
                    <div class="info-item"><div class="lbl">Account Created</div><div class="val"><?php echo $joinDate; ?></div></div>
                    <div class="info-item"><div class="lbl">Last Login</div><div class="val"><?php echo $lastLogin; ?></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-pen"></i> Edit Profile</h5>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($errors)): ?>
                <div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php echo csrfField(); ?>

                    <div class="form-section-title">Basic Information</div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="req">*</span></label>
                                <input type="text" id="first_name" name="first_name" class="form-control-admin"
                                       value="<?php echo sanitize($user['first_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="req">*</span></label>
                                <input type="text" id="last_name" name="last_name" class="form-control-admin"
                                       value="<?php echo sanitize($user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email Address <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" class="form-control-admin"
                                           value="<?php echo sanitize($user['email']); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" id="phone" name="phone" class="form-control-admin"
                                           value="<?php echo sanitize($user['phone'] ?? ''); ?>"
                                           placeholder="e.g. +1 555 123 4567">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Save Changes</button>
                        <a href="<?php echo SITE_URL; ?>/admin/index.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>