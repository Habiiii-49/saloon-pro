<?php
/**
 * Admin – Product View
 * Full product profile: stock, valuation, transaction history and purchase orders.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Product Details';
$activeMenu      = 'inventory';
$inventoryActive = 'products';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

$productId = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT i.*, COALESCE(c.name, i.category, 'Uncategorized') AS category_name,
           COALESCE(s.supplier_name, s.company_name) AS supplier_name,
           s.phone AS supplier_phone, s.supplier_id AS real_supplier_id
    FROM inventory i
    LEFT JOIN inventory_categories c ON c.id = i.category_id
    LEFT JOIN suppliers s ON s.supplier_id = i.supplier_id
    WHERE i.inventory_id = :id
");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'Product not found.');
    redirect('admin/inventory/products.php');
}

/* ---------- Stock stats ---------- */
$stock = [
    'transactions' => 0,
    'total_in'     => 0,
    'total_out'    => 0,
    'moves'        => [],
    'POS'          => [],
];
$cntStmt = $db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE product_id = :id");
$cntStmt->execute([':id' => $productId]);
$stock['transactions'] = (int)$cntStmt->fetchColumn();

try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END),0) AS tin, COALESCE(SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END),0) AS tout FROM inventory_transactions WHERE product_id = :id");
    $stmt->execute([':id' => $productId]);
    $agg = $stmt->fetch();
    $stock['total_in']  = (int)$agg['tin'];
    $stock['total_out'] = (int)$agg['tout'];

    $stmt = $db->prepare("
        SELECT t.id, t.transaction_type, t.quantity, t.previous_stock, t.new_stock,
               t.unit_cost, t.reference, t.reason, t.created_at,
               CONCAT(u.first_name,' ',u.last_name) AS user_name
        FROM inventory_transactions t
        LEFT JOIN users u ON u.user_id = t.user_id
        WHERE t.product_id = :id
        ORDER BY t.id DESC
        LIMIT 15
    ");
    $stmt->execute([':id' => $productId]);
    $stock['moves'] = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT poi.purchase_order_id, poi.ordered_quantity, poi.received_quantity, poi.unit_cost, poi.total,
               po.po_number, po.order_date, po.expected_date, po.status,
               COALESCE(s.supplier_name, s.company_name) AS supplier_name
        FROM purchase_order_items poi
        JOIN purchase_orders po ON po.id = poi.purchase_order_id
        LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
        WHERE poi.product_id = :id
        ORDER BY po.id DESC
        LIMIT 10
    ");
    $stmt->execute([':id' => $productId]);
    $stock['POS'] = $stmt->fetchAll();
} catch (PDOException $e) {
    $stock['moves'] = [];
    $stock['POS']   = [];
}

