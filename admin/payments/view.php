<?php
/**
 * Admin - Payment Detail + refunds for this payment.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Payment Detail';
$activeMenu = 'payments';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db    = getDBConnection();
$paymentId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT p.*,
           i.invoice_number, i.total AS invoice_total, i.client_id, i.stylist_id,
           CONCAT(c.first_name,' ',c.last_name) AS client_name, c.phone AS client_phone,
           CONCAT(u.first_name,' ',u.last_name) AS received_by_name,
           r.receipt_id, r.receipt_number, r.created_at AS receipt_created_at
    FROM payments p
    LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
    LEFT JOIN clients c ON c.client_id = COALESCE(p.client_id, i.client_id)
    LEFT JOIN users u ON u.user_id = p.received_by
    LEFT JOIN receipts r ON r.payment_id = p.payment_id
    WHERE p.payment_id = :id LIMIT 1
");
$stmt->execute([':id' => $paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlash('error', 'Payment not found.');
    redirect('admin/payments/index.php');
}

$refundable = round((float)$payment['amount'] - (float)$payment['refunded_amount'], 2);

$refunds = [];
$stmt = $db->prepare("
    SELECT r.*, CONCAT(u.first_name,' ',u.last_name) AS processed_by_name
    FROM refunds r
    LEFT JOIN users u ON u.user_id = r.processed_by
    WHERE r.payment_id = :id ORDER BY r.created_at DESC
");
$stmt->execute([':id' => $paymentId]);
$refunds = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-money-bill-wave"></i> Payment #<?php echo (int)$payment['payment_id']; ?></h1>
        <p><?php echo financePaymentRecordBadge($payment['payment_status']); ?>
            <?php if ($payment['invoice_number']): ?>
            · Invoice <a class="text-cyan-link" href="<?php echo SITE_URL; ?>/admin/invoices/view.php?id=<?php echo (int)$payment['invoice_id']; ?>"><?php echo sanitize($payment['invoice_number']); ?></a>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($refundable > 0 && in_array($payment['payment_status'], ['paid', 'completed', 'partial'], true)): ?>
        <a class="btn-admin btn-danger-soft" href="<?php echo SITE_URL; ?>/admin/payments/refund.php?payment_id=<?php echo (int)$payment['payment_id']; ?>"><i class="fas fa-rotate-left"></i> Refund (<?php echo formatMoney($refundable); ?>)</a>
        <?php endif; ?>
        <?php if ($payment['receipt_number']): ?>
        <a class="btn-admin btn-outline" target="_blank" href="<?php echo SITE_URL; ?>/admin/receipts/print.php?receipt_id=<?php echo (int)$payment['receipt_id']; ?>"><i class="fas fa-print"></i> Receipt <?php echo sanitize($payment['receipt_number']); ?></a>
        <?php endif; ?>
        <a class="btn-admin btn-outline" href="<?php echo SITE_URL; ?>/admin/payments/index.php"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-circle-info"></i> Payment Details</h5></div>
            <div class="card-body-custom">
                <ul class="list-unstyled mb-0 small">
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Amount</span><strong><?php echo formatMoney($payment['amount']); ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Refunded so far</span><strong class="fin-amount-neg"><?php echo formatMoney($payment['refunded_amount']); ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Net collected</span><strong><?php echo formatMoney((float)$payment['amount'] - (float)$payment['refunded_amount']); ?></strong></li>
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Method</span><?php echo sanitize(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></li>
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Paid At</span><?php echo $payment['paid_at'] ? formatDate($payment['paid_at'], 'M d, Y g:i A') : '—'; ?></li>
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Recorded</span><?php echo formatDate($payment['created_at'], 'M d, Y g:i A'); ?></li>
                    <?php if ($payment['transaction_ref']): ?>
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Reference</span><?php echo sanitize($payment['transaction_ref']); ?></li>
                    <?php endif; ?>
                    <li class="mb-2 d-flex justify-content-between"><span class="fin-muted">Received By</span><?php echo $payment['received_by_name'] ? sanitize($payment['received_by_name']) : '—'; ?></li>
                    <?php if ($payment['notes']): ?>
                    <li class="mb-2"><span class="fin-muted">Notes</span><br><?php echo sanitize($payment['notes']); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-user"></i> Customer</h5></div>
            <div class="card-body-custom">
                <h5 class="mb-0"><?php echo $payment['client_name'] ? sanitize($payment['client_name']) : '—'; ?></h5>
                <div class="fin-muted"><?php echo $payment['client_phone'] ? sanitize($payment['client_phone']) : ''; ?></div>
                <?php if ($payment['client_id']): ?>
                <a class="text-cyan-link small" href="<?php echo SITE_URL; ?>/admin/coming-soon.php?page=clients">View client profile</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-card mt-3">
            <div class="card-header-custom"><h5><i class="fas fa-rotate-left"></i> Refunds (<?php echo count($refunds); ?>)</h5></div>
            <?php if ($refunds): ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Refund</th><th>Date</th><th class="fin-amount">Amount</th><th>By</th></tr></thead>
                    <tbody>
                        <?php foreach ($refunds as $rf): ?>
                        <tr>
                            <td>#<?php echo (int)$rf['refund_id']; ?><?php if ($rf['refund_reference']): ?><div class="fin-muted"><?php echo sanitize($rf['refund_reference']); ?></div><?php endif; ?></td>
                            <td><?php echo formatDate($rf['created_at'], 'M d, Y g:i A'); ?></td>
                            <td class="fin-amount fin-amount-neg"><?php echo formatMoney($rf['amount']); ?></td>
                            <td><?php echo $rf['processed_by_name'] ? sanitize($rf['processed_by_name']) : '—'; ?></td>
                        </tr>
                        <?php if ($rf['refund_reason']): ?>
                        <tr><td colspan="4" class="fin-muted"><?php echo sanitize($rf['refund_reason']); ?></td></tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state py-4"><i class="fas fa-shield"></i><h5>No refunds</h5><p>This payment has not been refunded.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>