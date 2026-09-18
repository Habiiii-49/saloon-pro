<?php
/**
 * Elegance Salon - AJAX: client live search (name / phone / email)
 * GET user/ajax/search-clients.php?q=...
 * Returns JSON: { ok: true, clients: [ {id, name, phone, email, initial} ] }
 */
require_once __DIR__ . '/_guard.php';

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'clients' => []]);
    exit;
}

try {
    $db = getDBConnection();
    $like = '%' . $q . '%';
    $stmt = $db->prepare("
        SELECT client_id, first_name, last_name, phone, email
        FROM clients
        WHERE first_name LIKE :l1
           OR last_name LIKE :l2
           OR CONCAT(first_name, ' ', last_name) LIKE :l3
           OR phone LIKE :l4
           OR email LIKE :l5
        ORDER BY first_name ASC, last_name ASC
        LIMIT 12
    ");
    $stmt->execute([
        ':l1' => $like, ':l2' => $like, ':l3' => $like,
        ':l4' => $like, ':l5' => $like,
    ]);
    $rows = $stmt->fetchAll();

    $clients = [];
    foreach ($rows as $r) {
        $name = trim($r['first_name'] . ' ' . $r['last_name']);
        $clients[] = [
            'id'      => (int)$r['client_id'],
            'name'    => $name,
            'phone'   => $r['phone'] ?? '',
            'email'   => $r['email'] ?? '',
            'initial' => strtoupper(mb_substr($r['first_name'], 0, 1)),
        ];
    }

    echo json_encode(['ok' => true, 'clients' => $clients]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Search failed.']);
}