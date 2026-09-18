<?php
/**
 * Elegance Salon - AJAX: available time slots
 * GET user/ajax/available-slots.php?service_id=&staff_id=&date=[&appointment_id=]
 * Returns JSON: { ok, slots: ["HH:MM:SS", ...] }
 *
 * Slot rules:
 *  - slots fit inside salon opening hours (09:00-20:00 default, configurable)
 *  - slot length = service duration
 *  - overlapping bookings for that stylist (pending/confirmed/in_progress) are excluded
 *  - appointment_id (when editing) is excluded from the overlap set
 *  - selection is re-validated server-side on every insert/update (never trust this only)
 */
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../includes/booking.php';

$serviceId = (int)($_GET['service_id'] ?? 0);
$staffId   = (int)($_GET['staff_id'] ?? 0);
$date      = trim($_GET['date'] ?? '');
$exclude   = (int)($_GET['appointment_id'] ?? 0);

if (!$serviceId || !$staffId || $date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid parameters.', 'slots' => []]);
    exit;
}

// Past dates are always invalid.
if ($date < date('Y-m-d')) {
    echo json_encode(['ok' => false, 'error' => 'Cannot book a past date.', 'slots' => []]);
    exit;
}

$service = getServiceById($serviceId);
if (!$service) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid service.', 'slots' => []]);
    exit;
}

$slots = buildAvailableSlots($date, $staffId, $serviceId, $exclude ?: null);
echo json_encode(['ok' => true, 'slots' => $slots]);