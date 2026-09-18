<?php
/**
 * Elegance Salon - Receptionist Dashboard
 * Operational control center for daily salon activities.
 */
require_once __DIR__ . '/includes/booking.php';
require_once __DIR__ . '/../includes/finance.php';

$pageTitle = "Receptionist Dashboard";
$activeMenu = 'index';

$db = getDBConnection();
$today = date('Y-m-d');

/* ---------- Statistics ---------- */
$stats = [
    'today_appointments' => 0,
    'pending_appointments' => 0,
    'confirmed_appointments' => 0,
    'today_completed' => 0,
    'total_clients' => 0,
    'today_revenue' => 0.00,
    'month_revenue' => 0.00,
    'month_count' => 0,
    'outstanding' => 0.00,
    'unpaid_invoices' => 0,
];
$todayAppointments = [];
$upcomingAppointments = [];
$recentClients = [];
$recentPayments = [];

try {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date = :d AND status <> 'cancelled'");
    $stmt->execute([':d' => $today]);
    $stats['today_appointments'] = (int)$stmt->fetch()['c'];

    $stmt = $db->query("SELECT COUNT(*) AS c FROM appointments WHERE status = 'pending'");
    $stats['pending_appointments'] = (int)$stmt->fetch()['c'];

    $stmt = $db->query("SELECT COUNT(*) AS c FROM appointments WHERE status = 'confirmed'");
    $stats['confirmed_appointments'] = (int)$stmt->fetch()['c'];

    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM appointments WHERE appointment_date = :d AND status = 'completed'");
    $stmt->execute([':d' => $today]);
    $stats['today_completed'] = (int)$stmt->fetch()['c'];

    $stmt = $db->query("SELECT COUNT(*) AS c FROM clients");
    $stats['total_clients'] = (int)$stmt->fetch()['c'];

    /* Collected today, per payment date (payments may now attach to invoices). */
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0) AS total
        FROM payments p
        WHERE DATE(p.paid_at) = :d AND p.payment_status IN ('paid','completed')
    ");
    $stmt->execute([':d' => $today]);
    $stats['today_revenue'] = (float)$stmt->fetch()['total'];

    /* Collected this month + transaction count. */
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt
        FROM payments
        WHERE YEAR(paid_at) = :y AND MONTH(paid_at) = :m AND payment_status IN ('paid','completed')
    ");
    $stmt->execute([':y' => date('Y'), ':m' => date('n')]);
    $mr = $stmt->fetch();
    $stats['month_revenue'] = (float)$mr['total'];
    $stats['month_count']   = (int)$mr['cnt'];

    /* Outstanding across unpaid / partially-paid invoices. */
    $stmt = $db->query("
        SELECT COALESCE(SUM(total - paid_amount), 0) AS bal, COUNT(*) AS cnt
        FROM invoices
        WHERE payment_status IN ('unpaid','partially_paid') AND status <> 'cancelled'
    ");
    $oo = $stmt->fetch();
    $stats['outstanding']    = round(max(0.0, (float)$oo['bal']), 2);
    $stats['unpaid_invoices'] = (int)$oo['cnt'];

    /* ---------- Today's appointments ---------- */
    $stmt = $db->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.end_time, a.status,
               a.total_amount, a.notes, s.service_name, s.price AS service_price,
               CONCAT(c.first_name, ' ', c.last_name) AS client_name, c.email AS client_email,
               CONCAT(u.first_name, ' ', u.last_name) AS stylist_name,
               i.payment_status AS inv_payment_status, i.paid_amount, i.invoice_id, i.invoice_number
        FROM appointments a
        JOIN clients c ON c.client_id = a.client_id
        JOIN services s ON s.service_id = a.service_id
        LEFT JOIN staff st ON st.staff_id = a.staff_id
        LEFT JOIN users u ON u.user_id = st.user_id
        LEFT JOIN invoices i ON i.appointment_id = a.appointment_id
        WHERE a.appointment_date = :d
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([':d' => $today]);
    $todayAppointments = $stmt->fetchAll();

    /* ---------- Upcoming appointments (next 5, chronological) ---------- */
    $stmt = $db->prepare("
        SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.total_amount,
               s.service_name, CONCAT(c.first_name, ' ', c.last_name) AS client_name,
               CONCAT(u.first_name, ' ', u.last_name) AS stylist_name
        FROM appointments a
        JOIN clients c ON c.client_id = a.client_id
        JOIN services s ON s.service_id = a.service_id
        LEFT JOIN staff st ON st.staff_id = a.staff_id
        LEFT JOIN users u ON u.user_id = st.user_id
        WHERE a.appointment_date >= :d AND a.status IN ('pending','confirmed')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([':d' => $today]);
    $upcomingAppointments = $stmt->fetchAll();

    /* ---------- Recent clients ---------- */
    $recentClients = $db->query(
        "SELECT client_id, first_name, last_name, phone, email, created_at,
                (SELECT COUNT(*) FROM appointments a2 WHERE a2.client_id = clients.client_id) AS visits
         FROM clients ORDER BY created_at DESC LIMIT 5"
    )->fetchAll();

    /* ---------- Recent payments ---------- */
    $recentPayments = $db->query("
        SELECT p.payment_id, p.amount, p.payment_method, p.payment_status, p.paid_at,
               p.transaction_ref, i.invoice_number, i.invoice_id,
               CONCAT(c.first_name, ' ', c.last_name) AS client_name
        FROM payments p
        LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
        LEFT JOIN clients c ON c.client_id = COALESCE(p.client_id, i.client_id)
        WHERE p.payment_status IN ('paid','completed')
        ORDER BY p.paid_at DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    // friendly fallback - stats remain zero, tables empty
}

/* Status display map */
$statusLabels = [
    'pending'     => ['Pending', 'pending'],
    'confirmed'   => ['Confirmed', 'confirmed'],
    'in_progress' => ['In Progress', 'in_progress'],
    'completed'   => ['Completed', 'completed'],
    'cancelled'   => ['Cancelled', 'cancelled'],
    'no_show'     => ['No Show', 'no_show'],
];
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- WELCOME -->
<section class="hero-welcome">
    <h1>Welcome back, <span><?php echo sanitize(currentUserFirstName() ?: currentUserName()); ?></span> 👋</h1>
    <p>Manage today's appointments, clients and salon activities.</p>
    <div class="hero-date"><i class="fas fa-calendar-day"></i> <?php echo date('l, F j, Y'); ?></div>
</section>

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-meta">
            <div class="stat-value" data-count="<?php echo (int)$stats['today_appointments']; ?>">0</div>
            <div class="stat-label">Today's Appointments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-meta">
            <div class="stat-value" data-count="<?php echo (int)$stats['pending_appointments']; ?>">0</div>
            <div class="stat-label">Pending Appointments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-check-to-slot"></i></div>
        <div class="stat-meta">
            <div class="stat-value" data-count="<?php echo (int)$stats['confirmed_appointments']; ?>">0</div>
            <div class="stat-label">Confirmed Appointments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-scissors"></i></div>
        <div class="stat-meta">
            <div class="stat-value" data-count="<?php echo (int)$stats['today_completed']; ?>">0</div>
            <div class="stat-label">Today's Completed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-users"></i></div>
        <div class="stat-meta">
            <div class="stat-value" data-count="<?php echo (int)$stats['total_clients']; ?>">0</div>
            <div class="stat-label">Total Clients</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-sack-dollar"></i></div>
        <div class="stat-meta">
            <div class="stat-value"><?php echo formatMoney($stats['today_revenue']); ?></div>
            <div class="stat-label">Today's Collection</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon cyan"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-meta">
            <div class="stat-value"><?php echo formatMoney($stats['month_revenue']); ?></div>
            <div class="stat-label"><?php echo date('F'); ?> Collection (<?php echo (int)$stats['month_count']; ?> txns)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-hand-holding-dollar"></i></div>
        <div class="stat-meta">
            <div class="stat-value"><?php echo formatMoney($stats['outstanding']); ?></div>
            <div class="stat-label">Outstanding (<?php echo (int)$stats['unpaid_invoices']; ?> invoices)</div>
        </div>
    </div>
</div>

<!-- RECENT PAYMENTS -->
<div class="panel" style="grid-column:1/-1">
    <div class="panel-head">
        <h5><i class="fas fa-money-bill-wave"></i> Recent Payments</h5>
        <div class="panel-actions">
            <a href="<?php echo SITE_URL; ?>/user/payments.php" class="btn-user btn-outline btn-sm">View all</a>
        </div>
    </div>
    <div class="table-responsive-wrap">
        <table class="table-salon">
            <thead>
                <tr><th>Client</th><th>Invoice</th><th>Method</th><th>Amount</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php if (empty($recentPayments)): ?>
                <tr><td colspan="5"><div class="table-empty"><i class="fas fa-money-bill-wave"></i><p>No payments collected yet.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($recentPayments as $rp): ?>
                <tr>
                    <td class="cell-main"><?php echo sanitize($rp['client_name'] ?: '—'); ?></td>
                    <td>
                        <?php if ($rp['invoice_number']): ?><a href="<?php echo SITE_URL; ?>/user/invoice-view.php?invoice_id=<?php echo (int)$rp['invoice_id']; ?>" class="text-cyan-link"><?php echo sanitize($rp['invoice_number']); ?></a><?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $rp['payment_method'])); ?></td>
                    <td class="stat-inline"><?php echo formatMoney($rp['amount']); ?></td>
                    <td><?php echo formatDate($rp['paid_at'], 'M d, Y g:i A'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2col">

    <!-- TODAY'S APPOINTMENTS -->
    <div class="panel">
        <div class="panel-head">
            <h5><i class="fas fa-calendar-day"></i> Today's Appointments</h5>
            <div class="panel-actions">
                <a href="<?php echo SITE_URL; ?>/user/appointments.php" class="btn-user btn-outline btn-sm">View all</a>
            </div>
        </div>
        <div class="table-responsive-wrap">
            <table class="table-salon">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Stylist</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todayAppointments)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="table-empty">
                                <i class="fas fa-calendar-xmark"></i>
                                <p>No appointments scheduled for today.</p>
                                <a href="<?php echo SITE_URL; ?>/user/appointment-add.php" class="btn-user btn-cyan">+ New Appointment</a>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($todayAppointments as $a):
                        $sl = $statusLabels[$a['status']] ?? [ucfirst($a['status']), 'pending']; ?>
                    <tr>
                        <td>
                            <span class="cell-main"><?php echo formatSlotTime($a['appointment_time']); ?></span>
                            <span class="cell-sub"><?php echo !empty($a['end_time']) ? 'until ' . formatSlotTime($a['end_time']) : ''; ?></span>
                        </td>
                        <td class="cell-main"><?php echo sanitize($a['client_name']); ?></td>
                        <td><?php echo sanitize($a['service_name']); ?></td>
                        <td><?php echo sanitize($a['stylist_name'] ?: '—'); ?></td>
                        <td><span class="status-badge <?php echo $sl[1]; ?>"><?php echo $sl[0]; ?></span></td>
                        <td>
                            <?php if ($a['inv_payment_status'] === 'paid'): ?>
                                <span class="status-badge paid"><?php echo formatMoney($a['paid_amount'] ?? 0); ?></span>
                            <?php else: ?>
                                <?php echo financePaymentStatusBadge($a['inv_payment_status']); ?>
                                <?php if ($a['invoice_number']): ?>
                                <span class="cell-sub"><a href="<?php echo SITE_URL; ?>/user/invoice-view.php?invoice_id=<?php echo (int)$a['invoice_id']; ?>" class="text-cyan-link"><?php echo sanitize($a['invoice_number']); ?></a></span>
                                <?php elseif ($a['status'] === 'completed'): ?>
                                <span class="cell-sub"><a href="<?php echo SITE_URL; ?>/user/payments.php" class="text-cyan-link">Record payment</a></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- UPCOMING APPOINTMENTS -->
    <div class="panel">
        <div class="panel-head">
            <h5><i class="fas fa-arrow-trend-up"></i> Upcoming Appointments</h5>
            <div class="panel-actions">
                <a href="<?php echo SITE_URL; ?>/user/calendar.php" class="btn-user btn-outline btn-sm"><i class="fas fa-calendar-days"></i> Calendar</a>
            </div>
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingAppointments)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="table-empty">
                                <i class="fas fa-calendar-xmark"></i>
                                <p>No upcoming appointments.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($upcomingAppointments as $a):
                        $sl = $statusLabels[$a['status']] ?? [ucfirst($a['status']), 'pending']; ?>
                    <tr>
                        <td class="cell-main"><?php echo formatDate($a['appointment_date'], 'M d'); ?><span class="cell-sub"><?php echo formatDate($a['appointment_date'], 'Y'); ?></span></td>
                        <td><?php echo formatSlotTime($a['appointment_time']); ?></td>
                        <td><?php echo sanitize($a['client_name']); ?></td>
                        <td><?php echo sanitize($a['service_name']); ?></td>
                        <td><?php echo sanitize($a['stylist_name'] ?: '—'); ?></td>
                        <td><span class="status-badge <?php echo $sl[1]; ?>"><?php echo $sl[0]; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid-2col">

    <!-- RECENT CLIENTS -->
    <div class="panel">
        <div class="panel-head">
            <h5><i class="fas fa-users"></i> Recent Clients</h5>
            <div class="panel-actions">
                <a href="<?php echo SITE_URL; ?>/user/clients.php" class="btn-user btn-outline btn-sm">View all</a>
            </div>
        </div>
        <div class="list-group-salon">
            <?php if (empty($recentClients)): ?>
            <div class="empty-block"><i class="fas fa-user-slash"></i><h6>No clients yet</h6><p>Add your first client to get started.</p></div>
            <?php else: ?>
            <?php foreach ($recentClients as $c): ?>
            <a class="lgi" href="<?php echo SITE_URL; ?>/user/client-view.php?client_id=<?php echo (int)$c['client_id']; ?>">
                <span class="cr-avatar"><?php echo strtoupper(mb_substr($c['first_name'], 0, 1)); ?></span>
                <div class="cr-main" style="min-width:0">
                    <div class="cr-name" style="color:#fff"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></div>
                    <div class="cr-sub"><?php echo sanitize($c['phone'] ?: ($c['email'] ?: '—')); ?></div>
                </div>
                <span class="status-badge completed ms-auto"><?php echo (int)$c['visits']; ?> visits</span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="panel">
        <div class="panel-head"><h5><i class="fas fa-bolt"></i> Quick Actions</h5></div>
        <div class="panel-body">
            <div class="quick-actions">
                <a href="<?php echo SITE_URL; ?>/user/appointment-add.php" class="quick-action">
                    <i class="fas fa-calendar-plus"></i><span>New Appointment</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/user/client-add.php" class="quick-action">
                    <i class="fas fa-user-plus"></i><span>Add Client</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/user/clients.php" class="quick-action">
                    <i class="fas fa-users-viewfinder"></i><span>View Clients</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/user/calendar.php" class="quick-action">
                    <i class="fas fa-calendar-days"></i><span>View Calendar</span>
                </a>
            </div>

            <div class="change-inside" style="margin-top:1.1rem">
                <div class="form-group">
                    <label><i class="fas fa-bell"></i> Appointment Reminders <span class="text-muted">(Email &amp; SMS channels ready — provider connection pending)</span></label>
                    <div class="list-group-salon" style="border:1px solid var(--border);border-radius:10px">
                        <?php if (empty($upcomingAppointments)): ?>
                        <div class="table-empty"><i class="fas fa-bell-slash"></i><p>No pending reminders. Book an appointment to see reminders here.</p></div>
                        <?php else: ?>
                        <?php foreach (array_slice($upcomingAppointments, 0, 3) as $a): ?>
                        <div class="lgi" style="border-bottom:1px solid var(--border)">
                            <i class="fas fa-clock" style="color:var(--accent)"></i>
                            <div>
                                <div style="color:#fff;font-size:.85rem;font-weight:600"><?php echo sanitize($a['client_name']); ?> — <?php echo sanitize($a['service_name']); ?></div>
                                <small style="color:var(--text-muted)"><?php echo formatDate($a['appointment_date'], 'D, M j'); ?> &middot; <?php echo formatSlotTime($a['appointment_time']); ?></small>
                            </div>
                            <span class="status-badge pending ms-auto">Remind</span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>