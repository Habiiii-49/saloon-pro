<?php
/**
 * Admin – Inventory Transactions
 * Full audit log of every stock movement.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Transactions';
$activeMenu      = 'inventory';
$inventoryActive = 'transactions';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

$type       = $_GET['type'] ?? '';
$search     = trim($_GET['search'] ?? '');
$productId  = (int)($_GET['product'] ?? 0);
$dateFrom   = trim($_GET['from'] ?? '');
$dateTo     = trim($_GET['to'] ?? '');
$validTypes = array_keys(invTransactionTypes());
if (!in_array($type, array_merge([''], $validTypes), true)) {
    $type = '';
}
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$where  = [];
$params = [];
if ($type !== '') {
    $where[] = "t.transaction_type = :type";
    $params[':type'] = $type;
}
if ($productId > 0) {
    $where[] = "t.product_id = :pid";
    $params[':pid'] = $productId;
}
if ($search !== '') {
    $where[] = "(i.item_name LIKE :s1 OR t.reference LIKE :s2 OR t.reason LIKE :s3)";
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
    $params[':s3'] = '%' . $search . '%';
}
if ($dateFrom !== '') {
    $where[] = "DATE(t.created_at) >= :from";
    $params[':from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "DATE(t.created_at) <= :to";
    $params[':to'] = $dateTo;
}
$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT t.id, t.transaction_type, t.quantity, t.previous_stock, t.new_stock, t.unit_cost,
           t.reference, t.reason, t.created_at,
           i.item_name, i.sku, i.unit,
           COALESCE(s.supplier_name, s.company_name) AS supplier_name,
           po.po_number,
           CONCAT(u.first_name,' ',u.last_name) AS user_name
    FROM inventory_transactions t
    JOIN inventory i ON i.inventory_id = t.product_id
    LEFT JOIN suppliers s ON s.supplier_id = t.supplier_id
    LEFT JOIN purchase_orders po ON po.id = t.purchase_order_id
    LEFT JOIN users u ON u.user_id = t.user_id
    $whereSql
    ORDER BY t.id DESC
    LIMIT 500
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

/* ---------- Summary (filtered) ---------- */
$summary = ['in' => 0, 'out' => 0, 'adjustments' => 0, 'count' => count($transactions)];
foreach ($transactions as $tx) {
    $q = (int)$tx['quantity'];
    if (in_array($tx['transaction_type'], ['stock_in', 'purchase_received'], true)) {
        $summary['in'] += abs($q);
    } elseif (in_array($tx['transaction_type'], ['stock_out', 'purchase_return'], true)) {
        $summary['out'] += abs($q);
    } else {
        $summary['adjustments']++;
    }
}

$products = [];
try {
    $products = $db->query("SELECT inventory_id, item_name FROM inventory ORDER BY item_name ASC")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-clock-rotate-left"></i> Inventory Transactions</h1>
        <p>Every stock movement across all products.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/reports.php" class="btn-admin btn-outline"><i class="fas fa-chart-pie"></i> Reports</a>
</div>

<div class="stat-grid mb-4">
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon cyan"><i class="fas fa-arrow-down-to-bracket"></i></span><span class="stat-trend info">In</span></div>
        <div class="stat-value" data-count="<?php echo $summary['in']; ?>">0</div>
        <div class="stat-label">Units Received</div>
        <div class="stat-sub">Stock in + purchase receipts</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon red"><i class="fas fa-arrow-up-from-bracket"></i></span><span class="stat-trend down">Out</span></div>
        <div class="stat-value" data-count="<?php echo $summary['out']; ?>">0</div>
        <div class="stat-label">Units Removed</div>
        <div class="stat-sub">Stock out + returns</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon orange"><i class="fas fa-sliders"></i></span><span class="stat-trend warn">Adjust</span></div>
        <div class="stat-value" data-count="<?php echo $summary['adjustments']; ?>">0</div>
        <div class="stat-label">Adjustments</div>
        <div class="stat-sub">Reconciliations &amp; corrections</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span class="stat-icon blue"><i class="fas fa-list-check"></i></span><span class="stat-trend info">Rows</span></div>
        <div class="stat-value" data-count="<?php echo $summary['count']; ?>">0</div>
        <div class="stat-label">Transactions Shown</div>
        <div class="stat-sub">Newest first (max 500)</div>
    </div>
</div>

<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-filter"></i> Filter Transactions</h5>
        <form method="get" action="" class="d-flex gap-2 flex-wrap">
            <div class="filter-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search product, reference or reason..." class="form-control-admin form-control-sm">
            </div>
            <select name="type" class="form-control-admin form-control-sm">
                <option value="">All Types</option>
                <?php foreach (invTransactionTypes() as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $type === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="product" class="form-control-admin form-control-sm">
                <option value="0">All Products</option>
                <?php foreach ($products as $p): ?>
                <option value="<?php echo (int)$p['inventory_id']; ?>" <?php echo $productId === (int)$p['inventory_id'] ? 'selected' : ''; ?>><?php echo sanitize($p['item_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?php echo sanitize($dateFrom); ?>" class="form-control-admin form-control-sm" title="From date">
            <input type="date" name="to" value="<?php echo sanitize($dateTo); ?>" class="form-control-admin form-control-sm" title="To date">
            <button type="submit" class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Apply</button>
            <?php if ($search !== '' || $type !== '' || $productId > 0 || $dateFrom !== '' || $dateTo !== ''): ?>
            <a href="<?php echo SITE_URL; ?>/admin/inventory/transactions.php" class="btn-admin btn-outline btn-xs">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($transactions)): ?>
    <div class="empty-state"><i class="fas fa-clock-rotate-left"></i><h5>No transactions match your filters</h5><p>Try clearing the filters or record a stock movement.</p></div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Change</th>
                    <th>Stock</th>
                    <th>Unit Cost</th>
                    <th>Reference</th>
                    <th>Supplier</th>
                    <th>User</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td class="cell-sub">#<?php echo (int)$tx['id']; ?></td>
                    <td>
                        <span class="cell-strong"><?php echo sanitize($tx['item_name']); ?></span>
                        <div class="cell-sub"><?php echo sanitize($tx['sku'] ?? ''); ?></div>
                    </td>
                    <td><span class="<?php echo invTxnTypeBadge($tx['transaction_type']); ?>"><?php echo ucwords(str_replace('_',' ', $tx['transaction_type'])); ?></span></td>
                    <td>
                        <span class="<?php echo (int)$tx['quantity'] >= 0 ? 'status-badge completed' : 'status-badge cancelled'; ?>">
                            <?php echo (int)$tx['quantity'] >= 0 ? '+' : ''; ?><?php echo (int)$tx['quantity']; ?>
                        </span>
                    </td>
                    <td><span class="cell-sub"><?php echo (int)$tx['previous_stock']; ?> → <?php echo (int)$tx['new_stock']; ?></span></td>
                    <td class="cell-sub">$<?php echo number_format((float)$tx['unit_cost'], 2); ?></td>
                    <td>
                        <?php if ($tx['reference']): ?><span class="cell-sub"><?php echo sanitize($tx['reference']); ?></span><?php endif; ?>
                        <?php if ($tx['reason']): ?><div class="cell-sub" style="max-width:220px;"><?php echo sanitize($tx['reason']); ?></div><?php endif; ?>
                    </td>
                    <td class="cell-sub"><?php echo $tx['supplier_name'] ? sanitize($tx['supplier_name']) : '—'; ?></td>
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