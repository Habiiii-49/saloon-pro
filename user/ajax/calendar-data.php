<?php
/**
 * Elegance Salon - AJAX: calendar month data
 * GET user/ajax/calendar-data.php?month=0-11&year=YYYY
 * Returns JSON: { ok, title, html }
 */
require_once __DIR__ . '/_guard.php';

$month = (int)($_GET['month'] ?? (int)date('m') - 1);
$year  = (int)($_GET['year'] ?? (int)date('Y'));
$month = max(0, min(11, $month));
$year  = max(2000, min(2100, $year));

$first = new DateTime(sprintf('%04d-%02d-01', $year, $month + 1));
$daysInMonth = (int)$first->format('t');
$startDow = (int)$first->format('N'); // 1=Mon..7=Sun

try {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status,
               s.service_name, CONCAT(c.first_name,' ',c.last_name) AS client_name
        FROM appointments a
        JOIN services s ON s.service_id = a.service_id
        JOIN clients c ON c.client_id = a.client_id
        WHERE a.appointment_date BETWEEN :from AND :to
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->execute([
        ':from' => $first->format('Y-m-d'),
        ':to'   => $first->modify('last day of this month')->format('Y-m-d'),
    ]);
    $appts = $stmt->fetchAll();
} catch (PDOException $e) {
    $appts = [];
}

$first->modify('first day of this month');
$byDate = [];
foreach ($appts as $a) {
    $byDate[$a['appointment_date']][] = $a;
}

// Build grid: start cell offset from Monday
$html = '<div class="cal-dow">Mon</div><div class="cal-dow">Tue</div><div class="cal-dow">Wed</div><div class="cal-dow">Thu</div><div class="cal-dow">Fri</div><div class="cal-dow">Sat</div><div class="cal-dow">Sun</div>';

$today = date('Y-m-d');
$cursor = new DateTime(sprintf('%04d-%02d-01', $year, $month + 1));
$cursor->modify('-' . ($startDow - 1) . ' days'); // move back to Monday before/on 1st

for ($cell = 0; $cell < 42; $cell++) {
    $date = $cursor->format('Y-m-d');
    $inMonth = $cursor->format('n') === (string)($month + 1) && $cursor->format('Y') === (string)$year;
    $classes = ['cal-cell'];
    if (!$inMonth) $classes[] = 'other-month';
    if ($date === $today) $classes[] = 'today';

    $events = $byDate[$date] ?? [];
    $html .= '<div class="' . implode(' ', $classes) . '">';
    $html .= '<div class="cal-date">' . $cursor->format('j') . '</div>';
    if ($events) {
        foreach ($events as $ev) {
            $time = date('g:i A', strtotime($ev['appointment_time']));
            $html .= '<button type="button" class="cal-event ' . sanitize($ev['status']) . '" data-cal-event="' . (int)$ev['appointment_id'] . '" title="' . sanitize($ev['client_name']) . ' - ' . sanitize($ev['service_name']) . ' (' . $time . ')">';
            $html .= $time . ' ' . sanitize($ev['client_name']);
            $html .= '</button>';
        }
    }
    $html .= '</div>';
    $cursor->modify('+1 day');
}

$title = $first->format('F Y');
echo json_encode(['ok' => true, 'title' => $title, 'html' => $html]);