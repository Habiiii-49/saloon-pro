<?php
/**
 * Elegance Salon - User / Receptionist Panel - Sidebar
 */

function userSidebarIsActive(string $key): bool
{
    global $activeMenu;
    return $activeMenu === $key;
}

$userSidebarMenu = [
    'Overview' => [
        ['key' => 'index',          'label' => 'Dashboard',        'icon' => 'fa-gauge-high',       'url' => 'user/index.php'],
    ],
    'Bookings' => [
        ['key' => 'appointments',   'label' => 'Appointments',     'icon' => 'fa-calendar-check',    'url' => 'user/appointments.php'],
        ['key' => 'appointment-add','label' => 'New Appointment',  'icon' => 'fa-circle-plus',       'url' => 'user/appointment-add.php'],
        ['key' => 'calendar',       'label' => 'Calendar',         'icon' => 'fa-calendar-days',     'url' => 'user/calendar.php'],
    ],
    'Clients' => [
        ['key' => 'clients',        'label' => 'Clients',          'icon' => 'fa-users',             'url' => 'user/clients.php'],
        ['key' => 'client-add',     'label' => 'Add Client',       'icon' => 'fa-user-plus',         'url' => 'user/client-add.php'],
    ],
    'Directory' => [
        ['key' => 'services',       'label' => 'Services',         'icon' => 'fa-scissors',          'url' => 'user/services.php'],
        ['key' => 'stylists',       'label' => 'Stylists',         'icon' => 'fa-user-tie',          'url' => 'user/stylists.php'],
    ],
    'Billing' => [
        ['key' => 'payments',       'label' => 'Payments',         'icon' => 'fa-money-bill-wave',   'url' => 'user/payments.php'],
        ['key' => 'invoices',       'label' => 'Invoices',         'icon' => 'fa-file-invoice-dollar','url' => 'user/invoices.php'],
    ],
    'System' => [
        ['key' => 'notifications',  'label' => 'Notifications',    'icon' => 'fa-bell',              'url' => 'user/notifications.php'],
        ['key' => 'profile',        'label' => 'Profile',          'icon' => 'fa-user-circle',       'url' => 'user/profile.php'],
        ['key' => 'change-password','label' => 'Change Password',  'icon' => 'fa-key',               'url' => 'user/change-password.php'],
    ],
];
?>

<aside class="user-sidebar" id="userSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo"><i class="fas fa-wand-magic-sparkles"></i></div>
        <div>
            <div class="sidebar-brand-name">Elegance Salon</div>
            <div class="sidebar-brand-sub">Reception Desk</div>
        </div>
        <button type="button" class="sidebar-close-mobile d-lg-none" data-user-close aria-label="Close menu">
            <i class="fas fa-xmark"></i>
        </button>
    </div>

    <div class="sidebar-scroll">
        <ul class="sidebar-nav">
            <?php foreach ($userSidebarMenu as $groupLabel => $items): ?>
                <li class="sidebar-group-label"><?php echo $groupLabel; ?></li>
                <?php foreach ($items as $item):
                    $isActive = userSidebarIsActive($item['key']); ?>
                <li>
                    <a class="sidebar-link <?php echo $isActive ? 'active' : ''; ?>"
                       href="<?php echo SITE_URL; ?>/<?php echo $item['url']; ?>"
                       title="<?php echo $item['label']; ?>">
                        <i class="fas <?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['label']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <li class="sidebar-group-label">Session</li>
            <li>
                <a class="sidebar-link logout-link" href="<?php echo SITE_URL; ?>/logout.php" title="Logout">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-footer-clock">
            <i class="fas fa-clock"></i> <span id="sidebarClock">--:-- --</span>
        </div>
        Elegance Salon &copy; <?php echo date('Y'); ?>
    </div>
</aside>