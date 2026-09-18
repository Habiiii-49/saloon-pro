<?php
/**
 * Elegance Salon - Receptionist - My Profile
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$userId = currentUserId();

$stmt = $db->prepare("SELECT user_id, first_name, last_name, username, email, phone, avatar, created_at FROM users WHERE user_id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'Account not found.');
    redirect('user/index.php');
}

$errors = [];
$old = ['first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'email' => $user['email'], 'phone' => $user['phone']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('user/profile.php');
    }

    $old = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'phone'      => trim($_POST['phone'] ?? ''),
    ];

    if ($old['first_name'] === '') $errors[] = 'First name is required.';
    if ($old['last_name'] === '') $errors[] = 'Last name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if ($old['phone'] !== '' && !preg_match('/^[+\d][\d\s\-()]{5,19}$/', $old['phone'])) $errors[] = 'Please enter a valid phone number.';

    /* avatar upload */
    $avatarPath = $user['avatar'];
    if (empty($errors) && !empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
        finfo_close($finfo);
        if (!isset($allowed[$ext]) || $mime !== $allowed[$ext]) {
            $errors[] = 'Avatar must be a JPG, PNG or GIF image.';
        } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Avatar must be smaller than 2MB.';
        } else {
            $dir = __DIR__ . '/../uploads/avatars';
            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
            $filename = 'u' . $userId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $filename)) {
                $avatarPath = 'uploads/avatars/' . $filename;
            } else {
                $errors[] = 'Could not upload the avatar image.';
            }
        }
    }

    if (empty($errors)) {
        $dup = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :e AND user_id <> :id");
        $dup->execute([':e' => $old['email'], ':id' => $userId]);
        if ((int)$dup->fetchColumn() > 0) {
            $errors[] = 'This email is already in use by another account.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE users SET first_name = :f, last_name = :l, email = :e, phone = :p, avatar = :a, updated_at = NOW() WHERE user_id = :id");
            $stmt->execute([
                ':f' => $old['first_name'], ':l' => $old['last_name'], ':e' => $old['email'],
                ':p' => $old['phone'] !== '' ? $old['phone'] : null,
                ':a' => $avatarPath, ':id' => $userId,
            ]);
            setFlash('success', 'Profile updated successfully.');
            redirect('user/profile.php');
        } catch (PDOException $e) {
            $errors[] = 'Could not update your profile. Please try again.';
        }
    }
    foreach ($errors as $err) { setFlash('error', $err); }
}

$pageTitle = "My Profile";
$activeMenu = 'profile';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-id-badge"></i> My Profile</h1>
        <p class="page-subtitle">Update your account information and photo.</p>
    </div>
</div>

<div class="panel" style="max-width:760px">
    <form method="post" action="" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

        <div class="profile-avatar-row">
            <div class="cr-avatar lg" style="--size:86px;font-size:1.6rem">
                <?php if ($old['avatar'] ?? $user['avatar']): ?>
                    <img src="<?php echo SITE_URL . '/' . sanitize($old['avatar'] ?? $user['avatar']); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(mb_substr($user['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div>
                <label class="btn-user btn-outline btn-sm" style="cursor:pointer"><i class="fas fa-camera"></i> Change Photo
                    <input type="file" name="avatar" accept="image/*" style="display:none">
                </label>
                <div class="stext muted" style="margin-top:.35rem">JPG / PNG / GIF up to 2MB</div>
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>First Name <span class="req">*</span></label>
                <input type="text" name="first_name" class="form-control-salon" value="<?php echo sanitize($old['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Last Name <span class="req">*</span></label>
                <input type="text" name="last_name" class="form-control-salon" value="<?php echo sanitize($old['last_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control-salon" value="<?php echo sanitize($user['username']); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" class="form-control-salon" value="<?php echo sanitize($old['phone']); ?>">
            </div>
            <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" class="form-control-salon" value="<?php echo sanitize($old['email']); ?>" required>
            </div>
            <div class="form-group">
                <label>Member Since</label>
                <input type="text" class="form-control-salon" value="<?php echo formatDate($user['created_at'], 'M d, Y'); ?>" disabled>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-user btn-cyan"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>