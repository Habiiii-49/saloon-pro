<?php
/**
 * AJAX - Invoice lookup (with balances) for payment forms (POST).
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../../includes/finance.php';

$db = getDBConnection();

$q = trim((string)($_POST['q'] ?? ''));
$q = mb_substr($q, 0, 60);

$filterStatus = trim((string)($_POST['payment_status'] ?? ''));
$filterStatus = preg_replace('/[^a-z_]/', '', $filterStatus);
$clientId     = (int)($_POST['client_id'] ?? 0);

$sql = "
    SELECT i.invoice_id, i.invoice_number, i.total, i.paid_amount, i.payment_status,
           i.status AS lifecycle_status,
           CONCAT(c.first_name, ' ', c.last_name) AS client_name,
           c.client_id
    FROM invoices i
    JOIN clients c ON c.client_id = i.client_id
    WHERE i.status <> 'cancelled'
";
$params = [];
if ($clientId > 0) {
    $sql .= " AND i.client_id = :cid";
    $params[':cid'] = $clientId;
}
if ($filterStatus !== '') {
    $sql .= " AND i.payment_status = :ps";
    $params[':ps'] = $filterStatus;
}
if ($q !== '') {
    $sql .= " AND (i.invoice_number LIKE :q1
                  OR CONCAT(c.first_name, ' ', c.last_name) LIKE :q2)";
    $params[':q1'] = "%{$q}%";
    $params[':q2'] = "%{$q}%";
}
$sql .= " ORDER BY i.invoice_id DESC LIMIT 20";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$out = [];
foreach ($rows as $r) {
    $balance = round(max(0.0, (float)$r['total'] - (float)$r['paid_amount']), 2);
    $out[] = $r + ['balance' => $balance];
}

finJson(['ok' => true, 'invoices' => $out]);