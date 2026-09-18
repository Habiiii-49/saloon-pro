<?php
/**
 * Admin Panel - Sidebar
 */

// Sidebar is rendered inside admin-layout (from header.php).
function adminSidebarIsActive(string $key): bool
{
    global $activeMenu;
    return $activeMenu === $key;
}

/* Menu configuration */
$adminSidebarMenu = [
    'main' => [
        ['key' => 'index',        'label' => 'Dashboard',    'icon' => 'fa-gauge-high',       'url' => 'admin/index.php'],
    ],
    'Management' => [
        ['key' => 'appointments', 'label' => 'Appointments', 'icon' => 'fa-calendar-check',   'url' => 'admin/coming-soon.php?page=appointments'],
        ['key' => 'clients',      'label' => 'Clients',      'icon' => 'fa-users',            'url' => 'admin/coming-soon.php?page=clients'],
        ['key' => 'services',     'label' => 'Services',     'icon' => 'fa-scissors',         'url' => 'admin/coming-soon.php?page=services'],
        ['key' => 'staff',        'label' => 'Staff / Stylists', 'icon' => 'fa-user-tie',     'url' => 'admin/coming-soon.php?page=staff'],
        [
            'key'      => 'inventory',
            'label'    => 'Inventory',
            'icon'     => 'fa-boxes-stacked',
            'url'      => 'admin/inventory/index.php',
            'children' => [
                ['key' => 'inv-dashboard',    'label' => 'Inventory Dashboard', 'icon' => 'fa-gauge-high',            'url' => 'admin/inventory/index.php'],
                ['key' => 'products',         'label' => 'Products',            'icon' => 'fa-box',                  'url' => 'admin/inventory/products.php'],
                ['key' => 'product-add',      'label' => 'Add Product',         'icon' => 'fa-box-open',             'url' => 'admin/inventory/product-add.php'],
                ['key' => 'categories',       'label' => 'Categories',          'icon' => 'fa-tags',                 'url' => 'admin/inventory/categories.php'],
                ['key' => 'stock-in',         'label' => 'Stock In',            'icon' => 'fa-arrow-down-to-bracket','url' => 'admin/inventory/stock-in.php'],
                ['key' => 'stock-out',        'label' => 'Stock Out',           'icon' => 'fa-arrow-up-from-bracket','url' => 'admin/inventory/stock-out.php'],
                ['key' => 'stock-adjustment', 'label' => 'Stock Adjustments',   'icon' => 'fa-scale-balanced',       'url' => 'admin/inventory/stock-adjustment.php'],
                ['key' => 'suppliers',        'label' => 'Suppliers',           'icon' => 'fa-truck',                'url' => 'admin/inventory/suppliers.php'],
                ['key' => 'purchase-orders',  'label' => 'Purchase Orders',     'icon' => 'fa-file-signature',       'url' => 'admin/inventory/purchase-orders.php'],
                ['key' => 'low-stock',        'label' => 'Low Stock',           'icon' => 'fa-triangle-exclamation', 'url' => 'admin/inventory/low-stock.php'],
                ['key' => 'transactions',     'label' => 'Inventory Transactions','icon' => 'fa-arrows-rotate',      'url' => 'admin/inventory/transactions.php'],
                ['key' => 'reports',          'label' => 'Inventory Reports',   'icon' => 'fa-chart-pie',            'url' => 'admin/inventory/reports.php'],
            ],
        ],
    ],
    'Billing' => [
        ['key' => 'payments',       'label' => 'Payments',       'icon' => 'fa-money-bill-wave',     'url' => 'admin/payments/index.php'],
        ['key' => 'invoices',       'label' => 'Invoices',       'icon' => 'fa-file-invoice-dollar', 'url' => 'admin/invoices/index.php'],
        ['key' => 'receipts',       'label' => 'Receipts',       'icon' => 'fa-receipt',             'url' => 'admin/receipts/index.php'],
        ['key' => 'revenue-report', 'label' => 'Revenue Reports', 'icon' => 'fa-chart-line',          'url' => 'admin/reports/revenue.php'],
        ['key' => 'commissions',    'label' => 'Commissions',    'icon' => 'fa-percent',             'url' => 'admin/coming-soon.php?page=commissions'],
    ],
    'Insights' => [
        ['key' => 'calendar',     'label' => 'Calendar',     'icon' => 'fa-calendar-days',    'url' => 'admin/coming-soon.php?page=calendar'],
        ['key' => 'reports',      'label' => 'Reports',      'icon' => 'fa-chart-pie',        'url' => 'admin/reports/revenue.php'],
        ['key' => 'gallery',      'label' => 'Gallery',      'icon' => 'fa-images',           'url' => 'admin/coming-soon.php?page=gallery'],
        ['key' => 'feedback',     'label' => 'Feedback',     'icon' => 'fa-star',             'url' => 'admin/coming-soon.php?page=feedback'],
        ['key' => 'notifications','label' => 'Notifications','icon' => 'fa-bell',             'url' => 'admin/coming-soon.php?page=notifications'],
    ],
    'System' => [
        ['key' => 'users',        'label' => 'Users',        'icon' => 'fa-user-gear',        'url' => 'admin/users.php'],
        ['key' => 'profile',      'label' => 'My Profile',   'icon' => 'fa-user-circle',      'url' => 'admin/profile.php'],
        ['key' => 'settings',     'label' => 'Settings',     'icon' => 'fa-gear',             'url' => 'admin/coming-soon.php?page=settings'],
    ],
];

