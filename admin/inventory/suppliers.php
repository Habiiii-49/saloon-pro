<?php
/**
 * Admin – Suppliers
 * Manage vendors supplying salon products.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Suppliers';
$activeMenu      = 'inventory';
$inventoryActive = 'suppliers';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/inventory/suppliers.php');
    }

    $action = $_POST['action'] ?? '';
    $supplierId = (int)($_POST['id'] ?? 0);

    if ($supplierId <= 0) {
        setFlash('error', 'Invalid supplier selected.');
        redirect('admin/inventory/suppliers.php');
    }

    $stmt = $db->prepare("SELECT * FROM suppliers WHERE supplier_id = :id");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch();

    if (!$supplier) {
        setFlash('error', 'Supplier not found.');
        redirect('admin/inventory/suppliers.php');
    }

    if ($action === 'toggle_status') {
        $stmt = $db->prepare("UPDATE suppliers SET status = IF(status = 'active', 'inactive', 'active') WHERE supplier_id = :id");
        $stmt->execute([':id' => $supplierId]);
        setFlash('success', 'Supplier status updated.');
    } elseif ($action === 'delete') {
        $poCount = $db->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = :id");
        $poCount->execute([':id' => $supplierId]);
        $prodCount = $db->prepare("SELECT COUNT(*) FROM inventory WHERE supplier_id = :id");
        $prodCount->execute([':id' => $supplierId]);

        if ((int)$poCount->fetchColumn() > 0 || (int)$prodCount->fetchColumn() > 0) {
            setFlash('error', 'This supplier has purchase orders or products attached and cannot be deleted. Deactivate them instead.');
        } else {
            $stmt = $db->prepare("DELETE FROM suppliers WHERE supplier_id = :id");
            $stmt->execute([':id' => $supplierId]);
            setFlash('success', 'Supplier "' . invSupplierDisplayName($supplier) . '" deleted.');
        }
    }

    redirect('admin/inventory/suppliers.php');
}

/* ---------- Filters ---------- */
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, ['', 'active', 'inactive'], true)) {
    $status = '';
}

$sql = "
    SELECT s.*,
           (SELECT COUNT(*) FROM inventory i WHERE i.supplier_id = s.supplier_id) AS product_count,
           (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.supplier_id) AS po_count,
           (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.supplier_id AND po.status IN ('pending','ordered','partially_received')) AS open_po_count
    FROM suppliers s
";
$params = [];
$where = [];
if ($search !== '') {
    $where[] = "(COALESCE(s.supplier_name, s.company_name) LIKE :s1 OR s.contact_person LIKE :s2 OR s.email LIKE :s3 OR s.city LIKE :s4)";
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
    $params[':s3'] = '%' . $search . '%';
    $params[':s4'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = "s.status = :st";
    $params[':st'] = $status;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY COALESCE(s.supplier_name, s.company_name) ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-truck"></i> Suppliers</h1>
        <p>Manage your vendors and their purchase history.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-add.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> Add Supplier</a>
</div>

<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-truck"></i> All Suppliers <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($suppliers); ?></span></h5>
        <form method="get" action="" class="d-flex gap-2 flex-wrap">
            <div class="filter-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search name, contact, email or city..." class="form-control-admin form-control-sm">
            </div>
            <select name="status" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button type="submit" class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $status !== ''): ?>
            <a href="<?php echo SITE_URL; ?>/admin/inventory/suppliers.php" class="btn-admin btn-outline btn-xs">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($suppliers)): ?>
    <div class="empty-state">
        <i class="fas fa-truck"></i>
        <h5>No suppliers found</h5>
        <p>Add your first supplier to streamline purchase orders.</p>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-add.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> Add Supplier</a>
    </div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Supplier</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Products</th>
                    <th>Open POs</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $sup): ?>
                <tr>
                    <td>
                        <span class="cell-strong"><?php echo sanitize(invSupplierDisplayName($sup)); ?></span>
                        <div class="cell-sub"><?php echo sanitize($sup['email'] ?? '—'); ?></div>
                    </td>
                    <td class="cell-sub"><?php echo $sup['contact_person'] ? sanitize($sup['contact_person']) : '—'; ?></td>
                    <td class="cell-sub"><?php echo $sup['phone'] ? sanitize($sup['phone']) : '—'; ?></td>
                    <td class="cell-sub"><?php echo $sup['city'] ? sanitize($sup['city']) : '—'; ?></td>
                    <td><?php echo (int)$sup['product_count']; ?></td>
                    <td>
                        <?php if ((int)$sup['open_po_count'] > 0): ?>
                        <span class="status-badge pending"><?php echo (int)$sup['open_po_count']; ?></span>
                        <?php else: ?><span class="cell-sub">0</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $sup['status'] === 'active' ? 'active' : 'inactive'; ?>">
                            <?php echo $sup['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-view.php?id=<?php echo (int)$sup['supplier_id']; ?>" class="btn-admin btn-outline btn-xs" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php?supplier=<?php echo (int)$sup['supplier_id']; ?>" class="btn-admin btn-outline btn-xs" title="Create PO"><i class="fas fa-file-signature"></i></a>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-edit.php?id=<?php echo (int)$sup['supplier_id']; ?>" class="btn-admin btn-outline btn-xs" title="Edit"><i class="fas fa-pen"></i></a>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo (int)$sup['supplier_id']; ?>">
                                <button type="submit" class="btn-admin btn-outline btn-xs" title="<?php echo $sup['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $sup['status'] === 'active' ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                </button>
                            </form>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$sup['supplier_id']; ?>">
                                <button type="submit"
                                        data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                            'title'       => 'Delete supplier?',
                                            'message'     => 'This permanently removes "' . invSupplierDisplayName($sup) . '". Suppliers with products or purchase orders are protected.',
                                            'confirmText' => 'Delete',
                                        ]), ENT_QUOTES); ?>'
                                        class="btn-admin btn-danger-outline btn-xs" title="Delete">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>