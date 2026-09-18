<?php
/**
 * ELEGANCE SALON - INVENTORY MODULE HELPERS (PART 5)
 * Shared functions used by all admin inventory pages.
 * Requires the core functions.php (auth / session / CSRF) to be loaded first.
 */

if (!function_exists('requireRole')) {
    require_once __DIR__ . '/../../includes/functions.php';
}

/* ------------------------------------------------------------------ */
/* Dictionaries                                                       */
/* ------------------------------------------------------------------ */

function invUnits(): array
{
    return ['Piece', 'Bottle', 'Box', 'Pack', 'Tube', 'Jar', 'Set', 'Other'];
}

function invTransactionTypes(): array
{
    return [
        'stock_in'          => 'Stock In',
        'stock_out'         => 'Stock Out',
        'adjustment'        => 'Adjustment',
        'purchase_received' => 'Purchase Received',
        'purchase_return'   => 'Purchase Return',
        'manual_correction' => 'Manual Correction',
    ];
}

function invPOStatuses(): array
{
    return [
        'draft'              => 'Draft',
        'pending'            => 'Pending',
        'ordered'            => 'Ordered',
        'partially_received' => 'Partially Received',
        'received'           => 'Received',
        'cancelled'          => 'Cancelled',
    ];
}

/** Editable statuses: a purchase order in one of these may be edited/cancelled. */
function invEditablePOStatuses(): array
{
    return ['draft', 'pending', 'ordered'];
}

function invTxnTypeBadge(string $type): string
{
    switch ($type) {
        case 'stock_in':          return 'status-badge completed';
        case 'stock_out':         return 'status-badge cancelled';
        case 'adjustment':        return 'status-badge pending';
        case 'purchase_received': return 'status-badge confirmed';
        case 'purchase_return':   return 'status-badge danger';
        default:                  return 'status-badge inactive';
    }
}

/** Returns ['label' => ..., 'class' => ...] for a stock level. */
function invStockLevel(int $quantity, int $minimumStock): array
{
    if ($quantity <= 0) {
        return ['label' => 'Out of Stock', 'class' => 'status-badge danger'];
    }
    if ($quantity <= $minimumStock) {
        return ['label' => 'Low Stock', 'class' => 'status-badge pending'];
    }
    return ['label' => 'In Stock', 'class' => 'status-badge active'];
}

function invSuggestedOrderQty(int $current, int $minimum, int $maximum): int
{
    if ($maximum > 0) {
        return (int)max($maximum - $current, 0);
    }
    // Fallback when no maximum is configured: bring to twice the minimum.
    return (int)max(($minimum * 2) - $current, 1);
}

/* ------------------------------------------------------------------ */
/* Simple lookups                                                     */
/* ------------------------------------------------------------------ */

function invGetCategory(int $id): ?array
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM inventory_categories WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function invCategories(bool $activeOnly = false): array
{
    $db = getDBConnection();
    $sql = "SELECT * FROM inventory_categories";
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= " ORDER BY name ASC";
    return $db->query($sql)->fetchAll();
}

function invGetSupplier(int $id): ?array
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE supplier_id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function invSuppliers(bool $activeOnly = true): array
{
    $db = getDBConnection();
    $sql = "SELECT * FROM suppliers";
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= " ORDER BY COALESCE(supplier_name, company_name) ASC";
    return $db->query($sql)->fetchAll();
}

function invSupplierDisplayName(array $supplier): string
{
    return trim($supplier['supplier_name'] ?? '') !== ''
        ? $supplier['supplier_name']
        : $supplier['company_name'];
}

function invGetProduct(int $id): ?array
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM inventory WHERE inventory_id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function invProductCost(array $product): float
{
    return (float)($product['cost_price'] ?? $product['unit_price'] ?? 0);
}

/** Inventory value for a product row (current stock x cost price). */
function invProductValue(array $product): float
{
    return (float)$product['quantity'] * invProductCost($product);
}

// Resolve the legacy string category for new products so older views still work.
function invResolveCategoryName(?int $categoryId): ?string
{
    if (!$categoryId) {
        return null;
    }
    $cat = invGetCategory($categoryId);
    return $cat ? $cat['name'] : null;
}

/* ------------------------------------------------------------------ */
/* Notification helpers                                               */
/* ------------------------------------------------------------------ */

/**
 * Insert a row into inventory_notifications and optionally broadcast to the
 * existing notifications table. Endpoint is idempotent through the
 * inventory_notifications table, so alerts are never duplicated per refresh.
 */
