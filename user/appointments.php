<?php
/**
 * Elegance Salon - Receptionist - Appointments
 * List / search / filter appointments, change status, cancel.
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$today = date('Y-m-d');

/* ---------- POST: cancel appointment (status = cancelled) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $token  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($token)) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('user/appointments.php');
    }

    if ($action === 'cancel' && $id > 0) {
        $stmt = $db->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE appointment_id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() > 0) {
            $info = $db->prepare("SELECT CONCAT(c.first_name,' ',c.last_name) AS n, s.service_name FROM appointments a JOIN clients c ON c.client_id=a.client_id JOIN services s ON s.service_id=a.service_id WHERE a.appointment_id=:id");
            $info->execute([':id' => $id]);
            $row = $info->fetch();
            if ($row) {
                createNotification(null, 'Appointment Cancelled', "{$row['n']} - {$row['service_name']} was cancelled.", 'warning');
            }
        }
        setFlash('success', 'Appointment cancelled. The record is preserved for history.');
        redirect('user/appointments.php');
    }

    setFlash('error', 'Unknown action.');
    redirect('user/appointments.php');
}

/* ---------- Filters ---------- */
$statusFilter = $_GET['status'] ?? '';
$dateFilter   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where  = [];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['pending','confirmed','in_progress','completed','cancelled','no_show'], true)) {
    $where[] = "a.status = :status";
    $params[':status'] = $statusFilter;
}
if ($dateFilter !== '') {
    $where[] = "a.appointment_date = :date";
    $params[':date'] = $dateFilter;
}
if ($search !== '') {
    $where[] = "(CONCAT(c.first_name,' ',c.last_name) LIKE :q OR s.service_name LIKE :q2 OR CONCAT(u.first_name,' ',u.last_name) LIKE :q3)";
    $params[':q'] = '%' . $search . '%';
    $params[':q2'] = '%' . $search . '%';
    $params[':q3'] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* count */
$countStmt = $db->prepare("
    SELECT COUNT(*) AS c
    FROM appointments a
    JOIN clients c ON c.client_id = a.client_id
    JOIN services s ON s.service_id = a.service_id
    LEFT JOIN staff st ON st.staff_id = a.staff_id
    LEFT JOIN users u ON u.user_id = st.user_id
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$appointments = [];
if ($totalRows > 0) {
    $sql = "
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.end_time, a.status,
               a.total_amount, a.notes, a.created_at,
               s.service_name, s.duration_minutes,
               CONCAT(c.first_name, ' ', c.last_name) AS client_name, c.client_id, c.phone AS client_phone, c.email AS client_email,
               CONCAT(u.first_name, ' ', u.last_name) AS stylist_name, st.staff_id, u.email AS stylist_email,
               p.payment_status, p.amount AS paid_amount
        FROM appointments a
        JOIN clients c ON c.client_id = a.client_id
        JOIN services s ON s.service_id = a.service_id
        LEFT JOIN staff st ON st.staff_id = a.staff_id
        LEFT JOIN users u ON u.user_id = st.user_id
        LEFT JOIN payments p ON p.appointment_id = a.appointment_id
        $whereSql
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT $perPage OFFSET $offset
    ";
    $appointments = $db->prepare($sql);
    $appointments->execute($params);
    $appointments = $appointments->fetchAll();
}

$statusLabels = [
    'pending'     => ['Pending', 'pending'],
    'confirmed'   => ['Confirmed', 'confirmed'],
    'in_progress' => ['In Progress', 'in_progress'],
    'completed'   => ['Completed', 'completed'],
    'cancelled'   => ['Cancelled', 'cancelled'],
    'no_show'     => ['No Show', 'no_show'],
];

$pageTitle = "Appointments";
$activeMenu = 'appointments';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-check"></i> Appointments</h1>
        <p class="page-subtitle">Manage daily bookings, reschedule and update statuses.</p>
    </div>
    <div class="page-head-right">
        <a href="<?php echo SITE_URL; ?>/user/appointment-add.php" class="btn-user btn-cyan"><i class="fas fa-circle-plus"></i> New Appointment</a>
        <a href="<?php echo SITE_URL; ?>/user/calendar.php" class="btn-user btn-outline"><i class="fas fa-calendar-days"></i> Calendar</a>
    </div>
</div>

<form method="get" action="" class="filter-bar">
    <input type="text" name="q" class="form-control-salon fser" placeholder="Search client, service or stylist..." value="<?php echo sanitize($search); ?>">
    <select name="status" class="form-control-salon">
        <option value="">All statuses</option>
        <?php foreach ($statusLabels as $key => $label): ?>
        <option value="<?php echo $key; ?>" <?php echo $statusFilter === $key ? 'selected' : ''; ?>><?php echo $label[0]; ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="date" class="form-control-salon" value="<?php echo sanitize($dateFilter); ?>">
    <button type="submit" class="btn-user btn-outline"><i class="fas fa-magnifying-glass"></i> Filter</button>
    <a href="<?php echo SITE_URL; ?>/user/appointments.php" class="btn-user btn-ghost"><i class="fas fa-rotate"></i></a>
</form>

<div class="panel">
    <div class="panel-head">
        <h5><i class="fas fa-list"></i> All Appointments <span class="text-muted">(<?php echo (int)$totalRows; ?>)</span></h5>
    </div>
    <div class="table-responsive-wrap">
        <table class="table-salon">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Stylist</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="8">
                        <div class="table-empty">
                            <i class="fas fa-calendar-xmark"></i>
                            <p>
                                <?php if ($statusFilter || $dateFilter || $search): ?>
                                No appointments found matching your filters.
                                <?php else: ?>
                                No appointments found.
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo SITE_URL; ?>/user/appointment-add.php" class="btn-user btn-cyan"><i class="fas fa-circle-plus"></i> Create the first appointment</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($appointments as $a):
                    $sl = $statusLabels[$a['status']] ?? [ucfirst($a['status']), 'pending']; ?>
                <tr>
                    <td class="cell-main"><?php echo formatDate($a['appointment_date'], 'M d, Y'); ?></td>
                    <td>
                        <span class="cell-main"><?php echo formatSlotTime($a['appointment_time']); ?></span>
                        <?php if (!empty($a['end_time'])): ?><span class="cell-sub">to <?php echo formatSlotTime($a['end_time']); ?></span><?php endif; ?>
                    </td>
                    <td><span class="cell-main"><?php echo sanitize($a['client_name']); ?></span></td>
                    <td>
                        <?php echo sanitize($a['service_name']); ?>
                        <?php if (!empty($a['duration_minutes'])): ?><span class="cell-sub"><?php echo (int)$a['duration_minutes']; ?> min</span><?php endif; ?>
                    </td>
                    <td><?php echo sanitize($a['stylist_name'] ?: '—'); ?></td>
                    <td>
                        <select class="form-control-salon" style="padding:.3rem .6rem;font-size:.75rem;min-width:120px" data-status-select data-id="<?php echo (int)$a['appointment_id']; ?>" data-csrf="<?php echo generateCSRFToken(); ?>">
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $a['status'] === $key ? 'selected' : ''; ?>><?php echo $label[0]; ?></option>
                        <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <?php if (in_array($a['payment_status'], ['paid', 'completed'], true)): ?>
                            <span class="status-badge paid"><?php echo formatCurrency($a['paid_amount'] ?? 0); ?></span>
                        <?php else: ?>
                            <span class="status-badge unpaid"><?php echo $a['payment_status'] === 'partial' ? 'Partial' : 'Unpaid'; ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="tbl-actions">
                            <a href="#" title="View" data-open-appointment
                               data-id="<?php echo (int)$a['appointment_id']; ?>"
                               data-client="<?php echo sanitize($a['client_name']); ?>"
                               data-service="<?php echo sanitize($a['service_name']); ?>"
                               data-stylist="<?php echo sanitize($a['stylist_name'] ?: 'Unassigned'); ?>"
                               data-date="<?php echo formatDate($a['appointment_date'], 'D, M d, Y'); ?>"
                               data-time="<?php echo formatSlotTime($a['appointment_time']) . (!empty($a['end_time']) ? ' - ' . formatSlotTime($a['end_time']) : ''); ?>"
                               data-amount="<?php echo formatCurrency($a['total_amount']); ?>"
                               data-status="<?php echo $sl[0]; ?>"
                               data-notes="<?php echo sanitize($a['notes'] ?: '—'); ?>"
                               data-payment="<?php echo in_array($a['payment_status'], ['paid','completed'], true) ? 'Paid (' . formatCurrency($a['paid_amount'] ?? 0) . ')' : ucfirst($a['payment_status'] ?? 'unpaid'); ?>"
                            ><i class="fas fa-eye"></i></a>
                            <a href="<?php echo SITE_URL; ?>/user/appointment-edit.php?id=<?php echo (int)$a['appointment_id']; ?>" title="Edit / Reschedule"><i class="fas fa-pen-to-square"></i></a>
                            <button type="button" class="btn-ghost act-danger"
                                    data-confirm-post='{"title":"Cancel Appointment","message":"Cancel this appointment? The record stays in history with status Cancelled.","confirmText":"Yes, cancel","danger":true}'
                                    data-action="<?php echo SITE_URL; ?>/user/appointments.php"
                                    data-csrf_token="<?php echo generateCSRFToken(); ?>"
                                    data-id="<?php echo (int)$a['appointment_id']; ?>"
                                    title="Cancel"><i class="fas fa-ban"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination-salon">
        <?php
        $qs = http_build_query(array_filter([
            'status' => $statusFilter ?: null,
            'date'   => $dateFilter ?: null,
            'q'      => $search ?: null,
        ]));
        ?>
        <a href="?page=<?php echo max(1, $page - 1); ?>&<?php echo $qs; ?>" class="<?php echo $page <= 1 ? 'pe-none' : ''; ?>"><i class="fas fa-chevron-left"></i></a>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?php echo $p; ?>&<?php echo $qs; ?>" class="<?php echo $p === $page ? 'cur' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <a href="?page=<?php echo min($totalPages, $page + 1); ?>&<?php echo $qs; ?>" class="<?php echo $page >= $totalPages ? 'pe-none' : ''; ?>"><i class="fas fa-chevron-right"></i></a>
        <span class="page-info ms-auto"><?php echo $totalRows; ?> appointment(s)</span>
    </div>
    <?php endif; ?>
</div>

<!-- Appointment detail modal -->
<div class="modal fade modal-user" id="appointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-check"></i> Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="appointmentModalBody"></div>
            <div class="modal-footer" id="appointmentModalFooter"></div>
        </div>
    </div>
</div>

<?php
// Auto-open view modal when redirected from calendar (?view=ID)
$autoView = (int)($_GET['view'] ?? 0);
if ($autoView > 0):
    $avStmt = $db->prepare("
        SELECT a.appointment_id, CONCAT(c.first_name,' ',c.last_name) AS client_name, s.service_name,
               CONCAT(u.first_name,' ',u.last_name) AS stylist_name, a.appointment_date, a.appointment_time, a.end_time,
               a.total_amount, a.status, a.notes, p.payment_status, p.amount AS paid_amount
        FROM appointments a
        JOIN clients c ON c.client_id = a.client_id
        JOIN services s ON s.service_id = a.service_id
        LEFT JOIN staff st ON st.staff_id = a.staff_id
        LEFT JOIN users u ON u.user_id = st.user_id
        LEFT JOIN payments p ON p.appointment_id = a.appointment_id
        WHERE a.appointment_id = :id LIMIT 1
    ");
    $avStmt->execute([':id' => $autoView]);
    $avu = $avStmt->fetch();
    if ($avu):
        $avStatus = $statusLabels[$avu['status']] ?? [ucfirst($avu['status']), 'pending'];
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.USER.appointmentFromData({
        client: <?php echo json_encode($avu['client_name']); ?>,
        service: <?php echo json_encode($avu['service_name']); ?>,
        stylist: <?php echo json_encode($avu['stylist_name'] ?: 'Unassigned'); ?>,
        date: <?php echo json_encode(formatDate($avu['appointment_date'], 'D, M d, Y')); ?>,
        time: <?php echo json_encode(formatSlotTime($avu['appointment_time']) . (!empty($avu['end_time']) ? ' - ' . formatSlotTime($avu['end_time']) : '')); ?>,
        amount: <?php echo json_encode(formatCurrency($avu['total_amount'])); ?>,
        status: <?php echo json_encode($avStatus[0]); ?>,
        notes: <?php echo json_encode($avu['notes'] ?: '—'); ?>,
        payment: <?php echo json_encode(in_array($avu['payment_status'], ['paid','completed'], true) ? 'Paid (' . formatCurrency($avu['paid_amount'] ?? 0) . ')' : ucfirst($avu['payment_status'] ?? 'unpaid')); ?>
    });
});
</script>
<?php
    endif;
endif;
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>