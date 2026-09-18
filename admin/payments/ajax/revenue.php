<?php
/**
 * AJAX - Revenue series for charts / reports (POST, admin).
 * group: day|month|method|service|stylist
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../../includes/finance.php';

$db    = getDBConnection();
$from  = trim((string)($_POST['from'] ?? ''));
$to    = trim((string)($_POST['to'] ?? ''));
$group = trim((string)($_POST['group'] ?? 'day'));

try {
    $totals = financeRevenueRange($db, $from, $to);
    $rows   = financeRevenueGroup($db, $from, $to, $group);
    finJson([
        'ok'     => true,
        'totals' => $totals,
        'labels' => array_column($rows, 'label'),
        'values' => array_map(fn($r) => round((float)$r['value'], 2), $rows),
    ]);
} catch (Throwable $e) {
    finJson(['ok' => false, 'error' => 'Could not load revenue data.'], 500);
}