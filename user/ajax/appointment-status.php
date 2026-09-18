<?php
/**
 * Elegance Salon - AJAX: update appointment status
 * POST user/ajax/appointment-status.php  { id, status, csrf_token }
 * Returns JSON.
 * Used optionally; the appointment pages also support plain POST forms.
 */
require_once __DIR__ . '/_guard.php';

$payload = getJsonPayload();
$id      = (int)($payload['id'] ?? 0);
$status  = $payload['status'] ?? '';
$token   = $payload['csrf_token'] ?? '';

if (!verifyCSRFToken($token)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$allowed = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
if (!$id || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid appointment or status.']);
    exit;
}

try {
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT a.appointment_id, a.total_amount, a.appointment_date, a.appointment_time,
               s.service_name, CONCAT(c.first_name, ' ', c.last_name) AS client_name
        FROM appointments a
        JOIN services s ON s.service_id = a.service_id
        JOIN clients c ON c.client_id = a.client_id
        WHERE a.appointment_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $appt = $stmt->fetch();

    if (!$appt) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Appointment not found.']);
        exit;
    }

    // Update status (record stays; history preserved)
    $upd = $db->prepare("UPDATE appointments SET status = :s, updated_at = NOW() WHERE appointment_id = :id");
    $upd->execute([':s' => $status, ':id' => $id]);

    // When marked completed, ensure a payment record exists so finance stays consistent.
    if ($status === 'completed') {
        $chk = $db->prepare("SELECT payment_id FROM payments WHERE appointment_id = :id LIMIT 1");
        $chk->execute([':id' => $id]);
        if (!$chk->fetch()) {
            $insP = $db->prepare("
                INSERT INTO payments (appointment_id, amount, payment_method, payment_status, paid_at)
                VALUES (:id, :amt, 'cash', 'completed', NOW())
            ");
            $insP->execute([':id' => $id, ':amt' => $appt['total_amount']]);
        }
    }

    // Notify the salon (title varies by status)
    $title = 'Appointment ' . ucfirst(str_replace('_', ' ', $status));
    $msg = "{$appt['client_name']} - {$appt['service_name']} on " . date('M d, Y', strtotime($appt['appointment_date'])) . ' is now ' . $status . '.';
    $type = $status === 'cancelled' ? 'warning' : ($status === 'no_show' ? 'danger' : 'info');
    createNotification(null, $title, $msg, $type);

    echo json_encode(['ok' => true, 'status' => $status]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not update status.']);
}