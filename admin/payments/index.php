<?php
/**
 * Admin - Payments
 * Searchable payment ledger with status/method/date filters and CSV export.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Payments';
$activeMenu = 'payments';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db = getDBConnection();

/* ---------- Filters ---------- */
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$method  = $_GET['method'] ?? '';
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');
if (!in_array($status, ['', 'pending', 'completed', 'paid', 'failed', 'refunded', 'partial'], true)) $status = '';
$validMethods = ['cash', 'card', 'online', 'other', 'bank_transfer', 'jazzcash', 'easypaisa'];
if (!in_array($method, $validMethods, true)) $method = '';

$sql = "
    SELECT p.payment_id, p.amount, p.refunded_amount, p.payment_method, p.payment_status,
           p.transaction_ref, p.paid_at, p.created_at, p.invoice_id, p.client_id, p.received_by,
           i.invoice_number, i.total AS invoice_total,
           CONCAT(c.first_name, ' ', c.last_name) AS client_name,
           CONCAT(u.first_name, ' ', u.last_name) AS received_by_name,
           r.receipt_number
    FROM payments p
    LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
    LEFT JOIN clients c ON c.client_id = p.client_id
    LEFT JOIN users u ON u.user_id = p.received_by
    LEFT JOIN receipts r ON r.payment_id = p.payment_id
