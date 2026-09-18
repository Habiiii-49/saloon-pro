<?php
/**
 * Admin - Invoice Detail
 * Items, totals, payments and refunds for a single invoice.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Invoice Detail';
$activeMenu = 'invoices';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db    = getDBConnection();
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = $invoiceId > 0 ? getInvoiceFull($db, $invoiceId) : null;
if (!$invoice) {
    setFlash('error', 'Invoice not found.');
    redirect('admin/invoices/index.php');
}

$items        = getInvoiceItemsFull($db, $invoiceId);
$payments     = getInvoicePaymentsFull($db, $invoiceId);
$summary      = invoicePaymentSummary($db, $invoiceId);

$refunds = [];
$stmt = $db->prepare("
    SELECT r.*, CONCAT(u.first_name,' ',u.last_name) AS processed_by_name
    FROM refunds r
    LEFT JOIN users u ON u.user_id = r.processed_by
    WHERE r.invoice_id = :id ORDER BY r.created_at DESC
");
$stmt->execute([':id' => $invoiceId]);
$refunds = $stmt->fetchAll();

$editable = in_array($invoice['status'], ['draft', 'sent'], true) && $summary['net_paid'] <= 0;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-file-invoice-dollar"></i> <?php echo sanitize($invoice['invoice_number']); ?></h1>
        <p>
            <?php echo financeInvoiceStatusBadge($invoice['status']); ?>
            <?php echo financePaymentStatusBadge($invoice['payment_status']); ?>
            <span class="fin-muted">Issued <?php echo formatDate($invoice['issued_at']); ?></span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn-admin btn-cyan" href="<?php echo SITE_URL; ?>/admin/payments/create.php?invoice_id=<?php echo (int)$invoice['invoice_id']; ?>"><i class="fas fa-money-bill-wave"></i> Record Payment</a>
        <a class="btn-admin btn-outline" target="_blank" href="<?php echo SITE_URL; ?>/admin/invoices/print.php?id=<?php echo (int)$invoice['invoice_id']; ?>"><i class="fas fa-print"></i> Print</a>
        <?php if ($editable): ?>
        <a class="btn-admin btn-outline" href="<?php echo SITE_URL; ?>/admin/invoices/edit.php?id=<?php echo (int)$invoice['invoice_id']; ?>"><i class="fas fa-pen"></i> Edit</a>
        <?php endif; ?>
        <a class="btn-admin btn-outline" href="<?php echo SITE_URL; ?>/admin/invoices/index.php"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-list"></i> Line Items</h5></div>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th class="text-center">Qty</th>
                            <th class="fin-amount">Unit</th>
                            <th class="fin-amount">Discount</th>
                            <th class="fin-amount">Tax</th>
                            <th class="fin-amount">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$items): ?>
                        <tr><td colspan="6" class="fin-muted text-center py-3">This legacy invoice has no itemized lines yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($items as $li): ?>
                        <tr>
                            <td><?php echo sanitize($li['service_name']); ?></td>
                            <td class="text-center"><?php echo (int)$li['quantity']; ?></td>
                            <td class="fin-amount"><?php echo formatMoney($li['unit_price']); ?></td>
                            <td class="fin-amount <?php echo (float)$li['discount'] > 0 ? 'fin-amount-neg' : ''; ?>"><?php echo formatMoney($li['discount']); ?></td>
                            <td class="fin-amount"><?php echo formatMoney($li['tax']); ?></td>
                            <td class="fin-amount"><?php echo formatMoney($li['line_total']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body-custom">
                <table class="paper-totals">
                    <tr><td>Subtotal</td><td class="text-end fin-amount"><?php echo formatMoney($invoice['subtotal']); ?></td></tr>
                    <?php if ((float)$invoice['discount'] > 0): ?>
                    <tr><td>Invoice discount</td><td class="text-end fin-amount fin-amount-neg">− <?php echo formatMoney($invoice['discount']); ?></td></tr>
                    <?php endif; ?>
                    <?php if ((float)$invoice['tax_amount'] > 0): ?>
                    <tr><td>Tax (<?php echo (float)$invoice['tax_rate']; ?>%)</td><td class="text-end fin-amount"><?php echo formatMoney($invoice['tax_amount']); ?></td></tr>
                    <?php endif; ?>
                    <tr class="grand"><td>Total</td><td class="text-end fin-amount"><?php echo formatMoney($invoice['total']); ?></td></tr>
                    <tr><td>Paid</td><td class="text-end fin-amount fin-amount-pos"><?php echo formatMoney($summary['paid']); ?></td></tr>
                    <tr><td>Refunded</td><td class="text-end fin-amount fin-amount-neg"><?php echo formatMoney($summary['refunded']); ?></td></tr>
                    <tr class="grand"><td>Balance Due</td><td class="text-end fin-amount <?php echo $summary['balance'] > 0 ? 'fin-amount-neg' : ''; ?>"><?php echo formatMoney($summary['balance']); ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-user"></i> Details</h5></div>
            <div class="card-body-custom">
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2"><strong>Client:</strong><br><?php echo sanitize($invoice['client_name']); ?><br>
                        <span class="fin-muted"><?php echo sanitize($invoice['client_phone']); ?><?php echo $invoice['client_email'] ? ' · ' . sanitize($invoice['client_email']) : ''; ?></span></li>
                    <li class="mb-2"><strong>Stylist:</strong><br><?php echo $invoice['stylist_name'] ? sanitize($invoice['stylist_name']) : '<span class="fin-muted">Not assigned</span>'; ?></li>
                    <li class="mb-2"><strong>Appointment:</strong><br>
                        <?php if ($invoice['appointment_id']): ?>
                        <a class="text-cyan-link" href="<?php echo SITE_URL; ?>/admin/coming-soon.php?page=appointments">#<?php echo (int)$invoice['appointment_id']; ?></a>
                        <span class="fin-muted"><?php echo $invoice['appointment_date'] ? formatDate($invoice['appointment_date']) : ''; ?> <?php echo $invoice['appointment_time'] ? substr($invoice['appointment_time'], 0, 5) : ''; ?></span>
                        <?php else: ?><span class="fin-muted">None (manual)</span><?php endif; ?>
                    </li>
                    <li class="mb-2"><strong>Dates:</strong><br>
                        <span class="fin-muted">Issued <?php echo $invoice['issued_at'] ? formatDate($invoice['issued_at']) : '—'; ?><br>Due <?php echo $invoice['due_at'] ? formatDate($invoice['due_at']) : '—'; ?></span></li>
                    <li class="mb-2"><strong>Created by:</strong><br><span class="fin-muted"><?php echo $invoice['created_by_name'] ? sanitize($invoice['created_by_name']) : 'System'; ?></span></li>
                    <?php if ($invoice['notes']): ?>
                    <li><strong>Notes:</strong><br><span class="fin-muted"><?php echo sanitize($invoice['notes']); ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="admin-card mt-3">
    <div class="card-header-custom">
        <h5><i class="fas fa-money-bill-wave"></i> Payment History (<?php echo count($payments); ?>)</h5>
        <a class="btn-admin btn-cyan btn-xs" href="<?php echo SITE_URL; ?>/admin/payments/create.php?invoice_id=<?php echo (int)$invoice['invoice_id']; ?>"><i class="fas fa-plus"></i> Record Payment</a>
    </div>
    <?php if (!$payments): ?>
    <div class="empty-state py-4"><i class="fas fa-money-bill"></i><h5>No payments yet</h5><p>This invoice has no recorded payments.</p></div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th class="fin-amount">Amount</th>
                    <th class="fin-amount">Refunded</th>
                    <th>Receipt</th>
                    <th>Status</th>
                    <th>Received By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $pm): ?>
                <tr>
                    <td>#<?php echo (int)$pm['payment_id']; ?><?php if ($pm['transaction_ref']): ?><div class="fin-muted"><?php echo sanitize($pm['transaction_ref']); ?></div><?php endif; ?></td>
                    <td><?php echo $pm['paid_at'] ? formatDate($pm['paid_at'], 'M d, Y g:i A') : '—'; ?></td>
                    <td><?php echo sanitize(ucfirst(str_replace('_', ' ', $pm['payment_method']))); ?></td>
                    <td class="fin-amount fin-amount-pos"><?php echo formatMoney($pm['amount']); ?></td>
                    <td class="fin-amount <?php echo (float)$pm['refunded_amount'] > 0 ? 'fin-amount-neg' : ''; ?>"><?php echo formatMoney($pm['refunded_amount']); ?></td>
                    <td>
                        <?php if ($pm['receipt_number']): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/receipts/print.php?receipt_id=<?php echo (int)$pm['receipt_id']; ?>" class="text-cyan-link" target="_blank"><?php echo sanitize($pm['receipt_number']); ?></a>
                        <?php else: ?><span class="fin-muted">—</span><?php endif; ?>
                    </td>
                    <td><?php echo financePaymentRecordBadge($pm['payment_status']); ?></td>
                    <td><?php echo $pm['received_by_name'] ? sanitize($pm['received_by_name']) : '<span class="fin-muted">—</span>'; ?></td>
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

<?php if ($refunds): ?>
<div class="admin-card mt-3">
    <div class="card-header-custom"><h5><i class="fas fa-rotate-left"></i> Refunds (<?php echo count($refunds); ?>)</h5></div>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>Refund</th><th>Date</th><th class="fin-amount">Amount</th><th>Payment</th><th>Reference</th><th>Reason</th><th>Processed By</th></tr>
            </thead>
            <tbody>
                <?php foreach ($refunds as $rf): ?>
                <tr>
                    <td>#<?php echo (int)$rf['refund_id']; ?></td>
                    <td><?php echo formatDate($rf['created_at'], 'M d, Y g:i A'); ?></td>
                    <td class="fin-amount fin-amount-neg"><?php echo formatMoney($rf['amount']); ?></td>
                    <td>#<?php echo (int)$rf['payment_id']; ?></td>
                    <td><?php echo $rf['refund_reference'] ? sanitize($rf['refund_reference']) : '—'; ?></td>
                    <td class="fin-muted"><?php echo $rf['refund_reason'] ? sanitize($rf['refund_reason']) : '—'; ?></td>
                    <td><?php echo $rf['processed_by_name'] ? sanitize($rf['processed_by_name']) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>