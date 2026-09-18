<?php
/**
 * Elegance Salon - Receptionist - Add Client
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$stylists = getActiveStylists();
$services = getReceptionistServices();

$back = ($_GET['back'] ?? '') === 'appointment-add' ? 'appointment-add' : 'clients';

$errors = [];
$old = [
    'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'dob' => '',
    'gender' => '', 'address' => '', 'preferred_staff_id' => '', 'preferred_service_id' => '', 'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('user/client-add.php');
    }

    foreach (array_keys($old) as $k) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    // Validation
    if ($old['first_name'] === '') $errors[] = 'First name is required.';
    if ($old['last_name'] === '') $errors[] = 'Last name is required.';

    if ($old['phone'] !== '') {
        if (!preg_match('/^[+\d][\d\s\-()]{5,19}$/', $old['phone'])) {
            $errors[] = 'Please enter a valid phone number.';
        }
    }
    if ($old['email'] !== '') {
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $chk = $db->prepare("SELECT client_id FROM clients WHERE email = :e LIMIT 1");
            $chk->execute([':e' => $old['email']]);
            if ($chk->fetch()) {
                $errors[] = 'A client with this email already exists. Use the existing client instead.';
            }
        }
    }
    if ($old['dob'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['dob'])) {
        $errors[] = 'Please enter a valid date of birth.';
    }
    if ($old['gender'] !== '' && !in_array($old['gender'], ['male', 'female', 'other'], true)) {
        $errors[] = 'Please choose a valid gender.';
    }
    if ($old['preferred_staff_id'] !== '') {
        $ok = false;
        foreach ($stylists as $s) { if ((int)$s['staff_id'] === (int)$old['preferred_staff_id']) { $ok = true; break; } }
        if (!$ok) $errors[] = 'Please choose a valid preferred stylist.';
    }
    if ($old['preferred_service_id'] !== '') {
        $ok = false;
        foreach ($services as $sv) { if ((int)$sv['service_id'] === (int)$old['preferred_service_id']) { $ok = true; break; } }
        if (!$ok) $errors[] = 'Please choose a valid preferred service.';
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO clients (first_name, last_name, email, phone, dob, gender, address, preferred_staff_id, preferred_service_id, notes, last_visit)
                VALUES (:f, :l, :e, :p, :d, :g, :a, :ps, :pr, :n, NULL)
            ");
            $stmt->execute([
                ':f' => $old['first_name'],
                ':l' => $old['last_name'],
                ':e' => $old['email'] !== '' ? $old['email'] : null,
                ':p' => $old['phone'] !== '' ? $old['phone'] : null,
                ':d' => $old['dob'] !== '' ? $old['dob'] : null,
                ':g' => $old['gender'] !== '' ? $old['gender'] : null,
                ':a' => $old['address'] !== '' ? $old['address'] : null,
                ':ps' => $old['preferred_staff_id'] !== '' ? (int)$old['preferred_staff_id'] : null,
                ':pr' => $old['preferred_service_id'] !== '' ? (int)$old['preferred_service_id'] : null,
                ':n' => $old['notes'] !== '' ? $old['notes'] : null,
            ]);
            $newId = (int)$db->lastInsertId();
            createNotification(null, 'New Client Added', "{$old['first_name']} {$old['last_name']} was added to the client book.", 'success');
            if ($back === 'appointment-add') {
                setFlash('success', 'Client added. Return to the booking wizard to continue.');
                redirect('user/appointment-add.php');
            }
            setFlash('success', 'Client added successfully.');
            redirect('user/client-view.php?client_id=' . $newId);
        } catch (PDOException $e) {
            $errors[] = 'Could not add the client. Please try again.';
        }
    }
    foreach ($errors as $err) { setFlash('error', $err); }
}

$pageTitle = "Add Client";
$activeMenu = 'client-add';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-plus"></i> Add Client</h1>
        <p class="page-subtitle">Register a new client in the salon book.</p>
    </div>
    <div class="page-head-right">
        <a href="<?php echo SITE_URL; ?>/user/clients.php" class="btn-user btn-outline"><i class="fas fa-arrow-left"></i> Back to Clients</a>
    </div>
</div>

<form method="post" action="" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

    <div class="panel">
        <div class="panel-head"><h5><i class="fas fa-circle-user"></i> Personal Information</h5></div>
        <div class="panel-body">
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
                    <label>Phone</label>
                    <input type="tel" name="phone" class="form-control-salon" value="<?php echo sanitize($old['phone']); ?>" placeholder="+92 300 1234567">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control-salon" value="<?php echo sanitize($old['email']); ?>">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" class="form-control-salon" value="<?php echo sanitize($old['dob']); ?>">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control-salon">
                        <option value="">— Select —</option>
                        <option value="male" <?php echo $old['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo $old['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo $old['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control-salon" value="<?php echo sanitize($old['address']); ?>" placeholder="Street, City">
                </div>
                <div class="form-group">
                    <label>Preferred Stylist</label>
                    <select name="preferred_staff_id" class="form-control-salon">
                        <option value="">— None —</option>
                        <?php foreach ($stylists as $s): ?>
                        <option value="<?php echo (int)$s['staff_id']; ?>" <?php echo (int)$old['preferred_staff_id'] === (int)$s['staff_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize(trim($s['first_name'] . ' ' . $s['last_name'])); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Preferred Service</label>
                    <select name="preferred_service_id" class="form-control-salon">
                        <option value="">— None —</option>
                        <?php foreach ($services as $sv): ?>
                        <option value="<?php echo (int)$sv['service_id']; ?>" <?php echo (int)$old['preferred_service_id'] === (int)$sv['service_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($sv['service_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control-salon" placeholder="Preferences, allergies..."><?php echo sanitize($old['notes']); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-user btn-cyan"><i class="fas fa-save"></i> Add Client</button>
                <a href="<?php echo SITE_URL; ?>/user/clients.php" class="btn-user btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>