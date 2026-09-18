<?php
/**
 * Elegance Salon - AJAX: quick walk-in client (used by booking wizard)
 * POST user/ajax/quick-client.php { first_name, last_name, phone, csrf_token }
 * Returns JSON { ok, client_id, name }
 */
require_once __DIR__ . '/_guard.php';

$payload = getJsonPayload();
$token   = $payload['csrf_token'] ?? '';
if (!verifyCSRFToken($token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
    exit;
}

$first = trim($payload['first_name'] ?? '');
$last  = trim($payload['last_name'] ?? '');
$phone = trim($payload['phone'] ?? '');

if ($first === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'First name is required.']);
    exit;
}

try {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO clients (first_name, last_name, phone, email) VALUES (:f, :l, :p, NULL)");
    $stmt->execute([
        ':f' => $first,
        ':l' => $last !== '' ? $last : null,
        ':p' => $phone !== '' ? $phone : null,
    ]);
    $id = (int)$db->lastInsertId();
    echo json_encode(['ok' => true, 'client_id' => $id, 'name' => trim($first . ' ' . $last)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not create the walk-in client.']);
}