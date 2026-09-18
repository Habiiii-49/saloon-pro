<?php
/**
 * Admin - Invoice Payment History (dedicated screen).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Payment History';
$activeMenu = 'invoices';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db    = getDBConnection();
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = getInvoiceFull($db, $invoiceId);
if (!$invoice) {
    setFlash('error', 'Invoice not found.');
    redirect('admin/invoices/index.php');
}

$payments = getInvoicePaymentsFull($db, $invoiceId);
$summary  = invoicePaymentSummary($db, $invoiceId);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-clock-rotate-left"></i> Payment History — <?php echo sanitize($invoice['invoice_number']); ?></h1>
        <p>
            <?php echo financePaymentStatusBadge($invoice['payment_status']); ?>
            <span class="fin-muted ms-2">Balance <?php echo formatMoney($summary['balance']); ?> · Paid <?php echo formatMoney($summary['paid']); ?> · Refunded <?php echo formatMoney($summary['refunded']); ?></span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo SITE_URL; ?>/admin/payments/create.php?invoice_id=<?php echo (int)$invoiceId; ?>" class="btn-admin btn-cyan"><i class="fas fa-money-bill-wave"></i> Record Payment</a>
        <a href="<?php echo SITE_URL; ?>/admin/invoices/view.php?id=<?php echo (int)$invoiceId; ?>" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Invoice</a>
    </div>
</div>

<div class="admin-card">
    <?php if (!$payments): ?>
    <div class="empty-state py-5"><i class="fas fa-money-bill-wave"></i><h5>No payments</h5><p>Record the first payment for this invoice.</p></div>
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
                    <td><?php echo $pm['receipt_number'] ? '<a class="text-cyan-link" target="_blank" href="' . SITE_URL . '/admin/receipts/print.php?receipt_id=' . (int)$pm['receipt_id'] . '">' . sanitize($pm['receipt_number']) . '</a>' : '—'; ?></td>
                    <td><?php echo financePaymentRecordBadge($pm['payment_status']); ?></td>
                    <td><?php echo $pm['received_by_name'] ? sanitize($pm['received_by_name']) : '—'; ?></td>
                    <td>
                        <a class="btn-admin btn-outline btn-xs" href="<?php echo SITE_URL; ?>/admin/payments/view.php?id=<?php echo (int)$pm['payment_id']; ?>"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>