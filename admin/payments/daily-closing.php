<?php
/**
 * Admin - Daily Closing (cash reconciliation for a single day).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Daily Closing';
$activeMenu = 'payments';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db = getDBConnection();

$date = $_GET['date'] ?? date('Y-m-d');
if (!strtotime($date)) $date = date('Y-m-d');
$dateSql = date('Y-m-d', strtotime($date));

$stmt = $db->prepare("
    SELECT payment_method,
           COUNT(*) AS cnt,
           COALESCE(SUM(amount), 0) AS gross,
           COALESCE(SUM(refunded_amount), 0) AS refunded,
           COALESCE(SUM(amount - refunded_amount), 0) AS net
    FROM payments
    WHERE payment_status IN ('paid','completed','partial')
      AND DATE(paid_at) = :d
    GROUP BY payment_method
    ORDER BY net DESC
");
$stmt->execute([':d' => $dateSql]);
$methods = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount),0) g, COALESCE(SUM(refunded_amount),0) r,
           COALESCE(SUM(amount - refunded_amount),0) n, COUNT(*) c
    FROM payments
    WHERE payment_status IN ('paid','completed','partial')
      AND DATE(paid_at) = :d
");
$stmt->execute([':d' => $dateSql]);
$totals = $stmt->fetch();

$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount),0) AS rf
    FROM refunds
    WHERE status = 'completed' AND DATE(created_at) = :d
");
$stmt->execute([':d' => $dateSql]);
$dayRefunds = (float)$stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT p.payment_id, p.amount, p.refunded_amount, p.payment_method, p.transaction_ref, p.received_by,
           p.paid_at, i.invoice_number,
           CONCAT(c.first_name,' ',c.last_name) AS client_name,
           r.receipt_number
    FROM payments p
    LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
    LEFT JOIN clients c ON c.client_id = COALESCE(p.client_id, i.client_id)
    LEFT JOIN receipts r ON r.payment_id = p.payment_id
    WHERE p.payment_status IN ('paid','completed','partial')
      AND DATE(p.paid_at) = :d
    ORDER BY p.paid_at ASC
");
$stmt->execute([':d' => $dateSql]);
$dayPayments = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-cash-register"></i> Daily Closing</h1>
        <p>Reconcile the till for a single business day.</p>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
        <input type="date" name="date" value="<?php echo sanitize($dateSql); ?>" class="form-control-admin form-control-sm">
        <button class="btn-admin btn-cyan btn-xs"><i class="fas fa-magnifying-glass"></i> Load Day</button>
        <button type="button" onclick="window.print()" class="btn-admin btn-outline btn-xs"><i class="fas fa-print"></i> Print</button>
    </form>
</div>

<div class="finance-grid mb-4">
    <div class="stat-card">
        <div class="stat-value"><?php echo formatMoney((float)$totals['g']); ?></div>
        <div class="stat-label">Gross Collected</div>
    </div>
    <div class="stat-card">
        <div class="stat-value fin-amount-neg"><?php echo formatMoney((float)$totals['r'] + $dayRefunds); ?></div>
        <div class="stat-label">Refunded (Payments + Refunds)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color:#4ade80;"><?php echo formatMoney((float)$totals['n']); ?></div>
        <div class="stat-label">Net Revenue</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo (int)$totals['c']; ?></div>
        <div class="stat-label">Payments</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-chart-simple"></i> By Payment Method</h5></div>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Method</th><th class="text-center">Count</th><th class="fin-amount">Gross</th><th class="fin-amount">Net</th></tr></thead>
                    <tbody>
                        <?php if (!$methods): ?>
                        <tr><td colspan="4" class="fin-muted text-center py-3">No collected payments on this day.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($methods as $m): ?>
                        <tr>
                            <td><?php echo sanitize(ucfirst(str_replace('_', ' ', $m['payment_method']))); ?></td>
                            <td class="text-center"><?php echo (int)$m['cnt']; ?></td>
                            <td class="fin-amount"><?php echo formatMoney($m['gross']); ?></td>
                            <td class="fin-amount"><?php echo formatMoney($m['net']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-receipt"></i> Transactions — <?php echo formatDate($dateSql); ?></h5></div>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Time</th><th>Invoice</th><th>Client</th><th>Method</th><th class="fin-amount">Gross</th><th class="fin-amount">Refunded</th><th>Receipt</th></tr></thead>
                    <tbody>
                        <?php if (!$dayPayments): ?>
                        <tr><td colspan="7" class="fin-muted text-center py-3">No transactions.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($dayPayments as $pm): ?>
                        <tr>
                            <td><?php echo substr((string)$pm['paid_at'], 11, 5); ?></td>
                            <td><?php echo $pm['invoice_number'] ? '<a class="text-cyan-link" href="' . SITE_URL . '/admin/invoices/view.php?id=' . (int)$pm['invoice_id'] . '">' . sanitize($pm['invoice_number']) . '</a>' : '—'; ?></td>
                            <td><?php echo sanitize($pm['client_name'] ?? '—'); ?></td>
                            <td><?php echo sanitize(ucfirst(str_replace('_', ' ', $pm['payment_method']))); ?></td>
                            <td class="fin-amount"><?php echo formatMoney($pm['amount']); ?></td>
                            <td class="fin-amount <?php echo (float)$pm['refunded_amount'] > 0 ? 'fin-amount-neg' : ''; ?>"><?php echo formatMoney($pm['refunded_amount']); ?></td>
                            <td><?php echo $pm['receipt_number'] ? sanitize($pm['receipt_number']) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>