<?php
/**
 * Admin - Record Payment
 * Payment entry attached to an invoice; balance is capped server-side.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Record Payment';
$activeMenu = 'payments';
$extraCss   = 'admin/payments/assets/css/payments.css';
$extraJs    = 'admin/payments/assets/js/payments.js';

$db      = getDBConnection();
$errors  = [];
$tryInvoiceId = (int)($_GET['invoice_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/payments/create.php');
    }

    $token = trim((string)($_POST['request_token'] ?? ''));
    if ($token === '' || strlen($token) > 64) {
        $errors[] = 'Missing payment token — reload the page and retry.';
    } else {
        $paidAt = trim((string)($_POST['paid_at'] ?? ''));
        if ($paidAt !== '' && !strtotime($paidAt)) {
            $errors[] = 'Invalid payment date.';
        }
        if (empty($errors)) {
            $result = recordPayment($db, [
                'invoice_id'      => (int)($_POST['invoice_id'] ?? 0),
                'amount'          => $_POST['amount'] ?? '',
                'method'          => $_POST['method'] ?? 'cash',
                'status'          => ($_POST['status'] ?? '') === 'pending' ? 'pending' : 'completed',
                'transaction_ref' => $_POST['transaction_ref'] ?? '',
                'paid_at'         => $paidAt,
                'notes'           => $_POST['notes'] ?? '',
                'received_by'     => currentUserId(),
                'request_token'   => $token,
            ]);
            if (!$result['ok']) {
                $errors[] = $result['error'];
            } else {
                if (!empty($result['duplicate'])) {
                    setFlash('warning', 'Duplicate submission ignored — this payment was already recorded.');
                    redirect('admin/payments/view.php?id=' . (int)$result['payment_id']);
                }
                if ($result['payment_status'] === 'paid') {
                    setFlash('success', 'Payment recorded — invoice fully paid.'
                        . ($result['receipt_number'] ? ' Receipt ' . $result['receipt_number'] . ' issued.' : ''));
                } else {
                    setFlash('success', 'Payment recorded'
                        . ($result['receipt_number'] ? ' — Receipt ' . $result['receipt_number'] . ' issued.' : '')
                        . ' Invoice status: ' . str_replace('_', ' ', $result['payment_status']) . '.');
                }
                redirect('admin/payments/view.php?id=' . (int)$result['payment_id']);
            }
        }
    }
}

$requestToken = bin2hex(random_bytes(16));
$methods = ['cash', 'card', 'online', 'bank_transfer', 'jazzcash', 'easypaisa', 'other'];

$preInvoice = null;
if ($tryInvoiceId > 0) {
    $preInvoice = getInvoiceFull($db, $tryInvoiceId);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-money-bill-wave"></i> Record Payment</h1>
        <p>You can never collect more than the outstanding balance.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/payments/index.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Payments</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<form method="post" action="" class="row g-3">
    <?php echo csrfField(); ?>
    <input type="hidden" name="request_token" value="<?php echo sanitize($requestToken); ?>">

    <div class="col-lg-7">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-file-invoice-dollar"></i> Invoice</h5></div>
            <div class="card-body-custom" data-fin-invoice-search-root>
                <label class="form-label-admin">Choose Invoice <span class="text-danger">*</span></label>
                <div class="fin-search-wrap">
                    <input type="text" data-fin-invoice-search class="form-control-admin" autocomplete="off" placeholder="Type invoice number or client…"
                        value="<?php echo $preInvoice ? sanitize($preInvoice['invoice_number']) : ''; ?>">
                    <input type="hidden" name="invoice_id" data-fin-invoice-id value="<?php echo $preInvoice ? (int)$preInvoice['invoice_id'] : 0; ?>">
                    <input type="hidden" data-fin-invoice-balance-max value="<?php echo $preInvoice ? (float)invoicePaymentSummary($db, $preInvoice['invoice_id'])['balance'] : 0; ?>">
                    <div class="fin-search-results"></div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <label class="form-label-admin">Amount <span class="text-danger">*</span></label>
                        <input type="text" name="amount" data-fin-invoice-balance class="form-control-admin" placeholder="0.00" step="0.01">
                        <div class="fin-muted mt-1">Maximum = outstanding balance of the selected invoice.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Payment Method</label>
                        <select name="method" class="form-control-admin">
                            <?php foreach ($methods as $m): ?>
                            <option value="<?php echo $m; ?>"><?php echo ucfirst(str_replace('_', ' ', $m)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Status</label>
                        <select name="status" class="form-control-admin">
                            <option value="completed">Completed (money received)</option>
                            <option value="pending">Pending (not yet collected)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Payment Date/Moment</label>
                        <input type="datetime-local" name="paid_at" class="form-control-admin" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-admin">Transaction Reference</label>
                        <input type="text" name="transaction_ref" class="form-control-admin" placeholder="e.g. TXN-2026-0007 or bank ref">
                    </div>
                    <div class="col-12">
                        <label class="form-label-admin">Notes</label>
                        <textarea name="notes" class="form-control-admin" rows="2" placeholder="Optional internal note"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3">
            <a href="<?php echo SITE_URL; ?>/admin/payments/index.php" class="btn-admin btn-outline">Cancel</a>
            <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check-double"></i> Record Payment</button>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-shield"></i> Safety</h5></div>
            <div class="card-body-custom">
                <ul class="mb-0 small ps-3 text-muted">
                    <li>A receipt number is issued automatically for collected payments.</li>
                    <li>Over-payment is rejected; the outstanding balance is computed server-side from the invoice total minus everything already collected or refunded.</li>
                    <li>Double-clicking submit is safe — duplicate posts are ignored.</li>
                    <li>Every payment is written to the audit log.</li>
                </ul>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>