<?php
/**
 * ELEGANCE SALON - INVENTORY DASHBOARD (PART 5)
 * All statistics computed live from MySQL.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Inventory Dashboard';
$activeMenu      = 'inventory';
$inventoryActive = 'inv-dashboard';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

/* ---------- Stats ---------- */
$stats = [
    'total_products'        => 0,
    'total_stock_units'     => 0,
    'low_stock_products'    => 0,
    'out_of_stock_products' => 0,
    'inventory_value'       => 0.0,
    'active_suppliers'      => 0,
    'pending_po'            => 0,
    'po_this_month'         => 0,
];
$queriesOk = true;
try {
    $stats['total_products']        = (int)$db->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $stats['total_stock_units']     = (int)$db->query("SELECT COALESCE(SUM(quantity),0) FROM inventory")->fetchColumn();
    $stats['low_stock_products']    = (int)$db->query("SELECT COUNT(*) FROM inventory WHERE status='active' AND quantity <= minimum_stock AND quantity > 0")->fetchColumn();
    $stats['out_of_stock_products'] = (int)$db->query("SELECT COUNT(*) FROM inventory WHERE status='active' AND quantity = 0")->fetchColumn();
    $stats['inventory_value']       = (float)$db->query("SELECT COALESCE(SUM(quantity * cost_price),0) FROM inventory WHERE status='active'")->fetchColumn();
    $stats['active_suppliers']      = (int)$db->query("SELECT COUNT(*) FROM suppliers WHERE status='active'")->fetchColumn();
    $stats['pending_po']            = (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft','pending','ordered','partially_received')")->fetchColumn();
    $stats['po_this_month']         = (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE YEAR(order_date)=YEAR(CURDATE()) AND MONTH(order_date)=MONTH(CURDATE())")->fetchColumn();
} catch (PDOException $e) {
    $queriesOk = false;
}

/* ---------- Stock overview (doughnut) ---------- */
$stockOverview = ['in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
try {
    $sql = "SELECT CASE WHEN quantity = 0 THEN 'out_of_stock' WHEN quantity <= minimum_stock THEN 'low_stock' ELSE 'in_stock' END AS grp, COUNT(*) AS c
            FROM inventory WHERE status='active' GROUP BY grp";
    foreach ($db->query($sql) as $row) {
        $stockOverview[$row['grp']] = (int)$row['c'];
    }
} catch (PDOException $e) {
    $stockOverview = ['in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
}

/* ---------- Inventory value by category (bar) ---------- */
$categoryValue = ['labels' => [], 'values' => []];
try {
    $rows = $db->query("
        SELECT COALESCE(c.name, 'Uncategorized') AS name, COALESCE(SUM(i.quantity * i.cost_price),0) AS val
        FROM inventory i
        LEFT JOIN inventory_categories c ON c.id = i.category_id
        WHERE i.status='active'
        GROUP BY c.id, c.name
        ORDER BY val DESC LIMIT 8
    ")->fetchAll();
    foreach ($rows as $r) {
        $categoryValue['labels'][] = $r['name'];
        $categoryValue['values'][] = (float)$r['val'];
    }
} catch (PDOException $e) {
    $categoryValue = ['labels' => [], 'values' => []];
}

/* ---------- Stock movement (monthly, current year) ---------- */
$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$stockInMonthly  = array_fill(0, 12, 0);
$stockOutMonthly = array_fill(0, 12, 0);
$adjustMonthly   = array_fill(0, 12, 0);
try {
    $stmt = $db->prepare("
        SELECT MONTH(created_at) AS m,
               transaction_type,
               SUM(quantity) AS qty
        FROM inventory_transactions
        WHERE YEAR(created_at) = :yr
          AND transaction_type IN ('stock_in','stock_out','adjustment','purchase_received','purchase_return')
        GROUP BY m, transaction_type
    ");
    $stmt->execute([':yr' => date('Y')]);
    foreach ($stmt->fetchAll() as $row) {
        $idx = max(0, min(11, (int)$row['m'] - 1));
        $qty = (int)$row['qty'];
        switch ($row['transaction_type']) {
            case 'stock_in':
            case 'purchase_received':
                $stockInMonthly[$idx] += max(0, $qty);
                break;
            case 'stock_out':
            case 'purchase_return':
                $stockOutMonthly[$idx] += abs(min(0, $qty));
                break;
            case 'adjustment':
                $adjustMonthly[$idx] += abs($qty);
                break;
        }
    }
} catch (PDOException $e) {
    // keep zeros
}

/* ---------- Top used products ---------- */
$topUsed = ['labels' => [], 'values' => []];
try {
    $rows = $db->query("
        SELECT i.item_name AS name, SUM(ABS(t.quantity)) AS used
        FROM inventory_transactions t
        JOIN inventory i ON i.inventory_id = t.product_id
        WHERE t.transaction_type IN ('stock_out','purchase_return')
        GROUP BY i.inventory_id, i.item_name
        ORDER BY used DESC
        LIMIT 7
    ")->fetchAll();
    foreach ($rows as $r) {
        $topUsed['labels'][] = $r['name'];
        $topUsed['values'][] = (int)$r['used'];
    }
} catch (PDOException $e) {
    $topUsed = ['labels' => [], 'values' => []];
}

/* ---------- Recent transactions ---------- */
$recent = [];
try {
    $recent = $db->query("
        SELECT t.id, t.transaction_type, t.quantity, t.previous_stock, t.new_stock,
               t.unit_cost, t.reference, t.created_at,
               i.item_name AS product_name,
               CONCAT(u.first_name,' ',u.last_name) AS user_name
        FROM inventory_transactions t
        JOIN inventory i ON i.inventory_id = t.product_id
        LEFT JOIN users u ON u.user_id = t.user_id
        ORDER BY t.id DESC
        LIMIT 10
    ")->fetchAll();
} catch (PDOException $e) {
    $recent = [];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-boxes-stacked"></i> Inventory Dashboard</h1>
        <p>Monitor products, suppliers and purchase orders in real time.</p>
    </div>
    <span class="page-date"><i class="far fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?></span>
</div>

<?php if (!$queriesOk): ?>
<div class="alert-admin warning"><i class="fas fa-triangle-exclamation"></i><div>Some statistics could not be loaded. Please check the database connection.</div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stat-grid mb-4">
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon cyan"><i class="fas fa-box"></i></span>
            <span class="stat-trend info"><i class="fas fa-layer-group"></i> Total</span>
        </div>
        <div class="stat-value" data-count="<?php echo $stats['total_products']; ?>">0</div>
        <div class="stat-label">Total Products</div>
        <div class="stat-sub">In catalogue</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon blue"><i class="fas fa-cubes"></i></span>
            <span class="stat-trend info"><i class="fas fa-box"></i> Units</span>
        </div>
        <div class="stat-value" data-count="<?php echo $stats['total_stock_units']; ?>">0</div>
        <div class="stat-label">Total Stock Units</div>
        <div class="stat-sub">Across all products</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon orange"><i class="fas fa-triangle-exclamation"></i></span>
            <span class="stat-trend warn"><i class="fas fa-bell"></i> Watch</span>
        </div>
        <div class="stat-value" data-count="<?php echo $stats['low_stock_products']; ?>">0</div>
        <div class="stat-label">Low Stock Products</div>
        <div class="stat-sub">At or below minimum</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon red"><i class="fas fa-box-open"></i></span>
            <span class="stat-trend down"><i class="fas fa-circle-exclamation"></i> Empty</span>
        </div>
        <div class="stat-value" data-count="<?php echo $stats['out_of_stock_products']; ?>">0</div>
        <div class="stat-label">Out of Stock</div>
        <div class="stat-sub">Currently unavailable</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon gold"><i class="fas fa-sack-dollar"></i></span>
            <span class="stat-trend info"><i class="fas fa-coins"></i> Value</span>
        </div>
        <div class="stat-value" data-prefix="$" data-decimals="0" data-count="<?php echo (int)$stats['inventory_value']; ?>">$0</div>
        <div class="stat-label">Inventory Value</div>
        <div class="stat-sub">Current stock × cost</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon violet"><i class="fas fa-truck"></i></span>
            <span class="stat-trend info"><i class="fas fa-users"></i> Active</span>
        </div>
        <div class="stat-value" data-count="<?php echo $stats['active_suppliers']; ?>">0</div>
        <div class="stat-label">Active Suppliers</div>
        <div class="stat-sub">Current vendors</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon pink"><i class="fas fa-file-signature"></i></span>
            <span class="stat-trend warn"><i class="fas fa-hourglass-half"></i> Open</span>
        </div>
        <div class="stat-value" data-count="<?php echo $stats['pending_po']; ?>">0</div>
        <div class="stat-label">Pending Purchase Orders</div>
        <div class="stat-sub">Awaiting delivery</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon blue"><i class="fas fa-cart-flatbed"></i></span>
            <span class="stat-trend info"><i class="fas fa-calendar"></i> <?php echo date('M'); ?></span>
        </div>
        <div class="stat-value" data-count="<?php echo $stats['po_this_month']; ?>">0</div>
        <div class="stat-label">Purchase Orders This Month</div>
        <div class="stat-sub">Created in <?php echo date('F Y'); ?></div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="mb-4">
    <h6 class="form-section-title"><i class="fas fa-bolt"></i> Quick Actions</h6>
    <div class="quick-actions">
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/product-add.php"><i class="fas fa-box-open"></i><span>Add Product</span></a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/stock-in.php"><i class="fas fa-arrow-down-to-bracket"></i><span>Stock In</span></a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/stock-out.php"><i class="fas fa-arrow-up-from-bracket"></i><span>Stock Out</span></a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php"><i class="fas fa-file-signature"></i><span>New Purchase Order</span></a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/supplier-add.php"><i class="fas fa-truck"></i><span>Add Supplier</span></a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/low-stock.php"><i class="fas fa-triangle-exclamation"></i><span>View Low Stock</span></a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/reports.php"><i class="fas fa-chart-pie"></i><span>Inventory Reports</span></a>
    </div>
</div>

<!-- CHARTS ROW 1 -->
<div class="row g-4 mb-4">
    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-simple"></i> Stock Overview</h5>
            </div>
            <div class="card-body-custom">
                <div class="chart-box" style="min-height:240px;">
                    <canvas id="stockOverviewChart"
                            data-labels='["In Stock","Low Stock","Out of Stock"]'
                            data-values='<?php echo json_encode([$stockOverview['in_stock'], $stockOverview['low_stock'], $stockOverview['out_of_stock']]); ?>'></canvas>
                    <div class="donut-center"><b><?php echo array_sum($stockOverview); ?></b><span>Products</span></div>
                </div>
                <div class="chart-legend-wrap mt-3">
                    <?php
                    $legend = [
                        ['In Stock', $stockOverview['in_stock'], '#22c55e'],
                        ['Low Stock', $stockOverview['low_stock'], '#f59e0b'],
                        ['Out of Stock', $stockOverview['out_of_stock'], '#ef4444'],
                    ];
                    foreach ($legend as $lg): ?>
                    <div class="lg-item">
                        <span class="lg-dot" style="background:<?php echo $lg[2]; ?>"></span>
                        <?php echo $lg[0]; ?>
                        <span class="lg-val"><?php echo $lg[1]; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-column"></i> Inventory Value by Category</h5>
                <span class="stat-trend info"><i class="fas fa-coins"></i> $<?php echo number_format($stats['inventory_value'], 2); ?></span>
            </div>
            <div class="card-body-custom">
                <div class="chart-box" style="min-height:300px;">
                    <canvas id="valueChart"
                            data-labels='<?php echo json_encode($categoryValue['labels']); ?>'
                            data-values='<?php echo json_encode($categoryValue['values']); ?>'></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS ROW 2 -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-line"></i> Stock Movement — <?php echo date('Y'); ?></h5>
            </div>
            <div class="card-body-custom">
                <div class="chart-box" style="min-height:300px;">
                    <canvas id="movementChart"
                            data-months='<?php echo json_encode($monthLabels); ?>'
                            data-in='<?php echo json_encode($stockInMonthly); ?>'
                            data-out='<?php echo json_encode($stockOutMonthly); ?>'
                            data-adj='<?php echo json_encode($adjustMonthly); ?>'></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-ranking-star"></i> Top Used Products</h5>
            </div>
            <div class="card-body-custom">
                <div class="chart-box" style="min-height:300px;">
                    <canvas id="topUsedChart"
                            data-labels='<?php echo json_encode($topUsed['labels']); ?>'
                            data-values='<?php echo json_encode($topUsed['values']); ?>'></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- RECENT ACTIVITY -->
<div class="row g-4">
    <div class="col-12">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-clock-rotate-left"></i> Recent Inventory Activity</h5>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/transactions.php" class="btn-admin btn-outline btn-xs">View Transactions</a>
            </div>
            <?php if (empty($recent)): ?>
            <div class="empty-state"><i class="fas fa-box"></i><h5>No inventory activity yet</h5><p>Stock movements and purchase receipts will appear here.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr><th>Product</th><th>Type</th><th>Quantity</th><th>Stock Changed</th><th>Unit Cost</th><th>Reference</th><th>User</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $tx): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($tx['product_name']); ?></td>
                            <td><span class="<?php echo invTxnTypeBadge($tx['transaction_type']); ?>"><?php echo ucwords(str_replace('_',' ', $tx['transaction_type'])); ?></span></td>
                            <td>
                                <span class="<?php echo (int)$tx['quantity'] >= 0 ? 'status-badge completed' : 'status-badge cancelled'; ?>">
                                    <?php echo (int)$tx['quantity'] >= 0 ? '+' : ''; ?><?php echo (int)$tx['quantity']; ?>
                                </span>
                            </td>
                            <td><span class="cell-sub"><?php echo (int)$tx['previous_stock']; ?> → <?php echo (int)$tx['new_stock']; ?></span></td>
                            <td>$<?php echo number_format((float)$tx['unit_cost'], 2); ?></td>
                            <td class="cell-sub"><?php echo $tx['reference'] ? sanitize($tx['reference']) : '—'; ?></td>
                            <td class="cell-sub"><?php echo $tx['user_name'] ? sanitize($tx['user_name']) : '—'; ?></td>
                            <td><span class="cell-sub"><?php echo formatDate($tx['created_at'], 'M d, Y g:i A'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    window.InvApp = window.InvApp || {};
    if (!window.Chart) return;

    // 1. Stock overview doughnut
    var so = document.getElementById('stockOverviewChart');
    if (so) {
        new Chart(so, {
            type: 'doughnut',
            data: {
                labels: JSON.parse(so.getAttribute('data-labels') || '[]'),
                datasets: [{
                    data: JSON.parse(so.getAttribute('data-values') || '[]'),
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                    borderColor: '#0d2638', borderWidth: 3, hoverOffset: 8
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0d2638', borderColor: 'rgba(0,194,217,0.35)', borderWidth: 1, titleColor: '#fff', bodyColor: '#c3cfe0' } }
            }
        });
    }

    // 2. Category value bar
    var vc = document.getElementById('valueChart');
    if (vc) {
        new Chart(vc, {
            type: 'bar',
            data: {
                labels: JSON.parse(vc.getAttribute('data-labels') || '[]'),
                datasets: [{
                    label: 'Value ($)', data: JSON.parse(vc.getAttribute('data-values') || '[]'),
                    backgroundColor: 'rgba(0,194,217,0.75)', borderColor: '#00C2D9', borderWidth: 1, borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0d2638', borderColor: 'rgba(0,194,217,0.35)', borderWidth: 1, titleColor: '#fff', bodyColor: '#c3cfe0', callbacks: { label: function (c) { return ' $' + Number(c.parsed.y).toLocaleString(); } } } },
                scales: { x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#7d8da1' } }, y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#7d8da1', callback: function (v) { return '$' + v.toLocaleString(); } } } }
            }
        });
    }

    // 3. Stock movement multi-line
    var mv = document.getElementById('movementChart');
    if (mv) {
        new Chart(mv, {
            type: 'line',
            data: {
                labels: JSON.parse(mv.getAttribute('data-months') || '[]'),
                datasets: [
                    { label: 'Stock In', data: JSON.parse(mv.getAttribute('data-in') || '[]'), borderColor: '#00C2D9', backgroundColor: 'rgba(0,194,217,0.2)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3 },
                    { label: 'Stock Out', data: JSON.parse(mv.getAttribute('data-out') || '[]'), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.15)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3 },
                    { label: 'Adjustments', data: JSON.parse(mv.getAttribute('data-adj') || '[]'), borderColor: '#f59e0b', backgroundColor: 'transparent', borderDash: [4,4], tension: 0.4, borderWidth: 2, pointRadius: 3 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#c3cfe0' } }, tooltip: { backgroundColor: '#0d2638', borderColor: 'rgba(0,194,217,0.35)', borderWidth: 1, titleColor: '#fff', bodyColor: '#c3cfe0' } },
                scales: { x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#7d8da1' } }, y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#7d8da1', beginAtZero: true } } }
            }
        });
    }

    // 4. Top used products (horizontal bar)
    var tu = document.getElementById('topUsedChart');
    if (tu) {
        new Chart(tu, {
            type: 'bar',
            data: {
                labels: JSON.parse(tu.getAttribute('data-labels') || '[]'),
                datasets: [{ label: 'Units used', data: JSON.parse(tu.getAttribute('data-values') || '[]'), backgroundColor: 'rgba(212,168,67,0.8)', borderRadius: 6 }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0d2638', borderColor: 'rgba(212,168,67,0.5)', borderWidth: 1, titleColor: '#fff', bodyColor: '#c3cfe0' } },
                scales: { x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#7d8da1', beginAtZero: true } }, y: { grid: { display: false }, ticks: { color: '#c3cfe0' } } }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>