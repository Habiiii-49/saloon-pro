<?php
/**
 * Admin - Create Invoice
 * Manual / appointment-linked invoice with snapshot line items.
 * Totals are always recomputed on the server from validated inputs.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

requireRole('admin');

$pageTitle = 'New Invoice';
$activeMenu = 'invoices';
$extraCss   = 'admin/payments/assets/css/payments.css';
$extraJs    = 'admin/payments/assets/js/payments.js';

$db     = getDBConnection();
$errors = [];

/* ---------- Reference data ---------- */
$services = $db->query("SELECT service_id, service_name, price, duration_minutes FROM services WHERE is_active = 1 ORDER BY service_name ASC")->fetchAll();
$stylists = $db->query("
    SELECT st.staff_id, CONCAT(u.first_name, ' ', u.last_name) AS name
    FROM staff st JOIN users u ON u.user_id = st.user_id
    ORDER BY name ASC
")->fetchAll();
$appointments = $db->query("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.client_id, a.total_amount,
           a.staff_id,
           CONCAT(c.first_name, ' ', c.last_name) AS client_name,
           s.service_name
    FROM appointments a
    LEFT JOIN clients c ON c.client_id = a.client_id
    LEFT JOIN services s ON s.service_id = a.service_id
    WHERE a.status IN ('pending','confirmed','in_progress','completed')
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 400
")->fetchAll();

$defaultTax = (float)getSetting('financial_tax_rate', '0');

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/invoices/create.php');
    }

    $clientId      = (int)($_POST['client_id'] ?? 0);
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $stylistId     = (int)($_POST['stylist_id'] ?? 0);
    $issuedAt      = trim((string)($_POST['issued_at'] ?? ''));
    $dueAt         = trim((string)($_POST['due_at'] ?? ''));
    $taxRate       = moneyToFloat($_POST['tax_rate'] ?? '0') ?? 0.0;
    $discount      = moneyToFloat($_POST['discount'] ?? '0') ?? 0.0;
    $status        = ($_POST['status'] ?? 'draft') === 'sent' ? 'sent' : 'draft';
    $notes         = trim((string)($_POST['notes'] ?? ''));

    $serviceIds   = $_POST['service_id'] ?? [];
    $itemNames    = $_POST['item_name'] ?? [];
    $qtys         = $_POST['qty'] ?? [];
    $unitPrices   = $_POST['unit_price'] ?? [];
    $lineDiscounts = $_POST['line_discount'] ?? [];

    if ($appointmentId > 0) {
        $stmt = $db->prepare("SELECT client_id, staff_id, total_amount FROM appointments WHERE appointment_id = :id");
        $stmt->execute([':id' => $appointmentId]);
        $appt = $stmt->fetch();
        if ($appt) {
            $clientId  = (int)$appt['client_id'];
            $stylistId = (int)$appt['staff_id']; // appointment owns whose service is billed
        }
    }

    if ($clientId <= 0) {
        setFlash('error', 'Please choose a client.');
        $errors[] = 'Please choose a client.';
    }
    if ($stylistId <= 0) {
        $errors[] = 'A stylist must be assigned to the invoice.';
    }

    $items = [];
    $count = max(count($serviceIds), count($itemNames));
    for ($i = 0; $i < $count; $i++) {
        $qty = max(1, (int)($qtys[$i] ?? 1));
        $unitPrice = moneyToFloat($unitPrices[$i] ?? '') ;
        $lineDisc  = moneyToFloat($lineDiscounts[$i] ?? '') ?? 0.0;
        $sid  = (int)($serviceIds[$i] ?? 0);
        $name = trim((string)($itemNames[$i] ?? ''));

        if ($sid > 0) {
            $stmt = $db->prepare("SELECT service_name, price FROM services WHERE service_id = :id");
            $stmt->execute([':id' => $sid]);
            $svc = $stmt->fetch();
            if (!$svc) {
                $errors[] = 'One of the chosen services no longer exists.';
                continue;
            }
            $name = $svc['service_name'];
            if ($unitPrice === null) {
                $unitPrice = (float)$svc['price'];
            }
        }
        if ($name === '' || $unitPrice === null || $unitPrice < 0) {
            continue;
        }
        $items[] = [
            'service_id'   => $sid > 0 ? $sid : null,
            'service_name' => $name,
            'quantity'     => $qty,
            'unit_price'   => $unitPrice,
            'discount'     => $lineDisc,
        ];
    }

    if (empty($items)) {
        $errors[] = 'Add at least one service line to the invoice.';
    }

    if (empty($errors)) {
        $computed = composeInvoiceTotals($items, $taxRate, $discount);

        try {
            $db->beginTransaction();
            $invoiceNumber = generateInvoiceNumber($db);
            $stmt = $db->prepare("
                INSERT INTO invoices
                    (invoice_number, client_id, appointment_id, stylist_id,
                     subtotal, tax_rate, tax_amount, discount, total,
                     status, issued_at, due_at, notes, created_by)
                VALUES
                    (:num, :cid, :aid, :stid,
                     :sub, :tr, :tax, :disc, :tot,
                     :status, :issued, :due, :notes, :by)
            ");
            $stmt->execute([
                ':num'      => $invoiceNumber,
                ':cid'      => $clientId,
                ':aid'      => $appointmentId > 0 ? $appointmentId : null,
                ':stid'     => $stylistId > 0 ? $stylistId : null,
                ':sub'      => $computed['subtotal'],
                ':tr'       => $computed['tax_rate'],
                ':tax'      => $computed['tax'],
                ':disc'     => $computed['discount'],
                ':tot'      => $computed['total'],
                ':status'   => $status,
                ':issued'   => $issuedAt !== '' ? date('Y-m-d', strtotime($issuedAt)) : date('Y-m-d'),
                ':due'      => $dueAt !== '' ? date('Y-m-d', strtotime($dueAt)) : null,
                ':notes'    => $notes !== '' ? $notes : null,
                ':by'       => currentUserId(),
            ]);
            $invoiceId = (int)$db->lastInsertId();

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

            logFinancialAudit($db, currentUserId(), 'invoice', $invoiceId, 'invoice.created',
                              null, json_encode(['number' => $invoiceNumber, 'total' => $computed['total']]));
            $db->commit();

            setFlash('success', 'Invoice ' . $invoiceNumber . ' created (' . formatMoney($computed['total']) . ').');
            redirect('admin/invoices/view.php?id=' . $invoiceId);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Could not create the invoice. Please try again.';
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-file-circle-plus"></i> New Invoice</h1>
        <p>Totals are calculated on the server; you can never over-charge the browser.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/invoices/index.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<form method="post" action="" class="row g-3">
    <?php echo csrfField(); ?>

    <div class="col-lg-7">
        <div class="admin-card">
            <div class="card-header-custom"><h5><i class="fas fa-user"></i> Customer &amp; Booking</h5></div>
            <div class="card-body-custom">
                <div class="row g-3">
                    <div class="col-md-8" data-fin-client-search-root>
                        <label class="form-label-admin">Client <span class="text-danger">*</span></label>
                        <div class="fin-search-wrap">
                            <input type="text" data-fin-client-search class="form-control-admin" placeholder="Type to search clients..." autocomplete="off">
                            <input type="hidden" name="client_id" data-fin-client-id value="<?php echo (int)($clientId ?? 0); ?>">
                            <div class="fin-search-results"></div>
                        </div>
                        <div class="fin-muted mt-1" data-fin-client-selection></div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Link Appointment (optional)</label>
                        <select name="appointment_id" class="form-control-admin">
                            <option value="0">Manually invoiced</option>
                            <?php foreach ($appointments as $ap): ?>
                            <option value="<?php echo (int)$ap['appointment_id']; ?>" data-client="<?php echo (int)$ap['client_id']; ?>"
                                <?php echo (int)($_POST['appointment_id'] ?? 0) === (int)$ap['appointment_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($ap['appointment_date'] . ' ' . substr((string)$ap['appointment_time'], 0, 5))
                                    . ' — ' . sanitize($ap['client_name']) . ' — ' . sanitize($ap['service_name'] ?? 'Service'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="fin-muted mt-1">Picking an appointment locks it to the right client &amp; stylist.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Stylist <span class="text-danger">*</span></label>
                        <select name="stylist_id" class="form-control-admin">
                            <option value="0">— Select —</option>
                            <?php foreach ($stylists as $st): ?>
                            <option value="<?php echo (int)$st['staff_id']; ?>" <?php echo (int)($_POST['stylist_id'] ?? 0) === (int)$st['staff_id'] ? 'selected' : ''; ?>><?php echo sanitize($st['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Issue Date</label>
                        <input type="date" name="issued_at" class="form-control-admin" value="<?php echo sanitize($_POST['issued_at'] ?? date('Y-m-d')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Due Date</label>
                        <input type="date" name="due_at" class="form-control-admin" value="<?php echo sanitize($_POST['due_at'] ?? date('Y-m-d', strtotime('+14 days'))); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-admin">Status</label>
                        <select name="status" class="form-control-admin">
                            <option value="draft" <?php echo ($_POST['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent"  <?php echo ($_POST['status'] ?? '') === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label-admin">Notes (private)</label>
                        <textarea name="notes" class="form-control-admin" rows="2"><?php echo sanitize($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <div class="card-header-custom">
                <h5><i class="fas fa-layer-group"></i> Line Items</h5>
                <button type="button" class="btn-admin btn-outline btn-xs" data-fin-add-row><i class="fas fa-plus"></i> Add Row</button>
            </div>
            <div class="card-body-custom">
                <div data-fin-rows>
                    <?php for ($r = 0; $r < max(1, count($itemNames ?? [])); $r++): ?>
                    <div class="fin-item-row">
                        <select name="service_id[]" data-fin-service class="form-control-admin form-control-sm">
                            <option value="">Manual item / pick service…</option>
                            <?php foreach ($services as $sv): ?>
                            <option value="<?php echo (int)$sv['service_id']; ?>" data-price="<?php echo (float)$sv['price']; ?>"
                                <?php echo (int)($serviceIds[$r] ?? 0) === (int)$sv['service_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($sv['service_name']); ?> — <?php echo formatMoney($sv['price']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="item_name[]" data-fin-name class="form-control-admin form-control-sm" placeholder="Description" value="<?php echo sanitize($itemNames[$r] ?? ''); ?>">
                        <input type="number" name="qty[]" data-fin-qty class="form-control-admin form-control-sm" value="<?php echo (int)($qtys[$r] ?? 1); ?>" min="1">
                        <input type="text" name="unit_price[]" data-fin-price class="form-control-admin form-control-sm" placeholder="Price" value="<?php echo sanitize($unitPrices[$r] ?? ''); ?>">
                        <input type="text" name="line_discount[]" data-fin-line-discount class="form-control-admin form-control-sm" placeholder="Discount" value="<?php echo sanitize($lineDiscounts[$r] ?? ''); ?>">
                        <button type="button" class="btn-admin btn-danger-soft btn-xs" data-fin-remove-row><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endfor; ?>
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
                        <input type="text" name="tax_rate" data-fin-tax-rate class="form-control-admin" value="<?php echo sanitize($_POST['tax_rate'] ?? '') !== '' ? sanitize($_POST['tax_rate'] ?? '') : number_format($defaultTax, 2); ?>">
                    </div>
                    <div class="flex-fill">
                        <label class="form-label-admin">Invoice Discount</label>
                        <input type="text" name="discount" data-fin-invoice-discount class="form-control-admin" value="<?php echo sanitize($_POST['discount'] ?? ''); ?>" placeholder="0.00">
                    </div>
                </div>
                <table class="paper-table">
                    <tbody>
                        <tr><td>Subtotal</td><td class="text-end fin-amount" data-fin-sum-subtotal>0.00</td></tr>
                        <tr><td>Line discounts</td><td class="text-end fin-amount" data-fin-sum-discount>0.00</td></tr>
                        <tr><td>Tax</td><td class="text-end fin-amount" data-fin-sum-tax>0.00</td></tr>
                        <tr class="grand"><td>Grand Total</td><td class="text-end fin-amount" data-fin-sum-total>0.00</td></tr>
                    </tbody>
                </table>
                <p class="fin-muted mt-2 mb-0"><i class="fas fa-shield"></i> All totals are recomputed server-side before saving.</p>
            </div>
        </div>

        <div class="d-flex gap-2 mt-3 justify-content-end">
            <a href="<?php echo SITE_URL; ?>/admin/invoices/index.php" class="btn-admin btn-outline">Cancel</a>
            <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-file-invoice"></i> Create Invoice</button>
        </div>
    </div>
</form>

<?php
require_once __DIR__ . '/../includes/footer.php';