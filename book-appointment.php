<?php
/**
 * Book Appointment Page
 * Elegance Salon
 */
$pageTitle = "Book Appointment - Elegance Salon";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$success = '';
$error = '';

$servicesList = getActiveServices();

// Pre-fill for logged-in clients.
$prefill = [
    'name'  => '',
    'email' => '',
    'phone' => '',
];
if (isLoggedIn()) {
    $prefill['name']  = trim(currentUserFirstName() . ' ' . currentUserLastName());
    $prefill['email'] = currentUserEmail();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Your session expired. Please try again.";
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = trim($_POST['phone'] ?? '');
        $service = (int)($_POST['service'] ?? 0);
        $date    = trim($_POST['date'] ?? '');
        $time    = trim($_POST['time'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        $svc = $service > 0 ? getServiceById($service) : null;

        if ($name === '' || $email === '' || $phone === '' || !$svc || $date === '' || $time === '') {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strtotime($date) < strtotime(date('Y-m-d'))) {
            $error = "Please select a valid date (today or later).";
        } elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            $error = "Please select a valid time.";
        } else {
            try {
                $db = getDBConnection();

                // Split name into first/last (best effort).
                $parts    = preg_split('/\s+/', trim($name), 2);
                $first    = $parts[0] ?? $name;
                $last     = $parts[1] ?? '';
                $clientId = findOrCreateClient($first, $last, $email, $phone, isLoggedIn() ? currentUserId() : null);

                $staffId = nextAvailableStylist($date);

                $stmt = $db->prepare("
                    INSERT INTO appointments
                        (client_id, staff_id, service_id, appointment_date, appointment_time, status, notes, total_amount)
                    VALUES (:cid, :sid, :svc, :d, :t, 'pending', :n, :amt)
                ");
                $stmt->execute([
                    ':cid'  => $clientId,
                    ':sid'  => $staffId,
                    ':svc'  => $svc['service_id'],
                    ':d'    => $date,
                    ':t'    => $time,
                    ':n'    => $notes !== '' ? $notes : null,
                    ':amt'  => $svc['price'],
                ]);
                $apptId = (int)$db->lastInsertId();

                createNotification(
                    1,
                    'New booking request',
                    $name . ' booked "' . $svc['service_name'] . '" on ' . date('M d, Y', strtotime($date)) . ' at ' . date('g:i A', strtotime($time)) . '.',
                    'success'
                );

                setFlash('success', 'Your appointment request has been submitted! Booking #' . $apptId . ' — we will confirm shortly via email or phone.');
                redirect('book-appointment.php?done=1');
            } catch (PDOException $e) {
                $error = "Something went wrong while saving your booking. Please try again.";
            }
        }
    }
}
?>

<!-- Page Header -->
<section class="page-header">
    <div class="page-header-bg" style="background-image: url('https://images.unsplash.com/photo-1521590832167-7bcbfaa6381f?w=1920&q=80');"></div>
    <div class="container page-header-content">
        <div class="page-header-icon"><i class="fas fa-calendar-check"></i></div>
        <h1>Book Appointment</h1>
        <p class="page-header-desc">Ready for your next look? Reserve your visit in seconds — our team will confirm shortly after.</p>
        <div class="breadcrumb">
            <a href="<?php echo SITE_URL; ?>/index.php">Home</a>
            <span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>
            <span class="breadcrumb-current">Book Appointment</span>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 animate-on-scroll">
                <div style="background: var(--card-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 40px;">
                    <div class="text-center mb-4">
                        <span class="section-subtitle">Book Now</span>
                        <h2 class="section-title">Schedule Your Visit</h2>
                        <p style="color: var(--muted);">Fill in the details below to request an appointment.</p>
                    </div>

                    <?php if ($success): ?>
                    <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); color: #22c55e; border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 24px;">
                        <i class="fas fa-check-circle me-2"></i><?php echo sanitize($success); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 24px;">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo sanitize($error); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Full Name *</label>
                                <input type="text" name="name" class="form-control-custom" placeholder="Your full name" required value="<?php echo sanitize($_POST['name'] ?? $prefill['name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Email Address *</label>
                                <input type="email" name="email" class="form-control-custom" placeholder="your@email.com" required value="<?php echo sanitize($_POST['email'] ?? $prefill['email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control-custom" placeholder="+1 (555) 000-0000" required value="<?php echo sanitize($_POST['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Service *</label>
                                <select name="service" id="booking-service" class="form-control-custom" required>
                                    <option value="">Select a service</option>
                                    <?php foreach ($servicesList as $svc): ?>
                                    <option value="<?php echo (int)$svc['service_id']; ?>" data-price="<?php echo (float)$svc['price']; ?>"
                                        <?php echo ((int)($_POST['service'] ?? 0) === (int)$svc['service_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize($svc['service_name']); ?> - $<?php echo number_format($svc['price'], 2); ?>
                                        (<?php echo (int)$svc['duration_minutes']; ?> min)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Preferred Date *</label>
                                <input type="date" name="date" class="form-control-custom" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo sanitize($_POST['date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Preferred Time *</label>
                                <select name="time" class="form-control-custom" required>
                                    <option value="">Select a time</option>
                                    <?php for ($h = 9; $h <= 20; $h++): ?>
                                    <option value="<?php echo sprintf('%02d:00', $h); ?>" <?php echo (isset($_POST['time']) && $_POST['time'] === sprintf('%02d:00', $h)) ? 'selected' : ''; ?>>
                                        <?php echo date('g:i A', strtotime(sprintf('%02d:00', $h))); ?>
                                    </option>
                                    <option value="<?php echo sprintf('%02d:30', $h); ?>" <?php echo (isset($_POST['time']) && $_POST['time'] === sprintf('%02d:30', $h)) ? 'selected' : ''; ?>>
                                        <?php echo date('g:i A', strtotime(sprintf('%02d:30', $h))); ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <div id="booking-summary" class="booking-summary" style="display:none;">
                                    <i class="fas fa-receipt"></i>
                                    <span>Selected service total:</span>
                                    <strong id="booking-total">$0.00</strong>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-accent">
                                    <i class="fas fa-calendar-check"></i> Request Appointment
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
