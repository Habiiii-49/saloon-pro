<?php
/**
 * Elegance Salon - Receptionist - Stylists (view availability)
 */
require_once __DIR__ . '/includes/booking.php';

$stylists = getActiveStylists();

/* Count today's bookings per stylist for a quick workload hint */
$db = getDBConnection();
$today = date('Y-m-d');
$bookingsToday = [];
try {
    $stmt = $db->prepare("SELECT staff_id, COUNT(*) AS c FROM appointments WHERE appointment_date = :d AND status IN ('pending','confirmed','in_progress') GROUP BY staff_id");
    $stmt->execute([':d' => $today]);
    foreach ($stmt->fetchAll() as $r) {
        $bookingsToday[(int)$r['staff_id']] = (int)$r['c'];
    }
} catch (PDOException $e) { /* ignore */ }

$pageTitle = "Stylists";
$activeMenu = 'stylists';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-tie"></i> Stylists</h1>
        <p class="page-subtitle">View stylist availability for the salon floor.</p>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
    <?php if (empty($stylists)): ?>
    <div class="panel" style="grid-column:1/-1">
        <div class="empty-block"><i class="fas fa-user-tie"></i><h6>No stylists available</h6><p>Stylists are assigned by the administrator.</p></div>
    </div>
    <?php else: ?>
    <?php foreach ($stylists as $st): ?>
    <div class="panel" style="margin-bottom:0">
        <div class="panel-body" style="display:flex;gap:.9rem;align-items:flex-start">
            <span class="cr-avatar" style="width:46px;height:46px;border-radius:14px;font-size:1rem">
                <?php echo strtoupper(mb_substr($st['first_name'], 0, 1)); ?>
            </span>
            <div style="flex:1;min-width:0">
                <div style="color:#fff;font-weight:700;font-size:.95rem"><?php echo sanitize(trim($st['first_name'] . ' ' . $st['last_name'])); ?></div>
                <div style="color:var(--text-muted);font-size:.8rem;margin-top:.1rem"><?php echo sanitize($st['specialty'] ?: 'All services'); ?></div>
                <div style="margin-top:.6rem;display:flex;gap:.5rem;flex-wrap:wrap">
                    <?php if ((int)$st['is_available'] === 1): ?>
                    <span class="status-badge active"><i class="fa-solid fa-circle"></i> Available today</span>
                    <?php else: ?>
                    <span class="status-badge inactive"><i class="fa-solid fa-circle"></i> Away</span>
                    <?php endif; ?>
                    <span class="status-badge <?php echo isset($bookingsToday[(int)$st['staff_id']]) ? 'confirmed' : 'info'; ?>">
                        <?php echo (int)($bookingsToday[(int)$st['staff_id']] ?? 0); ?> bookings today
                    </span>
                    <?php if (!empty($st['experience_years'])): ?>
                    <span class="status-badge gold"><?php echo (int)$st['experience_years']; ?> yrs exp</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($st['working_days']) || !empty($st['working_hours'])): ?>
                <div style="margin-top:.7rem;font-size:.76rem;color:var(--text-muted)">
                    <?php if (!empty($st['working_days'])): ?><i class="fas fa-calendar-day"></i> <?php echo sanitize($st['working_days']); ?> &nbsp;<?php endif; ?>
                    <?php if (!empty($st['working_hours'])): ?><i class="fas fa-clock"></i> <?php echo sanitize($st['working_hours']); ?><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>