// Current child page highlighted inside the Inventory submenu.
$inventoryActive = $inventoryActive ?? ($activeMenu === 'inventory' ? 'inv-dashboard' : '');

?>

<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo"><i class="fas fa-wand-magic-sparkles"></i></div>
        <div>
            <div class="sidebar-brand-name">Elegance Salon</div>
            <div class="sidebar-brand-sub">Admin Panel</div>
        </div>
    </div>

    <div class="sidebar-scroll">
        <ul class="sidebar-nav">
            <?php foreach ($adminSidebarMenu as $groupLabel => $sidebarItems): ?>
                <li class="sidebar-group-label">
                    <?php echo $groupLabel === 'main' ? 'Overview' : $groupLabel; ?>
                </li>
                <?php foreach ($sidebarItems as $sidebarItem):
                    $isActive = adminSidebarIsActive($sidebarItem['key']);
                    $hasChildren = !empty($sidebarItem['children']);
                    // Future features land on the dashboard for now (invisible disabled state not needed).
                ?>
                <?php if ($hasChildren): ?>
                <li class="sidebar-has-sub <?php echo $isActive ? 'open' : ''; ?>">
                    <span class="sidebar-link sub-parent <?php echo $isActive ? 'active' : ''; ?>" tabindex="0" role="button">
                        <i class="fas <?php echo $sidebarItem['icon']; ?>"></i>
                        <span><?php echo $sidebarItem['label']; ?></span>
                        <i class="fas fa-chevron-down sub-arrow"></i>
                    </span>
                    <ul class="sidebar-sub">
                        <?php foreach ($sidebarItem['children'] as $child): ?>
                        <?php
                        $childActive = $isActive && $inventoryActive === $child['key'];
                        ?>
                        <li>
                            <a class="sidebar-link sidebar-sub-link <?php echo $childActive ? 'active' : ''; ?>"
                               href="<?php echo SITE_URL; ?>/<?php echo $child['url']; ?>"
                               title="<?php echo $child['label']; ?>">
                                <i class="fas <?php echo $child['icon']; ?>"></i>
                                <span><?php echo $child['label']; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php else: ?>
                <li>
                    <a class="sidebar-link <?php echo $isActive ? 'active' : ''; ?>"
href="<?php echo SITE_URL; ?>/<?php echo $sidebarItem['url']; ?>"
                        title="<?php echo $sidebarItem['label']; ?>">
                        <i class="fas <?php echo $sidebarItem['icon']; ?>"></i>
                        <span><?php echo $sidebarItem['label']; ?></span>
                        <?php if (in_array($sidebarItem['key'], ['appointments','notifications'], true) && !empty($globalSidebarBadges[$sidebarItem['key']])): ?>
                        <span class="badge-pill"><?php echo (int)$globalSidebarBadges[$sidebarItem['key']]; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
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
        Elegance Salon &copy; <?php echo date('Y'); ?>
    </div>
</aside>