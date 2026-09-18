<?php
/**
 * Admin - Revenue by Payment Method
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Payment Methods';
$activeMenu = 'reports';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db = getDBConnection();

$from = trim($_GET['from'] ?? '') ?: date('Y-m-01');
$to   = trim($_GET['to'] ?? '') ?: date('Y-m-d');
[$from, $to] = financeNormalizeRange($from, $to);

$rows = financeRevenueGroup($db, $from, $to, 'method');
$net  = financeRevenueRange($db, $from, $to)['net'];

/* counts per method */
$stmt = $db->prepare("
    SELECT payment_method, COUNT(*) AS c
    FROM payments
    WHERE payment_status IN ('paid','completed') AND paid_at >= :from
      AND paid_at < DATE_ADD(:to, INTERVAL 1 DAY)
    GROUP BY payment_method
");
$stmt->execute([':from' => $from, ':to' => $to]);
$counts = [];
foreach ($stmt->fetchAll() as $c) { $counts[$c['payment_method']] = (int)$c['c']; }

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="method-revenue-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Method', 'Count', 'Revenue']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['label'], $counts[str_replace(' ', '_', strtolower($r['label']))] ?? 0, number_format((float)$r['value'], 2, '.', '')]);
    }
    fclose($out);
    exit;
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-credit-card"></i> Revenue by Payment Method</h1>
        <p>How customers actually pay.</p>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
        <input type="date" name="from" value="<?php echo sanitize($from); ?>" class="form-control-admin form-control-sm">
        <input type="date" name="to" value="<?php echo sanitize($to); ?>" class="form-control-admin form-control-sm">
        <button class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Apply</button>
        <a href="?export=1&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn-admin btn-outline btn-xs"><i class="fas fa-file-csv"></i> CSV</a>
    </form>
</div>

<div class="finance-grid mb-4">
    <div class="stat-card"><div class="stat-value"><?php echo formatMoney($net); ?></div><div class="stat-label">Net Collected</div></div>
</div>

<div class="admin-card">
    <div class="card-header-custom"><h5><i class="fas fa-chart-pie"></i> Methods Breakdown</h5></div>
    <div class="table-scroll">
        <table class="admin-table">
            <thead><tr><th>Method</th><th class="text-center">Transactions</th><th class="fin-amount">Revenue</th><th>Share</th></tr></thead>
            <tbody>
                <?php $total = array_sum(array_map(fn($r) => (float)$r['value'], $rows)); foreach ($rows as $r): ?>
                <tr>
                    <td><i class="fas fa-circle me-2" style="color: <?php
                        $palette = ['#00c2d9', '#8b5cf6', '#fbbf24', '#34d399', '#f472b6', '#60a5fa', '#f87171'];
                        echo $palette[array_search($r['label'], array_column($rows, 'label'), true) % count($palette)];
                    ?>;"></i><?php echo sanitize(ucfirst(str_replace('_', ' ', $r['label']))); ?></td>
                    <td class="text-center"><?php echo $counts[$r['label']] ?? 0; ?></td>
                    <td class="fin-amount"><?php echo formatMoney((float)$r['value']); ?></td>
                    <td><?php echo $total > 0 ? number_format(((float)$r['value'] / $total) * 100, 1) : 0; ?>%</td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="fin-muted text-center py-3">No revenue in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>