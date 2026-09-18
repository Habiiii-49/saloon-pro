<?php
/**
 * Admin - Refund Payment
 * Admin-only. Original payment is never deleted; a refunds row is the trail.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Refund Payment';
$activeMenu = 'payments';
$extraCss   = 'admin/payments/assets/css/payments.css';

$db     = getDBConnection();
$errors = [];
$paymentId = (int)($_GET['payment_id'] ?? ($_POST['payment_id'] ?? 0));

$stmt = $db->prepare("
    SELECT p.*, i.invoice_number, CONCAT(c.first_name,' ',c.last_name) AS client_name
    FROM payments p
    LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
    LEFT JOIN clients c ON c.client_id = COALESCE(p.client_id, i.client_id)
    WHERE p.payment_id = :id LIMIT 1
");
$stmt->execute([':id' => $paymentId]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlash('error', 'Payment not found.');
    redirect('admin/payments/index.php');
}

$refundable = round((float)$payment['amount'] - (float)$payment['refunded_amount'], 2);
$isRefundable = $refundable > 0 && in_array($payment['payment_status'], ['paid', 'completed', 'partial'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/payments/refund.php?payment_id=' . $paymentId);
    }
    if (!$isRefundable) {
        setFlash('error', 'This payment cannot be refunded.');
        redirect('admin/payments/view.php?id=' . $paymentId);
    }
    $result = processRefund($db, [
        'payment_id'   => $paymentId,
        'amount'       => $_POST['amount'] ?? '',
        'reason'       => $_POST['reason'] ?? '',
        'reference'    => $_POST['reference'] ?? '',
        'processed_by' => currentUserId(),
    ]);
    if (!$result['ok']) {
        $errors[] = $result['error'];
    } else {
        setFlash('success', 'Refund of ' . formatMoney((float)$_POST['amount']) . ' issued successfully.');
        redirect('admin/payments/view.php?id=' . $paymentId);
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-rotate-left"></i> Refund Payment #<?php echo (int)$payment['payment_id']; ?></h1>
        <p>Invoice <?php echo $payment['invoice_number'] ? sanitize($payment['invoice_number']) : '—'; ?> · <?php echo sanitize($payment['client_name'] ?? 'Unknown client'); ?></p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/payments/view.php?id=<?php echo (int)$paymentId; ?>" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Payment</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<?php if ($isRefundable): ?>
<form method="post" action="" class="row g-3">
    <?php echo csrfField(); ?>
    <input type="hidden" name="payment_id" value="<?php echo (int)$paymentId; ?>">

    <div class="col-lg-7">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-rotate-left"></i> Refund Details</h5></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-admin">Refund Amount <span class="text-danger">*</span></label>
                        <input type="text" name="amount" class="form-control-admin" placeholder="<?php echo number_format($refundable, 2, '.', ''); ?>" value="<?php echo number_format($refundable, 2, '.', ''); ?>">
                        <div class="fin-muted mt-1">Maximum refundable: <?php echo formatMoney($refundable); ?>.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Reference</label>
                        <input type="text" name="reference" class="form-control-admin" placeholder="Optional reference e.g. REF-0001">
                    </div>
                    <div class="col-12">
                        <label class="form-label-admin">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control-admin" rows="3" required placeholder="Why is this being refunded? (stored in the audit trail)"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3">
            <a href="<?php echo SITE_URL; ?>/admin/payments/view.php?id=<?php echo (int)$paymentId; ?>" class="btn-admin btn-outline">Cancel</a>
            <button type="submit" class="btn-admin btn-danger-soft"><i class="fas fa-rotate-left"></i> Issue Refund</button>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-shield"></i> Audit</h5></div>
            <div class="card-body-custom">
                <ul class="mb-0 small ps-3 text-muted">
                    <li>The original payment stays on the ledger — a refund row is appended instead.</li>
                    <li>Full refunds mark the payment as <strong>refunded</strong> and the invoice balance is restored.</li>
                    <li>You can refund less than the full amount; the remainder stays collectable.</li>
                </ul>
            </div>
        </div>
    </div>
</form>
<?php else: ?>
<div class="admin-card">
    <div class="card-header-custom"><h5><i class="fas fa-circle-xmark"></i> Not refundable</h5></div>
    <div class="card-body-custom">
        <p class="mb-0">This payment is not refundable (already refunded, or it was never collected).</p>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>