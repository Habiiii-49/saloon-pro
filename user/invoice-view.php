<?php
/**
 * Elegance Salon - Receptionist - Invoice View / Print
 */
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/../includes/finance.php';

$db = getDBConnection();
$invoiceId = (int)($_GET['invoice_id'] ?? 0);

$invoice = getInvoiceFull($db, $invoiceId);
if (!$invoice) {
    setFlash('error', 'Invoice not found.');
    redirect('user/invoices.php');
}

$items    = getInvoiceItemsFull($db, $invoiceId);
$payments = getInvoicePaymentsFull($db, $invoiceId);
$summary  = invoicePaymentSummary($db, $invoiceId);
$balance  = round(max(0.0, (float)$invoice['total'] - (float)$invoice['paid_amount']), 2);

$receiptByPayment = [];
if (!empty($payments)) {
    $ids = array_map('intval', array_column($payments, 'payment_id'));
    $in = implode(',', $ids);
    $stmt = $db->query("SELECT payment_id, receipt_id, receipt_number FROM receipts WHERE payment_id IN ($in)");
    foreach ($stmt->fetchAll() as $r) { $receiptByPayment[$r['payment_id']] = $r; }
}

$salonName = getSetting('site_name', 'Elegance Salon');
$salonPhone = getSetting('site_phone', '');
$salonAddress = getSetting('site_address', '');
$salonEmail = getSetting('site_email', '');

