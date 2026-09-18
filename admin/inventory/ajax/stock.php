<?php
/**
 * Inventory AJAX – Fast stock in / stock out / adjustment.
 * Used by the quick-stock modal on product screens.
 */
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();

$action    = $_POST['action'] ?? '';
$productId = (int)($_POST['product_id'] ?? 0);
$quantity  = (int)($_POST['quantity'] ?? 0);
$targetQty = (int)($_POST['target_qty'] ?? 0);

if (!in_array($action, ['stock_in', 'stock_out', 'adjustment'], true)) {
    invJson(['ok' => false, 'error' => 'Invalid action.']);
}

$product = invGetProduct($productId);
if (!$product) {
    invJson(['ok' => false, 'error' => 'Product not found.']);
}
if ($product['status'] !== 'active') {
    invJson(['ok' => false, 'error' => 'This product is inactive. Activate it before changing stock.']);
}

$reason = trim((string)($_POST['reason'] ?? ''));
$reference = trim((string)($_POST['reference'] ?? ''));
$unitCost = (float)($_POST['unit_cost'] ?? ($product['cost_price'] ?? 0));

if ($reason === '' && in_array($action, ['stock_out', 'adjustment'], true)) {
    invJson(['ok' => false, 'error' => 'A reason is required for this action.']);
}

$result = invApplyStockChange(
    $db,
    $productId,
    currentUserId(),
    $action,
    $quantity,
    $action === 'adjustment' ? $targetQty : null,
    $product['supplier_id'] ? (int)$product['supplier_id'] : null,
    null,
    $unitCost > 0 ? $unitCost : invProductCost($product),
    $reference !== '' ? $reference : strtoupper($action . '-' . substr(md5(uniqid('', true)), 0, 6)),
    $reason !== '' ? $reason : ($action === 'stock_in' ? 'Stock in' : 'Stock adjustment'),
    'Quick ' . str_replace('_', ' ', $action)
);

if ($result['ok']) {
    invJson([
        'ok'        => true,
        'message'   => $result['message'],
        'new_stock' => $result['new_stock'],
        'quantity'  => $result['new_stock'],
    ]);
}
invJson(['ok' => false, 'error' => $result['message']], 400);