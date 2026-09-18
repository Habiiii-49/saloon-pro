<?php
/**
 * AJAX - Client lookup for invoice creation / payment forms (POST).
 */
require_once __DIR__ . '/_guard.php';

$db = getDBConnection();

$q = trim((string)($_POST['q'] ?? ''));
$q = mb_substr($q, 0, 60);

$rows = [];
try {
    if ($q === '') {
        $stmt = $db->prepare("
            SELECT client_id, CONCAT(first_name, ' ', last_name) AS name,
                   phone, email
            FROM clients
            ORDER BY client_id DESC
            LIMIT 20
        ");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("
            SELECT client_id, CONCAT(first_name, ' ', last_name) AS name,
                   phone, email
            FROM clients
            WHERE CONCAT(first_name, ' ', last_name) LIKE :q1
               OR phone LIKE :q2
               OR email LIKE :q3
            ORDER BY first_name ASC
            LIMIT 20
        ");
        $stmt->execute([':q1' => "%{$q}%", ':q2' => "%{$q}%", ':q3' => "%{$q}%"]);
    }
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    finJson(['ok' => false, 'error' => 'Could not search clients.'], 500);
}

finJson(['ok' => true, 'clients' => $rows]);