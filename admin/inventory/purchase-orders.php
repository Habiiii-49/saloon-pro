<?php
/**
 * Admin – Purchase Orders
 * List, filter, cancel or delete purchase orders.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Purchase Orders';
$activeMenu      = 'inventory';
$inventoryActive = 'purchase-orders';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/inventory/purchase-orders.php');
    }

    $action  = $_POST['action'] ?? '';
    $poId    = (int)($_POST['id'] ?? 0);
    $po      = $poId > 0 ? invGetPurchaseOrder($poId) : null;

    if (!$po) {
        setFlash('error', 'Purchase order not found.');
        redirect('admin/inventory/purchase-orders.php');
    }

    if ($action === 'mark_ordered') {
        if (!in_array($po['status'], ['draft', 'pending'], true)) {
            setFlash('error', 'Only draft/pending purchase orders can be marked as ordered.');
        } else {
            $db->prepare("UPDATE purchase_orders SET status = 'ordered' WHERE id = :id")->execute([':id' => $poId]);
            setFlash('success', 'Purchase order ' . $po['po_number'] . ' marked as ordered.');
        }
    } elseif ($action === 'cancel') {
        if (!invPOEditable($po['status'])) {
            setFlash('error', 'This purchase order cannot be cancelled anymore — it already has received stock.');
        } else {
            $db->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = :id")->execute([':id' => $poId]);
            setFlash('success', 'Purchase order ' . $po['po_number'] . ' cancelled.');
        }
    } elseif ($action === 'delete') {
        if ($po['status'] !== 'draft') {
            setFlash('error', 'Only draft purchase orders can be deleted.');
        } else {
            try {
                $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = :id")->execute([':id' => $poId]);
                $db->prepare("DELETE FROM purchase_orders WHERE id = :id")->execute([':id' => $poId]);
                setFlash('success', 'Purchase order ' . $po['po_number'] . ' deleted.');
            } catch (PDOException $e) {
                setFlash('error', 'Could not delete the purchase order: ' . $e->getMessage());
            }
        }
    }

    redirect('admin/inventory/purchase-orders.php');
}

/* ---------- Filters ---------- */
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
if (!in_array($status, ['', 'draft', 'pending', 'ordered', 'partially_received', 'received', 'cancelled'], true)) {
    $status = '';
}
$supplierId = (int)($_GET['supplier'] ?? 0);

$sql = "
    SELECT po.*,
           COALESCE(s.supplier_name, s.company_name) AS supplier_name,
           (SELECT COUNT(*) FROM purchase_order_items i WHERE i.purchase_order_id = po.id) AS line_count,
           (SELECT COALESCE(SUM(i.received_quantity),0) FROM purchase_order_items i WHERE i.purchase_order_id = po.id) AS total_received
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
";
$params = [];
$where = [];
if ($search !== '') {
    $where[] = "(po.po_number LIKE :s1 OR COALESCE(s.supplier_name, s.company_name) LIKE :s2)";
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = "po.status = :st";
    $params[':st'] = $status;
}
if ($supplierId > 0) {
    $where[] = "po.supplier_id = :sup";
    $params[':sup'] = $supplierId;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY po.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-file-signature"></i> Purchase Orders</h1>
        <p>Track supplier orders from draft to delivery.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> New Purchase Order</a>
</div>

<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-file-signature"></i> All Purchase Orders <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($orders); ?></span></h5>
        <form method="get" action="" class="d-flex gap-2 flex-wrap">
            <div class="filter-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search PO number or supplier..." class="form-control-admin form-control-sm">
            </div>
            <select name="status" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (invPOStatuses() as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $status !== '' || $supplierId > 0): ?>
            <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-orders.php" class="btn-admin btn-outline btn-xs">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <i class="fas fa-file-signature"></i>
        <h5>No purchase orders found</h5>
        <p>Create your first purchase order to restock your salon.</p>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> New Purchase Order</a>
    </div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $po): ?>
                <?php
                $receivedOk = (int)$po['total_received'] > 0;
                $editable = invPOEditable($po['status']);
                ?>
                <tr>
                    <td class="cell-strong"><?php echo sanitize($po['po_number']); ?></td>
                    <td class="cell-sub"><?php echo sanitize($po['supplier_name'] ?? '—'); ?></td>
                    <td><span class="cell-sub"><?php echo formatDate($po['order_date'], 'M d, Y'); ?></span></td>
                    <td><span class="cell-sub"><?php echo $po['expected_date'] && $po['expected_date'] !== '0000-00-00' ? formatDate($po['expected_date'], 'M d, Y') : '—'; ?></span></td>
                    <td><?php echo (int)$po['line_count']; ?></td>
                    <td class="cell-sub">$<?php echo number_format((float)$po['grand_total'], 2); ?></td>
                    <td>
                        <?php if ($receivedOk): ?>
                        <span class="status-badge confirmed"><?php echo (int)$po['total_received']; ?></span>
                        <?php else: ?><span class="cell-sub">—</span><?php endif; ?>
                    </td>
                    <td><span class="po-status <?php echo $po['status']; ?>"><?php echo str_replace('_', ' ', ucwords($po['status'])); ?></span></td>
                    <td>
                        <div class="action-btns">
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-view.php?id=<?php echo (int)$po['id']; ?>" class="btn-admin btn-outline btn-xs" title="View / Print"><i class="fas fa-eye"></i></a>
                            <?php if ($editable): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-edit.php?id=<?php echo (int)$po['id']; ?>" class="btn-admin btn-outline btn-xs" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                            <?php if (in_array($po['status'], ['ordered', 'partially_received'], true)): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-receive.php?id=<?php echo (int)$po['id']; ?>" class="btn-admin btn-cyan btn-xs" title="Receive"><i class="fas fa-truck-ramp-box"></i></a>
                            <?php endif; ?>
                            <?php if ($po['status'] === 'draft'): ?>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="mark_ordered">
                                <input type="hidden" name="id" value="<?php echo (int)$po['id']; ?>">
                                <button type="submit" class="btn-admin btn-outline btn-xs" title="Mark as ordered"><i class="fas fa-paper-plane"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($editable): ?>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?php echo (int)$po['id']; ?>">
                                <button type="submit"
                                        data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                            'title'       => 'Cancel purchase order?',
                                            'message'     => 'This marks "' . $po['po_number'] . '" as cancelled. This cannot be undone.',
                                            'confirmText' => 'Cancel PO',
                                        ]), ENT_QUOTES); ?>'
                                        class="btn-admin btn-danger-outline btn-xs" title="Cancel"><i class="fas fa-ban"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($po['status'] === 'draft'): ?>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$po['id']; ?>">
                                <button type="submit"
                                        data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                            'title'       => 'Delete draft PO?',
                                            'message'     => 'This permanently removes "' . $po['po_number'] . '".',
                                            'confirmText' => 'Delete',
                                        ]), ENT_QUOTES); ?>'
                                        class="btn-admin btn-danger-outline btn-xs" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
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