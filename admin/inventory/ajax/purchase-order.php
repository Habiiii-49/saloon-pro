<?php
/**
 * Inventory AJAX – Purchase order actions.
 * Supported actions: mark_ordered | cancel | delete | receive
 */
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();

$action = $_POST['action'] ?? '';
$poId   = (int)($_POST['po_id'] ?? ($_POST['id'] ?? 0));

if ($poId <= 0) {
    invJson(['ok' => false, 'error' => 'Purchase order ID is required.']);
}

$po = invGetPurchaseOrder($poId);
if (!$po) {
    invJson(['ok' => false, 'error' => 'Purchase order not found.'], 404);
}

try {
    switch ($action) {

        case 'mark_ordered':
            if (!in_array($po['status'], ['draft', 'pending'], true)) {
                invJson(['ok' => false, 'error' => 'Only draft/pending purchase orders can be marked as ordered.']);
            }
            $db->prepare("UPDATE purchase_orders SET status = 'ordered' WHERE id = :id")->execute([':id' => $poId]);
            invJson(['ok' => true, 'message' => $po['po_number'] . ' marked as ordered.']);

        case 'cancel':
            if (!invPOEditable($po['status'])) {
                invJson(['ok' => false, 'error' => 'This purchase order can no longer be cancelled.']);
            }
            $db->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = :id")->execute([':id' => $poId]);
            invJson(['ok' => true, 'message' => $po['po_number'] . ' cancelled.']);

        case 'delete':
            if ($po['status'] !== 'draft') {
                invJson(['ok' => false, 'error' => 'Only draft purchase orders can be deleted.']);
            }
            $db->prepare("DELETE FROM purchase_order_items WHERE purchase_order_id = :id")->execute([':id' => $poId]);
            $db->prepare("DELETE FROM purchase_orders WHERE id = :id")->execute([':id' => $poId]);
            invJson(['ok' => true, 'message' => $po['po_number'] . ' deleted.']);

        case 'receive':
            if (!in_array($po['status'], ['ordered', 'partially_received'], true)) {
                invJson(['ok' => false, 'error' => 'Only ordered / partially received purchase orders can receive stock.']);
            }
            $items     = invGetPOItems($poId);
            $rawQty    = $_POST['receive_qty'] ?? [];
            $receiving = [];

            foreach ($items as $it) {
                $itemId    = (int)$it['id'];
                $remaining = (int)$it['ordered_quantity'] - (int)$it['received_quantity'];
                $qty       = (int)($rawQty[$itemId] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                if ($qty > $remaining) {
                    invJson(['ok' => false, 'error' => $it['item_name'] . ': only ' . $remaining . ' unit(s) remain to receive.']);
                }
                $receiving[] = [
                    'item_id'    => $itemId,
                    'product_id' => (int)$it['product_id'],
                    'qty'        => $qty,
                    'unit_cost'  => (float)$it['unit_cost'],
                    'name'       => $it['item_name'],
                ];
            }

            if (empty($receiving)) {
                invJson(['ok' => false, 'error' => 'Enter at least one quantity to receive.']);
            }

            $db->beginTransaction();
            foreach ($receiving as $line) {
                $upd = $db->prepare("
                    UPDATE purchase_order_items SET received_quantity = received_quantity + :qty
                    WHERE id = :id AND received_quantity + :qty2 <= ordered_quantity
                ");
                $upd->execute([':qty' => $line['qty'], ':qty2' => $line['qty'], ':id' => $line['item_id']]);
                if ($upd->rowCount() !== 1) {
                    throw new Exception($line['name'] . ' may already be fully received.');
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
            if ($newStatus === 'received') {
                invNotify($db, null, 'purchase_received', 'Purchase order ' . $po['po_number'] . ' has been fully received.', true);
            }
            $db->commit();

            invJson(['ok' => true, 'message' => 'Stock received for ' . $po['po_number'] . '.', 'status' => $newStatus]);

        default:
            invJson(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    invJson(['ok' => false, 'error' => $e->getMessage()], 400);
}