<?php
/**
 * Elegance Salon - Receptionist - Edit / Reschedule Appointment
 * Changing date/time/stylist re-runs availability validation (no overlap).
 * POST action=update updates; action=cancel cancels (status -> cancelled).
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$today = date('Y-m-d');
$apptId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));

if ($apptId <= 0) {
    setFlash('error', 'No appointment id provided.');
    redirect('user/appointments.php');
}

$appt = null;
try {
    $stmt = $db->prepare("
        SELECT a.*, c.first_name AS c_first, c.last_name AS c_last, c.phone AS c_phone, c.email AS c_email,
               s.service_name, CONCAT(u.first_name,' ',u.last_name) AS stylist_name
        FROM appointments a
        JOIN clients c ON c.client_id = a.client_id
        JOIN services s ON s.service_id = a.service_id
        LEFT JOIN staff st ON st.staff_id = a.staff_id
        LEFT JOIN users u ON u.user_id = st.user_id
        WHERE a.appointment_id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $apptId]);
    $appt = $stmt->fetch();
} catch (PDOException $e) {
    $appt = null;
}

if (!$appt) {
    setFlash('error', 'Appointment not found.');
    redirect('user/appointments.php');
}

$clients = $db->query("SELECT client_id, first_name, last_name, phone, email FROM clients ORDER BY first_name ASC")->fetchAll();
$services = getReceptionistServices();
$stylists = getActiveStylists();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        redirect('user/appointment-edit.php?id=' . $apptId);
    }

    if ($action === 'cancel') {
        $upd = $db->prepare("UPDATE appointments SET status='cancelled', updated_at=NOW() WHERE appointment_id=:id");
        $upd->execute([':id' => $apptId]);
        createNotification(null, 'Appointment Cancelled', "Appointment #{$apptId} was cancelled.", 'warning');
        setFlash('success', 'Appointment cancelled. The record is preserved for history.');
        redirect('user/appointments.php');
    }

    if ($action === 'update') {
        $clientId  = (int)($_POST['client_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $staffId   = (int)($_POST['staff_id'] ?? 0);
        $date      = trim($_POST['appointment_date'] ?? '');
        $time      = trim($_POST['appointment_time'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $status    = trim($_POST['status'] ?? '');

        $allowedStatus = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowedStatus, true)) { $status = 'pending'; }

        if ($clientId <= 0 || !getClientById($clientId)) $errors[] = 'Please choose a valid client.';
        if ($serviceId <= 0 || !($service = getServiceById($serviceId))) $errors[] = 'Please choose a valid service.';
        $isValidStaff = false;
        foreach ($stylists as $st) {
            if ((int)$st['staff_id'] === $staffId && (int)$st['is_available'] === 1) { $isValidStaff = true; break; }
        }
        if (!$isValidStaff) $errors[] = 'Please choose a valid, available stylist.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $errors[] = 'Please choose a valid date.';
        elseif ($date < $today) $errors[] = 'You cannot book an appointment in the past.';
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) $errors[] = 'Please select a valid time slot.';

        // Overlap check excluding this appointment
        if (!$errors && isset($service)) {
            [$slotOk, $slotMsg] = validateAppointmentSlot($staffId, $date, $time, $serviceId, $apptId);
            if (!$slotOk) $errors[] = $slotMsg;
        }

        if (empty($errors)) {
            try {
                $endTime = minutesToTime(timeToMinutes($time) + max(1, (int)$service['duration_minutes']));
                $upd = $db->prepare("
                    UPDATE appointments
                    SET client_id=:c, staff_id=:stf, service_id=:sv, appointment_date=:d,
                        appointment_time=:t, end_time=:e, status=:status, notes=:n, total_amount=:amt, updated_at=NOW()
                    WHERE appointment_id=:id
                ");
                $upd->execute([
                    ':c' => $clientId, ':stf' => $staffId, ':sv' => $serviceId,
                    ':d' => $date, ':t' => $time, ':e' => $endTime,
                    ':status' => $status, ':n' => $notes !== '' ? $notes : null,
                    ':amt' => $service['price'], ':id' => $apptId,
                ]);

                createNotification(null, 'Appointment Updated', "Appointment #{$apptId} was updated/rescheduled.", 'info');
                setFlash('success', 'Appointment updated. Slot availability was re-validated.');
                redirect('user/appointments.php');
            } catch (PDOException $e) {
                $errors[] = 'Could not update the appointment. Please try again.';
            }
        }
        foreach ($errors as $err) { setFlash('error', $err); }
    }
}

$timeRaw = $appt['appointment_time'];
$pageTitle = "Edit Appointment #{$apptId}";
$activeMenu = 'appointments';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-pen-to-square"></i> Edit / Reschedule Appointment</h1>
        <p class="page-subtitle">Appointment #<?php echo (int)$apptId; ?> &middot; originally <?php echo formatDate($appt['appointment_date'], 'M d, Y'); ?> at <?php echo formatSlotTime($appt['appointment_time']); ?></p>
    </div>
    <div class="page-head-right">
        <a href="<?php echo SITE_URL; ?>/user/appointments.php" class="btn-user btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<form method="post" action="" id="editAppointmentForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="id" value="<?php echo (int)$apptId; ?>">
    <input type="hidden" name="action" value="update">

    <div class="panel">
        <div class="panel-head"><h5><i class="fas fa-pen-to-square"></i> Appointment Details</h5></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="form-group">
                    <label for="client_id">Client <span class="req">*</span></label>
                    <select name="client_id" id="client_id" class="form-control-salon">
                        <option value="">— Select client —</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?php echo (int)$c['client_id']; ?>" <?php echo (int)$c['client_id'] === (int)$appt['client_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['first_name'] . ' ' . $c['last_name'] . ($c['phone'] ? ' (' . $c['phone'] . ')' : '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_id">Service <span class="req">*</span></label>
                    <select name="service_id" id="service_id" class="form-control-salon" data-slot-deps>
                        <option value="">— Select service —</option>
                        <?php foreach ($services as $sv): ?>
                        <option value="<?php echo (int)$sv['service_id']; ?>" <?php echo (int)$sv['service_id'] === (int)$appt['service_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($sv['service_name'] . ' (' . (int)$sv['duration_minutes'] . ' min - ' . formatCurrency($sv['price']) . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="staff_id">Stylist <span class="req">*</span></label>
                    <select name="staff_id" id="staff_id" class="form-control-salon" data-slot-deps>
                        <option value="">— Select stylist —</option>
                        <?php foreach ($stylists as $st): ?>
                        <option value="<?php echo (int)$st['staff_id']; ?>" <?php echo (int)$st['staff_id'] === (int)$appt['staff_id'] ? 'selected' : ''; ?> <?php echo (int)$st['is_available'] === 1 ? '' : 'disabled'; ?>>
                            <?php echo sanitize(trim($st['first_name'] . ' ' . $st['last_name']) . ($st['specialty'] ? ' — ' . $st['specialty'] : '') . ((int)$st['is_available'] === 1 ? '' : ' (away)')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="appointment_date">Date <span class="req">*</span></label>
                    <input type="date" id="appointment_date" name="appointment_date" class="form-control-salon"
                           min="<?php echo $today; ?>" value="<?php echo sanitize($appt['appointment_date']); ?>" data-slot-deps>
                </div>
            </div>

            <div class="form-group">
                <label>Available Slots <span class="req">*</span></label>
                <div class="slot-grid" id="slotResults">
                    <div class="slot-loading" style="grid-column:1/-1"><i class="fas fa-spinner fa-spin"></i> Loading available slots...</div>
                </div>
                <input type="hidden" id="wizardTime" name="appointment_time" value="<?php echo sanitize($timeRaw); ?>">
                <input type="hidden" id="slotServiceId" value="<?php echo (int)$appt['service_id']; ?>">
                <input type="hidden" id="slotStaffId" value="<?php echo (int)$appt['staff_id']; ?>">
                <input type="hidden" id="slotDate" value="<?php echo sanitize($appt['appointment_date']); ?>">
                <input type="hidden" id="slotAppointmentId" value="<?php echo (int)$apptId; ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control-salon">
                        <?php
                        $sLabels = ['pending'=>'Pending','confirmed'=>'Confirmed','in_progress'=>'In Progress','completed'=>'Completed','cancelled'=>'Cancelled','no_show'=>'No Show'];
                        foreach ($sLabels as $k => $l): ?>
                        <option value="<?php echo $k; ?>" <?php echo $appt['status'] === $k ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" class="form-control-salon" placeholder="Preferences, allergies..."><?php echo sanitize($appt['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-user btn-cyan"><i class="fas fa-save"></i> Save Changes</button>
                <button type="button" class="btn-user btn-danger-outline"
                        data-confirm-form='{"title":"Cancel Appointment","message":"Change status to Cancelled? The record stays in history.","confirmText":"Yes, cancel","danger":true}'
                        data-cancel-form="1"><i class="fas fa-ban"></i> Cancel Appointment</button>
                <a href="<?php echo SITE_URL; ?>/user/appointments.php" class="btn-user btn-outline">Discard</a>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var cancelBtn = document.querySelector('[data-cancel-form]');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            var form = document.getElementById('editAppointmentForm');
            if (!form) return;
            form.elements.action.value = 'cancel';
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>