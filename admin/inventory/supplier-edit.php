<?php
/**
 * Admin – Edit Supplier
 * Update supplier contact and business details.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Edit Supplier';
$activeMenu      = 'inventory';
$inventoryActive = 'suppliers';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

$supplierId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$supplier = invGetSupplier($supplierId);

if (!$supplier) {
    setFlash('error', 'Supplier not found.');
    redirect('admin/inventory/suppliers.php');
}

$old = [
    'company_name'   => $supplier['company_name'],
    'supplier_name'  => $supplier['supplier_name'],
    'contact_person' => $supplier['contact_person'],
    'email'          => $supplier['email'],
    'phone'          => $supplier['phone'],
    'address'        => $supplier['address'],
    'city'           => $supplier['city'],
    'business_id'    => $supplier['business_id'],
    'notes'          => $supplier['notes'],
    'status'         => $supplier['status'] ?? 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['company_name']   = trim($_POST['company_name'] ?? '');
        $old['supplier_name']  = trim($_POST['supplier_name'] ?? '');
        $old['contact_person'] = trim($_POST['contact_person'] ?? '');
        $old['email']          = strtolower(trim($_POST['email'] ?? ''));
        $old['phone']          = trim($_POST['phone'] ?? '');
        $old['address']        = trim($_POST['address'] ?? '');
        $old['city']           = trim($_POST['city'] ?? '');
        $old['business_id']    = trim($_POST['business_id'] ?? '');
        $old['notes']          = trim($_POST['notes'] ?? '');
        $old['status']         = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($old['company_name'] === '' && $old['supplier_name'] === '') {
            $errors[] = 'Provide at least a company or display name.';
        }
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if (empty($errors)) {
            try {
                if ($old['company_name'] !== '') {
                    $chk = $db->prepare("SELECT supplier_id FROM suppliers WHERE LOWER(TRIM(company_name)) = LOWER(:c) AND supplier_id <> :id LIMIT 1");
                    $chk->execute([':c' => $old['company_name'], ':id' => $supplierId]);
                    if ($chk->fetch()) {
                        $errors[] = 'A supplier with that company name already exists.';
                    }
                }
            } catch (PDOException $e) {
                // fallthrough
            }

            if (empty($errors)) {
                try {
                    $stmt = $db->prepare("
                        UPDATE suppliers SET
                            company_name = :c, supplier_name = :s, contact_person = :cp,
                            email = :e, phone = :p, address = :a, city = :city,
                            business_id = :biz, notes = :n, status = :st
                        WHERE supplier_id = :id
                    ");
                    $stmt->execute([
                        ':c'    => $old['company_name'] !== '' ? $old['company_name'] : $old['supplier_name'],
                        ':s'    => $old['supplier_name'] !== '' ? $old['supplier_name'] : null,
                        ':cp'   => $old['contact_person'] !== '' ? $old['contact_person'] : null,
                        ':e'    => $old['email'] !== '' ? $old['email'] : null,
                        ':p'    => $old['phone'] !== '' ? $old['phone'] : null,
                        ':a'    => $old['address'] !== '' ? $old['address'] : null,
                        ':city' => $old['city'] !== '' ? $old['city'] : null,
                        ':biz'  => $old['business_id'] !== '' ? $old['business_id'] : null,
                        ':n'    => $old['notes'] !== '' ? $old['notes'] : null,
                        ':st'   => $old['status'],
                        ':id'   => $supplierId,
                    ]);
                    setFlash('success', 'Supplier updated successfully.');
                    redirect('admin/inventory/supplier-view.php?id=' . $supplierId);
                } catch (PDOException $e) {
                    $errors[] = 'Could not update the supplier: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-pen-to-square"></i> Edit Supplier</h1>
        <p><?php echo sanitize(invSupplierDisplayName($supplier)); ?></p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-view.php?id=<?php echo $supplierId; ?>" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Supplier</a>
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
                    <input type="hidden" name="id" value="<?php echo $supplierId; ?>">

                    <div class="form-section-title"><i class="fas fa-building"></i> Business Information</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="company_name">Company Name</label>
                                <input type="text" id="company_name" name="company_name" class="form-control-admin" value="<?php echo sanitize($old['company_name'] ?? ''); ?>" maxlength="120">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="supplier_name">Display Name</label>
                                <input type="text" id="supplier_name" name="supplier_name" class="form-control-admin" value="<?php echo sanitize($old['supplier_name'] ?? ''); ?>" maxlength="120">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="contact_person">Contact Person</label>
                                <input type="text" id="contact_person" name="contact_person" class="form-control-admin" value="<?php echo sanitize($old['contact_person'] ?? ''); ?>" maxlength="120">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="business_id">Business / Tax ID</label>
                                <input type="text" id="business_id" name="business_id" class="form-control-admin" value="<?php echo sanitize($old['business_id'] ?? ''); ?>" maxlength="80">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" class="form-control-admin" value="<?php echo sanitize($old['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-phone"></i>
                                    <input type="text" id="phone" name="phone" class="form-control-admin" value="<?php echo sanitize($old['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" class="form-control-admin" rows="2"><?php echo sanitize($old['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control-admin" value="<?php echo sanitize($old['city'] ?? ''); ?>" maxlength="80">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" class="form-control-admin" rows="2" maxlength="500"><?php echo sanitize($old['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <div style="display:flex;gap:1.2rem;align-items:center;padding-top:.45rem;">
                                    <label class="remember" style="color:#c3cfe0;font-size:.86rem;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;">
                                        <input type="checkbox" name="status" value="active" style="accent-color:#00C2D9;width:16px;height:16px;" <?php echo $old['status'] === 'active' ? 'checked' : ''; ?>>
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Save Changes</button>
                        <a href="<?php echo SITE_URL; ?>/admin/inventory/suppliers.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>