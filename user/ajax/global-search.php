<?php
/**
 * Elegance Salon - AJAX: global search (topbar)
 * GET user/ajax/global-search.php?q=...  -> JSON { clients, appointments, invoices }
 */
require_once __DIR__ . '/_guard.php';

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'clients' => [], 'appointments' => [], 'invoices' => []]);
    exit;
}

$result = ['clients' => [], 'appointments' => [], 'invoices' => []];
try {
    $db = getDBConnection();
    $like = '%' . $q . '%';

    // Clients
    $stmt = $db->prepare("
        SELECT client_id, first_name, last_name, phone, email
        FROM clients
        WHERE first_name LIKE :l OR last_name LIKE :l2 OR CONCAT(first_name,' ',last_name) LIKE :l3
           OR phone LIKE :l4 OR email LIKE :l5
        ORDER BY first_name ASC LIMIT 6
    ");
    $stmt->execute([':l' => $like, ':l2' => $like, ':l3' => $like, ':l4' => $like, ':l5' => $like]);
    foreach ($stmt->fetchAll() as $r) {
        $result['clients'][] = [
            'id'    => (int)$r['client_id'],
            'title' => trim($r['first_name'] . ' ' . $r['last_name']),
            'sub'   => trim(($r['phone'] ?? '') . ($r['phone'] ? ' &middot; ' : '') . ($r['email'] ?? '')),
        ];
    }

    // Appointments
    $stmt = $db->prepare("
        SELECT a.appointment_id, DATE_FORMAT(a.appointment_date,'%b %d, %Y') AS d,
               CONCAT(c.first_name,' ',c.last_name) AS client_name, s.service_name, a.status
        FROM appointments a
        JOIN clients c ON c.client_id = a.client_id
        JOIN services s ON s.service_id = a.service_id
        WHERE c.first_name LIKE :l OR c.last_name LIKE :l2 OR CONCAT(c.first_name,' ',c.last_name) LIKE :l3
           OR s.service_name LIKE :l4 OR a.status LIKE :l5
        ORDER BY a.appointment_date DESC LIMIT 6
    ");
    $stmt->execute([':l' => $like, ':l2' => $like, ':l3' => $like, ':l4' => $like, ':l5' => $like]);
    foreach ($stmt->fetchAll() as $r) {
        $result['appointments'][] = [
            'id'    => (int)$r['appointment_id'],
            'title' => $r['client_name'] . ' - ' . $r['service_name'],
            'sub'   => $r['d'] . ' &middot; ' . ucfirst($r['status']),
        ];
    }

    // Invoices
    $stmt = $db->prepare("
        SELECT i.invoice_id, i.invoice_number, i.total, i.status,
               CONCAT(c.first_name,' ',c.last_name) AS client_name
        FROM invoices i
        JOIN clients c ON c.client_id = i.client_id
        WHERE i.invoice_number LIKE :l OR c.first_name LIKE :l2 OR c.last_name LIKE :l3
           OR CONCAT(c.first_name,' ',c.last_name) LIKE :l4
        ORDER BY i.issued_at DESC LIMIT 6
    ");
    $stmt->execute([':l' => $like, ':l2' => $like, ':l3' => $like, ':l4' => $like]);
    foreach ($stmt->fetchAll() as $r) {
        $result['invoices'][] = [
            'id'    => (int)$r['invoice_id'],
            'title' => $r['invoice_number'] . ' &middot; ' . $r['client_name'],
            'sub'   => '$' . number_format((float)$r['total'], 2) . ' &middot; ' . ucfirst($r['status']),
        ];
    }

    echo json_encode(['ok' => true] + $result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Search failed.']);
}