$qty        = (int)$product['quantity'];
$minQty     = (int)$product['minimum_stock'];
$maxQty     = (int)$product['maximum_stock'];
$stockStatus = $qty === 0 ? 'out' : ($qty <= $minQty ? 'low' : 'ok');
$stockPct    = $maxQty > 0 ? (int)min(100, floor($qty / $maxQty * 100)) : 0;
$valueCost   = (int)$qty * (float)$product['cost_price'];
$valueSell   = (int)$qty * (float)$product['selling_price'];
$netMovements = $stock['total_in'] - $stock['total_out'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-box"></i> <?php echo sanitize($product['item_name']); ?></h1>
        <p>SKU: <?php echo sanitize($product['sku']); ?><?php echo $product['category_name'] !== 'Uncategorized' ? ' &middot; ' . sanitize($product['category_name']) : ''; ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo SITE_URL; ?>/admin/inventory/stock-in.php?id=<?php echo $productId; ?>" class="btn-admin btn-cyan btn-sm"><i class="fas fa-plus"></i> Stock In</a>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/product-edit.php?id=<?php echo $productId; ?>" class="btn-admin btn-outline"><i class="fas fa-pen"></i> Edit</a>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Products</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Stock level -->
    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-warehouse"></i> Current Stock</h5>
            </div>
            <div class="card-body-custom text-center">
                <div class="stock-big <?php echo $stockStatus; ?>">
                    <span><?php echo $qty; ?></span>
                    <small><?php echo strtoupper(sanitize($product['unit'] ?? 'pcs')); ?> in stock</small>
                </div>
                <div class="stock-meter stock-meter-lg mt-3">
                    <span class="stock-meter-fill <?php echo $stockStatus === 'out' ? 'fill-out' : ($stockStatus === 'low' ? 'fill-low' : 'fill-ok'); ?>" style="width:<?php echo $stockPct; ?>%;"></span>
                </div>
                <div class="row g-2 mt-3 text-start">
                    <div class="col-6"><div class="info-item"><div class="lbl">Minimum</div><div class="val"><?php echo $minQty; ?></div></div></div>
                    <div class="col-6"><div class="info-item"><div class="lbl">Maximum</div><div class="val"><?php echo $maxQty; ?></div></div></div>
                    <div class="col-6"><div class="info-item"><div class="lbl">Status</div><div class="val"><?php echo ucfirst($stockStatus); ?></div></div></div>
                    <div class="col-6"><div class="info-item"><div class="lbl">Net Movements</div><div class="val"><?php echo $netMovements >= 0 ? '+' : ''; ?><?php echo $netMovements; ?></div></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Costs & supplier -->
    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-sack-dollar"></i> Valuation &amp; Pricing</h5>
            </div>
            <div class="card-body-custom">
                <div class="info-list">
                    <div class="info-item">
                        <div class="lbl">Cost Price</div>
                        <div class="val">$<?php echo number_format((float)$product['cost_price'], 2); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Selling Price</div>
                        <div class="val">$<?php echo number_format((float)$product['selling_price'], 2); ?></div>
                    </div>
                    <?php if ((float)$product['cost_price'] > 0): ?>
                    <div class="info-item">
                        <div class="lbl">Margin</div>
                        <div class="val" style="color:#22c55e;"><?php echo round((((float)$product['selling_price'] - (float)$product['cost_price']) / (float)$product['cost_price']) * 100, 1); ?>%</div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Stock Value (cost)</div>
                        <div class="val">$<?php echo number_format($valueCost, 2); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Stock Value (retail)</div>
                        <div class="val">$<?php echo number_format($valueSell, 2); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="lbl">Preferred Supplier</div>
                        <div class="val" style="line-height:1.4;">
                            <?php if (!empty($product['real_supplier_id'])): ?>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-view.php?id=<?php echo (int)$product['real_supplier_id']; ?>" style="color:var(--accent-bright);"><?php echo sanitize($product['supplier_name']); ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($product['expiry_date']) && $product['expiry_date'] !== '0000-00-00'): ?>
                    <div class="info-item">
                        <div class="lbl">Expiry Date</div>
                        <div class="val"><?php echo formatDate($product['expiry_date'], 'M d, Y'); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-circle-info"></i> Details</h5>
            </div>
            <div class="card-body-custom">
                <div class="info-list">
                    <div class="info-item">
                        <div class="lbl">SKU</div>
                        <div class="val"><?php echo sanitize($product['sku']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Barcode</div>
                        <div class="val"><?php echo $product['barcode'] ? sanitize($product['barcode']) : '—'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Category</div>
                        <div class="val"><?php echo sanitize($product['category_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Unit</div>
                        <div class="val"><?php echo strtoupper(sanitize($product['unit'] ?? 'pcs')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Lifecycle Status</div>
                        <div class="val"><span class="status-badge <?php echo $product['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo $product['status'] === 'active' ? 'Active' : 'Inactive'; ?></span></div>
                    </div>
                    <div class="info-item">
                        <div class="lbl">Created</div>
                        <div class="val"><?php echo formatDate($product['created_at'], 'M d, Y'); ?></div>
                    </div>
                </div>
                <?php if (!empty($product['description'])): ?>
                <div class="mt-3">
                    <div class="lbl mb-1">Description</div>
                    <div class="cell-sub"><?php echo nl2br(sanitize($product['description'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Purchase orders -->
<div class="admin-card mb-4">
    <div class="card-header-custom">
        <h5><i class="fas fa-file-signature"></i> Purchase Orders</h5>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php?product=<?php echo $productId; ?>" class="btn-admin btn-cyan btn-xs"><i class="fas fa-plus"></i> Order More</a>
    </div>
    <?php if (empty($stock['POS'])): ?>
    <div class="empty-state"><i class="fas fa-file-signature"></i><h5>No purchase orders yet</h5><p>Create a purchase order to re-stock this product.</p></div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>PO Number</th><th>Supplier</th><th>Order Date</th><th>Expected</th><th>Ordered</th><th>Received</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($stock['POS'] as $po): ?>
                <tr>
                    <td class="cell-strong"><?php echo sanitize($po['po_number']); ?></td>
                    <td class="cell-sub"><?php echo sanitize($po['supplier_name'] ?? '—'); ?></td>
                    <td><span class="cell-sub"><?php echo formatDate($po['order_date'], 'M d, Y'); ?></span></td>
                    <td><span class="cell-sub"><?php echo $po['expected_date'] && $po['expected_date'] !== '0000-00-00' ? formatDate($po['expected_date'], 'M d, Y') : '—'; ?></span></td>
                    <td><?php echo (int)$po['ordered_quantity']; ?></td>
                    <td><?php echo (int)$po['received_quantity']; ?></td>
                    <td><span class="po-status <?php echo $po['status']; ?>"><?php echo str_replace('_', ' ', ucwords($po['status'])); ?></span></td>
                    <td><a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-view.php?id=<?php echo (int)$po['purchase_order_id']; ?>" class="btn-admin btn-outline btn-xs"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Transaction history -->
<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-clock-rotate-left"></i> Stock History <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo (int)$stock['transactions']; ?></span></h5>
        <span class="stat-trend info"><i class="fas fa-arrow-down-to-bracket"></i> +<?php echo $stock['total_in']; ?> <i class="fas fa-arrow-up-from-bracket ms-2"></i> −<?php echo $stock['total_out']; ?></span>
    </div>
    <?php if (empty($stock['moves'])): ?>
    <div class="empty-state"><i class="fas fa-clock-rotate-left"></i><h5>No stock movements recorded</h5><p>Record a stock in, stock out or adjustment to start the history.</p></div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>#</th><th>Type</th><th>Qty</th><th>Stock</th><th>Unit Cost</th><th>Reason / Reference</th><th>User</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($stock['moves'] as $tx): ?>
                <tr>
                    <td class="cell-sub">#<?php echo (int)$tx['id']; ?></td>
                    <td><span class="<?php echo invTxnTypeBadge($tx['transaction_type']); ?>"><?php echo ucwords(str_replace('_',' ', $tx['transaction_type'])); ?></span></td>
                    <td>
                        <span class="<?php echo (int)$tx['quantity'] >= 0 ? 'status-badge completed' : 'status-badge cancelled'; ?>">
                            <?php echo (int)$tx['quantity'] >= 0 ? '+' : ''; ?><?php echo (int)$tx['quantity']; ?>
                        </span>
                    </td>
                    <td><span class="cell-sub"><?php echo (int)$tx['previous_stock']; ?> → <?php echo (int)$tx['new_stock']; ?></span></td>
                    <td class="cell-sub">$<?php echo number_format((float)$tx['unit_cost'], 2); ?></td>
                    <td>
                        <?php if ($tx['reason']): ?><div class="cell-sub"><?php echo sanitize($tx['reason']); ?></div><?php endif; ?>
                        <?php if ($tx['reference']): ?><span class="status-badge pending" style="font-size:.72rem;"><?php echo sanitize($tx['reference']); ?></span><?php endif; ?>
                    </td>
                    <td class="cell-sub"><?php echo $tx['user_name'] ? sanitize($tx['user_name']) : 'System'; ?></td>
                    <td><span class="cell-sub"><?php echo formatDate($tx['created_at'], 'M d, Y g:i A'); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>