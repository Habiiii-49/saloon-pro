<?php
/**
 * Elegance Salon - Receptionist - Payments
 * Record salon payments (Cash / Card / Bank Transfer / JazzCash / Easypaisa / online).
 * Payments are always attached to an invoice; balances are enforced server-side.
 */
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/../includes/finance.php';

$db = getDBConnection();
$errors = [];

/* ---------- POST: record payment ---------- */
$old = ['appointment_id' => '', 'amount' => '', 'payment_method' => 'cash', 'payment_status' => 'paid', 'transaction_ref' => '', 'paid_at' => date('Y-m-d\TH:i'), 'notes' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('user/payments.php');
    }

    $old = [
        'appointment_id'  => trim($_POST['appointment_id'] ?? ''),
        'amount'          => trim($_POST['amount'] ?? ''),
        'payment_method'  => trim($_POST['payment_method'] ?? 'cash'),
        'payment_status'  => trim($_POST['payment_status'] ?? 'paid'),
        'transaction_ref' => trim($_POST['transaction_ref'] ?? ''),
        'paid_at'         => trim($_POST['paid_at'] ?? ''),
        'notes'           => trim($_POST['notes'] ?? ''),
    ];

    $apptId = (int)$old['appointment_id'];
    $amount = moneyToFloat($old['amount']);

    $methods  = ['cash', 'card', 'bank_transfer', 'jazzcash', 'easypaisa', 'online', 'other'];
    $collected = in_array($old['payment_status'], ['paid', 'completed'], true);

    $appt = null;
    if ($apptId > 0) {
        $stmt = $db->prepare("
            SELECT a.appointment_id, a.total_amount,
                   CONCAT(c.first_name,' ',c.last_name) AS client_name, c.client_id, a.staff_id,
                   s.service_name, s.service_id
            FROM appointments a
            JOIN clients c ON c.client_id = a.client_id
            JOIN services s ON s.service_id = a.service_id
            WHERE a.appointment_id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $apptId]);
        $appt = $stmt->fetch();
    }
    if (!$appt) {
        $errors[] = 'Please choose a valid appointment.';
    }
    if ($amount === null || $amount <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }
    if (!in_array($old['payment_method'], $methods, true)) {
        $errors[] = 'Please choose a valid payment method.';
    }
    if (!in_array($old['payment_status'], ['pending', 'completed', 'paid'], true)) {
        $errors[] = 'Please choose a valid payment status.';
    }
    if ($old['paid_at'] !== '' && strtotime($old['paid_at']) === false) {
        $errors[] = 'Please enter a valid payment date.';
    }

    if (empty($errors) && $appt) {
        try {
            /* 1) Make sure the appointment has an invoice (with snapshot line item). */
            $stmt = $db->prepare("SELECT invoice_id FROM invoices WHERE appointment_id = :a LIMIT 1");
            $stmt->execute([':a' => $apptId]);
            $invRow = $stmt->fetch();

            if (!$invRow) {
                $db->beginTransaction();
                $invoiceNumber = generateInvoiceNumber($db);
                $total = (float)$appt['total_amount'];
                $taxRate = (float)getSetting('financial_tax_rate', '0');
                $computed = composeInvoiceTotals([[
                    'service_id'   => (int)$appt['service_id'],
                    'service_name' => $appt['service_name'],
                    'quantity'     => 1,
                    'unit_price'   => $total,
                    'discount'     => 0,
                ]], $taxRate, 0.0);

                $stmt = $db->prepare("
                    INSERT INTO invoices (invoice_number, client_id, appointment_id, stylist_id, subtotal, tax_rate, tax_amount, discount, total, status, issued_at, due_at, created_by)
                    VALUES (:num, :cid, :aid, :stid, :sub, :tr, :tax, :disc, :total, 'sent', :iss, :due, :by)
                ");
                $stmt->execute([
                    ':num'   => $invoiceNumber,
                    ':cid'   => $appt['client_id'],
                    ':aid'   => $apptId,
                    ':stid'  => $appt['staff_id'] ?: null,
                    ':sub'   => $computed['subtotal'],
                    ':tr'    => $computed['tax_rate'],
                    ':tax'   => $computed['tax'],
                    ':disc'  => $computed['discount'],
                    ':total' => $computed['total'],
                    ':iss'   => date('Y-m-d'),
                    ':due'   => date('Y-m-d', strtotime('+7 days')),
                    ':by'    => currentUserId(),
                ]);
                $invoiceId = (int)$db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO invoice_items (invoice_id, service_id, service_name, quantity, unit_price, discount, tax, line_total)
                    VALUES (:iid, :sid, :name, 1, :price, :disc, :tax, :lt)
                ");
                foreach ($computed['items'] as $line) {
                    $stmt->execute([
                        ':iid'   => $invoiceId,
                        ':sid'   => $line['service_id'],
                        ':name'  => $line['service_name'],
                        ':price' => $line['unit_price'],
                        ':disc'  => $line['discount'],
                        ':tax'   => $line['tax'],
                        ':lt'    => $line['line_total'],
                    ]);
                }
                logFinancialAudit($db, currentUserId(), 'invoice', $invoiceId, 'invoice.created.auto',
                                  null, json_encode(['number' => $invoiceNumber, 'total' => $computed['total']]));
                $db->commit();
            } else {
                $invoiceId = (int)$invRow['invoice_id'];
            }

            /* 2) Record the payment against the invoice (server-side balance check).
             *    recordPayment() owns its own transaction. */
            $token = 'salon:' . sha1($invoiceId . ':' . $amount . ':' . ($old['transaction_ref'] ?? '') . ':' . $old['paid_at']);
            $paidAt = $old['paid_at'] !== '' ? date('Y-m-d H:i', strtotime($old['paid_at'])) : date('Y-m-d H:i');

            $result = recordPayment($db, [
                'invoice_id'      => $invoiceId,
                'amount'          => $amount,
                'method'          => $old['payment_method'],
                'status'          => $collected ? 'completed' : 'pending',
                'transaction_ref' => $old['transaction_ref'],
                'paid_at'         => $paidAt,
                'notes'           => $old['notes'],
                'received_by'     => currentUserId(),
                'request_token'   => $token,
            ]);

            if (!$result['ok'] && empty($result['duplicate'])) {
                setFlash('error', $result['error']);
                redirect('user/payments.php');
            }

            if ($result['ok'] && empty($result['duplicate'])) {
                /* 3) Completion + notifications. */
                if ($collected) {
                    $summary = invoicePaymentSummary($db, $invoiceId);
                    if ($summary['payment_status'] === 'paid') {
                        $db->prepare("UPDATE appointments SET status = 'completed', updated_at = NOW() WHERE appointment_id = :a AND status <> 'cancelled'")
                           ->execute([':a' => $apptId]);
                    }
                    createNotification(null, 'Payment Recorded',
                        'Payment of ' . formatMoney($amount) . ' received from ' . $appt['client_name']
                        . ' (' . $appt['service_name'] . ').', 'success');
                }
            }

            setFlash('success', $result['duplicate'] ? 'That payment was already recorded.' : 'Payment recorded. Invoice is ready.');
            redirect('user/payments.php');
        } catch (Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $errors[] = 'Could not record the payment. Please try again.';
        }
    }
    foreach ($errors as $err) { setFlash('error', $err); }
}

