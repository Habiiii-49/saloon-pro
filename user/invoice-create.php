<?php
/**
 * Elegance Salon - Receptionist - Create Invoice (manual)
 * Totals are always recomputed on the server from validated inputs.
 */
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/../includes/finance.php';

$pageTitle = 'New Invoice';
$activeMenu = 'invoices';

$db     = getDBConnection();
$errors = [];

$old = [
    'client_id' => '', 'appointment_id' => '0', 'stylist_id' => '0',
    'issued_at' => date('Y-m-d'), 'due_at' => date('Y-m-d', strtotime('+14 days')),
    'status' => 'sent', 'tax_rate' => '', 'discount' => '',
    'notes' => '', 'service_id' => [], 'item_name' => [], 'qty' => [],
    'unit_price' => [], 'line_discount' => [],
];

$clients = $db->query("SELECT client_id, CONCAT(first_name,' ',last_name) AS name, phone FROM clients ORDER BY first_name ASC LIMIT 1000")->fetchAll();
$services = $db->query("SELECT service_id, service_name, price FROM services WHERE is_active = 1 ORDER BY service_name ASC")->fetchAll();
$stylists = $db->query("
    SELECT st.staff_id, CONCAT(u.first_name, ' ', u.last_name) AS name
    FROM staff st JOIN users u ON u.user_id = st.user_id
    ORDER BY name ASC
")->fetchAll();
$appointments = $db->query("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.client_id, a.staff_id,
           CONCAT(c.first_name, ' ', c.last_name) AS client_name, s.service_name
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
        redirect('user/invoice-create.php');
    }

    $old = [
        'client_id'      => (string)($_POST['client_id'] ?? ''),
        'appointment_id' => (string)($_POST['appointment_id'] ?? '0'),
        'stylist_id'     => (string)($_POST['stylist_id'] ?? '0'),
        'issued_at'      => trim((string)($_POST['issued_at'] ?? '')),
        'due_at'         => trim((string)($_POST['due_at'] ?? '')),
        'status'         => trim((string)($_POST['status'] ?? 'sent')),
        'tax_rate'       => trim((string)($_POST['tax_rate'] ?? '')),
        'discount'       => trim((string)($_POST['discount'] ?? '')),
        'notes'          => trim((string)($_POST['notes'] ?? '')),
        'service_id'     => $_POST['service_id'] ?? [],
        'item_name'      => $_POST['item_name'] ?? [],
        'qty'            => $_POST['qty'] ?? [],
        'unit_price'     => $_POST['unit_price'] ?? [],
        'line_discount'  => $_POST['line_discount'] ?? [],
    ];

    $clientId      = (int)$old['client_id'];
    $appointmentId = (int)$old['appointment_id'];
    $stylistId     = (int)$old['stylist_id'];
    $taxRate       = moneyToFloat($old['tax_rate']) ?? 0.0;
    $discount      = moneyToFloat($old['discount']) ?? 0.0;
    $status        = $old['status'] === 'draft' ? 'draft' : 'sent';

    if ($appointmentId > 0) {
        $stmt = $db->prepare("SELECT client_id, staff_id, appointment_date, appointment_time FROM appointments WHERE appointment_id = :id");
        $stmt->execute([':id' => $appointmentId]);
        $appt = $stmt->fetch();
        if ($appt) {
            if ($clientId <= 0) { $clientId = (int)$appt['client_id']; }
            if ($stylistId <= 0) { $stylistId = (int)$appt['staff_id']; }
        }
    }

    if ($clientId <= 0) { $errors[] = 'Please choose a client.'; }

    $items = [];
    $count = max(count($old['service_id']), count($old['item_name']));
    for ($i = 0; $i < $count; $i++) {
        $qty = max(1, (int)($old['qty'][$i] ?? 1));
        $unitPrice = moneyToFloat($old['unit_price'][$i] ?? '');
        $lineDisc  = moneyToFloat($old['line_discount'][$i] ?? '') ?? 0.0;
        $sid  = (int)($old['service_id'][$i] ?? 0);
        $name = trim((string)($old['item_name'][$i] ?? ''));

        if ($sid > 0) {
            $stmt = $db->prepare("SELECT service_name, price FROM services WHERE service_id = :id");
            $stmt->execute([':id' => $sid]);
            $svc = $stmt->fetch();
            if (!$svc) { $errors[] = 'One of the chosen services no longer exists.'; continue; }
            $name = $svc['service_name'];
            if ($unitPrice === null) { $unitPrice = (float)$svc['price']; }
        }
        if ($name === '' || $unitPrice === null || $unitPrice < 0) { continue; }
        $items[] = [
            'service_id'   => $sid > 0 ? $sid : null,
            'service_name' => $name,
            'quantity'     => $qty,
            'unit_price'   => $unitPrice,
            'discount'     => $lineDisc,
        ];
    }

    if (empty($items)) { $errors[] = 'Add at least one service line to the invoice.'; }

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
                ':issued'   => $old['issued_at'] !== '' ? date('Y-m-d', strtotime($old['issued_at'])) : date('Y-m-d'),
                ':due'      => $old['due_at'] !== '' ? date('Y-m-d', strtotime($old['due_at'])) : null,
                ':notes'    => $old['notes'] !== '' ? $old['notes'] : null,
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
            redirect('user/invoice-view.php?invoice_id=' . $invoiceId);
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[] = 'Could not create the invoice. Please try again.';
        }
    }
    foreach ($errors as $err) { setFlash('error', $err); }
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-circle-plus"></i> New Invoice</h1>
        <p class="page-subtitle">Create an invoice for a client. Totals are computed on the server.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/user/invoices.php" class="btn-user btn-outline"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
