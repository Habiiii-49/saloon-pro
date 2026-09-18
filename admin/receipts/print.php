<?php
/**
 * Receipt Print - standalone printable receipt.
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

$db = getDBConnection();
$receiptId = (int)($_GET['receipt_id'] ?? 0);

$stmt = $db->prepare("
    SELECT r.receipt_id, r.receipt_number, r.created_at,
           p.payment_id, p.amount, p.refunded_amount, p.payment_method, p.payment_status, p.paid_at, p.transaction_ref,
           i.invoice_id, i.invoice_number, i.total AS invoice_total, i.paid_amount AS invoice_paid,
           CONCAT(c.first_name,' ',c.last_name) AS client_name,
           c.phone AS client_phone, c.address AS client_address
    FROM receipts r
    JOIN payments p ON p.payment_id = r.payment_id
    LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
    LEFT JOIN clients c ON c.client_id = COALESCE(p.client_id, i.client_id)
    WHERE r.receipt_id = :id LIMIT 1
");
$stmt->execute([':id' => $receiptId]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found');
}

$businessName    = getSetting('salon_name', 'Elegance Salon');
$businessAddress = getSetting('salon_address', '');
$businessPhone   = getSetting('salon_phone', '');
$businessEmail   = getSetting('salon_email', '');
$netPaid = (float)$receipt['amount'] - (float)$receipt['refunded_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?php echo htmlspecialchars($receipt['receipt_number']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/admin/payments/assets/css/payments.css">
</head>
<body style="background:#0b1220;">
    <div class="paper-actionsbar">
        <a class="btn btn-outline-light" href="javascript:window.close();"><i class="fas fa-xmark"></i> Close</a>
        <button class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>

    <div class="fin-paper" style="max-width:620px;">
        <div class="paper-brand">
            <div>
                <h1><?php echo htmlspecialchars($businessName); ?></h1>
                <div class="sub"><?php echo htmlspecialchars($businessAddress); ?></div>
                <div class="sub"><?php echo htmlspecialchars($businessPhone); ?> <?php if ($businessEmail): ?>&middot; <?php echo htmlspecialchars($businessEmail); ?><?php endif; ?></div>
            </div>
            <div class="text-end">
                <div style="font-size:1.5rem; font-weight:800; color:#0ea5c9;">RECEIPT</div>
                <div class="mt-1"><strong><?php echo htmlspecialchars($receipt['receipt_number']); ?></strong></div>
                <div class="sub"><?php echo date('F j, Y g:i A', strtotime($receipt['created_at'])); ?></div>
            </div>
        </div>

        <hr style="margin:1.2rem 0; border-color:#e2e8f0;">

        <div class="row">
            <div class="col-sm-6">
                <div class="sub" style="text-transform:uppercase; letter-spacing:.06em;">Received From</div>
                <h4 class="mt-1 mb-0"><?php echo htmlspecialchars($receipt['client_name'] ?? 'Customer'); ?></h4>
                <?php if ($receipt['client_phone']): ?><div style="color:#5b6472;"><?php echo htmlspecialchars($receipt['client_phone']); ?></div><?php endif; ?>
            </div>
            <div class="col-sm-6 text-sm-end">
                <div class="sub" style="text-transform:uppercase; letter-spacing:.06em;">Invoice</div>
                <div class="mt-1" style="font-weight:700;"><?php echo $receipt['invoice_number'] ? htmlspecialchars($receipt['invoice_number']) : 'â€”'; ?></div>
                <?php if ($receipt['transaction_ref']): ?><div style="color:#5b6472;">Ref: <?php echo htmlspecialchars($receipt['transaction_ref']); ?></div><?php endif; ?>
            </div>
        </div>

        <table class="paper-table mt-4">
            <tbody>
                <tr><td>Payment Method</td><td class="text-end"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $receipt['payment_method']))); ?></td></tr>
                <tr><td>Payment Date</td><td class="text-end"><?php echo $receipt['paid_at'] ? htmlspecialchars(date('F j, Y g:i A', strtotime($receipt['paid_at']))) : htmlspecialchars(date('F j, Y g:i A', strtotime($receipt['created_at']))); ?></td></tr>
                <tr><td>Amount Received</td><td class="text-end" style="font-weight:700; color:#16a34a;"><?php echo formatMoney($receipt['amount']); ?></td></tr>
                <?php if ((float)$receipt['refunded_amount'] > 0): ?>
                <tr><td>Refunded</td><td class="text-end" style="color:#dc2626;">âˆ’ <?php echo formatMoney($receipt['refunded_amount']); ?></td></tr>
                <?php endif; ?>
                <tr class="grand"><td>Net Paid</td><td class="text-end"><?php echo formatMoney($netPaid); ?></td></tr>
                <tr><td>Invoice Total</td><td class="text-end"><?php echo formatMoney($receipt['invoice_total']); ?></td></tr>
            </tbody>
        </table>

        <div class="paper-foot text-center" style="margin-top:1.6rem;">
            Thank you for your visit. This receipt is proof of payment; refunds are handled only at the salon.
        </div>
    </div>
</body>
</html>