/* ---------- Filters + list ---------- */
$statusFilter = $_GET['status'] ?? '';
$methodFilter = $_GET['method'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where  = [];
$params = [];
if ($statusFilter !== '') { $where[] = "p.payment_status = :st"; $params[':st'] = $statusFilter; }
if ($methodFilter !== '') { $where[] = "p.payment_method = :m"; $params[':m'] = $methodFilter; }
if ($search !== '') {
    $where[] = "(CONCAT(c.first_name,' ',c.last_name) LIKE :q OR p.transaction_ref LIKE :q2 OR i.invoice_number LIKE :q3)";
    $params[':q'] = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
    $params[':q3'] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("
    SELECT COUNT(*) AS c FROM payments p
    LEFT JOIN appointments a ON a.appointment_id = p.appointment_id
    LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
    LEFT JOIN clients c ON c.client_id = COALESCE(p.client_id, i.client_id, a.client_id)
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$payments = [];
if ($totalRows > 0) {
    $sql = "
        SELECT p.*, c.client_id, CONCAT(c.first_name,' ',c.last_name) AS client_name,
               s.service_name, a.appointment_date, a.appointment_time, a.status AS appt_status,
               i.invoice_number, r.receipt_number
        FROM payments p
        LEFT JOIN appointments a ON a.appointment_id = p.appointment_id
        LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
        LEFT JOIN clients c ON c.client_id = COALESCE(p.client_id, i.client_id, a.client_id)
        LEFT JOIN services s ON s.service_id = a.service_id
        LEFT JOIN receipts r ON r.payment_id = p.payment_id
        $whereSql
        ORDER BY p.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    $payments = $db->prepare($sql);
    $payments->execute($params);
    $payments = $payments->fetchAll();
}

/* Appointments for the picker: show invoice + outstanding balance. */
$apptPicker = $db->query("
    SELECT a.appointment_id, a.total_amount, a.appointment_date, CONCAT(c.first_name,' ',c.last_name) AS client_name,
           s.service_name,
           i.invoice_id, i.invoice_number, i.paid_amount, i.payment_status
    FROM appointments a
    JOIN clients c ON c.client_id = a.client_id
    JOIN services s ON s.service_id = a.service_id
    LEFT JOIN invoices i ON i.appointment_id = a.appointment_id
    WHERE a.status NOT IN ('cancelled','no_show')
    ORDER BY a.appointment_date DESC
    LIMIT 200
")->fetchAll();

$methodLabels = [
    'cash' => 'Cash', 'card' => 'Card', 'online' => 'Online',
    'bank_transfer' => 'Bank Transfer', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'other' => 'Other',
];
$paymentStatusLabels = [
    'paid' => 'Paid', 'partial' => 'Partial', 'pending' => 'Pending', 'completed' => 'Completed',
    'refunded' => 'Refunded', 'failed' => 'Failed',
];

$pageTitle = "Payments";
$activeMenu = 'payments';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-money-bill-wave"></i> Payments</h1>
        <p class="page-subtitle">Record and track salon payments.</p>
    </div>
</div>

<div class="grid-2col">
    <!-- RECORD PAYMENT -->
    <div class="panel">
        <div class="panel-head"><h5><i class="fas fa-cash-register"></i> Record Payment</h5></div>
        <div class="panel-body">
            <form method="post" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Appointment <span class="req">*</span></label>
                        <select name="appointment_id" class="form-control-salon" id="payAppointment" required>
                            <option value="">— Select appointment —</option>
                            <?php foreach ($apptPicker as $ap):
                                $bal = round(max(0.0, (float)$ap['total_amount'] - (float)$ap['paid_amount']), 2);
                            ?>
                            <option value="<?php echo (int)$ap['appointment_id']; ?>"
                                data-total="<?php echo (float)$ap['total_amount']; ?>"
                                data-balance="<?php echo $bal; ?>"
                                <?php echo (int)$old['appointment_id'] === (int)$ap['appointment_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize('#' . $ap['appointment_id'] . ' ' . $ap['client_name'] . ' - ' . $ap['service_name'] . ' (' . formatDate($ap['appointment_date'], 'M d') . ')'); ?>
                                <?php if ($ap['invoice_number'] && $ap['payment_status'] === 'paid'): ?> [PAID]<?php elseif ($ap['invoice_number']): ?> [<?php echo $bal > 0 ? 'DUE ' . $bal : 'PAID'; ?>]<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-hint" id="payBalanceHint"></div>
                    </div>
                    <div class="form-group">
                        <label>Amount <span class="req">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="payAmount" name="amount" class="form-control-salon" value="<?php echo sanitize($old['amount']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Date</label>
                        <input type="datetime-local" name="paid_at" class="form-control-salon" value="<?php echo sanitize($old['paid_at']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Method <span class="req">*</span></label>
                        <select name="payment_method" class="form-control-salon">
                            <?php foreach ($methodLabels as $k => $l): ?>
                            <option value="<?php echo $k; ?>" <?php echo $old['payment_method'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status <span class="req">*</span></label>
                        <select name="payment_status" class="form-control-salon">
                            <option value="paid" <?php echo $old['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid (collected)</option>
                            <option value="partial" disabled>Partial — record what is collected</option>
                            <option value="pending" <?php echo $old['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending (not yet collected)</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Transaction Reference</label>
                        <input type="text" name="transaction_ref" class="form-control-salon" value="<?php echo sanitize($old['transaction_ref']); ?>" placeholder="Receipt / JazzCash / Easypaisa reference">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Notes</label>
                        <input type="text" name="notes" class="form-control-salon" value="<?php echo sanitize($old['notes']); ?>" placeholder="Optional note for this payment">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-user btn-cyan"><i class="fas fa-money-bill-transfer"></i> Record Payment</button>
                </div>
            </form>
            <p class="mt-2 mb-0" style="font-size:.8rem;color:var(--text-muted);">
                <i class="fas fa-shield"></i> Over-payment is blocked. A receipt is issued automatically for collected payments.
            </p>
        </div>
    </div>

    <!-- LIST -->
    <div class="panel">
        <div class="panel-head">
            <h5><i class="fas fa-list"></i> Payment History <span class="text-muted">(<?php echo (int)$totalRows; ?>)</span></h5>
        </div>

        <form method="get" action="" class="filter-bar" style="padding:0 1.3rem;margin:0">
            <input type="text" name="q" class="form-control-salon fser" placeholder="Search client, invoice or ref..." value="<?php echo sanitize($search); ?>">
            <select name="status" class="form-control-salon">
                <option value="">All statuses</option>
                <?php foreach ($paymentStatusLabels as $k => $l): ?>
                <option value="<?php echo $k; ?>" <?php echo $statusFilter === $k ? 'selected' : ''; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="method" class="form-control-salon">
                <option value="">All methods</option>
                <?php foreach ($methodLabels as $k => $l): ?>
                <option value="<?php echo $k; ?>" <?php echo $methodFilter === $k ? 'selected' : ''; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-user btn-outline"><i class="fas fa-magnifying-glass"></i></button>
        </form>

        <div class="table-responsive-wrap">
            <table class="table-salon">
                <thead>
                    <tr><th>Client</th><th>Invoice</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th><th>Receipt</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr><td colspan="7"><div class="table-empty"><i class="fas fa-money-bill-wave"></i><p>No payments recorded yet.</p></div></td></tr>
                    <?php else: ?>
                    <?php foreach ($payments as $pm):
                        $ps = $paymentStatusLabels[$pm['payment_status']] ?? ucfirst($pm['payment_status']);
                        $cls = in_array($pm['payment_status'], ['paid','completed'], true) ? 'paid' : ($pm['payment_status'] === 'partial' ? 'partial' : ($pm['payment_status'] === 'refunded' ? 'refunded' : ($pm['payment_status'] === 'failed' ? 'cancelled' : 'pending-pay'))); ?>
                    <tr>
                        <td class="cell-main"><?php echo sanitize($pm['client_name']); ?></td>
                        <td>
                            <?php if ($pm['invoice_number']): ?><a href="<?php echo SITE_URL; ?>/user/invoice-view.php?invoice_id=<?php echo (int)$pm['invoice_id']; ?>" class="text-cyan-link"><?php echo sanitize($pm['invoice_number']); ?></a><?php else: ?>—<?php endif; ?>
                            <?php if ($pm['service_name']): ?><span class="cell-sub"><?php echo $pm['appointment_date'] ? formatDate($pm['appointment_date'], 'M d') . ' &middot; ' . sanitize($pm['service_name']) : sanitize($pm['service_name']); ?></span><?php endif; ?>
                        </td>
                        <td><?php echo $methodLabels[$pm['payment_method']] ?? ucfirst($pm['payment_method']); ?></td>
                        <td class="stat-inline"><?php echo formatMoney($pm['amount']); ?></td>
                        <td><span class="status-badge <?php echo $cls; ?>"><?php echo $ps; ?></span></td>
                        <td><?php echo formatDate($pm['paid_at'] ?: $pm['created_at'], 'M d, Y'); ?></td>
                        <td>
                            <?php if ($pm['receipt_number']): ?>
                            <a href="#" onclick="window.open('<?php echo SITE_URL; ?>/admin/receipts/print.php?receipt_id=<?php echo (int)$pm['receipt_id']; ?>','_blank'); return false;" class="text-cyan-link" title="Print receipt"><?php echo sanitize($pm['receipt_number']); ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-salon">
            <a href="?page=<?php echo max(1, $page - 1); ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&method=<?php echo urlencode($methodFilter); ?>"><i class="fas fa-chevron-left"></i></a>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&method=<?php echo urlencode($methodFilter); ?>" class="<?php echo $p === $page ? 'cur' : ''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
            <a href="?page=<?php echo min($totalPages, $page + 1); ?>&q=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&method=<?php echo urlencode($methodFilter); ?>"><i class="fas fa-chevron-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('payAppointment')?.addEventListener('change', function () {
    var opt = this.selectedOptions[0];
    var hint = document.getElementById('payBalanceHint');
    var amount = document.getElementById('payAmount');
    if (!opt || !opt.dataset.balance) { if (hint) hint.textContent = ''; return; }
    var bal = parseFloat(opt.dataset.balance);
    var total = parseFloat(opt.dataset.total || 0);
    if (hint) hint.textContent = bal > 0
        ? 'Outstanding balance: ' + bal.toFixed(2) + ' (total ' + total.toFixed(2) + ')'
        : 'This appointment is fully paid.';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>