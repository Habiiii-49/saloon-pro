<?php
/**
 * Admin - Coming Soon placeholder
 * Used for modules planned for future parts, so no broken URLs exist.
 */
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageKey   = $_GET['page'] ?? 'module';
$pageKey   = preg_replace('/[^a-z0-9_-]/', '', $pageKey);

$pagesInfo = [
    'appointments'  => ['fa-calendar-check',      'Appointments'],
    'clients'       => ['fa-users',              'Clients'],
    'services'      => ['fa-scissors',           'Services'],
    'staff'         => ['fa-user-tie',           'Staff / Stylists'],
    'inventory'     => ['fa-boxes-stacked',      'Inventory'],
    'suppliers'     => ['fa-truck',              'Suppliers'],
    'payments'      => ['fa-money-bill-wave',    'Payments'],
    'invoices'      => ['fa-file-invoice-dollar','Invoices'],
    'commissions'   => ['fa-percent',            'Commissions'],
    'calendar'      => ['fa-calendar-days',      'Calendar'],
    'reports'       => ['fa-chart-pie',          'Reports'],
    'gallery'       => ['fa-images',             'Gallery'],
    'feedback'      => ['fa-star',               'Feedback'],
    'notifications' => ['fa-bell',               'Notifications'],
    'settings'      => ['fa-gear',               'Settings'],
];

[$pageIcon, $pageTitle] = $pagesInfo[$pageKey] ?? ['fa-cubes', 'Management Module'];
$pageTitleFull = $pageTitle . ' - Elegance Salon Admin';
$activeMenu = $pageKey;
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas <?php echo $pageIcon; ?>"></i> <?php echo sanitize($pageTitle); ?></h1>
        <p>This module is part of the next release.</p>
    </div>
    <span class="page-date"><i class="far fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?></span>
</div>

<div class="admin-card">
    <div class="card-body-custom">
        <div class="empty-state" style="padding:4rem 1.5rem;">
            <i class="fas fa-rocket"></i>
            <h4 style="font-weight:700;margin:0 0 0.5rem;">Coming Soon</h4>
            <h5><?php echo sanitize($pageTitle); ?></h5>
            <p>The <?php echo sanitize($pageTitle); ?> module will be available in a future update. The database tables are already prepared and ready.</p>
            <a href="<?php echo SITE_URL; ?>/admin/index.php" class="btn-admin btn-cyan"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>