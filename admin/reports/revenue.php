<?php
/**
 * Admin - Revenue Report (Part 6)
 * Real, collected revenue by day/month with refunds and outstanding.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Revenue Reports';
$activeMenu = 'reports';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db = getDBConnection();

$preset = $_GET['range'] ?? 'month';
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');

switch ($preset) {
    case 'today':  $from = date('Y-m-d'); $to = date('Y-m-d'); break;
    case '7d':     $from = date('Y-m-d', strtotime('-6 days')); $to = date('Y-m-d'); break;
    case '30d':    $from = date('Y-m-d', strtotime('-29 days')); $to = date('Y-m-d'); break;
    case 'custom': break;
    case 'month':
    default:       $from = date('Y-m-01'); $to = date('Y-m-d'); break;
}

[$from, $to] = financeNormalizeRange($from, $to);
$totals  = financeRevenueRange($db, $from, $to);
$byDay   = financeRevenueGroup($db, $from, $to, 'day');
$byMonth = financeRevenueGroup($db, $from, $to, 'month');
$byMethod = financeRevenueGroup($db, $from, $to, 'method');

$dailyLabels = array_column($byDay, 'label');
$dailyValues = array_map(fn($r) => round((float)$r['value'], 2), $byDay);
$methodLabels = array_column($byMethod, 'label');
$methodValues = array_map(fn($r) => round((float)$r['value'], 2), $byMethod);
$methodLabels['0'] = isset($methodLabels[0]) ? ucfirst(str_replace('_', ' ', $methodLabels[0])) : '—';

/* --- CSV export of the daily series (must run before any output) --- */
if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revenue-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Period', 'Net Revenue', 'Gross', 'Refunds', 'Payments']);
    foreach ($byDay as $d) {
        fputcsv($out, [$d['label'], number_format((float)$d['value'], 2, '.', ''), '', '']);
    }
    fputcsv($out, ['TOTAL', number_format($totals['net'], 2, '.', ''), number_format($totals['gross'], 2, '.', ''), number_format($totals['refunds'], 2, '.', ''), $totals['payments']]);
    fclose($out);
    exit;
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-chart-line"></i> Revenue Report</h1>
        <p>Collected and refunded money between <?php echo formatDate($from); ?> and <?php echo formatDate($to); ?>.</p>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
        <select name="range" class="form-control-admin form-control-sm" onchange="this.form.submit()">
            <option value="today"  <?php echo $preset === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="7d"     <?php echo $preset === '7d' ? 'selected' : ''; ?>>Last 7 days</option>
            <option value="30d"    <?php echo $preset === '30d' ? 'selected' : ''; ?>>Last 30 days</option>
            <option value="month"  <?php echo $preset === 'month' ? 'selected' : ''; ?>>This month</option>
            <option value="custom" <?php echo $preset === 'custom' ? 'selected' : ''; ?>>Custom range</option>
        </select>
        <?php if ($preset === 'custom'): ?>
        <input type="date" name="from" value="<?php echo sanitize($from); ?>" class="form-control-admin form-control-sm">
        <input type="date" name="to" value="<?php echo sanitize($to); ?>" class="form-control-admin form-control-sm">
        <button class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Apply</button>
        <?php endif; ?>
        <a href="?export=1&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn-admin btn-outline btn-xs"><i class="fas fa-file-csv"></i> CSV</a>
    </form>
</div>

<div class="finance-grid mb-4">
    <div class="stat-card">
        <div class="stat-value"><?php echo formatMoney($totals['gross']); ?></div>
        <div class="stat-label">Gross Revenue</div>
    </div>
    <div class="stat-card">
        <div class="stat-value fin-amount-neg"><?php echo formatMoney($totals['refunds']); ?></div>
        <div class="stat-label">Refunds (<?php echo $totals['refund_count']; ?>)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#4ade80;"><?php echo formatMoney($totals['net']); ?></div>
        <div class="stat-label">Net Revenue</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $totals['payments']; ?></div>
        <div class="stat-label">Collected Payments</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $totals['invoices']; ?></div>
        <div class="stat-label">Invoices Issued</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#fbbf24;"><?php echo formatMoney($totals['outstanding']); ?></div>
        <div class="stat-label">Outstanding (unpaid)</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-calendar-day"></i> Revenue by Day</h5></div>
            <div class="card-body-custom">
                <canvas id="revenueDayChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-chart-pie"></i> by Payment Method</h5></div>
            <div class="card-body-custom">
                <canvas id="revenueMethodChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mt-3">
    <div class="card-header-custom"><h5><i class="fas fa-calendar-week"></i> Monthly Series</h5></div>
    <div class="table-scroll">
        <table class="admin-table">
            <thead><tr><th>Month</th><th class="fin-amount">Revenue</th></tr></thead>
            <tbody>
                <?php foreach ($byMonth as $m): ?>
                <tr><td><?php echo sanitize($m['label']); ?></td><td class="fin-amount"><?php echo formatMoney((float)$m['value']); ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$byMonth): ?><tr><td colspan="2" class="fin-muted text-center py-3">No revenue in range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var cyan = getComputedStyle(document.body).getPropertyValue('--accent-bright') || '#00c2d9';
    var grid = 'rgba(255,255,255,0.06)';

    function theme() {
        return {
            ticks: { color: '#94a3b8' },
            grid: { color: grid }
        };
    }

    new Chart(document.getElementById('revenueDayChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_values($dailyLabels)); ?>,
            datasets: [{
                label: 'Net Revenue',
                data: <?php echo json_encode(array_values($dailyValues)); ?>,
                backgroundColor: 'rgba(0,194,217,0.35)',
                borderColor: cyan,
                borderWidth: 1.5,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ...theme() }, x: { ...theme() } }
        }
    });

    new Chart(document.getElementById('revenueMethodChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_values($methodLabels)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($methodValues)); ?>,
                backgroundColor: ['#00c2d9', '#8b5cf6', '#fbbf24', '#34d399', '#f472b6', '#60a5fa', '#f87171']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>