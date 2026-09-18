<?php
/**
 * Admin - Invoices
 * List, filter, cancel and (draft only) delete invoices. View/print/edit flow
 * from here. All money figures come from the database.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Invoices';
$activeMenu = 'invoices';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db     = getDBConnection();
$errors = [];

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/invoices/index.php');
    }

    $action    = $_POST['action'] ?? '';
    $invoiceId = (int)($_POST['id'] ?? 0);

    if ($invoiceId <= 0) {
        setFlash('error', 'Invalid invoice selected.');
        redirect('admin/invoices/index.php');
    }

    $stmt = $db->prepare("SELECT * FROM invoices WHERE invoice_id = :id");
    $stmt->execute([':id' => $invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        setFlash('error', 'Invoice not found.');
        redirect('admin/invoices/index.php');
    }

    $summary   = invoicePaymentSummary($db, $invoiceId);
    $invoiceNo = $invoice['invoice_number'];

    if ($action === 'cancel') {
        if ($summary['net_paid'] > 0) {
            setFlash('error', 'This invoice already has collected payments. '
                . 'Refund or keep it paid instead of cancelling.');
            redirect('admin/invoices/index.php');
        }
        $stmt = $db->prepare("UPDATE invoices SET status = 'cancelled', payment_status = 'cancelled' WHERE invoice_id = :id");
        $stmt->execute([':id' => $invoiceId]);
        logFinancialAudit($db, currentUserId(), 'invoice', $invoiceId, 'invoice.cancelled',
                          null, 'cancelled', 'Invoice ' . $invoiceNo . ' cancelled from admin list.');
        setFlash('success', 'Invoice ' . $invoiceNo . ' cancelled.');
        redirect('admin/invoices/index.php');
    } elseif ($action === 'mark_sent') {
        if ($invoice['status'] !== 'draft') {
            setFlash('error', 'Only draft invoices can be marked as sent.');
            redirect('admin/invoices/index.php');
        }
        $stmt = $db->prepare("UPDATE invoices SET status = 'sent' WHERE invoice_id = :id");
        $stmt->execute([':id' => $invoiceId]);
        logFinancialAudit($db, currentUserId(), 'invoice', $invoiceId, 'invoice.sent', 'draft', 'sent');
        setFlash('success', 'Invoice ' . $invoiceNo . ' marked as sent.');
        redirect('admin/invoices/index.php');
    } elseif ($action === 'delete') {
        if ($invoice['status'] !== 'draft') {
            setFlash('error', 'Only draft invoices can be deleted. Cancel instead.');
            redirect('admin/invoices/index.php');
        }
        $paid = $db->prepare("SELECT COUNT(*) FROM payments WHERE invoice_id = :id");
        $paid->execute([':id' => $invoiceId]);
        if ((int)$paid->fetchColumn() > 0) {
            setFlash('error', 'This draft invoice has payments and cannot be deleted.');
            redirect('admin/invoices/index.php');
        }
        $stmt = $db->prepare("DELETE FROM invoices WHERE invoice_id = :id");
        $stmt->execute([':id' => $invoiceId]);
        logFinancialAudit($db, currentUserId(), 'invoice', $invoiceId, 'invoice.deleted',
                          $invoiceNo, null, 'Draft invoice deleted.');
        setFlash('success', 'Draft invoice ' . $invoiceNo . ' deleted.');
        redirect('admin/invoices/index.php');
    }

    setFlash('error', 'Unknown action.');
    redirect('admin/invoices/index.php');
}

/* ---------- Filters ---------- */
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$pstatus = $_GET['payment_status'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';
if (!in_array($status, ['', 'draft', 'sent', 'paid', 'overdue', 'cancelled'], true)) $status = '';
if (!in_array($pstatus, ['', 'unpaid', 'partially_paid', 'paid', 'refunded', 'cancelled'], true)) $pstatus = '';

$sql = "
    SELECT i.invoice_id, i.invoice_number, i.client_id, i.total, i.paid_amount,
           i.payment_status, i.status, i.issued_at, i.due_at,
           CONCAT(c.first_name, ' ', c.last_name) AS client_name, c.phone AS client_phone
    FROM invoices i
    JOIN clients c ON c.client_id = i.client_id
";
$params = [];
$where  = [];
if ($search !== '') {
    $where[] = "(i.invoice_number LIKE :s1 OR CONCAT(c.first_name,' ',c.last_name) LIKE :s2 OR c.phone LIKE :s3)";
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
    $params[':s3'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = "i.status = :st";
    $params[':st'] = $status;
}
if ($pstatus !== '') {
    $where[] = "i.payment_status = :ps";
    $params[':ps'] = $pstatus;
}
if ($from !== '') {
    $where[] = "i.issued_at >= :from";
    $params[':from'] = date('Y-m-d', strtotime($from));
}
if ($to !== '') {
    $where[] = "i.issued_at <= :to";
    $params[':to'] = date('Y-m-d', strtotime($to));
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY i.invoice_id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-file-invoice-dollar"></i> Invoices</h1>
        <p>Create, monitor and manage customer invoices.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/invoices/create.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> New Invoice</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<div class="admin-card">
    <div class="card-header-custom flex-wrap gap-2">
        <h5><i class="fas fa-receipt"></i> All Invoices <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($invoices); ?></span></h5>
        <form method="get" action="" class="d-flex gap-2 flex-wrap">
            <div class="filter-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search number, client or phone..." class="form-control-admin form-control-sm">
            </div>
            <select name="status" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="">All Lifecycle</option>
                <?php foreach (['draft', 'sent', 'paid', 'overdue', 'cancelled'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="payment_status" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="">All Payment Status</option>
                <?php foreach (['unpaid', 'partially_paid', 'paid', 'refunded', 'cancelled'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $pstatus === $s ? 'selected' : ''; ?>><?php echo str_replace('_', ' ', ucfirst($s)); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?php echo sanitize($from); ?>" class="form-control-admin form-control-sm">
            <input type="date" name="to" value="<?php echo sanitize($to); ?>" class="form-control-admin form-control-sm">
            <button type="submit" class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $status !== '' || $pstatus !== '' || $from !== '' || $to !== ''): ?>
            <a href="<?php echo SITE_URL; ?>/admin/invoices/index.php" class="btn-admin btn-outline btn-xs">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($invoices)): ?>
    <div class="empty-state">
        <i class="fas fa-file-invoice"></i>
        <h5>No invoices found</h5>
        <p>Create an invoice and it will show up here.</p>
        <a href="<?php echo SITE_URL; ?>/admin/invoices/create.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> New Invoice</a>
    </div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th class="fin-amount">Total</th>
                    <th class="fin-amount">Paid</th>
                    <th class="fin-amount">Balance</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <?php
                $balance = round(max(0.0, (float)$inv['total'] - (float)$inv['paid_amount']), 2);
                ?>
                <tr>
                    <td>
                        <a href="<?php echo SITE_URL; ?>/admin/invoices/view.php?id=<?php echo (int)$inv['invoice_id']; ?>" class="fw-semibold text-cyan-link"><?php echo sanitize($inv['invoice_number']); ?></a>
                        <div class="fin-muted">#<?php echo (int)$inv['invoice_id']; ?></div>
                    </td>
                    <td><?php echo sanitize($inv['client_name']); ?><div class="fin-muted"><?php echo sanitize($inv['client_phone']); ?></div></td>
                    <td><?php echo formatDate($inv['issued_at'], 'M d, Y'); ?></td>
                    <td><?php echo $inv['due_at'] ? formatDate($inv['due_at'], 'M d, Y') : '—'; ?></td>
                    <td class="fin-amount"><?php echo formatMoney($inv['total']); ?></td>
                    <td class="fin-amount fin-amount-pos"><?php echo formatMoney($inv['paid_amount']); ?></td>
                    <td class="fin-amount <?php echo $balance > 0 ? 'fin-amount-neg' : ''; ?>"><?php echo formatMoney($balance); ?></td>
                    <td><?php echo financeInvoiceStatusBadge($inv['status']); ?></td>
                    <td><?php echo financePaymentStatusBadge($inv['payment_status']); ?></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a class="btn-admin btn-cyan btn-xs" href="<?php echo SITE_URL; ?>/admin/invoices/view.php?id=<?php echo (int)$inv['invoice_id']; ?>" title="View"><i class="fas fa-eye"></i></a>
                            <a class="btn-admin btn-outline btn-xs" target="_blank" href="<?php echo SITE_URL; ?>/admin/invoices/print.php?id=<?php echo (int)$inv['invoice_id']; ?>" title="Print"><i class="fas fa-print"></i></a>
                            <?php if (in_array($inv['status'], ['draft', 'sent'], true) && $balance > 0): ?>
                            <a class="btn-admin btn-outline btn-xs" href="<?php echo SITE_URL; ?>/admin/invoices/edit.php?id=<?php echo (int)$inv['invoice_id']; ?>" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                            <a class="btn-admin btn-cyan btn-xs" href="<?php echo SITE_URL; ?>/admin/payments/create.php?invoice_id=<?php echo (int)$inv['invoice_id']; ?>" title="Record Payment"><i class="fas fa-money-bill-wave"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="admin-card mt-3">
    <div class="card-header-custom">
        <h6><i class="fas fa-circle-info"></i> Invoicing rules</h6>
    </div>
    <ul class="mb-0 ps-3 text-muted small">
        <li>Draft invoices are editable and deletable; once a payment is received the invoice becomes immutable.</li>
        <li>Cancelling an invoice is only allowed while no money has been collected.</li>
        <li>Invoice numbers are generated server-side and are always unique (INV-YYYY-NNNNNN).</li>
    </ul>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>