<?php
/**
 * Admin - Revenue by Stylist
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Revenue by Stylist';
$activeMenu = 'reports';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db = getDBConnection();

$from = trim($_GET['from'] ?? '') ?: date('Y-m-01');
$to   = trim($_GET['to'] ?? '') ?: date('Y-m-d');
[$from, $to] = financeNormalizeRange($from, $to);

$rows  = financeRevenueGroup($db, $from, $to, 'stylist');
$total = array_sum(array_map(fn($r) => (float)$r['value'], $rows));

/* CSV */
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stylist-revenue-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Stylist', 'Revenue', 'Share %']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['label'], number_format((float)$r['value'], 2, '.', ''), $total > 0 ? number_format(((float)$r['value'] / $total) * 100, 1) : '0']);
    }
    fclose($out);
    exit;
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-user-tie"></i> Revenue by Stylist</h1>
        <p>Collected revenue allocated to the stylist who performed the work.</p>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
        <input type="date" name="from" value="<?php echo sanitize($from); ?>" class="form-control-admin form-control-sm">
        <input type="date" name="to" value="<?php echo sanitize($to); ?>" class="form-control-admin form-control-sm">
        <button class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Apply</button>
        <a href="?export=1&from=<?php echo $from; ?>&to=<?php echo $to; ?>" class="btn-admin btn-outline btn-xs"><i class="fas fa-file-csv"></i> CSV</a>
    </form>
</div>

<div class="finance-grid mb-4">
    <div class="stat-card"><div class="stat-value"><?php echo formatMoney($total); ?></div><div class="stat-label">Stylist Revenue</div></div>
</div>

<div class="admin-card">
    <div class="card-header-custom"><h5><i class="fas fa-ranking-star"></i> Stylists by Revenue</h5></div>
    <div class="table-scroll">
        <table class="admin-table">
            <thead><tr><th>#</th><th>Stylist</th><th class="fin-amount">Revenue</th><th>Share</th></tr></thead>
            <tbody>
                <?php $i = 1; foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo sanitize($r['label']); ?></td>
                    <td class="fin-amount"><?php echo formatMoney((float)$r['value']); ?></td>
                    <td>
                        <div class="progress" style="height:6px; width:180px;"><div class="progress-bar bg-info" style="width: <?php echo $total > 0 ? round(((float)$r['value'] / $total) * 100, 1) : 0; ?>%;"></div></div>
                        <span class="fin-muted"><?php echo $total > 0 ? number_format(((float)$r['value'] / $total) * 100, 1) : 0; ?>%</span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="fin-muted text-center py-3">No revenue in this range.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>