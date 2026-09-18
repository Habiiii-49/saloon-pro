<?php
/**
 * Admin - Edit Invoice (draft / sent / unpaid only).
 * Snapshot protection: once money exists, no edits.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'Edit Invoice';
$activeMenu = 'invoices';
$extraCss   = 'admin/payments/assets/css/payments.css';
$extraJs    = 'admin/payments/assets/js/payments.js';

$db     = getDBConnection();
$errors = [];
$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = getInvoiceFull($db, $invoiceId);
if (!$invoice) {
    setFlash('error', 'Invoice not found.');
    redirect('admin/invoices/index.php');
}

$summary = invoicePaymentSummary($db, $invoiceId);
$editable = in_array($invoice['status'], ['draft', 'sent'], true) && $summary['net_paid'] <= 0;
if (!$editable) {
    setFlash('error', 'This invoice is locked and cannot be edited.');
    redirect('admin/invoices/view.php?id=' . $invoiceId);
}

$services = $db->query("SELECT service_id, service_name, price, duration_minutes FROM services WHERE is_active = 1 ORDER BY service_name ASC")->fetchAll();
$stylists = $db->query("
    SELECT st.staff_id, CONCAT(u.first_name, ' ', u.last_name) AS name
    FROM staff st JOIN users u ON u.user_id = st.user_id
    ORDER BY name ASC
")->fetchAll();

$items = getInvoiceItemsFull($db, $invoiceId);

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/invoices/edit.php?id=' . $invoiceId);
    }

    $stylistId = (int)($_POST['stylist_id'] ?? 0);
    $issuedAt  = trim((string)($_POST['issued_at'] ?? ''));
    $dueAt     = trim((string)($_POST['due_at'] ?? ''));
    $taxRate   = moneyToFloat($_POST['tax_rate'] ?? '0') ?? 0.0;
    $discount  = moneyToFloat($_POST['discount'] ?? '0') ?? 0.0;
    $status    = ($_POST['status'] ?? 'draft') === 'sent' ? 'sent' : 'draft';
    $notes     = trim((string)($_POST['notes'] ?? ''));

    $serviceIds   = $_POST['service_id'] ?? [];
    $itemNames    = $_POST['item_name'] ?? [];
    $qtys         = $_POST['qty'] ?? [];
    $unitPrices   = $_POST['unit_price'] ?? [];
    $lineDiscounts = $_POST['line_discount'] ?? [];

    if ($stylistId <= 0) {
        $errors[] = 'A stylist must be assigned.';
    }

    $itemsIn = [];
    $count = max(count($serviceIds), count($itemNames));
    for ($i = 0; $i < $count; $i++) {
        $qty = max(1, (int)($qtys[$i] ?? 1));
        $unitPrice = moneyToFloat($unitPrices[$i] ?? '');
        $lineDisc  = moneyToFloat($lineDiscounts[$i] ?? '') ?? 0.0;
        $sid  = (int)($serviceIds[$i] ?? 0);
        $name = trim((string)($itemNames[$i] ?? ''));
        if ($sid > 0) {
            $stmt = $db->prepare("SELECT service_name, price FROM services WHERE service_id = :id");
            $stmt->execute([':id' => $sid]);
            $svc = $stmt->fetch();
            if (!$svc) { continue; }
            $name = $svc['service_name'];
            if ($unitPrice === null) { $unitPrice = (float)$svc['price']; }
        }
        if ($name === '' || $unitPrice === null || $unitPrice < 0) { continue; }
        $itemsIn[] = [
            'service_id'   => $sid > 0 ? $sid : null,
            'service_name' => $name,
            'quantity'     => $qty,
            'unit_price'   => $unitPrice,
            'discount'     => $lineDisc,
        ];
    }

    if (empty($itemsIn)) {
        $errors[] = 'Add at least one service line.';
    }

    if (empty($errors)) {
        $computed = composeInvoiceTotals($itemsIn, $taxRate, $discount);
        try {
            $db->beginTransaction();
            $oldJson = json_encode([
                'number' => $invoice['invoice_number'],
                'total'  => $invoice['total'],
                'status' => $invoice['status'],
            ]);
            $stmt = $db->prepare("
                UPDATE invoices SET
                    stylist_id = :stid, subtotal = :sub, tax_rate = :tr,
                    tax_amount = :tax, discount = :disc, total = :tot,
                    status = :status, issued_at = :issued, due_at = :due, notes = :notes
                WHERE invoice_id = :id
            ");
            $stmt->execute([
                ':stid'    => $stylistId,
                ':sub'     => $computed['subtotal'],
                ':tr'      => $computed['tax_rate'],
                ':tax'     => $computed['tax'],
                ':disc'    => $computed['discount'],
                ':tot'     => $computed['total'],
                ':status'  => $status,
                ':issued'  => $issuedAt !== '' ? date('Y-m-d', strtotime($issuedAt)) : $invoice['issued_at'],
                ':due'     => $dueAt !== '' ? date('Y-m-d', strtotime($dueAt)) : null,
                ':notes'   => $notes !== '' ? $notes : null,
                ':id'      => $invoiceId,
            ]);

            $stmt = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = :id");
            $stmt->execute([':id' => $invoiceId]);

            $stmt = $db->prepare("
                INSERT INTO invoice_items
                    (invoice_id, service_id, service_name, quantity, unit_price, discount, tax, line_total)
                VALUES (:iid, :sid, :name, :qty, :price, :disc, :tax, :lt)
            ");
            foreach ($computed['items'] as $line) {
                $stmt->execute([
                    ':iid'   => $invoiceId,
                    ':sid'   => $line['service_id'],
                    ':name'  => $line['service_name'],
                    ':qty'   => $line['quantity'],
                    ':price' => $line['unit_price'],
                    ':disc'  => $line['discount'],
                    ':tax'   => $line['tax'],
                    ':lt'    => $line['line_total'],
                ]);
            }

            syncInvoiceFinancials($db, $invoiceId); // keep payment_status consistent
            logFinancialAudit($db, currentUserId(), 'invoice', $invoiceId, 'invoice.updated',
                              $oldJson, json_encode(['total' => $computed['total'], 'status' => $status]));
            $db->commit();

            setFlash('success', 'Invoice ' . $invoice['invoice_number'] . ' updated (' . formatMoney($computed['total']) . ').');
            redirect('admin/invoices/view.php?id=' . $invoiceId);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Could not update the invoice. Please try again.';
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-pen"></i> Edit <?php echo sanitize($invoice['invoice_number']); ?></h1>
        <p><?php echo financeInvoiceStatusBadge($invoice['status']); ?> <?php echo financePaymentStatusBadge($invoice['payment_status']); ?></p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/invoices/view.php?id=<?php echo (int)$invoiceId; ?>" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Invoice</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<form method="post" action="" class="row g-3">
    <?php echo csrfField(); ?>

    <div class="col-lg-7">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-list"></i> Line Items</h5>
                <button type="button" class="btn-admin btn-outline btn-xs" data-fin-add-row><i class="fas fa-plus"></i> Add Row</button>
            </div>
            <div class="card-body-custom">
                <div data-fin-rows>
                    <?php if ($items): foreach ($items as $r => $li): ?>
                    <div class="fin-item-row">
                        <select name="service_id[]" data-fin-service class="form-control-admin form-control-sm">
                            <option value="">Manual item…</option>
                            <?php foreach ($services as $sv): ?>
                            <option value="<?php echo (int)$sv['service_id']; ?>" data-price="<?php echo (float)$sv['price']; ?>"
                                <?php echo (int)$li['service_id'] === (int)$sv['service_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($sv['service_name']); ?> — <?php echo formatMoney($sv['price']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="item_name[]" data-fin-name class="form-control-admin form-control-sm" placeholder="Description" value="<?php echo sanitize($li['service_name']); ?>">
                        <input type="number" name="qty[]" data-fin-qty class="form-control-admin form-control-sm" value="<?php echo (int)$li['quantity']; ?>" min="1">
                        <input type="text" name="unit_price[]" data-fin-price class="form-control-admin form-control-sm" value="<?php echo number_format((float)$li['unit_price'], 2, '.', ''); ?>">
                        <input type="text" name="line_discount[]" data-fin-line-discount class="form-control-admin form-control-sm" value="<?php echo number_format((float)$li['discount'], 2, '.', ''); ?>">
                        <button type="button" class="btn-admin btn-danger-soft btn-xs" data-fin-remove-row><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="fin-item-row">
                        <select name="service_id[]" data-fin-service class="form-control-admin form-control-sm"><option value="">Manual item…</option>
                            <?php foreach ($services as $sv): ?><option value="<?php echo (int)$sv['service_id']; ?>" data-price="<?php echo (float)$sv['price']; ?>"><?php echo sanitize($sv['service_name']); ?> — <?php echo formatMoney($sv['price']); ?></option><?php endforeach; ?>
                        </select>
                        <input type="text" name="item_name[]" class="form-control-admin form-control-sm" placeholder="Description">
                        <input type="number" name="qty[]" data-fin-qty class="form-control-admin form-control-sm" value="1" min="1">
                        <input type="text" name="unit_price[]" data-fin-price class="form-control-admin form-control-sm">
                        <input type="text" name="line_discount[]" data-fin-line-discount class="form-control-admin form-control-sm">
                        <button type="button" class="btn-admin btn-danger-soft btn-xs" data-fin-remove-row><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <div class="card-header-custom"><h5><i class="fas fa-user"></i> Details</h5></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-admin">Stylist <span class="text-danger">*</span></label>
                        <select name="stylist_id" class="form-control-admin">
                            <option value="0">— Select —</option>
                            <?php foreach ($stylists as $st): ?>
                            <option value="<?php echo (int)$st['staff_id']; ?>" <?php echo (int)($_POST['stylist_id'] ?? $invoice['stylist_id']) === (int)$st['staff_id'] ? 'selected' : ''; ?>><?php echo sanitize($st['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Issue Date</label>
                        <input type="date" name="issued_at" class="form-control-admin" value="<?php echo sanitize($_POST['issued_at'] ?? $invoice['issued_at']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Due Date</label>
                        <input type="date" name="due_at" class="form-control-admin" value="<?php echo sanitize($_POST['due_at'] ?? $invoice['due_at']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Status</label>
                        <select name="status" class="form-control-admin">
                            <option value="draft" <?php echo ($_POST['status'] ?? $invoice['status']) === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent"  <?php echo ($_POST['status'] ?? $invoice['status']) === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label-admin">Notes (private)</label>
                        <textarea name="notes" class="form-control-admin" rows="2"><?php echo sanitize($_POST['notes'] ?? $invoice['notes']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-calculator"></i> Totals</h5></div>
            <div class="card-body-custom">
                <div class="mb-3 d-flex gap-2">
                    <div class="flex-fill">
                        <label class="form-label-admin">Tax Rate %</label>
                        <input type="text" name="tax_rate" data-fin-tax-rate class="form-control-admin" value="<?php echo number_format((float)($_POST['tax_rate'] ?? $invoice['tax_rate']), 2, '.', ''); ?>">
                    </div>
                    <div class="flex-fill">
                        <label class="form-label-admin">Invoice Discount</label>
                        <input type="text" name="discount" data-fin-invoice-discount class="form-control-admin" value="<?php echo number_format((float)($_POST['discount'] ?? $invoice['discount']), 2, '.', ''); ?>">
                    </div>
                </div>
                <table class="paper-table">
                    <tbody>
                        <tr><td>Subtotal</td><td class="text-end fin-amount" data-fin-sum-subtotal><?php echo number_format((float)$invoice['subtotal'], 2); ?></td></tr>
                        <tr><td>Line discounts</td><td class="text-end fin-amount" data-fin-sum-discount><?php echo number_format((float)$invoice['discount'], 2); ?></td></tr>
                        <tr><td>Tax</td><td class="text-end fin-amount" data-fin-sum-tax><?php echo number_format((float)$invoice['tax_amount'], 2); ?></td></tr>
                        <tr class="grand"><td>Grand Total</td><td class="text-end fin-amount" data-fin-sum-total><?php echo number_format((float)$invoice['total'], 2); ?></td></tr>
                    </tbody>
                </table>
                <p class="fin-muted mt-2 mb-0"><i class="fas fa-shield"></i> Locked automatically on first payment.</p>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3 justify-content-end">
            <a href="<?php echo SITE_URL; ?>/admin/invoices/view.php?id=<?php echo (int)$invoiceId; ?>" class="btn-admin btn-outline">Cancel</a>
            <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>