<?php
/**
 * Elegance Salon - Receptionist - Invoices
 */
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/../includes/finance.php';

$db = getDBConnection();

$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where  = [];
$params = [];
if ($statusFilter !== '') { $where[] = "i.status = :st"; $params[':st'] = $statusFilter; }
if ($search !== '') {
    $where[] = "(i.invoice_number LIKE :q OR CONCAT(c.first_name,' ',c.last_name) LIKE :q2 OR c.phone LIKE :q3)";
    $params[':q'] = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
    $params[':q3'] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("
    SELECT COUNT(*) AS c FROM invoices i
    JOIN clients c ON c.client_id = i.client_id
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$invoices = [];
if ($totalRows > 0) {
    $sql = "
        SELECT i.*, CONCAT(c.first_name,' ',c.last_name) AS client_name, c.phone AS client_phone,
               s.service_name, a.appointment_date
        FROM invoices i
        JOIN clients c ON c.client_id = i.client_id
        LEFT JOIN appointments a ON a.appointment_id = i.appointment_id
        LEFT JOIN services s ON s.service_id = a.service_id
        $whereSql
        ORDER BY i.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    $invoices = $db->prepare($sql);
    $invoices->execute($params);
    $invoices = $invoices->fetchAll();
}

$invoiceStatusLabels = [
    'draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled',
];
$statusBadgeMap = [
    'draft' => 'pending', 'sent' => 'info', 'paid' => 'paid', 'overdue' => 'partial', 'cancelled' => 'cancelled',
];

$pageTitle = "Invoices";
$activeMenu = 'invoices';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-invoice-dollar"></i> Invoices</h1>
        <p class="page-subtitle">Invoices are generated when appointments are billed. Record payments to keep balances current.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/user/invoice-create.php" class="btn-user btn-cyan"><i class="fas fa-plus"></i> New Invoice</a>
</div>

<div class="panel">
    <form method="get" action="" class="filter-bar" style="padding:1rem 1.3rem;margin:0">
        <input type="text" name="q" class="form-control-salon fser" placeholder="Search invoice number, client or phone..." value="<?php echo sanitize($search); ?>">
        <select name="status" class="form-control-salon">
            <option value="">All statuses</option>
            <?php foreach ($invoiceStatusLabels as $k => $l): ?>
            <option value="<?php echo $k; ?>" <?php echo $statusFilter === $k ? 'selected' : ''; ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-user btn-outline"><i class="fas fa-magnifying-glass"></i></button>
    </form>

    <div class="table-responsive-wrap">
        <table class="table-salon">
            <thead>
                <tr><th>Invoice #</th><th>Client</th><th>Service / Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="8"><div class="table-empty"><i class="fas fa-file-invoice-dollar"></i><p>No invoices yet. Record a payment or create one manually.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv):
                    $bal = round(max(0.0, (float)$inv['total'] - (float)$inv['paid_amount']), 2);
                ?>
                <tr>
                    <td class="cell-main"><?php echo sanitize($inv['invoice_number']); ?></td>
                    <td><?php echo sanitize($inv['client_name']); ?></td>
                    <td><?php echo sanitize($inv['service_name'] ?: '—'); ?><span class="cell-sub"><?php echo $inv['appointment_date'] ? formatDate($inv['appointment_date'], 'M d, Y') : ''; ?></span></td>
                    <td class="stat-inline"><?php echo formatMoney($inv['total']); ?></td>
                    <td class="stat-inline" style="color:#2e8b57;"><?php echo formatMoney($inv['paid_amount']); ?></td>
                    <td class="stat-inline <?php echo $bal > 0 ? 'stat-down' : ''; ?>"><?php echo formatMoney($bal); ?></td>
                    <td>
                        <?php echo financeInvoiceStatusBadge($inv['status']); ?>
                        <div class="mt-1"><?php echo financePaymentStatusBadge($inv['payment_status']); ?></div>
                    </td>
                    <td><div class="tbl-actions">
                        <a href="<?php echo SITE_URL; ?>/user/invoice-view.php?invoice_id=<?php echo (int)$inv['invoice_id']; ?>" title="View / Print"><i class="fas fa-eye"></i></a>
                        <?php if ($bal > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/user/payments.php" title="Record Payment"><i class="fas fa-money-bill-wave"></i></a>
                        <?php endif; ?>
                    </div></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination-salon">
        <a href="?page=<?php echo max(1, $page - 1); ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"><i class="fas fa-chevron-left"></i></a>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="<?php echo $p === $page ? 'cur' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <a href="?page=<?php echo min($totalPages, $page + 1); ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"><i class="fas fa-chevron-right"></i></a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>