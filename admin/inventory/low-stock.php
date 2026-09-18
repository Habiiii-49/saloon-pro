<?php
/**
 * Admin – Low Stock
 * Products at or below minimum threshold, with out-of-stock at the top.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Low Stock';
$activeMenu      = 'inventory';
$inventoryActive = 'low-stock';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

$scope = $_GET['scope'] ?? 'all';
if (!in_array($scope, ['all', 'out', 'low'], true)) {
    $scope = 'all';
}

$sql = "
    SELECT i.inventory_id, i.item_name, i.sku, i.quantity, i.minimum_stock, i.maximum_stock,
           i.unit, i.cost_price, i.status,
           COALESCE(s.supplier_name, s.company_name) AS supplier_name,
           (SELECT MAX(t.created_at) FROM inventory_transactions t
             WHERE t.product_id = i.inventory_id AND t.transaction_type IN ('stock_in','purchase_received')) AS last_received
    FROM inventory i
    LEFT JOIN suppliers s ON s.supplier_id = i.supplier_id
    WHERE i.status = 'active' AND i.quantity <= i.minimum_stock
";
if ($scope === 'out') {
    $sql .= " AND i.quantity = 0";
} elseif ($scope === 'low') {
    $sql .= " AND i.quantity > 0";
}
$sql .= " ORDER BY i.quantity ASC, i.item_name ASC";

try {
    $items = $db->query($sql)->fetchAll();
} catch (PDOException $e) {
    $items = [];
}

$countOut = 0;
$countLow = 0;
foreach ($items as $item) {
    if ((int)$item['quantity'] === 0) {
        $countOut++;
    } else {
        $countLow++;
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-triangle-exclamation"></i> Low Stock Alerts</h1>
        <p>Products needing restock are shown here first.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo SITE_URL; ?>/admin/inventory/stock-in.php" class="btn-admin btn-cyan"><i class="fas fa-arrow-down-to-bracket"></i> Stock In</a>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php" class="btn-admin btn-outline"><i class="fas fa-file-signature"></i> New Purchase Order</a>
    </div>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon red"><i class="fas fa-box-open"></i></span>
            <span class="stat-trend down"><i class="fas fa-circle-exclamation"></i> Critical</span>
        </div>
        <div class="stat-value" data-count="<?php echo $countOut; ?>">0</div>
        <div class="stat-label">Out of Stock</div>
        <div class="stat-sub">Need immediate attention</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon orange"><i class="fas fa-box"></i></span>
            <span class="stat-trend warn"><i class="fas fa-triangle-exclamation"></i> Caution</span>
        </div>
        <div class="stat-value" data-count="<?php echo $countLow; ?>">0</div>
        <div class="stat-label">Low / Running Low</div>
        <div class="stat-sub">Below or at minimum quantity</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon cyan"><i class="fas fa-file-signature"></i></span>
            <span class="stat-trend info"><i class="fas fa-hourglass-half"></i> Open</span>
        </div>
        <div class="stat-value" data-count="<?php echo (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('pending','ordered','partially_received')")->fetchColumn(); ?>">0</div>
        <div class="stat-label">Open Purchase Orders</div>
        <div class="stat-sub">Already ordered / pending</div>
    </div>
</div>

<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-boxes-stacked"></i> Alerting Products <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($items); ?></span></h5>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo SITE_URL; ?>/admin/inventory/low-stock.php?scope=all" class="btn-admin btn-outline btn-xs <?php echo $scope === 'all' ? 'bg-opacity-25' : ''; ?>">All</a>
            <a href="<?php echo SITE_URL; ?>/admin/inventory/low-stock.php?scope=out" class="btn-admin btn-outline btn-xs <?php echo $scope === 'out' ? 'bg-opacity-25' : ''; ?>">Out of Stock</a>
            <a href="<?php echo SITE_URL; ?>/admin/inventory/low-stock.php?scope=low" class="btn-admin btn-outline btn-xs <?php echo $scope === 'low' ? 'bg-opacity-25' : ''; ?>">Low Stock</a>
        </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="empty-state">
        <i class="fas fa-circle-check" style="color:#22c55e;"></i>
        <h5>All inventory levels are healthy</h5>
        <p>No active products are at or below their minimum stock level.</p>
    </div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Stock Level</th>
                    <th>Min</th>
                    <th>Suggested Order</th>
                    <th>Last Received</th>
                    <th>Preferred Supplier</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <?php
                $out = (int)$item['quantity'] === 0;
                $suggested = invSuggestedOrderQty((int)$item['quantity'], (int)$item['minimum_stock'], (int)$item['maximum_stock']);
                ?>
                <tr>
                    <td>
                        <span class="cell-strong"><?php echo sanitize($item['item_name']); ?></span>
                        <div class="cell-sub"><?php echo sanitize($item['sku'] ?? ''); ?></div>
                    </td>
                    <td>
                        <span class="stock-count <?php echo $out ? 'stock-count-out' : 'stock-count-low'; ?>">
                            <?php echo (int)$item['quantity']; ?> <?php echo strtoupper(sanitize($item['unit'] ?? 'pcs')); ?>
                        </span>
                    </td>
                    <td class="cell-sub"><?php echo (int)$item['minimum_stock']; ?></td>
                    <td><span class="status-badge pending"><?php echo $suggested; ?></span></td>
                    <td><span class="cell-sub"><?php echo !empty($item['last_received']) ? formatDate($item['last_received'], 'M d, Y') : 'Never'; ?></span></td>
                    <td class="cell-sub"><?php echo $item['supplier_name'] ? sanitize($item['supplier_name']) : '—'; ?></td>
                    <td>
                        <div class="action-btns">
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/stock-in.php?id=<?php echo (int)$item['inventory_id']; ?>" class="btn-admin btn-cyan btn-xs"><i class="fas fa-arrow-down-to-bracket"></i> Restock</a>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php?product=<?php echo (int)$item['inventory_id']; ?>" class="btn-admin btn-outline btn-xs" title="Create purchase order"><i class="fas fa-file-signature"></i></a>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/product-view.php?id=<?php echo (int)$item['inventory_id']; ?>" class="btn-admin btn-outline btn-xs" title="View"><i class="fas fa-eye"></i></a>
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