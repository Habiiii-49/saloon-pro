<?php
/**
 * Admin – Receive Purchase Order
 * Receive some or all items on an ordered purchase order. Stock is only
 * increased through the stock engine and receiving is idempotent:
 * a line can never be received beyond its ordered quantity.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Receive Purchase Order';
$activeMenu      = 'inventory';
$inventoryActive = 'purchase-order-receive';
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
if (!in_array($po['status'], ['ordered', 'partially_received'], true)) {
    setFlash('error', 'Only ordered or partially received purchase orders can receive stock.');
    redirect('admin/inventory/purchase-order-view.php?id=' . $poId);
}

$items = invGetPOItems($poId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $rawQty = $_POST['receive_qty'] ?? [];
        $receivedLines = [];

        foreach ($items as $it) {
            $itemId   = (int)$it['id'];
            $ordered  = (int)$it['ordered_quantity'];
            $already  = (int)$it['received_quantity'];
            $remaining = $ordered - $already;
            $qty = (int)($rawQty[$itemId] ?? 0);

            if ($qty <= 0) {
                continue;
            }
            if ($qty > $remaining) {
                $errors[] = $it['item_name'] . ': only ' . $remaining . ' unit(s) remaining to receive.';
                continue;
            }
            $receivedLines[] = [
                'item_id'    => $itemId,
                'product_id' => (int)$it['product_id'],
                'qty'        => $qty,
                'unit_cost'  => (float)$it['unit_cost'],
                'name'       => $it['item_name'],
            ];
        }

        if (empty($errors) && empty($receivedLines)) {
            $errors[] = 'Enter at least one quantity to receive.';
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                foreach ($receivedLines as $line) {
                    // Atomic, idempotent guard: never exceed the ordered quantity.
                    $upd = $db->prepare("
                        UPDATE purchase_order_items
                        SET received_quantity = received_quantity + :qty
                        WHERE id = :id AND received_quantity + :qty2 <= ordered_quantity
                    ");
                    $upd->execute([
                        ':qty'  => $line['qty'],
                        ':qty2' => $line['qty'],
                        ':id'   => $line['item_id'],
                    ]);
                    if ($upd->rowCount() !== 1) {
                        throw new Exception('Could not receive ' . $line['name'] . ' — it may already be fully received.');
                    }

                    $result = invApplyStockChange(
                        $db,
                        $line['product_id'],
                        currentUserId(),
                        'purchase_received',
                        $line['qty'],
                        null,
                        $po['supplier_id'] ? (int)$po['supplier_id'] : null,
                        $poId,
                        $line['unit_cost'],
                        $po['po_number'],
                        'Received against ' . $po['po_number'],
                        'Purchase order receipt'
                    );
                    if (!$result['ok']) {
                        throw new Exception($result['message']);
                    }
                }

                $newStatus = invUpdatePOStatus($db, $poId);

                // Broadcast a single completion notification when fully received.
                if ($newStatus === 'received') {
                    invNotify($db, null, 'purchase_received', 'Purchase order ' . $po['po_number'] . ' has been fully received.', true);
                }

                $db->commit();

                setFlash('success', 'Stock received for ' . $po['po_number'] . '. Status: ' . str_replace('_', ' ', ucwords($newStatus)) . '.');
                redirect('admin/inventory/purchase-order-view.php?id=' . $poId);
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = $e->getMessage();
                // Refresh items so remaining quantities are accurate after any race.
                $items = invGetPOItems($poId);
            }
        }
    }
}

$supplier = $po['supplier_id'] ? invGetSupplier((int)$po['supplier_id']) : null;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-truck-ramp-box"></i> Receive <?php echo sanitize($po['po_number']); ?></h1>
        <p><?php echo $supplier ? sanitize(invSupplierDisplayName($supplier)) : 'No supplier'; ?></p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-view.php?id=<?php echo $poId; ?>" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Purchase Order</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<div class="alert-admin info"><i class="fas fa-circle-info"></i><div>Enter the quantity actually delivered for each line. Leave a line at 0 if it did not arrive. You can receive the rest later.</div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>

<form method="post" action="">
    <?php echo csrfField(); ?>
    <input type="hidden" name="id" value="<?php echo $poId; ?>">

    <div class="admin-card">
        <div class="card-header-custom">
            <h5><i class="fas fa-boxes-stacked"></i> Items to Receive</h5>
            <span class="status-badge pending"><?php echo count($items); ?> line(s)</span>
        </div>
        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Ordered</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Remaining</th>
                        <th class="text-end">Unit Cost</th>
                        <th style="width:160px;">Receive Now</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <?php
                    $ordered   = (int)$it['ordered_quantity'];
                    $already   = (int)$it['received_quantity'];
                    $remaining = max(0, $ordered - $already);
                    ?>
                    <tr>
                        <td>
                            <span class="cell-strong"><?php echo sanitize($it['item_name']); ?></span>
                            <div class="cell-sub">Current stock: <?php echo (int)$it['current_stock']; ?> <?php echo strtoupper(sanitize($it['unit'] ?? '')); ?></div>
                        </td>
                        <td class="text-end"><?php echo $ordered; ?></td>
                        <td class="text-end"><?php echo $already; ?></td>
                        <td class="text-end">
                            <span class="<?php echo $remaining > 0 ? 'status-badge pending' : 'status-badge completed'; ?>"><?php echo $remaining; ?></span>
                        </td>
                        <td class="text-end cell-sub">$<?php echo number_format((float)$it['unit_cost'], 2); ?></td>
                        <td>
                            <?php if ($remaining > 0): ?>
                            <input type="number" name="receive_qty[<?php echo (int)$it['id']; ?>]" class="form-control-admin" min="0" max="<?php echo $remaining; ?>" step="1" value="0" data-receive-input>
                            <?php else: ?>
                            <span class="status-badge completed"><i class="fas fa-check"></i> Fully received</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body-custom">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Receive Stock</button>
                <button type="button" class="btn-admin btn-outline" id="receive-all-btn"><i class="fas fa-fill-drip"></i> Receive All Remaining</button>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-orders.php" class="btn-admin btn-outline">Cancel</a>
                <span class="cell-sub ms-auto">Total receiving now: <strong id="receive-total">0</strong></span>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function recalc() {
        var total = 0;
        document.querySelectorAll('[data-receive-input]').forEach(function (inp) {
            total += parseInt(inp.value, 10) || 0;
        });
        var el = document.getElementById('receive-total');
        if (el) el.textContent = total;
    }
    document.querySelectorAll('[data-receive-input]').forEach(function (inp) {
        inp.addEventListener('input', recalc);
    });
    var allBtn = document.getElementById('receive-all-btn');
    if (allBtn) {
        allBtn.addEventListener('click', function () {
            document.querySelectorAll('[data-receive-input]').forEach(function (inp) {
                inp.value = inp.getAttribute('max') || 0;
            });
            recalc();
        });
    }
    recalc();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>