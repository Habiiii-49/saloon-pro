<?php
/**
 * Elegance Salon - Receptionist - New Appointment (wizard)
 * Steps: Client -> Service -> Stylist -> Date -> Slots -> Confirm
 * Server-side slot validation (double-booking protection) enforced on POST.
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$today = date('Y-m-d');
$clients = [];
$services = [];
$stylists = [];

try {
    $clients = $db->query("SELECT client_id, first_name, last_name, phone, email, updated_at FROM clients ORDER BY first_name ASC")->fetchAll();
    $services = getReceptionistServices();
    $stylists = getActiveStylists();
} catch (PDOException $e) {
    // handled below
}

/* ---------- POST: create appointment ---------- */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please refresh the page and try again.');
        redirect('user/appointment-add.php');
    }

    $clientId    = (int)($_POST['client_id'] ?? 0);
    $serviceId   = (int)($_POST['service_id'] ?? 0);
    $staffId     = (int)($_POST['staff_id'] ?? 0);
    $date        = trim($_POST['appointment_date'] ?? '');
    $time        = trim($_POST['appointment_time'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($clientId <= 0 || !getClientById($clientId)) {
        $errors[] = 'Please choose a valid client.';
    }
    if ($serviceId <= 0 || !($service = getServiceById($serviceId))) {
        $errors[] = 'Please choose a valid service.';
    }
    $validStaff = false;
    foreach ($stylists as $st) {
        if ((int)$st['staff_id'] === $staffId && (int)$st['is_available'] === 1) { $validStaff = true; break; }
    }
    if (!$validStaff) {
        $errors[] = 'Please choose a valid, available stylist.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $errors[] = 'Please choose a valid date.';
    } elseif ($date < $today) {
        $errors[] = 'You cannot book an appointment in the past.';
    }
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        $errors[] = 'Please select a valid time slot.';
    }

    // Overlap / double-booking check (server side - never trust the browser)
    if (!$errors && isset($service)) {
        [$slotOk, $slotMsg] = validateAppointmentSlot($staffId, $date, $time, $serviceId);
        if (!$slotOk) {
            $errors[] = $slotMsg;
        }
    }

    if (empty($errors)) {
        try {
            file_put_contents('C:/xampp/htdocs/saloon-pro/user/_dbg_log.txt', date('H:i:s') . " METHOD={$_SERVER['REQUEST_METHOD']} before-begin errors=" . count($errors) . ' hs=' . (headers_sent() ? 'yes' : 'no') . "\n", FILE_APPEND);
            $db->beginTransaction();

            $endTime = minutesToTime(timeToMinutes($time) + max(1, (int)$service['duration_minutes']));
            $stmt = $db->prepare("
                INSERT INTO appointments (client_id, staff_id, service_id, appointment_date, appointment_time, end_time, status, notes, total_amount)
                VALUES (:c, :stf, :sv, :d, :t, :e, 'pending', :n, :amt)
            ");
            $stmt->execute([
                ':c'   => $clientId,
                ':stf' => $staffId,
                ':sv'  => $serviceId,
                ':d'   => $date,
                ':t'   => $time,
                ':e'   => $endTime,
                ':n'   => $notes !== '' ? $notes : null,
                ':amt' => $service['price'],
            ]);
            $newId = (int)$db->lastInsertId();

            $db->commit();

            // Update client last_visit
            $db->prepare("UPDATE clients SET last_visit = :d WHERE client_id = :c AND (last_visit IS NULL OR last_visit < :d)")
               ->execute([':d' => $date, ':c' => $clientId]);

            createNotification(null, 'New Appointment Booked',
                "Appointment #{$newId} created for " . date('M d, Y', strtotime($date)) . ' at ' . formatSlotTime($time) . '.', 'info');

            setFlash('success', 'Appointment booked successfully. No conflicts on the selected slot.');
            file_put_contents('C:/xampp/htdocs/saloon-pro/user/_dbg_log.txt', date('H:i:s') . " about-to-redirect id=$newId hs=" . (headers_sent() ? 'yes' : 'no') . "\n", FILE_APPEND);
            redirect('user/appointment-edit.php?id=' . $newId);
        } catch (PDOException $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $errors[] = 'Could not create the appointment. Please try again.';
        }
    }
    foreach ($errors as $err) {
        setFlash('error', $err);
    }
}

$pageTitle = "New Appointment";
$activeMenu = 'appointment-add';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-wand-magic-sparkles"></i> New Appointment</h1>
        <p class="page-subtitle">Follow the steps to block a slot. Double-booking is checked server-side.</p>
    </div>
    <div class="page-head-right">
        <a href="<?php echo SITE_URL; ?>/user/appointments.php" class="btn-user btn-outline"><i class="fas fa-arrow-left"></i> Back to Appointments</a>
    </div>
</div>

<div class="wizard-steps" id="wizardSteps">
    <div class="wizard-step active" data-step="1"><span class="wz-num">1</span> Select Client</div>
    <div class="wizard-step" data-step="2"><span class="wz-num">2</span> Select Service</div>
    <div class="wizard-step" data-step="3"><span class="wz-num">3</span> Select Stylist</div>
    <div class="wizard-step" data-step="4"><span class="wz-num">4</span> Select Date</div>
    <div class="wizard-step" data-step="5"><span class="wz-num">5</span> Pick Slot</div>
    <div class="wizard-step" data-step="6"><span class="wz-num">6</span> Confirm</div>
</div>

<form method="post" action="" id="appointmentForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

    <!-- STEP 1: CLIENT -->
    <div class="panel wizard-pane active" data-step="1">
        <div class="panel-head"><h5><i class="fas fa-user"></i> Step 1 - Client</h5></div>
        <div class="panel-body">
            <div class="client-picker-head">
                <div class="input-with-icon" style="flex:1;min-width:240px">
                    <i class="fas fa-magnifying-glass"></i>
                    <input type="text" id="clientSearchInput" class="form-control-salon" placeholder="Search by name, phone or email (min 2 chars)...">
                </div>
                <a href="<?php echo SITE_URL; ?>/user/client-add.php?back=appointment-add" class="btn-user btn-outline"><i class="fas fa-user-plus"></i> + Add New Client</a>
            </div>
            <div id="clientResults"></div>

            <div id="selectedClientBox" style="margin-top:0.8rem"></div>
            <input type="hidden" id="wizardClientId" name="client_id">

            <div class="form-group" style="margin-top:1rem">
                <label for="quickNewFirstName">In a rush? Create a walk-in client:</label>
                <div class="form-grid">
                    <input type="text" id="quickNewFirstName" class="form-control-salon" placeholder="First name">
                    <input type="text" id="quickNewLastName" class="form-control-salon" placeholder="Last name">
                    <input type="tel" id="quickNewPhone" class="form-control-salon" placeholder="Phone">
                </div>
                <button type="button" class="btn-user btn-outline" id="quickAddClientBtn" style="margin-top:.6rem"><i class="fas fa-user-plus"></i> Quick Add &amp; Select</button>
            </div>
        </div>
    </div>

    <!-- STEP 2: SERVICE -->
    <div class="panel wizard-pane" data-step="2">
        <div class="panel-head"><h5><i class="fas fa-scissors"></i> Step 2 - Service</h5></div>
        <div class="panel-body">
            <p class="step-note">Select the service. Availability is computed from the service duration.</p>
            <div class="slot-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
                <?php if (empty($services)): ?>
                <div class="empty-block" style="grid-column:1/-1"><i class="fas fa-scissors"></i><h6>No active services</h6><p>Services need to be added by the administrator.</p></div>
                <?php else: ?>
                <?php foreach ($services as $sv): ?>
                <button type="button" class="slot-btn service-choice" data-service="<?php echo (int)$sv['service_id']; ?>"
                        data-svc-name="<?php echo sanitize($sv['service_name']); ?>"
                        data-svc-price="<?php echo formatCurrency($sv['price']); ?>"
                        data-svc-dur="<?php echo (int)$sv['duration_minutes']; ?>">
                    <b><?php echo sanitize($sv['service_name']); ?></b><br>
                    <small style="opacity:.85"><?php echo (int)$sv['duration_minutes']; ?> min &middot; <?php echo formatCurrency($sv['price']); ?></small>
                </button>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="change-inside" id="selectedServiceBox"></div>
            <input type="hidden" id="slotServiceId" name="service_id">
        </div>
    </div>

    <!-- STEP 3: STYLIST -->
    <div class="panel wizard-pane" data-step="3">
        <div class="panel-head"><h5><i class="fas fa-user-tie"></i> Step 3 - Stylist</h5></div>
        <div class="panel-body">
            <p class="step-note">Only active stylists are selectable.</p>
            <div class="slot-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
                <?php if (empty($stylists)): ?>
                <div class="empty-block" style="grid-column:1/-1"><i class="fas fa-user-tie"></i><h6>No stylists available</h6><p>Stylists need to be assigned by the administrator.</p></div>
                <?php else: ?>
                <?php foreach ($stylists as $st): ?>
                <button type="button" class="slot-btn stylist-choice <?php echo (int)$st['is_available'] === 1 ? '' : 'skipped'; ?>"
                        data-staff="<?php echo (int)$st['staff_id']; ?>"
                        data-stf-name="<?php echo sanitize(trim($st['first_name'] . ' ' . $st['last_name'])); ?>"
                        data-stf-specialty="<?php echo sanitize($st['specialty'] ?: 'Stylist'); ?>"
                        <?php echo (int)$st['is_available'] === 1 ? '' : 'disabled'; ?>>
                    <b><?php echo sanitize(trim($st['first_name'] . ' ' . $st['last_name'])); ?></b><br>
                    <small style="opacity:.85"><?php echo sanitize($st['specialty'] ?: 'All services'); ?></small>
                    <?php if ((int)$st['is_available'] === 1): ?><br><small style="color:var(--success)"><i class="fas fa-circle"></i> Available</small>
                    <?php else: ?><br><small style="color:var(--text-muted)">Unavailable</small><?php endif; ?>
                </button>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="change-inside" id="selectedStylistBox"></div>
            <input type="hidden" id="slotStaffId" name="staff_id">
        </div>
    </div>

    <!-- STEP 4: DATE -->
    <div class="panel wizard-pane" data-step="4">
        <div class="panel-head"><h5><i class="fas fa-calendar-day"></i> Step 4 - Date</h5></div>
        <div class="panel-body">
            <p class="step-note">Past dates are disabled. Choose a date to load available slots.</p>
            <input type="date" id="slotDate" name="appointment_date" class="form-control-salon" style="max-width:280px"
                   min="<?php echo $today; ?>" data-slot-deps>
        </div>
    </div>

    <!-- STEP 5: SLOTS -->
    <div class="panel wizard-pane" data-step="5">
        <div class="panel-head"><h5><i class="fas fa-clock"></i> Step 5 - Available Time Slots</h5></div>
        <div class="panel-body">
            <p class="step-note">Slots already block overlap with the selected stylist. Slot length matches the service duration.</p>
            <div class="slot-grid" id="slotResults">
                <div class="slot-empty" style="grid-column:1/-1"><i class="fas fa-circle-info"></i> Select service, stylist and date to see available slots.</div>
            </div>
        </div>
    </div>

    <!-- STEP 6: CONFIRM -->
    <div class="panel wizard-pane" data-step="6">
        <div class="panel-head"><h5><i class="fas fa-circle-check"></i> Step 6 - Confirm</h5></div>
        <div class="panel-body">
            <div class="detail-list" id="confirmSummary"></div>
            <input type="hidden" id="wizardTime" name="appointment_time">

            <div class="form-group" style="margin-top:1.2rem">
                <label for="notes">Notes <span class="text-muted">(optional)</span></label>
                <textarea class="form-control-salon" id="notes" name="notes" placeholder="Preferences, allergies, special requests..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-user btn-cyan"><i class="fas fa-check"></i> Confirm Appointment</button>
                <button type="button" class="btn-user btn-outline" data-wz-prev><i class="fas fa-arrow-left"></i> Back</button>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>