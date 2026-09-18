<?php
/**
 * Elegance Salon - Receptionist - Services (view only)
 * Service management (create/delete) remains admin-controlled.
 */
require_once __DIR__ . '/includes/booking.php';

$services = getReceptionistServices();

$pageTitle = "Services";
$activeMenu = 'services';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-scissors"></i> Services</h1>
        <p class="page-subtitle">Salon service menu (managed by the administrator).</p>
    </div>
</div>

<div class="panel">
    <div class="table-responsive-wrap">
        <table class="table-salon">
            <thead>
                <tr><th>Service</th><th>Category</th><th>Duration</th><th>Price</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($services)): ?>
                <tr><td colspan="5"><div class="table-empty"><i class="fas fa-scissors"></i><p>No active services available.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($services as $sv): ?>
                <tr>
                    <td class="cell-main">
                        <?php echo sanitize($sv['service_name']); ?>
                        <?php if (!empty($sv['description'])): ?><span class="cell-sub"><?php echo sanitize($sv['description']); ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($sv['category'])): ?>
                        <span class="status-badge info"><?php echo sanitize($sv['category']); ?></span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?php echo (int)$sv['duration_minutes']; ?> min</td>
                    <td class="stat-inline"><?php echo formatCurrency($sv['price']); ?></td>
                    <td><span class="status-badge active">Active</span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>