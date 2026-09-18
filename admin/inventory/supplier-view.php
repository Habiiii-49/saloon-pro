<?php
/**
 * Admin – Supplier View
 * Supplier profile with their products and purchase order history.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Supplier Details';
$activeMenu      = 'inventory';
$inventoryActive = 'suppliers';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

$supplierId = (int)($_GET['id'] ?? 0);
$supplier = invGetSupplier($supplierId);

if (!$supplier) {
    setFlash('error', 'Supplier not found.');
    redirect('admin/inventory/suppliers.php');
}

/* ---------- Stats ---------- */
$stats = [
    'product_count' => 0,
    'stock_value'   => 0.0,
    'po_count'      => 0,
    'po_value'      => 0.0,
    'open_po_count' => 0,
];
try {
    $pc = $db->prepare("SELECT COUNT(*) FROM inventory WHERE supplier_id = :id");
    $pc->execute([':id' => $supplierId]);
    $stats['product_count'] = (int)$pc->fetchColumn();
    $stats['stock_value']   = (float)$db->query("SELECT COALESCE(SUM(quantity * cost_price),0) FROM inventory WHERE supplier_id = " . $supplierId)->fetchColumn();
    $stats['po_count']      = (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = " . $supplierId)->fetchColumn();
    $stats['po_value']      = (float)$db->query("SELECT COALESCE(SUM(grand_total),0) FROM purchase_orders WHERE supplier_id = " . $supplierId . " AND status NOT IN ('draft','cancelled')")->fetchColumn();
    $stats['open_po_count'] = (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = " . $supplierId . " AND status IN ('pending','ordered','partially_received')")->fetchColumn();
} catch (PDOException $e) {
    $stats = array_map('intval', $stats);
}

$products = [];
try {
    $products = $db->query("SELECT inventory_id, item_name, sku, quantity, cost_price, selling_price, status FROM inventory WHERE supplier_id = " . $supplierId . " ORDER BY item_name ASC")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

$pos = [];
try {
    $pos = $db->query("SELECT id, po_number, order_date, expected_date, grand_total, status FROM purchase_orders WHERE supplier_id = " . $supplierId . " ORDER BY id DESC LIMIT 12")->fetchAll();
} catch (PDOException $e) {
    $pos = [];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-truck"></i> <?php echo sanitize(invSupplierDisplayName($supplier)); ?></h1>
        <p><?php echo $supplier['supplier_name'] && $supplier['supplier_name'] !== $supplier['company_name'] ? 'Company: ' . sanitize($supplier['company_name']) : sanitize($supplier['city'] ?? ''); ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php?supplier=<?php echo (int)$supplier['supplier_id']; ?>" class="btn-admin btn-cyan"><i class="fas fa-file-signature"></i> New Purchase Order</a>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-edit.php?id=<?php echo (int)$supplier['supplier_id']; ?>" class="btn-admin btn-outline"><i class="fas fa-pen"></i> Edit</a>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/suppliers.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Suppliers</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom"><h5><i class="fas fa-circle-info"></i> Details</h5></div>
            <div class="card-body-custom">
                <div class="info-list">
                    <div class="info-item"><div class="lbl">Company Name</div><div class="val"><?php echo sanitize($supplier['company_name']); ?></div></div>
                    <?php if ($supplier['supplier_name']): ?>
                    <div class="info-item"><div class="lbl">Display Name</div><div class="val"><?php echo sanitize($supplier['supplier_name']); ?></div></div>
                    <?php endif; ?>
                    <?php if ($supplier['contact_person']): ?>
                    <div class="info-item"><div class="lbl">Contact Person</div><div class="val"><?php echo sanitize($supplier['contact_person']); ?></div></div>
                    <?php endif; ?>
                    <?php if ($supplier['email']): ?>
                    <div class="info-item"><div class="lbl">Email</div><div class="val" style="word-break:break-all;"><?php echo sanitize($supplier['email']); ?></div></div>
                    <?php endif; ?>
                    <?php if ($supplier['phone']): ?>
                    <div class="info-item"><div class="lbl">Phone</div><div class="val"><?php echo sanitize($supplier['phone']); ?></div></div>
                    <?php endif; ?>
                    <?php if ($supplier['address'] || $supplier['city']): ?>
                    <div class="info-item"><div class="lbl">Address</div><div class="val"><?php echo nl2br(sanitize(trim($supplier['address'] . ($supplier['city'] ? ', ' . $supplier['city'] : '')))); ?></div></div>
                    <?php endif; ?>
                    <?php if ($supplier['business_id']): ?>
                    <div class="info-item"><div class="lbl">Business / Tax ID</div><div class="val"><?php echo sanitize($supplier['business_id']); ?></div></div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="lbl">Status</div>
                        <div class="val"><span class="status-badge <?php echo $supplier['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo $supplier['status'] === 'active' ? 'Active' : 'Inactive'; ?></span></div>
                    </div>
                </div>
                <?php if ($supplier['notes']): ?>
                <div class="mt-3">
                    <div class="lbl mb-1">Notes</div>
                    <div class="cell-sub"><?php echo nl2br(sanitize($supplier['notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="stat-grid" style="--cols:2;">
            <div class="stat-card">
                <div class="stat-top"><span class="stat-icon cyan"><i class="fas fa-box"></i></span><span class="stat-trend info">Products</span></div>
                <div class="stat-value" data-count="<?php echo (int)$stats['product_count']; ?>">0</div>
                <div class="stat-label">Supplied Products</div>
                <div class="stat-sub">In your catalogue</div>
            </div>
            <div class="stat-card">
                <div class="stat-top"><span class="stat-icon blue"><i class="fas fa-sack-dollar"></i></span><span class="stat-trend info">Value</span></div>
                <div class="stat-value" data-prefix="$" data-decimals="0" data-count="<?php echo (int)$stats['stock_value']; ?>">$0</div>
                <div class="stat-label">Stock Value at Cost</div>
                <div class="stat-sub">Products from this supplier</div>
            </div>
            <div class="stat-card">
                <div class="stat-top"><span class="stat-icon gold"><i class="fas fa-file-signature"></i></span><span class="stat-trend info">Orders</span></div>
                <div class="stat-value" data-count="<?php echo (int)$stats['po_count']; ?>">0</div>
                <div class="stat-label">Purchase Orders</div>
                <div class="stat-sub"><?php echo '$' . number_format((float)$stats['po_value'], 0); ?> spent</div>
            </div>
            <div class="stat-card">
                <div class="stat-top"><span class="stat-icon orange"><i class="fas fa-hourglass-half"></i></span><span class="stat-trend warn">Open</span></div>
                <div class="stat-value" data-count="<?php echo (int)$stats['open_po_count']; ?>">0</div>
                <div class="stat-label">Open Purchase Orders</div>
                <div class="stat-sub">Awaiting delivery</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-file-signature"></i> Purchase Orders</h5>
            </div>
            <?php if (empty($pos)): ?>
            <div class="empty-state"><i class="fas fa-file-signature"></i><h5>No purchase orders yet</h5><p>Create the first purchase order for this supplier.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>PO #</th><th>Order Date</th><th>Expected</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($pos as $po): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($po['po_number']); ?></td>
                            <td><span class="cell-sub"><?php echo formatDate($po['order_date'], 'M d, Y'); ?></span></td>
                            <td><span class="cell-sub"><?php echo $po['expected_date'] && $po['expected_date'] !== '0000-00-00' ? formatDate($po['expected_date'], 'M d, Y') : '—'; ?></span></td>
                            <td class="cell-sub">$<?php echo number_format((float)$po['grand_total'], 2); ?></td>
                            <td><span class="po-status <?php echo $po['status']; ?>"><?php echo str_replace('_', ' ', ucwords($po['status'])); ?></span></td>
                            <td><a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-view.php?id=<?php echo (int)$po['id']; ?>" class="btn-admin btn-outline btn-xs"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-box"></i> Products from this Supplier</h5>
            </div>
            <?php if (empty($products)): ?>
            <div class="empty-state"><i class="fas fa-box-open"></i><h5>No products linked</h5><p>Assign products to this supplier from the product editor.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Stock</th><th>Cost</th></tr></thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><a href="<?php echo SITE_URL; ?>/admin/inventory/product-view.php?id=<?php echo (int)$p['inventory_id']; ?>" class="cell-strong" style="color:var(--accent-bright);"><?php echo sanitize($p['item_name']); ?></a></td>
                            <td><span class="stock-count"><?php echo (int)$p['quantity']; ?></span></td>
                            <td class="cell-sub">$<?php echo number_format((float)$p['cost_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>