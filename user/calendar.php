<?php
/**
 * Elegance Salon - Receptionist - Appointment Calendar (monthly)
 * Navigation + load via AJAX (user/ajax/calendar-data.php).
 */
require_once __DIR__ . '/includes/booking.php';

$month = (int)($_GET['month'] ?? ((int)date('m') - 1));
$year  = (int)($_GET['year'] ?? (int)date('Y'));
$month = max(0, min(11, $month));
$year  = max(2000, min(2100, $year));

$first = new DateTime(sprintf('%04d-%02d-01', $year, $month + 1));
$daysInMonth = (int)$first->format('t');
$startDow = (int)$first->format('N');

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
    $stmt->execute([':from' => $first->format('Y-m-d'), ':to' => $first->modify('last day of this month')->format('Y-m-d')]);
    $appts = $stmt->fetchAll();
} catch (PDOException $e) {
    $appts = [];
}

$byDate = [];
foreach ($appts as $a) {
    $byDate[$a['appointment_date']][] = $a;
}

$today = date('Y-m-d');
$cursor = new DateTime(sprintf('%04d-%02d-01', $year, $month + 1));
$cursor->modify('-' . ($startDow - 1) . ' days');

$gridHtml = '<div class="cal-dow">Mon</div><div class="cal-dow">Tue</div><div class="cal-dow">Wed</div><div class="cal-dow">Thu</div><div class="cal-dow">Fri</div><div class="cal-dow">Sat</div><div class="cal-dow">Sun</div>';
for ($cell = 0; $cell < 42; $cell++) {
    $date = $cursor->format('Y-m-d');
    $inMonth = $cursor->format('n') === (string)($month + 1) && $cursor->format('Y') === (string)$year;
    $classes = ['cal-cell'];
    if (!$inMonth) $classes[] = 'other-month';
    if ($date === $today) $classes[] = 'today';

    $gridHtml .= '<div class="' . implode(' ', $classes) . '">';
    $gridHtml .= '<div class="cal-date">' . $cursor->format('j') . '</div>';
    if (!empty($byDate[$date])) {
        foreach ($byDate[$date] as $ev) {
            $time = date('g:i A', strtotime($ev['appointment_time']));
            $gridHtml .= '<button type="button" class="cal-event ' . sanitize($ev['status']) . '" data-cal-event="' . (int)$ev['appointment_id'] . '" title="' . sanitize($ev['client_name']) . ' - ' . sanitize($ev['service_name']) . ' (' . $time . ')">';
            $gridHtml .= $time . ' ' . sanitize($ev['client_name']);
            $gridHtml .= '</button>';
        }
    }
    $gridHtml .= '</div>';
    $cursor->modify('+1 day');
}

$pageTitle = "Calendar";
$activeMenu = 'calendar';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-days"></i> Appointment Calendar</h1>
        <p class="page-subtitle">Click an appointment to open its details.</p>
    </div>
    <div class="page-head-right">
        <a href="<?php echo SITE_URL; ?>/user/appointment-add.php" class="btn-user btn-cyan"><i class="fas fa-circle-plus"></i> New Appointment</a>
    </div>
</div>

<div class="panel">
    <div class="calendar-toolbar">
        <button type="button" class="cal-nav-btn" data-cal-nav="-1" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button>
        <h5 class="ct-title" id="calendarTitle"><?php echo $first->format('F Y'); ?></h5>
        <div style="display:flex;gap:.5rem">
            <a href="<?php echo SITE_URL; ?>/user/calendar.php" class="btn-user btn-outline btn-sm"><i class="fas fa-rotate"></i> Today</a>
            <button type="button" class="cal-nav-btn" data-cal-nav="1" aria-label="Next month"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>

    <div class="cal-legend">
        <span><i style="background:rgba(245,158,11,.85)"></i> Pending</span>
        <span><i style="background:rgba(0,194,217,.85)"></i> Confirmed</span>
        <span><i style="background:rgba(167,139,250,.85)"></i> In Progress</span>
        <span><i style="background:rgba(34,197,94,.85)"></i> Completed</span>
        <span><i style="background:rgba(239,68,68,.7)"></i> Cancelled</span>
        <span><i style="background:rgba(127,29,29,.8)"></i> No Show</span>
    </div>

    <div class="cal-grid" id="calendarGrid" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>">
        <?php echo $gridHtml; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>