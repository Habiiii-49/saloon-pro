<?php
/**
 * Admin – Create Purchase Order
 * Build a purchase order with multiple line items and save it as draft or ordered.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'New Purchase Order';
$activeMenu      = 'inventory';
$inventoryActive = 'purchase-order-add';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

$presetSupplier = (int)($_GET['supplier'] ?? 0);
$presetProduct  = (int)($_GET['product'] ?? 0);

$old = [
    'supplier_id'   => $presetSupplier,
    'order_date'    => date('Y-m-d'),
    'expected_date' => date('Y-m-d', strtotime('+7 days')),
    'tax'           => '0.00',
    'discount'      => '0.00',
    'notes'         => '',
];
$lines = [];

/* ---------- Seed a line from ?product= ---------- */
if ($presetProduct > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $seed = invGetProduct($presetProduct);
    if ($seed) {
        $lines[] = [
            'product_id' => (int)$seed['inventory_id'],
            'item_name'  => $seed['item_name'],
            'quantity'   => invSuggestedOrderQty((int)$seed['quantity'], (int)$seed['minimum_stock'], (int)$seed['maximum_stock']),
            'unit_cost'  => number_format(invProductCost($seed), 2, '.', ''),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['supplier_id']   = (int)($_POST['supplier_id'] ?? 0);
        $old['order_date']    = trim($_POST['order_date'] ?? '');
        $old['expected_date'] = trim($_POST['expected_date'] ?? '');
        $old['tax']           = (float)($_POST['tax'] ?? 0);
        $old['discount']      = (float)($_POST['discount'] ?? 0);
        $old['notes']         = trim($_POST['notes'] ?? '');
        $submitAction         = ($_POST['submit_action'] ?? 'draft') === 'order' ? 'ordered' : 'draft';

        $supplier = invGetSupplier($old['supplier_id']);
        if (!$supplier || $supplier['status'] !== 'active') {
            $errors[] = 'Please choose an active supplier.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['order_date'])) {
            $errors[] = 'Please choose a valid order date.';
        }
        if ($old['expected_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['expected_date'])) {
            $errors[] = 'Please choose a valid expected date.';
        }
        if ($old['tax'] < 0 || $old['discount'] < 0) {
            $errors[] = 'Tax and discount cannot be negative.';
        }

        $rawIds   = $_POST['product_id'] ?? [];
        $rawQty   = $_POST['quantity'] ?? [];
        $rawCost  = $_POST['unit_cost'] ?? [];
        $lineCount = max(count($rawIds), count($rawQty), count($rawCost));
        $seenProducts = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $pid  = (int)($rawIds[$i] ?? 0);
            $qty  = (int)($rawQty[$i] ?? 0);
            $cost = (float)($rawCost[$i] ?? 0);

            // Skip entirely blank rows.
            if ($pid <= 0 && $qty <= 0) {
                continue;
            }
            if ($pid <= 0) {
                $errors[] = 'Line ' . ($i + 1) . ': please select a product.';
                continue;
            }
            if ($qty <= 0) {
                $errors[] = 'Line ' . ($i + 1) . ': quantity must be at least 1.';
                continue;
            }
            if ($cost < 0) {
                $errors[] = 'Line ' . ($i + 1) . ': unit cost cannot be negative.';
                continue;
            }
            $prod = invGetProduct($pid);
            if (!$prod) {
                $errors[] = 'Line ' . ($i + 1) . ': product not found.';
                continue;
            }
            if (isset($seenProducts[$pid])) {
                $errors[] = 'Line ' . ($i + 1) . ': duplicate product rows are not allowed.';
                continue;
            }
            $seenProducts[$pid] = true;
            $lines[] = [
                'product_id' => $pid,
                'item_name'  => $prod['item_name'],
                'quantity'   => $qty,
                'unit_cost'  => number_format($cost, 2, '.', ''),
            ];
        }

        if (empty($lines)) {
            $errors[] = 'Add at least one product line before saving.';
        }
        if (mb_strlen($old['notes']) > 1000) {
            $errors[] = 'Notes must be 1000 characters or less.';
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();
                $poNumber = invGeneratePONumber($db);

                $stmt = $db->prepare("
                    INSERT INTO purchase_orders
                        (po_number, supplier_id, order_date, expected_date, subtotal, tax, discount, grand_total, status, notes, created_by)
                    VALUES
                        (:po, :sup, :od, :ed, 0, :tax, :disc, 0, :status, :notes, :by)
                ");
                $stmt->execute([
                    ':po'     => $poNumber,
                    ':sup'    => $old['supplier_id'],
                    ':od'     => $old['order_date'],
                    ':ed'     => $old['expected_date'] !== '' ? $old['expected_date'] : null,
                    ':tax'    => $old['tax'],
                    ':disc'   => $old['discount'],
                    ':status' => $submitAction,
                    ':notes'  => $old['notes'] !== '' ? $old['notes'] : null,
                    ':by'     => currentUserId(),
                ]);
                $poId = (int)$db->lastInsertId();

                $itemStmt = $db->prepare("
                    INSERT INTO purchase_order_items (purchase_order_id, product_id, ordered_quantity, received_quantity, unit_cost, total)
                    VALUES (:po, :pid, :qty, 0, :cost, :total)
                ");
                foreach ($lines as $line) {
                    $total = (float)$line['quantity'] * (float)$line['unit_cost'];
                    $itemStmt->execute([
                        ':po'    => $poId,
                        ':pid'   => $line['product_id'],
                        ':qty'   => $line['quantity'],
                        ':cost'  => (float)$line['unit_cost'],
                        ':total' => $total,
                    ]);
                }

                invRecalcPOTotals($db, $poId);
                $db->commit();

                setFlash('success', 'Purchase order ' . $poNumber . ' ' . ($submitAction === 'ordered' ? 'placed' : 'saved as draft') . ' successfully.');
                redirect('admin/inventory/purchase-order-view.php?id=' . $poId);
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Could not save the purchase order: ' . $e->getMessage();
            }
        }
    }
}

/* ---------- Ensure at least one editable row ---------- */
if (empty($lines)) {
    $lines[] = ['product_id' => 0, 'item_name' => '', 'quantity' => 1, 'unit_cost' => '0.00'];
}

$suppliers = [];
try {
    $suppliers = $db->query("SELECT supplier_id, COALESCE(supplier_name, company_name) AS name FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-file-signature"></i> New Purchase Order</h1>
        <p>Create an order and send it to a supplier.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-orders.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Purchase Orders</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<form method="post" action="" id="po-form">
    <?php echo csrfField(); ?>
    <input type="hidden" name="submit_action" id="po-submit-action" value="draft">

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="admin-card">
                <div class="card-header-custom"><h5><i class="fas fa-truck"></i> Order Details</h5></div>
                <div class="card-body-custom">
                    <div class="form-group">
                        <label for="supplier_id">Supplier <span class="req">*</span></label>
                        <select id="supplier_id" name="supplier_id" class="form-control-admin" required>
                            <option value="0">— Select supplier —</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo (int)$sup['supplier_id']; ?>" <?php echo $old['supplier_id'] === (int)$sup['supplier_id'] ? 'selected' : ''; ?>><?php echo sanitize($sup['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text-admin"><a href="<?php echo SITE_URL; ?>/admin/inventory/supplier-add.php" style="color:var(--accent-bright);">Add a new supplier</a></div>
                    </div>
                    <div class="form-group">
                        <label for="order_date">Order Date <span class="req">*</span></label>
                        <input type="date" id="order_date" name="order_date" class="form-control-admin" value="<?php echo sanitize($old['order_date']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="expected_date">Expected Delivery</label>
                        <input type="date" id="expected_date" name="expected_date" class="form-control-admin" value="<?php echo sanitize($old['expected_date']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control-admin" rows="3" maxlength="1000"><?php echo sanitize($old['notes']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="admin-card">
                <div class="card-header-custom">
                    <h5><i class="fas fa-boxes-stacked"></i> Order Items</h5>
                    <button type="button" class="btn-admin btn-outline btn-xs" data-add-po-line><i class="fas fa-plus"></i> Add Line</button>
                </div>
                <div class="table-scroll">
                    <table class="admin-table po-items-table" id="poItemsTable">
                        <thead>
                            <tr>
                                <th style="min-width:260px;">Product</th>
                                <th style="width:110px;">Qty</th>
                                <th style="width:130px;">Unit Cost ($)</th>
                                <th style="width:120px;">Total</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lines as $line): ?>
                            <?php echo invRenderPoBuilderRow($line); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-body-custom">
                    <div class="po-totals">
                        <div class="po-totals-row"><span>Subtotal</span><strong class="po-subtotal">$0.00</strong></div>
                        <div class="po-totals-row">
                            <span>Tax ($)</span>
                            <input type="number" name="tax" class="form-control-admin form-control-sm" style="max-width:130px;" value="<?php echo number_format((float)$old['tax'], 2, '.', ''); ?>" min="0" step="0.01">
                        </div>
                        <div class="po-totals-row">
                            <span>Discount ($)</span>
                            <input type="number" name="discount" class="form-control-admin form-control-sm" style="max-width:130px;" value="<?php echo number_format((float)$old['discount'], 2, '.', ''); ?>" min="0" step="0.01">
                        </div>
                        <div class="po-totals-row po-grand"><span>Grand Total</span><strong class="po-grand-total">$0.00</strong></div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 flex-wrap">
                <button type="button" class="btn-admin btn-outline" onclick="document.getElementById('po-submit-action').value='draft'; document.getElementById('po-form').submit();">
                    <i class="fas fa-floppy-disk"></i> Save as Draft
                </button>
                <button type="button" class="btn-admin btn-cyan" onclick="document.getElementById('po-submit-action').value='order'; document.getElementById('po-form').submit();">
                    <i class="fas fa-paper-plane"></i> Place Order
                </button>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-orders.php" class="btn-admin btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>