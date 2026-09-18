<?php
/**
 * Elegance Salon - User / Receptionist - Booking Helpers
 * Shared slot generation + overlap validation used by the receptionist panel.
 */
require_once __DIR__ . '/../../includes/functions.php';

/** Salon working hours + slot step (minutes), configurable via site_settings. */
function salonOpenTime(): string
{
    $v = getSetting('salon_open_time', '09:00');
    return $v !== '' ? $v : '09:00';
}
function salonCloseTime(): string
{
    $v = getSetting('salon_close_time', '20:00');
    return $v !== '' ? $v : '20:00';
}
function salonSlotStep(): int
{
    $v = (int)getSetting('slot_step', '30');
    return $v > 0 ? $v : 30;
}

/** Convert "HH:MM[:SS]" into minutes since midnight (handles presence/absence of seconds). */
function timeToMinutes(string $time): int
{
    $parts = array_map('intval', explode(':', $time));
    return ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
}

/** Convert minutes since midnight to "HH:MM:SS". */
function minutesToTime(int $minutes): string
{
    $minutes = max(0, $minutes);
    return str_pad(intdiv($minutes, 60), 2, '0', STR_PAD_LEFT) . ':' .
           str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) . ':00';
}

/** Format seconds-or-time string to "h:mm AM/PM". Accepts "HH:MM:SS" or "HH:MM". */
function formatSlotTime(string $time): string
{
    $full = str_contains($time, ':') && substr_count($time, ':') === 2 ? $time : $time . ':00';
    return date('g:i A', strtotime('1970-01-01 ' . $full));
}

/** Booked intervals for a stylist on a date (statuses that block a slot). */
function stylistBookedIntervals(int $staffId, string $date, ?int $excludeAppointmentId = null): array
{
    $blocks = [];
    try {
        $db = getDBConnection();
        $sql = "SELECT appointment_time, end_time, service_id
                FROM appointments
                WHERE staff_id = :sid AND appointment_date = :d
                  AND status IN ('pending','confirmed','in_progress')";
        if ($excludeAppointmentId !== null) {
            $sql .= " AND appointment_id <> :ex";
        }
        $stmt = $db->prepare($sql);
        $params = [':sid' => $staffId, ':d' => $date];
        if ($excludeAppointmentId !== null) {
            $params[':ex'] = $excludeAppointmentId;
        }
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            $end = !empty($row['end_time']) ? $row['end_time'] : $row['appointment_time'];
            $blocks[] = ['start' => $row['appointment_time'], 'end' => $end];
        }
    } catch (PDOException $e) {
        $blocks = [];
    }
    return $blocks;
}

/** Overlap test: existing_start < requested_end AND existing_end > requested_start. */
function hasTimeOverlap(array $blocks, string $start, string $end): bool
{
    $s = timeToMinutes($start);
    $e = timeToMinutes($end);
    foreach ($blocks as $b) {
        $bs = timeToMinutes($b['start']);
        $be = timeToMinutes($b['end']);
        if ($bs < $e && $be > $s) {
            return true;
        }
    }
    return false;
}

/**
 * Compute all available start slots for a stylist/service/date.
 * Returns array of "HH:MM:SS".
 */
function buildAvailableSlots(string $date, int $staffId, int $serviceId, ?int $excludeAppointmentId = null): array
{
    $service = getServiceById($serviceId);
    if (!$service) {
        return [];
    }
    $duration = (int)$service['duration_minutes'];
    if ($duration < 1) {
        $duration = 30;
    }

    $step      = salonSlotStep();
    $openMin   = timeToMinutes(salonOpenTime());
    $closeMin  = timeToMinutes(salonCloseTime());
    $blocks    = stylistBookedIntervals($staffId, $date, $excludeAppointmentId);

    // A slot must fit entirely inside opening hours: start + duration <= close
    $slots = [];
    for ($t = $openMin; $t + $duration <= $closeMin; $t += $step) {
        $start = minutesToTime($t);
        $end   = minutesToTime($t + $duration);
        if (!hasTimeOverlap($blocks, $start, $end)) {
            $slots[] = $start;
        }
    }
    return $slots;
}

/**
 * Validate that an appointment can be inserted/updated without overlap.
 * Returns [bool, string message].
 */
function validateAppointmentSlot(int $staffId, string $date, string $start, int $serviceId, ?int $excludeAppointmentId = null): array
{
    $service = getServiceById($serviceId);
    if (!$service) {
        return [false, 'Service not found or inactive.'];
    }
    $duration = max(1, (int)$service['duration_minutes']);
    $end = minutesToTime(timeToMinutes($start) + $duration);

    $blocks = stylistBookedIntervals($staffId, $date, $excludeAppointmentId);
    if (hasTimeOverlap($blocks, $start, $end)) {
        return [false, 'This time slot overlaps with an existing appointment for this stylist.'];
    }
    return [true, 'Slot is available.'];
}

/** Registered, active stylists (staff joining users). */
function getActiveStylists(): array
{
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT s.staff_id, s.specialty, s.bio, s.experience_years, s.commission_rate,
                   s.is_available, s.working_days, s.working_hours,
                   u.user_id, u.first_name, u.last_name, u.phone, u.email, u.avatar
            FROM staff s
            JOIN users u ON u.user_id = s.user_id
            WHERE u.is_active = 1
            ORDER BY s.is_available DESC, u.first_name ASC, u.last_name ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Active services list (id, name, price, duration, category). */
function getReceptionistServices(): array
{
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT service_id, service_name, price, duration_minutes, category, description, is_active
            FROM services
            WHERE is_active = 1
            ORDER BY category ASC, service_name ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/** Client lookup by id with joined stats kept minimal (full name, contact). */
function getClientById(int $clientId): ?array
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM clients WHERE client_id = :id LIMIT 1");
    $stmt->execute([':id' => $clientId]);
    $row = $stmt->fetch();
    return $row ?: null;
}