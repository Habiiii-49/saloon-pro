<?php
/**
 * Admin – Edit Purchase Order
 * Edit a draft / pending / ordered purchase order and replace its line items.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Edit Purchase Order';
$activeMenu      = 'inventory';
$inventoryActive = 'purchase-order-edit';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

$poId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$po   = invGetPurchaseOrder($poId);

if (!$po) {
    setFlash('error', 'Purchase order not found.');
    redirect('admin/inventory/purchase-orders.php');
}

$items = invGetPOItems($poId);
$hasReceipts = false;
foreach ($items as $it) {
    if ((int)$it['received_quantity'] > 0) {
        $hasReceipts = true;
        break;
    }
}

if (!invPOEditable($po['status']) || $hasReceipts) {
    setFlash('error', 'This purchase order can no longer be edited because it has been ordered/received. Create a new order instead.');
    redirect('admin/inventory/purchase-order-view.php?id=' . $poId);
}

$old = [
    'supplier_id'   => (int)$po['supplier_id'],
    'order_date'    => $po['order_date'],
    'expected_date' => $po['expected_date'] ?? '',
    'tax'           => number_format((float)$po['tax'], 2, '.', ''),
    'discount'      => number_format((float)$po['discount'], 2, '.', ''),
    'notes'         => (string)$po['notes'],
];

/* ---------- Build line list ---------- */
$lines = [];
foreach ($items as $it) {
    $lines[] = [
        'product_id' => (int)$it['product_id'],
        'item_name'  => $it['item_name'],
        'quantity'   => (int)$it['ordered_quantity'],
        'unit_cost'  => number_format((float)$it['unit_cost'], 2, '.', ''),
    ];
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
        $submitAction         = ($_POST['submit_action'] ?? 'keep') === 'order' ? 'ordered' : $po['status'];

        $lines = [];
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

        $rawIds  = $_POST['product_id'] ?? [];
        $rawQty  = $_POST['quantity'] ?? [];
        $rawCost = $_POST['unit_cost'] ?? [];
        $lineCount = max(count($rawIds), count($rawQty), count($rawCost));
        $seen = [];

        for ($i = 0; $i < $lineCount; $i++) {
            $pid  = (int)($rawIds[$i] ?? 0);
            $qty  = (int)($rawQty[$i] ?? 0);
            $cost = (float)($rawCost[$i] ?? 0);

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
            if (isset($seen[$pid])) {
                $errors[] = 'Line ' . ($i + 1) . ': duplicate product rows are not allowed.';
                continue;
            }
            $seen[$pid] = true;
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

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $status = $submitAction;
                // Keep valid status values only.
                if (!array_key_exists($status, invPOStatuses())) {
                    $status = $po['status'];
                }

                $stmt = $db->prepare("
                    UPDATE purchase_orders SET
                        supplier_id = :sup, order_date = :od, expected_date = :ed,
                        tax = :tax, discount = :disc, notes = :notes, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':sup'    => $old['supplier_id'],
                    ':od'     => $old['order_date'],
                    ':ed'     => $old['expected_date'] !== '' ? $old['expected_date'] : null,
                    ':tax'    => $old['tax'],
                    ':disc'   => $old['discount'],
                    ':notes'  => $old['notes'] !== '' ? $old['notes'] : null,
                    ':status' => $status,
                    ':id'     => $poId,
                ]);

                $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = :id")->execute([':id' => $poId]);

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

                setFlash('success', 'Purchase order ' . $po['po_number'] . ' updated successfully.');
                redirect('admin/inventory/purchase-order-view.php?id=' . $poId);
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Could not update the purchase order: ' . $e->getMessage();
            }
        }
    }
}

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
        <h1><i class="fas fa-pen-to-square"></i> Edit <?php echo sanitize($po['po_number']); ?></h1>
        <p>Adjust the supplier, dates, items or totals.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-view.php?id=<?php echo $poId; ?>" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Purchase Order</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<form method="post" action="" id="po-form">
    <?php echo csrfField(); ?>
    <input type="hidden" name="id" value="<?php echo $poId; ?>">
    <input type="hidden" name="submit_action" id="po-submit-action" value="keep">

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
                <button type="button" class="btn-admin btn-cyan" onclick="document.getElementById('po-submit-action').value='keep'; document.getElementById('po-form').submit();">
                    <i class="fas fa-check"></i> Save Changes
                </button>
                <?php if (in_array($po['status'], ['draft', 'pending'], true)): ?>
                <button type="button" class="btn-admin btn-outline" onclick="document.getElementById('po-submit-action').value='order'; document.getElementById('po-form').submit();">
                    <i class="fas fa-paper-plane"></i> Save &amp; Place Order
                </button>
                <?php endif; ?>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-orders.php" class="btn-admin btn-outline">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>