$pageTitle = "Invoice " . $invoice['invoice_number'];
$activeMenu = 'invoices';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header" id="invoiceToolbar">
    <div>
        <h1 class="page-title"><i class="fas fa-file-invoice"></i> <?php echo sanitize($invoice['invoice_number']); ?></h1>
        <p class="page-subtitle">
            <?php echo sanitize($invoice['client_name']); ?>
            <?php echo financeInvoiceStatusBadge($invoice['status']); ?>
            <?php echo financePaymentStatusBadge($invoice['payment_status']); ?>
            <?php if ($balance > 0): ?><span class="status-badge partial"><i class="fas fa-circle-exclamation"></i> Due <?php echo formatMoney($balance); ?></span><?php endif; ?>
        </p>
    </div>
    <div class="page-head-right">
        <?php if ($balance > 0): ?>
        <a href="<?php echo SITE_URL; ?>/user/payments.php" class="btn-user btn-cyan"><i class="fas fa-money-bill-wave"></i> Record Payment</a>
        <?php endif; ?>
        <a href="javascript:window.print()" class="btn-user btn-outline"><i class="fas fa-print"></i> Print</a>
        <a href="<?php echo SITE_URL; ?>/admin/invoices/print.php?id=<?php echo (int)$invoiceId; ?>" target="_blank" class="btn-user btn-outline"><i class="fas fa-file-pdf"></i> PDF View</a>
        <a href="<?php echo SITE_URL; ?>/user/invoices.php" class="btn-user btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="invoice-sheet">
    <div class="invoice-head">
        <div>
            <div class="invoice-logo"><i class="fas fa-scissors"></i> <?php echo sanitize($salonName); ?></div>
            <div class="invoice-meta muted">
                <?php if ($salonAddress): ?><div><?php echo sanitize($salonAddress); ?></div><?php endif; ?>
                <?php if ($salonPhone): ?><div><?php echo sanitize($salonPhone); ?></div><?php endif; ?>
                <?php if ($salonEmail): ?><div><?php echo sanitize($salonEmail); ?></div><?php endif; ?>
            </div>
        </div>
        <div class="invoice-title">
            <div class="inv-num">INVOICE</div>
            <div class="muted"><?php echo sanitize($invoice['invoice_number']); ?></div>
            <div class="muted">Issued: <?php echo formatDate($invoice['issued_at'], 'M d, Y'); ?></div>
            <div class="muted">Due: <?php echo formatDate($invoice['due_at'], 'M d, Y'); ?></div>
        </div>
    </div>

    <div class="invoice-bill-to">
        <div><strong>Bill To</strong></div>
        <div><?php echo sanitize($invoice['client_name']); ?></div>
        <?php if ($invoice['client_email']): ?><div class="muted"><?php echo sanitize($invoice['client_email']); ?></div><?php endif; ?>
        <?php if ($invoice['client_phone']): ?><div class="muted"><?php echo sanitize($invoice['client_phone']); ?></div><?php endif; ?>
        <?php if ($invoice['client_address']): ?><div class="muted"><?php echo sanitize($invoice['client_address']); ?></div><?php endif; ?>
    </div>

    <table class="invoice-items">
        <thead>
            <tr>
                <th>Description</th>
                <th>Date</th>
                <th class="t-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)):
                foreach ($items as $li): ?>
            <tr>
                <td><?php echo sanitize($li['service_name']); ?><?php if ((int)$li['quantity'] > 1): ?> <span class="muted">&times;<?php echo (int)$li['quantity']; ?></span><?php endif; ?></td>
                <td><?php if ($invoice['appointment_date']): echo formatDate($invoice['appointment_date'], 'M d, Y') . ' ' . formatSlotTime($invoice['appointment_time']); else: ?>—<?php endif; ?></td>
                <td class="t-right"><?php echo formatMoney($li['line_total']); ?></td>
            </tr>
            <?php endforeach;
            elseif ($invoice['service_name']): ?>
            <tr>
                <td><?php echo sanitize($invoice['service_name']); ?></td>
                <td><?php echo $invoice['appointment_date'] ? formatDate($invoice['appointment_date'], 'M d, Y') . ' ' . formatSlotTime($invoice['appointment_time']) : '—'; ?></td>
                <td class="t-right"><?php echo formatMoney($invoice['subtotal']); ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="invoice-totals">
        <div class="it-row"><span>Subtotal</span><span><?php echo formatMoney($invoice['subtotal']); ?></span></div>
        <?php if ((float)$invoice['tax_amount'] > 0): ?>
        <div class="it-row"><span>Tax (<?php echo rtrim(rtrim(number_format((float)$invoice['tax_rate'], 2), '0'), '.'); ?>%)</span><span><?php echo formatMoney($invoice['tax_amount']); ?></span></div>
        <?php endif; ?>
        <?php if ((float)$invoice['discount'] > 0): ?>
        <div class="it-row"><span>Discount</span><span>-<?php echo formatMoney($invoice['discount']); ?></span></div>
        <?php endif; ?>
        <div class="it-row it-total"><span>Total</span><span><?php echo formatMoney($invoice['total']); ?></span></div>
        <?php if ((float)$invoice['paid_amount'] > 0): ?>
        <div class="it-row"><span>Paid</span><span>-<?php echo formatMoney($invoice['paid_amount']); ?></span></div>
        <?php endif; ?>
        <div class="it-row it-due"><span>Balance Due</span><span><?php echo formatMoney($balance); ?></span></div>
    </div>

    <?php if (!empty($payments)): ?>
    <div class="invoice-payments">
        <div class="invpay-head">Payments Applied</div>
        <?php foreach ($payments as $pm): ?>
        <div class="invpay-row">
            <span><?php echo formatDate($pm['paid_at'] ?: $pm['created_at'], 'M d, Y'); ?> &middot; <?php echo ucfirst(str_replace('_', ' ', $pm['payment_method'])); ?> <?php echo $pm['transaction_ref'] ? '(' . sanitize($pm['transaction_ref']) . ')' : ''; ?>
                <?php if (isset($receiptByPayment[$pm['payment_id']]['receipt_number'])):
                    $it = $receiptByPayment[$pm['payment_id']]; ?>
                &middot; <a href="#" onclick="window.open('<?php echo SITE_URL; ?>/admin/receipts/print.php?receipt_id=<?php echo (int)$it['receipt_id']; ?>','_blank'); return false;" class="text-cyan-link"><?php echo sanitize($it['receipt_number']); ?></a>
                <?php endif; ?>
            </span>
            <span><?php echo financePaymentRecordBadge($pm['payment_status']) ?> <?php echo formatMoney($pm['amount']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($invoice['notes'])): ?>
    <div class="invoice-notes-block">
        <strong>Notes</strong>
        <div class="muted"><?php echo nl2br(sanitize($invoice['notes'])); ?></div>
    </div>
    <?php endif; ?>

    <div class="invoice-foot muted">Thank you for visiting <?php echo sanitize($salonName); ?>! <?php echo sanitize(getSetting('financial_invoice_footer', '')); ?></div>
</div>

<style media="print">
    @media print {
        body * { visibility: hidden; }
        #invoiceToolbar, .sidebar-user, .topbar-user, .mobile-nav-bar { display: none !important; }
        .invoice-sheet, .invoice-sheet * { visibility: visible; }
        .invoice-sheet { position: absolute; top: 0; left: 0; width: 100%; margin: 0; box-shadow: none; }
        .layout-user { margin-left: 0; padding: 0; }
        .inv-num { font-size: 28px; }
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>