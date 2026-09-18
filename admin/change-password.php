<?php
/**
 * Admin – Change Password
 * Verifies the current password, validates the new one, hashes and updates.
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'Change Password';
$activeMenu = 'profile';

$db = getDBConnection();
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $current   = (string)($_POST['current_password'] ?? '');
        $newPass   = (string)($_POST['new_password'] ?? '');
        $confirm   = (string)($_POST['confirm_password'] ?? '');

        if ($current === '') {
            $errors[] = 'Please enter your current password.';
        }
        if (strlen($newPass) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }
        if ($newPass !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (empty($errors)) {
            // Verify current password using stored hash.
            $stmt = $db->prepare("SELECT password FROM users WHERE user_id = :id LIMIT 1");
            $stmt->execute([':id' => currentUserId()]);
            $storedHash = $stmt->fetchColumn();

            if (!$storedHash || !password_verify($current, (string)$storedHash)) {
                $errors[] = 'Your current password is incorrect.';
            } elseif (password_verify($newPass, (string)$storedHash)) {
                $errors[] = 'New password must be different from the current password.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password = :p WHERE user_id = :id");
                $stmt->execute([':p' => $newHash, ':id' => currentUserId()]);

                setFlash('success', 'Your password has been changed successfully.');
                redirect('admin/change-password.php');
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-key"></i> Change Password</h1>
        <p>Strengthen your account security by updating your password.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/profile.php" class="btn-admin btn-outline"><i class="fas fa-user"></i> Back to Profile</a>
</div>

<div class="row justify-content-center">
    <div class="col-xl-7 col-lg-9">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-lock"></i> Update Password</h5>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($errors)): ?>
                <div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label for="current_password">Current Password <span class="req">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="current_password" name="current_password" class="form-control-admin" required autocomplete="current-password">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password <span class="req">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-lock-open"></i>
                            <input type="password" id="new_password" name="new_password" class="form-control-admin" required minlength="8" autocomplete="new-password">
                            <i class="fas fa-eye toggle-pw" data-pw-toggle="new_password" title="Show password" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);cursor:pointer;z-index:2;"></i>
                        </div>
                        <div class="form-text-admin">Minimum 8 characters. Use a mix of letters, numbers and symbols for a stronger password.</div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="req">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-check-double"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control-admin" required minlength="8" autocomplete="new-password">
                            <i class="fas fa-eye toggle-pw" data-pw-toggle="confirm_password" title="Show password" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:var(--text-muted);cursor:pointer;z-index:2;"></i>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Update Password</button>
                        <a href="<?php echo SITE_URL; ?>/admin/profile.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>