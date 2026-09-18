<?php
/**
 * Elegance Salon - Receptionist - Change Password
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$userId = currentUserId();

$stmt = $db->prepare("SELECT password FROM users WHERE user_id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$userRow = $stmt->fetch();

if (!$userRow) {
    setFlash('error', 'Account not found.');
    redirect('user/index.php');
}

$minLength = 6;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('user/change-password.php');
    }

    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $userRow['password'])) $errors[] = 'Your current password is incorrect.';
    if (strlen($newPass) < $minLength) $errors[] = 'New password must be at least ' . $minLength . ' characters.';
    if ($newPass !== $confirm) $errors[] = 'New password and confirmation do not match.';

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE users SET password = :p, updated_at = NOW() WHERE user_id = :id");
            $stmt->execute([':p' => password_hash($newPass, PASSWORD_DEFAULT), ':id' => $userId]);
            createNotification($userId, 'Password Changed', 'Your password was changed successfully.');
            setFlash('success', 'Password updated successfully.');
            redirect('user/index.php');
        } catch (PDOException $e) {
            $errors[] = 'Could not update your password. Please try again.';
        }
    }
    foreach ($errors as $err) { setFlash('error', $err); }
}

$pageTitle = "Change Password";
$activeMenu = 'change-password';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-lock"></i> Change Password</h1>
        <p class="page-subtitle">Keep your account secure with a strong password.</p>
    </div>
</div>

<div class="panel" style="max-width:520px">
    <form method="post" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <div class="form-group">
            <label for="current_password">Current Password <span class="req">*</span></label>
            <input type="password" id="current_password" name="current_password" class="form-control-salon" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label for="new_password">New Password <span class="req">*</span></label>
            <input type="password" id="new_password" name="new_password" class="form-control-salon" required autocomplete="new-password">
            <div class="stext muted" style="margin-top:.4rem">At least <?php echo (int)$minLength; ?> characters.</div>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password <span class="req">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control-salon" required autocomplete="new-password">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-user btn-cyan"><i class="fas fa-key"></i> Update Password</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>