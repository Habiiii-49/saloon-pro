<?php
/**
 * Inventory AJAX – Supplier search.
 */
require_once __DIR__ . '/_guard.php';

$db = getDBConnection();

$q = trim((string)($_POST['q'] ?? ''));
if (mb_strlen($q) < 1) {
    invJson(['ok' => true, 'data' => []]);
}
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$stmt = $db->prepare("
    SELECT supplier_id, COALESCE(supplier_name, company_name) AS name, contact_person, phone, email, city
    FROM suppliers
    WHERE status = 'active'
      AND (COALESCE(supplier_name, company_name) LIKE :q1 OR contact_person LIKE :q2 OR email LIKE :q3 OR city LIKE :q4)
    ORDER BY name ASC
    LIMIT 20
");
$stmt->execute([
    ':q1' => '%' . $q . '%',
    ':q2' => '%' . $q . '%',
    ':q3' => '%' . $q . '%',
    ':q4' => '%' . $q . '%',
]);

invJson(['ok' => true, 'data' => $stmt->fetchAll()]);