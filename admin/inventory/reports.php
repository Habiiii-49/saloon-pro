<?php
/**
 * Admin – Inventory Reports
 * Valuation, low stock, stock movement and supplier spend reports.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Inventory Reports';
$activeMenu      = 'inventory';
$inventoryActive = 'reports';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

$dateFrom = trim($_GET['from'] ?? '');
$dateTo   = trim($_GET['to'] ?? '');
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}
if ($dateFrom === '' && $dateTo === '') {
    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-d');
}
$rangeStart = $dateFrom !== '' ? $dateFrom . ' 00:00:00' : date('Y-m-01') . ' 00:00:00';
$rangeEnd   = $dateTo !== '' ? $dateTo . ' 23:59:59' : date('Y-m-d') . ' 23:59:59';

/* ---------- 1. Overall valuation ---------- */
$valuation = ['products' => 0, 'units' => 0, 'cost' => 0.0, 'retail' => 0.0, 'potential_profit' => 0.0];
try {
    $row = $db->query("
        SELECT COUNT(*) AS products,
               COALESCE(SUM(quantity),0) AS units,
               COALESCE(SUM(quantity * cost_price),0) AS cost,
               COALESCE(SUM(quantity * selling_price),0) AS retail
        FROM inventory WHERE status = 'active'
    ")->fetch();
    $valuation['products'] = (int)$row['products'];
    $valuation['units']    = (int)$row['units'];
    $valuation['cost']     = (float)$row['cost'];
    $valuation['retail']   = (float)$row['retail'];
    $valuation['potential_profit'] = $valuation['retail'] - $valuation['cost'];
} catch (PDOException $e) {
}

/* ---------- 2. Valuation by category ---------- */
$byCategory = [];
try {
    $byCategory = $db->query("
        SELECT COALESCE(c.name, 'Uncategorized') AS name,
               COUNT(i.inventory_id) AS products,
               COALESCE(SUM(i.quantity),0) AS units,
               COALESCE(SUM(i.quantity * i.cost_price),0) AS cost,
               COALESCE(SUM(i.quantity * i.selling_price),0) AS retail
        FROM inventory i
        LEFT JOIN inventory_categories c ON c.id = i.category_id
        WHERE i.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY cost DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $byCategory = [];
}

/* ---------- 3. Low stock report ---------- */
$lowStock = [];
try {
    $lowStock = $db->query("
        SELECT item_name, sku, quantity, minimum_stock, maximum_stock, unit, cost_price
        FROM inventory
        WHERE status = 'active' AND quantity <= minimum_stock
        ORDER BY quantity ASC, item_name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $lowStock = [];
}

/* ---------- 4. Stock movement in range ---------- */
$movement = [];
$movementTotals = ['in' => 0, 'out' => 0, 'adjust' => 0, 'transactions' => 0];
try {
    $stmt = $db->prepare("
        SELECT transaction_type,
               COUNT(*) AS cnt,
               COALESCE(SUM(ABS(quantity)),0) AS units
        FROM inventory_transactions
        WHERE created_at BETWEEN :from AND :to
        GROUP BY transaction_type
    ");
    $stmt->execute([':from' => $rangeStart, ':to' => $rangeEnd]);
    foreach ($stmt->fetchAll() as $row) {
        $movement[$row['transaction_type']] = [
            'count' => (int)$row['cnt'],
            'units' => (int)$row['units'],
        ];
        $movementTotals['transactions'] += (int)$row['cnt'];
        if (in_array($row['transaction_type'], ['stock_in', 'purchase_received'], true)) {
            $movementTotals['in'] += (int)$row['units'];
        } elseif (in_array($row['transaction_type'], ['stock_out', 'purchase_return'], true)) {
            $movementTotals['out'] += (int)$row['units'];
        } else {
            $movementTotals['adjust'] += (int)$row['units'];
        }
    }
} catch (PDOException $e) {
    $movement = [];
}

/* ---------- 5. Top moving products in range ---------- */
$topMoving = [];
try {
    $stmt = $db->prepare("
        SELECT i.item_name, i.sku, i.unit,
               COALESCE(SUM(CASE WHEN t.quantity > 0 THEN t.quantity ELSE 0 END),0) AS units_in,
               COALESCE(SUM(CASE WHEN t.quantity < 0 THEN -t.quantity ELSE 0 END),0) AS units_out
        FROM inventory_transactions t
        JOIN inventory i ON i.inventory_id = t.product_id
        WHERE t.created_at BETWEEN :from AND :to
        GROUP BY i.inventory_id, i.item_name, i.sku, i.unit
        ORDER BY units_out DESC, units_in DESC
        LIMIT 10
    ");
    $stmt->execute([':from' => $rangeStart, ':to' => $rangeEnd]);
    $topMoving = $stmt->fetchAll();
} catch (PDOException $e) {
    $topMoving = [];
}

/* ---------- 6. Supplier spend in range ---------- */
$supplierSpend = [];
try {
    $stmt = $db->prepare("
        SELECT COALESCE(s.supplier_name, s.company_name, 'Unknown supplier') AS name,
               COUNT(po.id) AS orders,
               COALESCE(SUM(po.grand_total),0) AS total
        FROM purchase_orders po
        LEFT JOIN suppliers s ON s.supplier_id = po.supplier_id
        WHERE po.order_date BETWEEN :from AND :to
          AND po.status <> 'cancelled'
        GROUP BY s.supplier_id, s.supplier_name, s.company_name
        ORDER BY total DESC
    ");
    $stmt->execute([':from' => $dateFrom !== '' ? $dateFrom : '1970-01-01', ':to' => $dateTo !== '' ? $dateTo : date('Y-m-d')]);
    $supplierSpend = $stmt->fetchAll();
} catch (PDOException $e) {
    $supplierSpend = [];
}

$supplierSpendTotal = 0.0;
foreach ($supplierSpend as $sp) {
    $supplierSpendTotal += (float)$sp['total'];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head no-print">
    <div class="page-head-title">
        <h1><i class="fas fa-chart-pie"></i> Inventory Reports</h1>
        <p>Valuation, movement and supplier spend analytics.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn-admin btn-outline" data-print-doc><i class="fas fa-print"></i> Print Report</button>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/transactions.php" class="btn-admin btn-outline"><i class="fas fa-clock-rotate-left"></i> Transactions</a>
    </div>
</div>

<div class="admin-card mb-4 no-print">
    <div class="card-body-custom">
        <form method="get" action="" class="filter-bar">
            <label class="cell-sub mb-0"><i class="fas fa-calendar"></i> Report range</label>
            <input type="date" name="from" value="<?php echo sanitize($dateFrom); ?>" class="form-control-admin form-control-sm">
            <span class="cell-sub">to</span>
            <input type="date" name="to" value="<?php echo sanitize($dateTo); ?>" class="form-control-admin form-control-sm">
            <button type="submit" class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Apply</button>
            <a href="<?php echo SITE_URL; ?>/admin/inventory/reports.php" class="btn-admin btn-outline btn-xs">Reset</a>
        </form>
    </div>
</div>

<!-- VALUATION SUMMARY -->
<div class="report-grid mb-4">
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon cyan"><i class="fas fa-box"></i></span><span class="stat-trend info">Active</span></div>
        <div class="stat-value" data-count="<?php echo (int)$valuation['products']; ?>">0</div>
        <div class="stat-label">Active Products</div>
        <div class="stat-sub"><?php echo (int)$valuation['units']; ?> units on hand</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon blue"><i class="fas fa-sack-dollar"></i></span><span class="stat-trend info">Cost</span></div>
        <div class="stat-value" data-prefix="$" data-decimals="0" data-count="<?php echo (int)$valuation['cost']; ?>">$0</div>
        <div class="stat-label">Inventory Value (Cost)</div>
        <div class="stat-sub">What you paid</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon gold"><i class="fas fa-tags"></i></span><span class="stat-trend info">Retail</span></div>
        <div class="stat-value" data-prefix="$" data-decimals="0" data-count="<?php echo (int)$valuation['retail']; ?>">$0</div>
        <div class="stat-label">Inventory Value (Retail)</div>
        <div class="stat-sub">If sold at full price</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon green"><i class="fas fa-arrow-trend-up"></i></span><span class="stat-trend info">Margin</span></div>
        <div class="stat-value" data-prefix="$" data-decimals="0" data-count="<?php echo (int)$valuation['potential_profit']; ?>">$0</div>
        <div class="stat-label">Potential Profit</div>
        <div class="stat-sub">Retail − cost</div>
    </div>
</div>

<!-- STOCK MOVEMENT -->
<div class="admin-card mb-4">
    <div class="card-header-custom">
        <h5><i class="fas fa-arrows-rotate"></i> Stock Movement</h5>
        <span class="cell-sub"><?php echo formatDate($rangeStart, 'M d, Y'); ?> – <?php echo formatDate($rangeEnd, 'M d, Y'); ?></span>
    </div>
    <div class="card-body-custom">
        <div class="report-grid">
            <div class="info-item">
                <div class="lbl">Units Received</div>
                <div class="val" style="color:#22c55e;">+<?php echo (int)$movementTotals['in']; ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Units Removed</div>
                <div class="val" style="color:#f87171;">−<?php echo (int)$movementTotals['out']; ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Adjustment Volume</div>
                <div class="val" style="color:#fbbf24;"><?php echo (int)$movementTotals['adjust']; ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Transactions</div>
                <div class="val"><?php echo (int)$movementTotals['transactions']; ?></div>
            </div>
        </div>

        <?php if (!empty($movement)): ?>
        <div class="table-scroll mt-3">
            <table class="admin-table">
                <thead><tr><th>Transaction Type</th><th class="text-end">Count</th><th class="text-end">Total Units</th></tr></thead>
                <tbody>
                    <?php foreach (invTransactionTypes() as $key => $label): ?>
                    <?php if (isset($movement[$key])): ?>
                    <tr>
                        <td><span class="<?php echo invTxnTypeBadge($key); ?>"><?php echo $label; ?></span></td>
                        <td class="text-end"><?php echo (int)$movement[$key]['count']; ?></td>
                        <td class="text-end cell-sub"><?php echo (int)$movement[$key]['units']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state mt-3"><i class="fas fa-arrows-rotate"></i><h5>No stock movement in this period</h5><p>Adjust the report range to see activity.</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- VALUATION BY CATEGORY -->
<div class="admin-card mb-4">
    <div class="card-header-custom"><h5><i class="fas fa-tags"></i> Valuation by Category</h5></div>
    <?php if (empty($byCategory)): ?>
    <div class="empty-state"><i class="fas fa-tags"></i><h5>No category data</h5><p>Add products with categories to see this breakdown.</p></div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>Category</th><th class="text-end">Products</th><th class="text-end">Units</th><th class="text-end">Value (Cost)</th><th class="text-end">Value (Retail)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($byCategory as $cat): ?>
                <tr>
                    <td class="cell-strong"><?php echo sanitize($cat['name']); ?></td>
                    <td class="text-end"><?php echo (int)$cat['products']; ?></td>
                    <td class="text-end cell-sub"><?php echo (int)$cat['units']; ?></td>
                    <td class="text-end cell-sub">$<?php echo number_format((float)$cat['cost'], 2); ?></td>
                    <td class="text-end cell-sub">$<?php echo number_format((float)$cat['retail'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="cell-strong">Total</td>
                    <td class="text-end"><?php echo (int)$valuation['products']; ?></td>
                    <td class="text-end"><?php echo (int)$valuation['units']; ?></td>
                    <td class="text-end cell-strong">$<?php echo number_format($valuation['cost'], 2); ?></td>
                    <td class="text-end cell-strong">$<?php echo number_format($valuation['retail'], 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <!-- LOW STOCK REPORT -->
    <div class="col-xl-7">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-triangle-exclamation"></i> Low Stock Report</h5>
                <span class="status-badge pending"><?php echo count($lowStock); ?> item(s)</span>
            </div>
            <?php if (empty($lowStock)): ?>
            <div class="empty-state"><i class="fas fa-circle-check" style="color:#22c55e;"></i><h5>No low stock items</h5><p>All active products are above their minimum levels.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th class="text-end">Stock</th><th class="text-end">Min</th><th class="text-end">Suggested Order</th></tr></thead>
                    <tbody>
                        <?php foreach ($lowStock as $item): ?>
                        <tr>
                            <td>
                                <span class="cell-strong"><?php echo sanitize($item['item_name']); ?></span>
                                <div class="cell-sub"><?php echo sanitize($item['sku'] ?? ''); ?></div>
                            </td>
                            <td class="text-end">
                                <span class="<?php echo (int)$item['quantity'] === 0 ? 'stock-count-out' : 'stock-count-low'; ?>">
                                    <?php echo (int)$item['quantity']; ?> <?php echo strtoupper(sanitize($item['unit'] ?? '')); ?>
                                </span>
                            </td>
                            <td class="text-end cell-sub"><?php echo (int)$item['minimum_stock']; ?></td>
                            <td class="text-end"><span class="status-badge pending"><?php echo invSuggestedOrderQty((int)$item['quantity'], (int)$item['minimum_stock'], (int)$item['maximum_stock']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SUPPLIER SPEND -->
    <div class="col-xl-5">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-truck"></i> Supplier Spend</h5>
                <span class="stat-trend info"><i class="fas fa-coins"></i> $<?php echo number_format($supplierSpendTotal, 2); ?></span>
            </div>
            <?php if (empty($supplierSpend)): ?>
            <div class="empty-state"><i class="fas fa-truck"></i><h5>No purchase orders in range</h5><p>Supplier spend will appear here.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Supplier</th><th class="text-end">POs</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($supplierSpend as $sp): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($sp['name']); ?></td>
                            <td class="text-end"><?php echo (int)$sp['orders']; ?></td>
                            <td class="text-end cell-sub">$<?php echo number_format((float)$sp['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TOP MOVING PRODUCTS -->
<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-ranking-star"></i> Top Moving Products</h5>
        <span class="cell-sub"><?php echo formatDate($rangeStart, 'M d, Y'); ?> – <?php echo formatDate($rangeEnd, 'M d, Y'); ?></span>
    </div>
    <?php if (empty($topMoving)): ?>
    <div class="empty-state"><i class="fas fa-ranking-star"></i><h5>No movement in this period</h5><p>Try widening the report range.</p></div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead><tr><th>Product</th><th>SKU</th><th class="text-end">Units In</th><th class="text-end">Units Out</th></tr></thead>
            <tbody>
                <?php foreach ($topMoving as $p): ?>
                <tr>
                    <td class="cell-strong"><?php echo sanitize($p['item_name']); ?></td>
                    <td class="cell-sub"><?php echo sanitize($p['sku'] ?? '—'); ?></td>
                    <td class="text-end"><span class="status-badge completed">+<?php echo (int)$p['units_in']; ?></span></td>
                    <td class="text-end"><span class="status-badge cancelled">−<?php echo (int)$p['units_out']; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>