function invNotify(PDO $db, ?int $productId, string $type, string $message, bool $broadcast = true): void
{
    $allowed = ['low_stock', 'out_of_stock', 'purchase_received', 'purchase_partial', 'other'];
    if (!in_array($type, $allowed, true)) {
        $type = 'other';
    }
    $stmt = $db->prepare(
        "INSERT INTO inventory_notifications (product_id, type, message, notified) VALUES (:p, :t, :m, :n)"
    );
    $stmt->execute([':p' => $productId, ':t' => $type, ':m' => $message, ':n' => $broadcast ? 1 : 0]);
    if ($broadcast) {
        createNotification(null, 'Inventory Alert', $message, $type === 'out_of_stock' ? 'danger' : 'warning');
    }
}

/**
 * Scan products and create/upgrade low-stock & out-of-stock alerts.
 * Resolves (marks read) alerts that are no longer applicable.
 * Only call this after stock-changing operations â€” never on page render.
 */
function invSyncLowStockAlerts(PDO $db, ?int $productId = null): void
{
    $sql = "SELECT inventory_id, item_name, quantity, minimum_stock FROM inventory WHERE status = 'active'";
    $params = [];
    if ($productId !== null) {
        $sql .= " AND inventory_id = :pid";
        $params[':pid'] = $productId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    foreach ($products as $p) {
        $pid = (int)$p['inventory_id'];
        $quantity = (int)$p['quantity'];
        $minimum = (int)$p['minimum_stock'];

        if ($quantity > $minimum) {
            // Healthy â€” resolve any outstanding alerts for this product.
            $db->prepare(
                "UPDATE inventory_notifications SET is_read = 1
                 WHERE product_id = :pid AND type IN ('low_stock','out_of_stock') AND is_read = 0"
            )->execute([':pid' => $pid]);
            continue;
        }

        $type = $quantity <= 0 ? 'out_of_stock' : 'low_stock';
        $message = $quantity <= 0
            ? 'Out of Stock: ' . $p['item_name'] . ' has no stock remaining.'
            : 'Low Stock Alert: ' . $p['item_name'] . ' has only ' . $quantity . ' units remaining.';

        // Existing unread alert for the same product/type? skip.
        $chk = $db->prepare(
            "SELECT id FROM inventory_notifications WHERE product_id = :pid AND type = :t AND is_read = 0 LIMIT 1"
        );
        $chk->execute([':pid' => $pid, ':t' => $type]);
        if ($chk->fetch()) {
            continue;
        }

        // If stock dropped from low to out of stock, upgrade the pending alert instead of duplicating.
        if ($type === 'out_of_stock') {
            $chk = $db->prepare(
                "SELECT id, notified FROM inventory_notifications WHERE product_id = :pid AND type = 'low_stock' AND is_read = 0 LIMIT 1"
            );
            $chk->execute([':pid' => $pid]);
            $existing = $chk->fetch();
            if ($existing) {
                $db->prepare(
                    "UPDATE inventory_notifications SET type = 'out_of_stock', message = :m WHERE id = :id"
                )->execute([':m' => $message, ':id' => $existing['id']]);
                continue;
            }
        }

        invNotify($db, $pid, $type, $message, true);
    }
}

/* ------------------------------------------------------------------ */
/* Core stock engine (transaction safe)                               */
/* ------------------------------------------------------------------ */

/**
 * Central stock change engine.
 *
 * For STOCK_IN  / PURCHASE_RECEIVED:   $new = old + $quantity (positive)
 * For STOCK_OUT / PURCHASE_RETURN:     $new = old - $quantity (positive, never negative)
 * For ADJUSTMENT / MANUAL_CORRECTION:  $new = $absolute target quantity
 *
 * Returns ['ok' => bool, 'message' => string, 'product' => ?array, 'new_stock' => ?int]
 */
function invApplyStockChange(
    PDO $db,
    int $productId,
    int $userId,
    string $type,
    int $quantity,
    ?int $absoluteTarget = null,
    ?int $supplierId = null,
    ?int $purchaseOrderId = null,
    float $unitCost = 0.0,
    ?string $reference = null,
    ?string $reason = null,
    ?string $notes = null
): array {
    $allowedTypes = ['stock_in', 'stock_out', 'adjustment', 'purchase_received', 'purchase_return', 'manual_correction'];
    if (!in_array($type, $allowedTypes, true)) {
        return ['ok' => false, 'message' => 'Invalid transaction type.', 'product' => null, 'new_stock' => null];
    }

    // Nesting support: join an outer transaction instead of opening a new one.
    $outer = $db->inTransaction();
    if (!$outer) {
        $db->beginTransaction();
    }
    $earlyExit = function (array $result) use ($db, $outer) {
        if (!$outer && $db->inTransaction()) {
            $db->rollBack();
        }
        return $result;
    };
    try {
        $stmt = $db->prepare(
            "SELECT inventory_id, item_name, quantity, minimum_stock, status FROM inventory WHERE inventory_id = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            return $earlyExit(['ok' => false, 'message' => 'Product not found.', 'product' => null, 'new_stock' => null]);
        }
        if ((int)$product['status'] && $product['status'] !== 'active') {
            // Inactive products can still receive adjustments but not primary stock moves.
        }

        $oldQty = (int)$product['quantity'];
        $newQty = $oldQty;

        switch ($type) {
            case 'stock_in':
            case 'purchase_received':
                if ($quantity <= 0) {
                    return $earlyExit(['ok' => false, 'message' => 'Quantity must be greater than zero.', 'product' => $product, 'new_stock' => $newQty]);
                }
                $newQty = $oldQty + $quantity;
                break;

            case 'stock_out':
            case 'purchase_return':
                if ($quantity <= 0) {
                    return $earlyExit(['ok' => false, 'message' => 'Quantity must be greater than zero.', 'product' => $product, 'new_stock' => $newQty]);
                }
                if ($quantity > $oldQty) {
                    return $earlyExit(['ok' => false, 'message' => 'Insufficient stock. Only ' . $oldQty . ' units available.', 'product' => $product, 'new_stock' => $newQty]);
                }
                $newQty = $oldQty - $quantity;
                break;

            case 'adjustment':
            case 'manual_correction':
                if ($absoluteTarget === null || $absoluteTarget < 0) {
                    return $earlyExit(['ok' => false, 'message' => 'Please enter a valid actual stock quantity.', 'product' => $product, 'new_stock' => $newQty]);
                }
                $newQty = $absoluteTarget;
                break;
        }

        if ($newQty < 0) {
            return $earlyExit(['ok' => false, 'message' => 'Stock can never go below zero.', 'product' => $product, 'new_stock' => $oldQty]);
        }

        $db->prepare("UPDATE inventory SET quantity = :q WHERE inventory_id = :id")
            ->execute([':q' => $newQty, ':id' => $productId]);

        $db->prepare(
            "INSERT INTO inventory_transactions
                (product_id, supplier_id, purchase_order_id, user_id, transaction_type,
                 quantity, previous_stock, new_stock, unit_cost, reference, reason, notes)
             VALUES
                (:pid, :sid, :poid, :uid, :type,
                 :qty, :prev, :new, :cost, :ref, :reason, :notes)"
        )->execute([
            ':pid'    => $productId,
            ':sid'    => $supplierId,
            ':poid'   => $purchaseOrderId,
            ':uid'    => $userId,
            ':type'   => $type,
            ':qty'    => $newQty - $oldQty,
            ':prev'   => $oldQty,
            ':new'    => $newQty,
            ':cost'   => $unitCost,
            ':ref'    => $reference,
            ':reason' => $reason,
            ':notes'  => $notes,
        ]);

        if (!$outer) {
            $db->commit();
        }
    } catch (PDOException $e) {
        // Only the outer scope rolls back the whole transaction.
        if (!$outer && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Inventory stock change failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Stock could not be updated. Please try again.', 'product' => $product ?? null, 'new_stock' => null];
    }

    try {
        invSyncLowStockAlerts($db, $productId);
    } catch (PDOException $e) {
        error_log('Low stock alert sync failed: ' . $e->getMessage());
    }

    return ['ok' => true, 'message' => 'Stock updated successfully.', 'product' => $product, 'new_stock' => $newQty];
}

/* ------------------------------------------------------------------ */
/* Purchase order builder row (shared by add + edit pages)            */
/* ------------------------------------------------------------------ */

function invRenderPoBuilderRow(array $line): string
{
    $pid = (int)($line['product_id'] ?? 0);
    $name = $line['item_name'] ?? '';
    $qty = (int)($line['quantity'] ?? 1);
    $cost = $line['unit_cost'] ?? '0.00';

    $html  = '<tr class="po-item-row">';
    $html .= '<td class="po-item-name">';
    $html .= '<input type="text" class="form-control-admin po-product-search" placeholder="Type to search product..." autocomplete="off" value="' . sanitize($name) . '">';
    $html .= '<input type="hidden" name="product_id[]" class="po-product-id" value="' . ($pid > 0 ? $pid : '') . '">';
    $html .= '<div class="po-search-results admin-card" style="display:none;"></div>';
    $html .= '</td>';
    $html .= '<td><input type="number" class="form-control-admin po-qty" name="quantity[]" min="1" value="' . ($qty > 0 ? $qty : 1) . '"></td>';
    $html .= '<td><input type="number" class="form-control-admin po-cost" name="unit_cost[]" min="0" step="0.01" value="' . sanitize((string)$cost) . '"></td>';
    $html .= '<td class="inv-value po-line-total">$0.00</td>';
    $html .= '<td><button type="button" class="po-remove-row" title="Remove line"><i class="fas fa-xmark"></i></button></td>';
    $html .= '</tr>';
    return $html;
}

/* ------------------------------------------------------------------ */
/* Purchase order helpers                                             */
/* ------------------------------------------------------------------ */

function invGeneratePONumber(PDO $db): string
{
    $prefix = 'PO-' . date('Y') . '-';
    $stmt = $db->prepare("SELECT po_number FROM purchase_orders WHERE po_number LIKE :p ORDER BY id DESC LIMIT 1");
    $stmt->execute([':p' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last) {
        $lastSeq = (int)substr((string)$last, strlen($prefix));
        $seq = $lastSeq + 1;
    }
    return $prefix . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}

function invGetPurchaseOrder(int $id): ?array
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function invGetPOItems(int $purchaseOrderId): array
{
    $db = getDBConnection();
    $stmt = $db->prepare(
        "SELECT poi.*, i.item_name, i.sku, i.unit, i.quantity AS current_stock
         FROM purchase_order_items poi
         JOIN inventory i ON i.inventory_id = poi.product_id
         WHERE poi.purchase_order_id = :id
         ORDER BY poi.id ASC"
    );
    $stmt->execute([':id' => $purchaseOrderId]);
    return $stmt->fetchAll();
}

/** Whether a purchase order can still be edited / cancelled. */
function invPOEditable(string $status): bool
{
    return in_array($status, invEditablePOStatuses(), true);
}

/** Recompute + persist the grand total of a purchase order. */
function invRecalcPOTotals(PDO $db, int $purchaseOrderId): array
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) AS subtotal FROM purchase_order_items WHERE purchase_order_id = :id");
    $stmt->execute([':id' => $purchaseOrderId]);
    $subtotal = (float)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT tax, discount FROM purchase_orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $purchaseOrderId]);
    $po = $stmt->fetch();
    $tax = (float)($po['tax'] ?? 0);
    $discount = (float)($po['discount'] ?? 0);
    $grand = $subtotal + $tax - $discount;
    if ($grand < 0) {
        $grand = 0;
    }

    $db->prepare("UPDATE purchase_orders SET subtotal = :s, grand_total = :g WHERE id = :id")
        ->execute([':s' => $subtotal, ':g' => $grand, ':id' => $purchaseOrderId]);

    return ['subtotal' => $subtotal, 'tax' => $tax, 'discount' => $discount, 'grand_total' => $grand];
}

/** Status computation for a PO based on received quantities. */
function invComputePOStatus(array $items): string
{
    if (empty($items)) {
        return 'draft';
    }
    $allFully = true;
    $anyReceived = false;
    foreach ($items as $item) {
        $ordered = (int)$item['ordered_quantity'];
        $received = (int)$item['received_quantity'];
        if ($received > 0) {
            $anyReceived = true;
        }
        if ($received < $ordered) {
            $allFully = false;
        }
    }
    if (!$anyReceived) {
        return 'ordered';
    }
    return $allFully ? 'received' : 'partially_received';
}

function invUpdatePOStatus(PDO $db, int $purchaseOrderId): string
{
    $items = invGetPOItems($purchaseOrderId);
    $status = invComputePOStatus($items);
    if ($status === 'ordered') {
        $status = 'ordered';
    }
    $db->prepare("UPDATE purchase_orders SET status = :s WHERE id = :id")
        ->execute([':s' => $status, ':id' => $purchaseOrderId]);
    return $status;
}