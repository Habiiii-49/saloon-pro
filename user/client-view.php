<?php
/**
 * Elegance Salon - Receptionist - Client Profile / History
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$clientId = (int)($_GET['client_id'] ?? 0);

$client = $clientId > 0 ? getClientById($clientId) : null;
if (!$client) {
    setFlash('error', 'Client not found.');
    redirect('user/clients.php');
}

/* Stats */
$statsStmt = $db->prepare("
    SELECT
        (SELECT COUNT(*) FROM appointments a WHERE a.client_id = :id) AS total_appointments,
        (SELECT COUNT(*) FROM appointments a WHERE a.client_id = :id AND a.status = 'completed') AS completed_appointments,
        (SELECT COUNT(*) FROM appointments a WHERE a.client_id = :id AND a.status = 'cancelled') AS cancelled_appointments,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN appointments a ON a.appointment_id = p.appointment_id WHERE a.client_id = :id AND p.payment_status IN ('paid','completed')) AS total_spent
");
$statsStmt->execute([':id' => $clientId]);
$stats = $statsStmt->fetch();

/* History */
$historyStmt = $db->prepare("
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.status, a.total_amount,
           s.service_name, CONCAT(u.first_name,' ',u.last_name) AS stylist_name
    FROM appointments a
    JOIN services s ON s.service_id = a.service_id
    LEFT JOIN staff st ON st.staff_id = a.staff_id
    LEFT JOIN users u ON u.user_id = st.user_id
    WHERE a.client_id = :id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$historyStmt->execute([':id' => $clientId]);
$history = $historyStmt->fetchAll();

$preferredStaff = null;
if (!empty($client['preferred_staff_id'])) {
    foreach (getActiveStylists() as $s) {
        if ((int)$s['staff_id'] === (int)$client['preferred_staff_id']) { $preferredStaff = $s; break; }
    }
}
$preferredService = !empty($client['preferred_service_id']) ? getServiceById((int)$client['preferred_service_id']) : null;

$statusLabels = [
    'pending' => ['Pending', 'pending'], 'confirmed' => ['Confirmed', 'confirmed'],
    'in_progress' => ['In Progress', 'in_progress'], 'completed' => ['Completed', 'completed'],
    'cancelled' => ['Cancelled', 'cancelled'], 'no_show' => ['No Show', 'no_show'],
];

$pageTitle = "Client Profile";
$activeMenu = 'clients';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-circle-user"></i> Client Profile</h1>
        <p class="page-subtitle"><?php echo sanitize($client['first_name'] . ' ' . $client['last_name']); ?></p>
    </div>
    <div class="page-head-right">
        <a href="<?php echo SITE_URL; ?>/user/client-edit.php?client_id=<?php echo (int)$clientId; ?>" class="btn-user btn-outline"><i class="fas fa-pen-to-square"></i> Edit</a>
        <a href="<?php echo SITE_URL; ?>/user/appointment-add.php" class="btn-user btn-cyan"><i class="fas fa-calendar-plus"></i> New Appointment</a>
    </div>
</div>

<div class="grid-2col">
    <!-- INFO -->
    <div class="panel">
        <div class="panel-head"><h5><i class="fas fa-address-card"></i> Client Information</h5></div>
        <div class="panel-body">
            <div class="detail-list">
                <div class="detail-item"><div class="dl-label">Name</div><div class="dl-value"><?php echo sanitize($client['first_name'] . ' ' . $client['last_name']); ?></div></div>
                <div class="detail-item"><div class="dl-label">Phone</div><div class="dl-value"><?php echo sanitize($client['phone'] ?: '—'); ?></div></div>
                <div class="detail-item"><div class="dl-label">Email</div><div class="dl-value"><?php echo sanitize($client['email'] ?: '—'); ?></div></div>
                <div class="detail-item"><div class="dl-label">Gender</div><div class="dl-value"><?php echo ucfirst($client['gender'] ?? '—'); ?></div></div>
                <div class="detail-item"><div class="dl-label">Date of Birth</div><div class="dl-value"><?php echo !empty($client['dob']) ? formatDate($client['dob'], 'M d, Y') : '—'; ?></div></div>
                <div class="detail-item"><div class="dl-label">Address</div><div class="dl-value"><?php echo sanitize($client['address'] ?: '—'); ?></div></div>
                <div class="detail-item"><div class="dl-label">Preferred Stylist</div><div class="dl-value"><?php echo $preferredStaff ? sanitize(trim($preferredStaff['first_name'] . ' ' . $preferredStaff['last_name'])) : '—'; ?></div></div>
                <div class="detail-item"><div class="dl-label">Preferred Service</div><div class="dl-value"><?php echo $preferredService ? sanitize($preferredService['service_name']) : '—'; ?></div></div>
                <div class="detail-item" style="grid-column:1/-1"><div class="dl-label">Notes</div><div class="dl-value"><?php echo sanitize($client['notes'] ?: '—'); ?></div></div>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="panel">
        <div class="panel-head"><h5><i class="fas fa-chart-simple"></i> Client Summary</h5></div>
        <div class="panel-body">
            <div class="stats-grid" style="grid-template-columns:repeat(2,1fr)">
                <div class="stat-card">
                    <div class="stat-icon cyan"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-meta"><div class="stat-value"><?php echo (int)$stats['total_appointments']; ?></div><div class="stat-label">Total Appointments</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
                    <div class="stat-meta"><div class="stat-value"><?php echo (int)$stats['completed_appointments']; ?></div><div class="stat-label">Completed</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-ban"></i></div>
                    <div class="stat-meta"><div class="stat-value"><?php echo (int)$stats['cancelled_appointments']; ?></div><div class="stat-label">Cancelled</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon gold"><i class="fas fa-sack-dollar"></i></div>
                    <div class="stat-meta"><div class="stat-value"><?php echo formatCurrency($stats['total_spent']); ?></div><div class="stat-label">Total Spent</div></div>
                </div>
            </div>
            <?php if ((int)$client['loyalty_points'] > 0): ?>
            <div class="status-badge gold" style="margin-top:1rem"><i class="fas fa-star"></i> <?php echo (int)$client['loyalty_points']; ?> loyalty points</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- HISTORY -->
<div class="panel">
    <div class="panel-head"><h5><i class="fas fa-clock-rotate-left"></i> Appointment History</h5></div>
    <div class="table-responsive-wrap">
        <table class="table-salon">
            <thead>
                <tr><th>Date</th><th>Time</th><th>Service</th><th>Stylist</th><th>Amount</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr><td colspan="7"><div class="table-empty"><i class="fas fa-calendar-xmark"></i><p>No appointments recorded for this client yet.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($history as $h):
                    $sl = $statusLabels[$h['status']] ?? [ucfirst($h['status']), 'pending']; ?>
                <tr>
                    <td class="cell-main"><?php echo formatDate($h['appointment_date'], 'M d, Y'); ?></td>
                    <td><?php echo formatSlotTime($h['appointment_time']); ?></td>
                    <td><?php echo sanitize($h['service_name']); ?></td>
                    <td><?php echo sanitize($h['stylist_name'] ?: '—'); ?></td>
                    <td class="stat-inline"><?php echo formatCurrency($h['total_amount']); ?></td>
                    <td><span class="status-badge <?php echo $sl[1]; ?>"><?php echo $sl[0]; ?></span></td>
                    <td><div class="tbl-actions"><a href="<?php echo SITE_URL; ?>/user/appointment-edit.php?id=<?php echo (int)$h['appointment_id']; ?>" title="View / Edit"><i class="fas fa-eye"></i></a></div></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>