</div>

<form method="post" action="" class="grid-2col">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

    <!-- LEFT: customer + items -->
    <div>
        <div class="panel">
            <div class="panel-head"><h5><i class="fas fa-user"></i> Client &amp; Booking</h5></div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Client <span class="req">*</span></label>
                        <select name="client_id" id="invClient" class="form-control-salon" required>
                            <option value="">— Select client —</option>
                            <?php foreach ($clients as $cl): ?>
                            <option value="<?php echo (int)$cl['client_id']; ?>"
                                <?php echo (int)$old['client_id'] === (int)$cl['client_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($cl['name']); ?><?php echo $cl['phone'] ? ' (' . sanitize($cl['phone']) . ')' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Link Appointment (optional)</label>
                        <select name="appointment_id" id="invAppointment" class="form-control-salon">
                            <option value="0">Manually invoiced</option>
                            <?php foreach ($appointments as $ap): ?>
                            <option value="<?php echo (int)$ap['appointment_id']; ?>" data-client="<?php echo (int)$ap['client_id']; ?>" data-stylist="<?php echo (int)$ap['staff_id']; ?>"
                                <?php echo (int)$old['appointment_id'] === (int)$ap['appointment_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($ap['appointment_date'] . ' ' . substr((string)$ap['appointment_time'], 0, 5) . ' — ' . $ap['client_name'] . ' — ' . ($ap['service_name'] ?? 'Service')); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Stylist (optional)</label>
                        <select name="stylist_id" id="invStylist" class="form-control-salon">
                            <option value="0">— None —</option>
                            <?php foreach ($stylists as $st): ?>
                            <option value="<?php echo (int)$st['staff_id']; ?>" <?php echo (int)$old['stylist_id'] === (int)$st['staff_id'] ? 'selected' : ''; ?>><?php echo sanitize($st['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Issue Date</label>
                        <input type="date" name="issued_at" class="form-control-salon" value="<?php echo sanitize($old['issued_at']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_at" class="form-control-salon" value="<?php echo sanitize($old['due_at']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control-salon">
                            <option value="sent" <?php echo $old['status'] === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="draft" <?php echo $old['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>Notes (private)</label>
                        <textarea name="notes" class="form-control-salon" rows="2"><?php echo sanitize($old['notes']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel mt-2">
            <div class="panel-head">
                <h5><i class="fas fa-layer-group"></i> Line Items</h5>
                <button type="button" class="btn-user btn-outline btn-xs" id="addItemRow"><i class="fas fa-plus"></i> Add Row</button>
            </div>
            <div class="panel-body">
                <div id="itemRows">
                    <?php for ($r = 0; $r < max(1, count($old['item_name'])); $r++): ?>
                    <div class="fin-item-row">
                        <select name="service_id[]" data-item-service class="form-control-salon">
                            <option value="">Manual item…</option>
                            <?php foreach ($services as $sv): ?>
                            <option value="<?php echo (int)$sv['service_id']; ?>" data-price="<?php echo (float)$sv['price']; ?>"
                                <?php echo (int)($old['service_id'][$r] ?? 0) === (int)$sv['service_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($sv['service_name']); ?> — <?php echo formatMoney($sv['price']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="item_name[]" data-item-name class="form-control-salon" placeholder="Description" value="<?php echo sanitize($old['item_name'][$r] ?? ''); ?>">
                        <input type="number" name="qty[]" data-item-qty class="form-control-salon" value="<?php echo (int)($old['qty'][$r] ?? 1); ?>" min="1">
                        <input type="text" name="unit_price[]" data-item-price class="form-control-salon" placeholder="Price" value="<?php echo sanitize($old['unit_price'][$r] ?? ''); ?>">
                        <input type="text" name="line_discount[]" data-item-disc class="form-control-salon" placeholder="Discount" value="<?php echo sanitize($old['line_discount'][$r] ?? ''); ?>">
                        <button type="button" class="btn-user btn-outline btn-xs item-remove"><i class="fas fa-times"></i></button>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: totals -->
    <div>
        <div class="panel">
            <div class="panel-head"><h5><i class="fas fa-calculator"></i> Totals</h5></div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tax Rate %</label>
                        <input type="text" name="tax_rate" id="invTaxRate" class="form-control-salon" value="<?php echo $old['tax_rate'] !== '' ? sanitize($old['tax_rate']) : number_format($defaultTax, 2); ?>">
                    </div>
                    <div class="form-group">
                        <label>Invoice Discount</label>
                        <input type="text" name="discount" id="invDiscount" class="form-control-salon" value="<?php echo sanitize($old['discount']); ?>" placeholder="0.00">
                    </div>
                </div>
                <table class="paper-table">
                    <tbody>
                        <tr><td>Subtotal</td><td class="text-end fin-amount" id="sumSubtotal">0.00</td></tr>
                        <tr><td>Line discounts</td><td class="text-end fin-amount" id="sumDiscount">0.00</td></tr>
                        <tr><td>Tax</td><td class="text-end fin-amount" id="sumTax">0.00</td></tr>
                        <tr class="grand"><td>Grand Total</td><td class="text-end fin-amount" id="sumTotal">0.00</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-user btn-cyan"><i class="fas fa-file-invoice"></i> Create Invoice</button>
        </div>
    </div>
</form>

<script>
(function () {
    var ROWS = document.getElementById('itemRows');
    var money = function (v) {
        v = String(v || '').replace(/,/g, '').replace(/\s+/g, '');
        var n = parseFloat(v);
        return isNaN(n) || n < 0 ? 0 : n;
    };
    var recalc = function () {
        var subtotal = 0, lineDisc = 0;
        ROWS.querySelectorAll('.fin-item-row').forEach(function (row) {
            var qty = Math.max(1, parseInt(row.querySelector('[data-item-qty]').value, 10) || 1);
            var price = money(row.querySelector('[data-item-price]').value);
            var d = money(row.querySelector('[data-item-disc]').value);
            subtotal += qty * price;
            lineDisc += d;
        });
        var base = subtotal - lineDisc;
        var taxRate = money(document.getElementById('invTaxRate').value);
        var tax = base * taxRate / 100;
        var invDisc = money(document.getElementById('invDiscount').value);
        var total = Math.max(0, base + tax - invDisc);
        document.getElementById('sumSubtotal').textContent = subtotal.toFixed(2);
        document.getElementById('sumDiscount').textContent = lineDisc.toFixed(2);
        document.getElementById('sumTax').textContent = tax.toFixed(2);
        document.getElementById('sumTotal').textContent = total.toFixed(2);
    };
    var template = ROWS.querySelector('.fin-item-row');
    var addRow = function () {
        var clone = template.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        var q = clone.querySelector('[data-item-qty]'); if (q) q.value = 1;
        clone.querySelector('[data-item-service]').value = '';
        clone.querySelector('[data-item-name]').value = '';
        ROWS.appendChild(clone);
    };
    ROWS.addEventListener('click', function (e) {
        var btn = e.target.closest('.item-remove');
        if (btn) {
            if (ROWS.querySelectorAll('.fin-item-row').length > 1) { btn.closest('.fin-item-row').remove(); recalc(); }
        }
    });
    ROWS.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('[data-item-service]')) {
            var opt = t.selectedOptions[0];
            if (opt && opt.dataset.price !== undefined) { t.closest('.fin-item-row').querySelector('[data-item-price]').value = opt.dataset.price; }
        }
        recalc();
    });
    ROWS.addEventListener('input', recalc);
    document.getElementById('addItemRow').addEventListener('click', addRow);
    document.getElementById('invTaxRate').addEventListener('input', recalc);
    document.getElementById('invDiscount').addEventListener('input', recalc);

    document.getElementById('invAppointment').addEventListener('change', function () {
        var opt = this.selectedOptions[0];
        if (!opt || !opt.dataset.client) return;
        if (document.getElementById('invClient').value === '') {
            document.getElementById('invClient').value = opt.dataset.client;
        }
        if (document.getElementById('invStylist').value === '0' && opt.dataset.stylist) {
            document.getElementById('invStylist').value = opt.dataset.stylist;
        }
    });

    recalc();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>