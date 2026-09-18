<?php
/**
 * Invoice Print - standalone printable view.
 * Accessible to admins and receptionists (opened in a new tab).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

if (!isLoggedIn()) {
    redirect('login.php');
}
if (!isAdmin() && !isReceptionist()) {
    http_response_code(403);
    exit('Forbidden');
}

$db    = getDBConnection();
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = $invoiceId > 0 ? getInvoiceFull($db, $invoiceId) : null;
if (!$invoice) {
    http_response_code(404);
    exit('Invoice not found');
}

$items     = getInvoiceItemsFull($db, $invoiceId);
$summary   = invoicePaymentSummary($db, $invoiceId);

$businessName    = getSetting('salon_name', 'Elegance Salon');
$businessAddress = getSetting('salon_address', '');
$businessPhone   = getSetting('salon_phone', '');
$businessEmail   = getSetting('salon_email', '');
$invoiceTerms    = getSetting('financial_invoice_terms', '');
$invoiceFooter   = getSetting('financial_invoice_footer', '');

$issueHuman = $invoice['issued_at'] ? date('F j, Y', strtotime($invoice['issued_at'])) : 'â€”';
$dueHuman   = $invoice['due_at'] ? date('F j, Y', strtotime($invoice['due_at'])) : 'â€”';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/payments/assets/css/payments.css">
</head>
<body style="background:#0b1220;">
    <div class="paper-actionsbar">
        <a class="btn btn-outline-light" href="javascript:window.close();"><i class="fas fa-xmark"></i> Close</a>
        <button class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
    </div>

    <div class="fin-paper">
        <div class="paper-brand">
            <div>
                <h1><?php echo htmlspecialchars($businessName); ?></h1>
                <div class="sub"><?php echo htmlspecialchars($businessAddress); ?></div>
                <div class="sub">
                    <?php echo htmlspecialchars($businessPhone); ?>
                    <?php if ($businessEmail): ?> &middot; <?php echo htmlspecialchars($businessEmail); ?><?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <div style="font-size:1.7rem; font-weight:800; color:#0ea5c9;">INVOICE</div>
                <div class="mt-2"><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></div>
                <div class="sub">Issued: <?php echo htmlspecialchars($issueHuman); ?></div>
                <div class="sub">Due: <?php echo htmlspecialchars($dueHuman); ?></div>
                <div class="mt-1">
                    <span class="badge text-bg-<?php echo $invoice['payment_status'] === 'paid' ? 'success' : ($invoice['payment_status'] === 'partially_paid' ? 'warning' : 'secondary'); ?>">
                        <?php echo strtoupper(str_replace('_', ' ', htmlspecialchars($invoice['payment_status']))); ?>
                    </span>
                </div>
            </div>
        </div>

        <hr style="margin:1.4rem 0; border-color:#e2e8f0;">

        <div class="row">
            <div class="col-sm-6">
                <div class="sub" style="text-transform:uppercase; letter-spacing:.06em;">Billed To</div>
                <h4 class="mt-1 mb-0"><?php echo htmlspecialchars($invoice['client_name']); ?></h4>
                <div style="color:#5b6472;"><?php echo htmlspecialchars($invoice['client_phone']); ?></div>
                <?php if ($invoice['client_email']): ?><div style="color:#5b6472;"><?php echo htmlspecialchars($invoice['client_email']); ?></div><?php endif; ?>
                <?php if ($invoice['client_address']): ?><div style="color:#5b6472;"><?php echo htmlspecialchars($invoice['client_address']); ?></div><?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-end">
                <div class="sub" style="text-transform:uppercase; letter-spacing:.06em;">Stylist</div>
                <h4 class="mt-1 mb-0"><?php echo $invoice['stylist_name'] ? htmlspecialchars($invoice['stylist_name']) : 'Not assigned'; ?></h4>
                <?php if ($invoice['appointment_date']): ?>
                <div style="color:#5b6472;">Appointment: <?php echo htmlspecialchars(date('F j, Y', strtotime($invoice['appointment_date'])) . ' ' . substr((string)$invoice['appointment_time'], 0, 5)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <table class="paper-table mt-4">
            <thead>
                <tr>
                    <th>Service</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $li): ?>
                <tr>
                    <td><?php echo htmlspecialchars($li['service_name']); ?></td>
                    <td class="text-center"><?php echo (int)$li['quantity']; ?></td>
                    <td class="text-end"><?php echo formatMoney($li['unit_price']); ?></td>
                    <td class="text-end"><?php echo $li['discount'] > 0 ? formatMoney($li['discount']) : 'â€”'; ?></td>
                    <td class="text-end"><?php echo $li['tax'] > 0 ? formatMoney($li['tax']) : 'â€”'; ?></td>
                    <td class="text-end"><strong><?php echo formatMoney($li['line_total']); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                <tr><td colspan="6" class="text-center" style="color:#94a3b8;">Legacy invoice (no line items stored)</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="paper-totals">
            <tr><td>Subtotal</td><td class="text-end"><?php echo formatMoney($invoice['subtotal']); ?></td></tr>
            <tr><td>Discount</td><td class="text-end"><?php echo formatMoney($invoice['discount']); ?></td></tr>
            <tr><td>Tax (<?php echo (float)$invoice['tax_rate']; ?>%)</td><td class="text-end"><?php echo formatMoney($invoice['tax_amount']); ?></td></tr>
            <tr class="grand"><td>Total Due</td><td class="text-end"><?php echo formatMoney($invoice['total']); ?></td></tr>
            <tr><td>Paid</td><td class="text-end" style="color:#16a34a;"><?php echo formatMoney($summary['paid']); ?></td></tr>
            <tr><td>Refunded</td><td class="text-end" style="color:#dc2626;">âˆ’ <?php echo formatMoney($summary['refunded']); ?></td></tr>
            <tr><td style="font-weight:700;">Balance</td><td class="text-end" style="font-weight:700;"><?php echo formatMoney($summary['balance']); ?></td></tr>
        </table>

        <?php if ($invoiceTerms): ?>
        <div class="paper-foot">
            <strong>Terms:</strong> <?php echo htmlspecialchars($invoiceTerms); ?>
        </div>
        <?php endif; ?>
        <?php if ($invoiceFooter): ?>
        <div class="paper-foot">
            <?php echo htmlspecialchars($invoiceFooter); ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>