";
$params = [];
$where  = [];
if ($search !== '') {
    $where[] = "(i.invoice_number LIKE :s1 OR p.transaction_ref LIKE :s2 OR r.receipt_number LIKE :s3 OR CONCAT(c.first_name,' ',c.last_name) LIKE :s4)";
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
    $params[':s3'] = '%' . $search . '%';
    $params[':s4'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = "p.payment_status = :st";
    $params[':st'] = $status;
}
if ($method !== '') {
    $where[] = "p.payment_method = :m";
    $params[':m'] = $method;
}
if ($from !== '') {
    $where[] = "p.paid_at >= :from";
    $params[':from'] = date('Y-m-d', strtotime($from));
}
if ($to !== '') {
    $where[] = "p.paid_at <= DATE_ADD(:to, INTERVAL 1 DAY)";
    $params[':to'] = date('Y-m-d', strtotime($to));
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY p.created_at DESC, p.payment_id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

/* ---------- CSV Export ---------- */
if (isset($_GET['export']) && $_GET['export'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments-' . date('Y-m-d-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Payment ID', 'Invoice', 'Client', 'Date', 'Method', 'Amount', 'Refunded', 'Net', 'Status', 'Transaction Ref', 'Receipt', 'Received By']);
    foreach ($payments as $p) {
        fputcsv($out, [
            $p['payment_id'],
            $p['invoice_number'] ?? '',
            $p['client_name'] ?? '',
            $p['paid_at'] ? date('Y-m-d H:i', strtotime($p['paid_at'])) : '',
            $p['payment_method'],
            number_format((float)$p['amount'], 2, '.', ''),
            number_format((float)$p['refunded_amount'], 2, '.', ''),
            number_format((float)$p['amount'] - (float)$p['refunded_amount'], 2, '.', ''),
            $p['payment_status'],
            $p['transaction_ref'] ?? '',
            $p['receipt_number'] ?? '',
            $p['received_by_name'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-money-bill-wave"></i> Payments</h1>
        <p>Ledger of every payment accepted at the salon.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo SITE_URL; ?>/admin/payments/daily-closing.php" class="btn-admin btn-outline"><i class="fas fa-cash-register"></i> Daily Closing</a>
        <a href="<?php echo SITE_URL; ?>/admin/payments/create.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> Record Payment</a>
    </div>
</div>

<div class="admin-card">
    <div class="card-header-custom flex-wrap gap-2">
        <h5><i class="fas fa-wallet"></i> Payment Ledger <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($payments); ?></span></h5>
        <form method="get" action="" class="d-flex gap-2 flex-wrap">
            <div class="filter-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Invoice, client, ref, receipt…" class="form-control-admin form-control-sm">
            </div>
            <select name="status" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'completed', 'paid', 'failed', 'refunded', 'partial'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="method" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="">All Methods</option>
                <?php foreach ($validMethods as $m): ?>
                <option value="<?php echo $m; ?>" <?php echo $method === $m ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $m)); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?php echo sanitize($from); ?>" class="form-control-admin form-control-sm">
            <input type="date" name="to" value="<?php echo sanitize($to); ?>" class="form-control-admin form-control-sm">
            <button type="submit" class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $status !== '' || $method !== '' || $from !== '' || $to !== ''): ?>
            <a href="<?php echo SITE_URL; ?>/admin/payments/index.php" class="btn-admin btn-outline btn-xs">Clear</a>
            <?php endif; ?>
            <a href="?export=1&<?php echo http_build_query(array_filter(['search' => $search, 'status' => $status, 'method' => $method, 'from' => $from, 'to' => $to])); ?>" class="btn-admin btn-outline btn-xs"><i class="fas fa-file-csv"></i> CSV</a>
        </form>
    </div>

    <?php if (empty($payments)): ?>
    <div class="empty-state"><i class="fas fa-money-bill-wave"></i><h5>No payments found</h5><p>Adjust your filters or record a payment.</p></div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Payment</th>
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th class="fin-amount">Amount</th>
                    <th class="fin-amount">Net</th>
                    <th>Status</th>
                    <th>Receipt</th>
                    <th>Received By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $pm): ?>
                <tr>
                    <td>#<?php echo (int)$pm['payment_id']; ?>
                        <?php if ($pm['transaction_ref']): ?><div class="fin-muted"><?php echo sanitize($pm['transaction_ref']); ?></div><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pm['invoice_number']): ?>
                        <a class="text-cyan-link" href="<?php echo SITE_URL; ?>/admin/invoices/view.php?id=<?php echo (int)$pm['invoice_id']; ?>"><?php echo sanitize($pm['invoice_number']); ?></a>
                        <?php else: ?><span class="fin-muted">—</span><?php endif; ?>
                    </td>
                    <td><?php echo $pm['client_name'] ? sanitize($pm['client_name']) : '<span class="fin-muted">—</span>'; ?></td>
                    <td><?php echo $pm['paid_at'] ? formatDate($pm['paid_at'], 'M d, Y g:i A') : '<span class="fin-muted">' . formatDate($pm['created_at'], 'M d, Y') . '</span>'; ?></td>
                    <td><?php echo sanitize(ucfirst(str_replace('_', ' ', $pm['payment_method']))); ?></td>
                    <td class="fin-amount fin-amount-pos"><?php echo formatMoney($pm['amount']); ?></td>
                    <td class="fin-amount"><?php echo formatMoney((float)$pm['amount'] - (float)$pm['refunded_amount']); ?></td>
                    <td><?php echo financePaymentRecordBadge($pm['payment_status']); ?></td>
                    <td>
                        <?php if ($pm['receipt_number']): ?>
                        <a class="text-cyan-link" target="_blank" href="<?php echo SITE_URL; ?>/admin/receipts/print.php?receipt_id=<?php echo (int)$pm['receipt_id']; ?>"><?php echo sanitize($pm['receipt_number']); ?></a>
                        <?php else: ?><span class="fin-muted">—</span><?php endif; ?>
                    </td>
                    <td><?php echo $pm['received_by_name'] ? sanitize($pm['received_by_name']) : '—'; ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a class="btn-admin btn-outline btn-xs" href="<?php echo SITE_URL; ?>/admin/payments/view.php?id=<?php echo (int)$pm['payment_id']; ?>" title="View"><i class="fas fa-eye"></i></a>
                            <?php if ((float)$pm['amount'] - (float)$pm['refunded_amount'] > 0 && in_array($pm['payment_status'], ['paid', 'completed', 'partial'], true)): ?>
                            <a class="btn-admin btn-danger-soft btn-xs" href="<?php echo SITE_URL; ?>/admin/payments/refund.php?payment_id=<?php echo (int)$pm['payment_id']; ?>" title="Refund"><i class="fas fa-rotate-left"></i></a>
                            <?php endif; ?>
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