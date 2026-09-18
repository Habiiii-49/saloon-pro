<?php
/**
 * Admin Dashboard
 * Elegance Salon – PART 2
 *
 * All statistics are computed live from MySQL (db-saloon).
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/inventory/includes/functions.php';

requireRole('admin');

$pageTitle = 'Dashboard';
$activeMenu = 'index';

$db = getDBConnection();
$allStats = [
    'total_appointments'    => 0,
    'today_appointments'    => 0,
    'yesterday_appointments'=> 0,
    'total_clients'         => 0,
    'total_stylists'        => 0,
    'total_services'        => 0,
    'monthly_revenue'       => 0.0,
    'prev_month_revenue'    => 0.0,
    'low_stock_count'       => 0,
    'pending_payments'      => 0,
];
$queriesOk = true;

/* ---------- 1. STATISTICS ---------- */
try {
    $allStats['total_appointments']   = (int)$db->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    $allStats['today_appointments']   = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
    $allStats['yesterday_appointments'] = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
    $allStats['total_clients']        = (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $allStats['total_stylists']       = (int)$db->query("SELECT COUNT(*) FROM staff")->fetchColumn();
    $allStats['total_services']       = (int)$db->query("SELECT COUNT(*) FROM services WHERE is_active = 1")->fetchColumn();

    $monthNow = "SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='completed' AND YEAR(COALESCE(paid_at, created_at)) = YEAR(CURDATE()) AND MONTH(COALESCE(paid_at, created_at)) = MONTH(CURDATE())";
    $monthPrev = "SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='completed' AND YEAR(COALESCE(paid_at, created_at)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(COALESCE(paid_at, created_at)) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    $allStats['monthly_revenue']      = (float)$db->query($monthNow)->fetchColumn();
    $allStats['prev_month_revenue']   = (float)$db->query($monthPrev)->fetchColumn();

    $allStats['low_stock_count']      = (int)$db->query("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level")->fetchColumn();
    $allStats['pending_payments']     = (int)$db->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    $queriesOk = false;
    setFlash('error', 'Could not load dashboard statistics.');
}

/* ---------- 2. TODAY'S APPOINTMENTS ---------- */
$todayAppointments = [];
try {
    $stmt = $db->query("
        SELECT a.appointment_id, a.appointment_date, DATE_FORMAT(a.appointment_time,'%h:%i %p') AS time_fmt,
               a.status AS appointment_status, a.total_amount, a.notes,
               CONCAT(cl.first_name,' ',cl.last_name) AS client_name, cl.phone,
               s.service_name,
               CONCAT(u.first_name,' ',u.last_name) AS stylist_name,
               COALESCE(p.payment_status,'pending') AS payment_status, p.payment_method
        FROM appointments a
        JOIN clients cl ON cl.client_id = a.client_id
        JOIN services s ON s.service_id = a.service_id
        LEFT JOIN staff st ON st.staff_id = a.staff_id
        LEFT JOIN users u ON u.user_id = st.user_id
        LEFT JOIN payments p ON p.appointment_id = a.appointment_id
        WHERE a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $todayAppointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $todayAppointments = [];
}

/* ---------- 3. STATUS SUMMARY (doughnut) ---------- */
$statusSummary = ['confirmed' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0];
try {
    $stmt = $db->query("
        SELECT
            CASE status
                WHEN 'confirmed'   THEN 'confirmed'
                WHEN 'in_progress' THEN 'confirmed'
                WHEN 'pending'     THEN 'pending'
                WHEN 'completed'   THEN 'completed'
                WHEN 'cancelled'   THEN 'cancelled'
                WHEN 'no_show'     THEN 'cancelled'
            END AS grp,
            COUNT(*) AS cnt
        FROM appointments
        GROUP BY grp
    ");
    while ($row = $stmt->fetch()) {
        if (isset($statusSummary[$row['grp']])) {
            $statusSummary[$row['grp']] = (int)$row['cnt'];
        }
    }
} catch (PDOException $e) {
    $statusSummary = ['confirmed' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0];
}

/* ---------- 4. MONTHLY REVENUE (current year) ---------- */
$monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthlyRevenue = array_fill(0, 12, 0.0);
try {
    $stmt = $db->prepare("
        SELECT MONTH(COALESCE(paid_at, created_at)) AS m, SUM(amount) AS total
        FROM payments
        WHERE payment_status = 'completed'
          AND YEAR(COALESCE(paid_at, created_at)) = :yr
        GROUP BY m
    ");
    $stmt->execute([':yr' => date('Y')]);
    while ($row = $stmt->fetch()) {
        $idx = (int)$row['m'] - 1;
        if ($idx >= 0 && $idx < 12) {
            $monthlyRevenue[$idx] = (float)$row['total'];
        }
    }
} catch (PDOException $e) {
    $monthlyRevenue = array_fill(0, 12, 0.0);
}

$yearRevenueTotal = array_sum($monthlyRevenue);

/* ---------- 5. POPULAR SERVICES (top 5) ---------- */
$popularServices = [];
try {
    $stmt = $db->query("
        SELECT s.service_id, s.service_name, s.price,
               COUNT(a.appointment_id) AS bookings,
               COALESCE(SUM(a.total_amount), 0) AS revenue
        FROM services s
        LEFT JOIN appointments a ON a.service_id = s.service_id
        GROUP BY s.service_id, s.service_name, s.price
        HAVING bookings > 0
        ORDER BY bookings DESC, revenue DESC
        LIMIT 5
    ");
    $popularServices = $stmt->fetchAll();
} catch (PDOException $e) {
    $popularServices = [];
}

/* ---------- 6. RECENT CLIENTS ---------- */
$recentClients = [];
try {
    $recentClients = $db->query("
        SELECT client_id, first_name, last_name, email, phone, created_at
        FROM clients ORDER BY created_at DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $recentClients = [];
}

/* ---------- 7. RECENT PAYMENTS ---------- */
$recentPayments = [];
try {
    $recentPayments = $db->query("
        SELECT p.payment_id, p.amount, p.payment_method, p.payment_status,
               COALESCE(p.paid_at, p.created_at) AS p_date,
               COALESCE(i.invoice_number, CONCAT('APT-', a.appointment_id)) AS invoice_ref,
               CONCAT(cl.first_name,' ',cl.last_name) AS client_name
        FROM payments p
        LEFT JOIN appointments a ON a.appointment_id = p.appointment_id
        LEFT JOIN clients cl ON cl.client_id = a.client_id
        LEFT JOIN invoices i ON i.appointment_id = p.appointment_id
        ORDER BY p.payment_id DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $recentPayments = [];
}

/* ---------- 8. LOW STOCK ---------- */
$lowStockItems = [];
try {
    $lowStockItems = $db->query("
        SELECT inventory_id, item_name, quantity, reorder_level
        FROM inventory WHERE quantity <= reorder_level
        ORDER BY quantity ASC LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {
    $lowStockItems = [];
}

/* ---------- 9. INVENTORY OVERVIEW (PART 5) ---------- */
$inventoryStats = [
    'low_stock_products'  => 0,
    'out_of_stock_products' => 0,
    'inventory_value'     => 0.0,
    'pending_purchase_orders' => 0,
];
$recentStockMoves = [];
try {
    $inventoryStats['low_stock_products']   = (int)$db->query("SELECT COUNT(*) FROM inventory WHERE status = 'active' AND quantity <= minimum_stock AND quantity > 0")->fetchColumn();
    $inventoryStats['out_of_stock_products']= (int)$db->query("SELECT COUNT(*) FROM inventory WHERE status = 'active' AND quantity = 0")->fetchColumn();
    $inventoryStats['inventory_value']      = (float)$db->query("SELECT COALESCE(SUM(quantity * cost_price), 0) FROM inventory WHERE status = 'active'")->fetchColumn();
    $inventoryStats['pending_purchase_orders'] = (int)$db->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft','pending','ordered','partially_received')")->fetchColumn();

    $recentStockMoves = $db->query("
        SELECT t.id, t.transaction_type, t.quantity, t.reference, t.created_at,
               i.item_name AS product_name,
               CONCAT(u.first_name, ' ', u.last_name) AS user_name
        FROM inventory_transactions t
        JOIN inventory i ON i.inventory_id = t.product_id
        LEFT JOIN users u ON u.user_id = t.user_id
        ORDER BY t.id DESC
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    $recentStockMoves = [];
}

/* ---------- 10. REVENUE OVERVIEW (PART 6) ---------- */
$financeStats = ['today' => [], 'month' => [], 'outstanding' => 0.0];
$financeCharts = ['day' => [], 'month' => [], 'method' => [], 'service' => [], 'stylist' => []];
try {
    $financeStats['today']  = financeRevenueRange($db, date('Y-m-d'), date('Y-m-d'));
    $financeStats['month']  = financeRevenueRange($db, date('Y-m-01'), date('Y-m-d'));
    $financeStats['outstanding'] = financeRevenueRange($db, date('Y-m-01'), date('Y-m-d'))['outstanding'];
    $financeStats['year']   = financeRevenueRange($db, date('Y-01-01'), date('Y-m-d'));

    $financeCharts['day']     = financeRevenueGroup($db, date('Y-m-01'), date('Y-m-d'), 'day');
    $financeCharts['month']   = financeRevenueGroup($db, date('Y-01-01'), date('Y-m-d'), 'month');
    $financeCharts['method']  = financeRevenueGroup($db, date('Y-m-01'), date('Y-m-d'), 'method');
    $financeCharts['service'] = financeRevenueGroup($db, date('Y-m-01'), date('Y-m-d'), 'service');
    $financeCharts['stylist'] = financeRevenueGroup($db, date('Y-m-01'), date('Y-m-d'), 'stylist');
} catch (PDOException $e) {
    $financeStats = ['today' => [], 'month' => [], 'outstanding' => 0.0];
}

$hour      = (int)date('G');
$greeting  = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$firstName = currentUserFirstName() ?: 'Admin';
$firstName = preg_replace('/\s+/', ' ', trim($firstName));
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Page Header -->
<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-gauge-high"></i> <?php echo $greeting; ?>, <?php echo sanitize($firstName); ?></h1>
        <p>Here's what's happening at Elegance Salon today.</p>
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
            <span class="stat-icon cyan"><i class="fas fa-calendar-check"></i></span>
            <span class="stat-trend info"><i class="fas fa-arrow-up"></i> Total</span>
        </div>
        <div class="stat-value" data-count="<?php echo $allStats['total_appointments']; ?>">0</div>
        <div class="stat-label">Total Appointments</div>
        <div class="stat-sub">All time bookings</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon blue"><i class="fas fa-calendar-day"></i></span>
            <?php
            $todayDelta = $allStats['today_appointments'] - $allStats['yesterday_appointments'];
            $deltaClass = $todayDelta >= 0 ? 'up' : 'down';
            ?>
            <span class="stat-trend <?php echo $deltaClass; ?>">
                <i class="fas <?php echo $todayDelta >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i> <?php echo abs($todayDelta); ?> vs yesterday
            </span>
        </div>
        <div class="stat-value" data-count="<?php echo $allStats['today_appointments']; ?>">0</div>
        <div class="stat-label">Today's Appointments</div>
        <div class="stat-sub"><?php echo date('M d, Y'); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon green"><i class="fas fa-users"></i></span>
            <span class="stat-trend info"><i class="fas fa-user-plus"></i> Total</span>
        </div>
        <div class="stat-value" data-count="<?php echo $allStats['total_clients']; ?>">0</div>
        <div class="stat-label">Total Clients</div>
        <div class="stat-sub">Registered customers</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon violet"><i class="fas fa-user-tie"></i></span>
            <span class="stat-trend info"><i class="fas fa-star"></i> Team</span>
        </div>
        <div class="stat-value" data-count="<?php echo $allStats['total_stylists']; ?>">0</div>
        <div class="stat-label">Total Stylists</div>
        <div class="stat-sub">Active staff</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon cyan"><i class="fas fa-scissors"></i></span>
            <span class="stat-trend info"><i class="fas fa-layer-group"></i> Offer</span>
        </div>
        <div class="stat-value" data-count="<?php echo $allStats['total_services']; ?>">0</div>
        <div class="stat-label">Total Services</div>
        <div class="stat-sub">Active catalogue</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon gold"><i class="fas fa-money-bill-trend-up"></i></span>
            <?php
            $revDelta = $allStats['monthly_revenue'] - $allStats['prev_month_revenue'];
            $revClass = $revDelta >= 0 ? 'up' : 'down';
            ?>
            <span class="stat-trend <?php echo $revClass; ?>">
                <i class="fas <?php echo $revDelta >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
                <?php echo $revDelta >= 0 ? '+' : '-'; ?>$<?php echo number_format(abs($revDelta), 2); ?>
            </span>
        </div>
        <div class="stat-value" data-prefix="$" data-decimals="0" data-count="<?php echo (int)$allStats['monthly_revenue']; ?>">$0</div>
        <div class="stat-label">Monthly Revenue</div>
        <div class="stat-sub"><?php echo date('F Y'); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon orange"><i class="fas fa-boxes-stacked"></i></span>
            <span class="stat-trend warn"><i class="fas fa-triangle-exclamation"></i> Alert</span>
        </div>
        <div class="stat-value" data-count="<?php echo $allStats['low_stock_count']; ?>">0</div>
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-sub">At or below reorder level</div>
    </div>

    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon red"><i class="fas fa-clock"></i></span>
            <span class="stat-trend warn"><i class="fas fa-hourglass-half"></i> Action</span>
        </div>
        <div class="stat-value" data-count="<?php echo $allStats['pending_payments']; ?>">0</div>
        <div class="stat-label">Pending Payments</div>
        <div class="stat-sub">Awaiting settlement</div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="mb-4">
    <h6 class="form-section-title"><i class="fas fa-bolt"></i> Quick Actions</h6>
    <div class="quick-actions">
        <a class="quick-action" href="<?php echo SITE_URL; ?>/book-appointment.php" title="Create a new appointment">
            <i class="fas fa-calendar-plus"></i><span>New Appointment</span>
        </a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/product-add.php" title="Add an inventory product">
            <i class="fas fa-box-open"></i><span>Add Product</span>
        </a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/stock-in.php" title="Add stock to a product">
            <i class="fas fa-arrow-down-to-bracket"></i><span>Stock In</span>
        </a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-add.php" title="Create a purchase order">
            <i class="fas fa-file-signature"></i><span>New Purchase Order</span>
        </a>
        <a class="quick-action" href="<?php echo SITE_URL; ?>/admin/inventory/low-stock.php" title="View low stock items">
            <i class="fas fa-triangle-exclamation"></i><span>Low Stock</span>
        </a>
    </div>
</div>

<!-- INVENTORY OVERVIEW (PART 5) -->
<div class="stat-grid mb-4">
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon orange"><i class="fas fa-triangle-exclamation"></i></span>
            <span class="stat-trend warn"><i class="fas fa-box"></i> Alert</span>
        </div>
        <div class="stat-value" data-count="<?php echo $inventoryStats['low_stock_products']; ?>">0</div>
        <div class="stat-label">Low Stock Products</div>
        <div class="stat-sub">At or below minimum level</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon red"><i class="fas fa-box-open"></i></span>
            <span class="stat-trend warn"><i class="fas fa-circle-exclamation"></i> Empty</span>
        </div>
        <div class="stat-value" data-count="<?php echo $inventoryStats['out_of_stock_products']; ?>">0</div>
        <div class="stat-label">Out of Stock</div>
        <div class="stat-sub">Unavailable products</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon gold"><i class="fas fa-sack-dollar"></i></span>
            <span class="stat-trend info"><i class="fas fa-coins"></i> Value</span>
        </div>
        <div class="stat-value" data-prefix="$" data-decimals="0" data-count="<?php echo (int)$inventoryStats['inventory_value']; ?>">$0</div>
        <div class="stat-label">Inventory Value</div>
        <div class="stat-sub">At cost price</div>
    </div>
    <div class="stat-card">
        <div class="stat-top">
            <span class="stat-icon cyan"><i class="fas fa-file-signature"></i></span>
            <span class="stat-trend info"><i class="fas fa-hourglass-half"></i> Open</span>
        </div>
        <div class="stat-value" data-count="<?php echo $inventoryStats['pending_purchase_orders']; ?>">0</div>
        <div class="stat-label">Pending Purchase Orders</div>
        <div class="stat-sub">Awaiting delivery</div>
    </div>
</div>

<!-- REVENUE OVERVIEW (PART 6) -->
<?php if (!empty($financeStats['today']) && !empty($financeStats['month'])): ?>
<div class="mb-4">
    <h6 class="form-section-title"><i class="fas fa-money-bill-trend-up"></i> Revenue Overview (Part 6)</h6>
    <div class="finance-grid mb-3">
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon green"><i class="fas fa-calendar-day"></i></span><span class="stat-trend up">Today</span></div>
            <div class="stat-value"><?php echo formatMoney($financeStats['today']['net']); ?></div>
            <div class="stat-label">Net Collected Today</div>
            <div class="stat-sub"><?php echo $financeStats['today']['payments']; ?> payments</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon cyan"><i class="fas fa-calendar-week"></i></span><span class="stat-trend info">This Month</span></div>
            <div class="stat-value"><?php echo formatMoney($financeStats['month']['net']); ?></div>
            <div class="stat-label">Net Revenue (<?php echo date('M'); ?>)</div>
            <div class="stat-sub"><?php echo formatMoney($financeStats['month']['gross']); ?> gross</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon gold"><i class="fas fa-calendar-year"></i></span><span class="stat-trend info"><?php echo date('Y'); ?></span></div>
            <div class="stat-value"><?php echo formatMoney($financeStats['year']['net'] ?? 0); ?></div>
            <div class="stat-label">Year to Date</div>
            <div class="stat-sub">Net collected</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><span class="stat-icon orange"><i class="fas fa-hourglass-half"></i></span><span class="stat-trend warn">Due</span></div>
            <div class="stat-value" style="color:#fbbf24;"><?php echo formatMoney($financeStats['outstanding']); ?></div>
            <div class="stat-label">Outstanding</div>
            <div class="stat-sub">Unpaid invoice balances</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4 col-md-6">
            <div class="admin-card h-100">
                <div class="card-header-custom"><h6><i class="fas fa-chart-bar"></i> Revenue by Day (this month)</h6></div>
                <div class="card-body-custom"><canvas id="finDayChart" style="height:200px;"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="admin-card h-100">
                <div class="card-header-custom"><h6><i class="fas fa-chart-line"></i> Monthly Series (<?php echo date('Y'); ?>)</h6></div>
                <div class="card-body-custom"><canvas id="finMonthChart" style="height:200px;"></canvas></div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="admin-card h-100">
                <div class="card-header-custom"><h6><i class="fas fa-chart-pie"></i> By Method (this month)</h6></div>
                <div class="card-body-custom"><canvas id="finMethodChart" style="height:200px;"></canvas></div>
            </div>
        </div>
        <div class="col-xl-6 col-md-6">
            <div class="admin-card h-100">
                <div class="card-header-custom"><h6><i class="fas fa-scissors"></i> By Service</h6>
                    <a class="btn-admin btn-outline btn-xs" href="<?php echo SITE_URL; ?>/admin/reports/service-revenue.php"><i class="fas fa-arrow-up-right-from-square"></i></a>
                </div>
                <div class="card-body-custom"><canvas id="finServiceChart" style="height:200px;"></canvas></div>
            </div>
        </div>
        <div class="col-xl-6 col-md-6">
            <div class="admin-card h-100">
                <div class="card-header-custom"><h6><i class="fas fa-user-tie"></i> By Stylist</h6>
                    <a class="btn-admin btn-outline btn-xs" href="<?php echo SITE_URL; ?>/admin/reports/stylist-revenue.php"><i class="fas fa-arrow-up-right-from-square"></i></a>
                </div>
                <div class="card-body-custom"><canvas id="finStylistChart" style="height:200px;"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var cyan = '#00c2d9';
    var muted = '#94a3b8';
    var grid = 'rgba(255,255,255,0.06)';
    var axes = { ticks: { color: muted }, grid: { color: grid } };
    var pal = ['#00c2d9', '#8b5cf6', '#fbbf24', '#34d399', '#f472b6', '#60a5fa', '#f87171', '#a3e635'];

    function finChart(id, type, labels, values, extra) {
        new Chart(document.getElementById(id), {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: type === 'bar' ? 'rgba(0,194,217,0.4)' : pal,
                    borderColor: type === 'bar' ? cyan : pal,
                    borderWidth: 1.5,
                    borderRadius: type === 'bar' ? 5 : 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: type !== 'bar', position: 'bottom', labels: { color: muted, boxWidth: 10 } } },
                scales: type === 'bar' || type === 'line' ? { y: { beginAtZero: true, ...axes }, x: { ...axes, ticks: { color: muted, maxRotation: 45, autoSkip: true, maxTicksLimit: 12 } } } : {}
            }
        });
    }

    finChart('finDayChart', 'bar',
        <?php echo json_encode(array_column($financeCharts['day'], 'label')); ?>,
        <?php echo json_encode(array_map(fn($r) => round((float)$r['value'], 2), $financeCharts['day'])); ?>);
    finChart('finMonthChart', 'line',
        <?php echo json_encode(array_map(fn($m) => substr((string)$m['label'], 0, 7), $financeCharts['month'])); ?>,
        <?php echo json_encode(array_map(fn($m) => round((float)$m['value'], 2), $financeCharts['month'])); ?>);
    finChart('finMethodChart', 'doughnut',
        <?php echo json_encode(array_map(fn($x) => ucfirst(str_replace('_', ' ', $x['label'])), $financeCharts['method'])); ?>,
        <?php echo json_encode(array_map(fn($x) => round((float)$x['value'], 2), $financeCharts['method'])); ?>);
    finChart('finServiceChart', 'doughnut',
        <?php echo json_encode(array_column(array_slice($financeCharts['service'], 0, 7), 'label')); ?>,
        <?php echo json_encode(array_map(fn($x) => round((float)$x['value'], 2), array_slice($financeCharts['service'], 0, 7))); ?>);
    finChart('finStylistChart', 'doughnut',
        <?php echo json_encode(array_column(array_slice($financeCharts['stylist'], 0, 7), 'label')); ?>,
        <?php echo json_encode(array_map(fn($x) => round((float)$x['value'], 2), array_slice($financeCharts['stylist'], 0, 7))); ?>);
});
</script>
<?php endif; ?>

<!-- APPOINTMENTS + STATUS -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-calendar-day"></i> Today's Appointments</h5>
                <a href="<?php echo SITE_URL; ?>/admin/coming-soon.php?page=appointments" class="btn-admin btn-outline btn-xs">View All</a>
            </div>
            <?php if (empty($todayAppointments)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-xmark"></i>
                <h5>No appointments scheduled for today</h5>
                <p>When clients book appointments, they will show up here instantly.</p>
                <a href="<?php echo SITE_URL; ?>/book-appointment.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> New Appointment</a>
            </div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Stylist</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayAppointments as $apt): ?>
                        <tr>
                            <td>
                                <span class="cell-strong"><?php echo sanitize($apt['client_name']); ?></span>
                                <div class="cell-sub"><?php echo sanitize($apt['phone']); ?></div>
                            </td>
                            <td class="cell-strong"><?php echo sanitize($apt['service_name']); ?></td>
                            <td><?php echo $apt['stylist_name'] ? sanitize($apt['stylist_name']) : '<span class="cell-sub">Unsigned</span>'; ?></td>
                            <td><i class="far fa-clock text-muted me-1"></i><?php echo sanitize($apt['time_fmt']); ?></td>
                            <td><span class="status-badge <?php echo sanitize($apt['appointment_status']); ?>"><?php echo ucwords(str_replace('_', ' ', sanitize($apt['appointment_status']))); ?></span></td>
                            <td>
                                <span class="status-badge <?php echo $apt['payment_status'] === 'completed' ? 'paid' : ($apt['payment_status'] === 'pending' ? 'pending' : 'failed'); ?>">
                                    <?php echo ucfirst(sanitize($apt['payment_status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button type="button" class="btn-admin btn-outline btn-xs"
                                            data-bs-toggle="modal" data-bs-target="#viewApptModal"
                                            data-name="<?php echo sanitize($apt['client_name']); ?>"
                                            data-phone="<?php echo sanitize($apt['phone']); ?>"
                                            data-service="<?php echo sanitize($apt['service_name']); ?>"
                                            data-stylist="<?php echo $apt['stylist_name'] ? sanitize($apt['stylist_name']) : 'Unsigned'; ?>"
                                            data-time="<?php echo sanitize($apt['time_fmt']); ?>"
                                            data-status="<?php echo ucwords(str_replace('_',' ', $apt['appointment_status'])); ?>"
                                            data-payment="<?php echo ucfirst($apt['payment_status']); ?>"
                                            data-amount="$<?php echo number_format((float)$apt['total_amount'], 2); ?>"
                                            data-notes="<?php echo $apt['notes'] ? sanitize($apt['notes']) : '—'; ?>"
                                            title="View"><i class="fas fa-eye"></i></button>
                                    <a class="btn-admin btn-outline btn-xs" href="<?php echo SITE_URL; ?>/admin/coming-soon.php?page=appointments" title="Edit"><i class="fas fa-pen"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-simple"></i> Appointment Status</h5>
            </div>
            <div class="card-body-custom">
                <div class="chart-box" style="min-height:240px;">
                    <canvas id="appointmentsChart"
                            data-labels='<?php echo json_encode(array_keys($statusSummary)); ?>'
                            data-values='<?php echo json_encode(array_values($statusSummary)); ?>'></canvas>
                    <div class="donut-center">
                        <b><?php echo array_sum($statusSummary); ?></b>
                        <span>Bookings</span>
                    </div>
                </div>
                <div class="chart-legend-wrap mt-3">
                    <?php
                    $legendColors = [
                        'confirmed' => '#00C2D9',
                        'pending'   => '#f59e0b',
                        'completed' => '#22c55e',
                        'cancelled' => '#ef4444',
                    ];
                    $legendLabels = ['Confirmed' => 'confirmed', 'Pending' => 'pending', 'Completed' => 'completed', 'Cancelled' => 'cancelled'];
                    foreach ($legendLabels as $label => $key): ?>
                    <div class="lg-item">
                        <span class="lg-dot" style="background:<?php echo $legendColors[$key]; ?>"></span>
                        <?php echo $label; ?>
                        <span class="lg-val"><?php echo $statusSummary[$key]; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- REVENUE + POPULAR -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-line"></i> Revenue Analytics — <?php echo date('Y'); ?></h5>
                <span class="stat-trend up"><i class="fas fa-coins"></i> $<?php echo number_format($yearRevenueTotal, 2); ?> this year</span>
            </div>
            <div class="card-body-custom">
                <div class="chart-box" style="min-height:300px;">
                    <canvas id="revenueChart"
                            data-months='<?php echo json_encode($monthNames); ?>'
                            data-revenues='<?php echo json_encode(array_map('floatval', $monthlyRevenue)); ?>'></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-crown"></i> Popular Services</h5>
            </div>
            <?php if (empty($popularServices)): ?>
            <div class="empty-state"><i class="fas fa-scissors"></i><h5>No bookings yet</h5><p>Service bookings will appear here once clients start booking.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table" style="min-width:0;">
                    <thead><tr><th>Service</th><th>Bookings</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($popularServices as $i => $svc): ?>
                        <tr>
                            <td>
                                <span class="status-badge completed me-2">#<?php echo $i + 1; ?></span>
                                <span class="cell-strong"><?php echo sanitize($svc['service_name']); ?></span>
                            </td>
                            <td><span class="status-badge confirmed"><?php echo (int)$svc['bookings']; ?> booked</span></td>
                            <td class="cell-strong">$<?php echo number_format((float)$svc['revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CLIENTS + PAYMENTS -->
<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-users"></i> Recent Clients</h5>
                <a href="<?php echo SITE_URL; ?>/admin/coming-soon.php?page=clients" class="btn-admin btn-outline btn-xs">View All</a>
            </div>
            <?php if (empty($recentClients)): ?>
            <div class="empty-state"><i class="fas fa-user-clock"></i><h5>No clients registered yet</h5><p>New clients added through the salon will appear here.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table" style="min-width:540px;">
                    <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Joined</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentClients as $client): ?>
                        <tr>
                            <td>
                                <span class="table-avatar"><?php echo strtoupper(mb_substr($client['first_name'], 0, 1)); ?></span>
                                <span class="cell-strong"><?php echo sanitize($client['first_name'] . ' ' . $client['last_name']); ?></span>
                            </td>
                            <td><?php echo $client['phone'] ? sanitize($client['phone']) : '—'; ?></td>
                            <td class="cell-sub"><?php echo $client['email'] ? sanitize($client['email']) : '—'; ?></td>
                            <td><span class="cell-sub"><?php echo formatDate($client['created_at'], 'M d, Y'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-receipt"></i> Recent Payments</h5>
                <a href="<?php echo SITE_URL; ?>/admin/coming-soon.php?page=payments" class="btn-admin btn-outline btn-xs">View All</a>
            </div>
            <?php if (empty($recentPayments)): ?>
            <div class="empty-state"><i class="fas fa-money-bill-wave"></i><h5>No payments recorded</h5><p>Payments received at the salon will appear here.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table" style="min-width:560px;">
                    <thead><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentPayments as $pay): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($pay['invoice_ref']); ?></td>
                            <td><?php echo $pay['client_name'] ? sanitize($pay['client_name']) : '<span class="cell-sub">—</span>'; ?></td>
                            <td class="cell-strong">$<?php echo number_format((float)$pay['amount'], 2); ?></td>
                            <td>
                                <span class="pay-method">
                                    <i class="fas <?php echo $pay['payment_method'] === 'cash' ? 'fa-money-bill' : ($pay['payment_method'] === 'card' ? 'fa-credit-card' : ($pay['payment_method'] === 'online' ? 'fa-globe' : 'fa-circle-dot')); ?>"></i>
                                    <span class="status-badge <?php echo sanitize($pay['payment_method']); ?>"><?php echo ucfirst(sanitize($pay['payment_method'])); ?></span>
                                </span>
                            </td>
                            <td><span class="cell-sub"><?php echo formatDate($pay['p_date'], 'M d, Y'); ?></span></td>
                            <td><span class="status-badge <?php echo $pay['payment_status'] === 'completed' ? 'paid' : ($pay['payment_status'] === 'pending' ? 'pending' : 'failed'); ?>"><?php echo ucfirst(sanitize($pay['payment_status'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- LOW STOCK + RECENT STOCK MOVEMENTS -->
<div class="row g-4">
    <div class="col-xl-7">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-boxes-stacked"></i> Low Stock Alert</h5>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/low-stock.php" class="btn-admin btn-cyan btn-xs"><i class="fas fa-box-open"></i> View Low Stock</a>
            </div>
            <div class="card-body-custom">
                <?php if (empty($lowStockItems)): ?>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <span class="stat-icon green"><i class="fas fa-circle-check"></i></span>
                    <div>
                        <strong style="color:#fff;">Inventory levels are healthy.</strong>
                        <div class="cell-sub">No items are currently at or below their minimum threshold.</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($lowStockItems as $item): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="info-item" style="border-color: rgba(239,68,68,0.3);">
                            <div class="lbl"><i class="fas fa-triangle-exclamation" style="color:#f87171;"></i> Low Stock</div>
                            <div class="val"><?php echo sanitize($item['item_name']); ?></div>
                            <div class="cell-sub mt-2">
                                Qty: <strong style="color:#f87171;"><?php echo (int)$item['quantity']; ?></strong>
                                / Min: <?php echo (int)$item['reorder_level']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="admin-card h-100">
            <div class="card-header-custom">
                <h5><i class="fas fa-arrows-rotate"></i> Recent Stock Movements</h5>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/transactions.php" class="btn-admin btn-outline btn-xs">View All</a>
            </div>
            <?php if (empty($recentStockMoves)): ?>
            <div class="empty-state"><i class="fas fa-box"></i><h5>No stock movements yet</h5><p>Stock in, stock out and adjustments will appear here.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table" style="min-width:420px;">
                    <thead><tr><th>Product</th><th>Type</th><th>Qty</th><th>By</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentStockMoves as $move): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($move['product_name']); ?></td>
                            <td><span class="<?php echo invTxnTypeBadge($move['transaction_type']); ?>"><?php echo ucwords(str_replace('_', ' ', $move['transaction_type'])); ?></span></td>
                            <td>
                                <span class="<?php echo ($move['quantity'] >= 0) ? 'status-badge completed' : 'status-badge cancelled'; ?>">
                                    <?php echo $move['quantity'] >= 0 ? '+' : ''; ?><?php echo (int)$move['quantity']; ?>
                                </span>
                            </td>
                            <td class="cell-sub"><?php echo $move['user_name'] ? sanitize($move['user_name']) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- VIEW APPOINTMENT MODAL -->
<div class="modal fade" id="viewApptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0d2638;border:1px solid rgba(0,194,217,0.25);border-radius:16px;">
            <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.09);">
                <h5 class="modal-title"><i class="fas fa-calendar-check" style="color:#00C2D9;"></i> Appointment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-4">
                <div class="info-grid">
                    <div class="info-item"><div class="lbl">Client</div><div class="val" id="aptName">—</div></div>
                    <div class="info-item"><div class="lbl">Phone</div><div class="val" id="aptPhone">—</div></div>
                    <div class="info-item"><div class="lbl">Service</div><div class="val" id="aptService">—</div></div>
                    <div class="info-item"><div class="lbl">Stylist</div><div class="val" id="aptStylist">—</div></div>
                    <div class="info-item"><div class="lbl">Time</div><div class="val" id="aptTime">—</div></div>
                    <div class="info-item"><div class="lbl">Amount</div><div class="val" id="aptAmount">—</div></div>
                    <div class="info-item"><div class="lbl">Status</div><div class="val" id="aptStatus">—</div></div>
                    <div class="info-item"><div class="lbl">Payment</div><div class="val" id="aptPayment">—</div></div>
                    <div class="info-item" style="grid-column:1/-1;"><div class="lbl">Notes</div><div class="val" id="aptNotes">—</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('viewApptModal');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            var map = {
                '#aptName': btn.getAttribute('data-name'),
                '#aptPhone': btn.getAttribute('data-phone'),
                '#aptService': btn.getAttribute('data-service'),
                '#aptStylist': btn.getAttribute('data-stylist'),
                '#aptTime': btn.getAttribute('data-time'),
                '#aptAmount': btn.getAttribute('data-amount'),
                '#aptStatus': btn.getAttribute('data-status'),
                '#aptPayment': btn.getAttribute('data-payment'),
                '#aptNotes': btn.getAttribute('data-notes')
            };
            Object.keys(map).forEach(function (id) {
                var el = document.querySelector(id);
                if (el) el.textContent = map[id];
            });
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>