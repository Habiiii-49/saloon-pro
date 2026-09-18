<?php
/**
 * Inventory AJAX – Product search / single lookup.
 * Used by the purchase order builder.
 */
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();

/* Single product lookup (for prefilled builder rows). */
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $db->prepare("
        SELECT inventory_id, item_name, sku, quantity, unit, cost_price, minimum_stock, maximum_stock
        FROM inventory WHERE inventory_id = :id AND status = 'active' LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
    if (!$product) {
        invJson(['ok' => false, 'error' => 'Product not found.']);
    }
    invJson(['ok' => true, 'data' => $product]);
}

/* Free-text search. */
$q = trim((string)($_POST['q'] ?? ''));
if (mb_strlen($q) < 1) {
    invJson(['ok' => true, 'data' => []]);
}
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$stmt = $db->prepare("
    SELECT inventory_id, item_name, sku, quantity, unit, cost_price, minimum_stock, maximum_stock
    FROM inventory
    WHERE status = 'active'
      AND (item_name LIKE :q1 OR sku LIKE :q2 OR barcode LIKE :q3)
    ORDER BY item_name ASC
    LIMIT 20
");
$stmt->execute([':q1' => '%' . $q . '%', ':q2' => '%' . $q . '%', ':q3' => '%' . $q . '%']);
$products = $stmt->fetchAll();

invJson(['ok' => true, 'data' => $products]);