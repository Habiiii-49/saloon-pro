<?php
/**
 * Elegance Salon - Receptionist - Client Management
 * Searchable client list with visit stats.
 */
require_once __DIR__ . '/includes/booking.php';

$db = getDBConnection();
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = '';
$params = [];
if ($search !== '') {
    $where = "WHERE (CONCAT(c.first_name,' ',c.last_name) LIKE :q OR c.phone LIKE :q2 OR c.email LIKE :q3)";
    $params = [':q' => '%' . $search . '%', ':q2' => '%' . $search . '%', ':q3' => '%' . $search . '%'];
}

$countStmt = $db->prepare("SELECT COUNT(*) AS c FROM clients c $where");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$clients = [];
if ($totalRows > 0) {
    $sql = "
        SELECT c.*,
               (SELECT COUNT(*) FROM appointments a WHERE a.client_id = c.client_id AND a.status <> 'cancelled') AS visits,
               (SELECT COUNT(*) FROM appointments a WHERE a.client_id = c.client_id AND a.status = 'completed') AS completed_visits,
               (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN appointments a ON a.appointment_id=p.appointment_id WHERE a.client_id=c.client_id AND p.payment_status IN ('paid','completed')) AS total_spent
        FROM clients c
        $where
        ORDER BY c.first_name ASC, c.last_name ASC
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
}

$pageTitle = "Clients";
$activeMenu = 'clients';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-users"></i> Clients</h1>
        <p class="page-subtitle">Manage the salon's client book.</p>
    </div>
    <div class="page-head-right">
        <a href="<?php echo SITE_URL; ?>/user/client-add.php" class="btn-user btn-cyan"><i class="fas fa-user-plus"></i> Add Client</a>
    </div>
</div>

<form method="get" action="" class="filter-bar">
    <input type="text" name="q" class="form-control-salon fser" placeholder="Search by name, phone or email..." value="<?php echo sanitize($search); ?>">
    <button type="submit" class="btn-user btn-outline"><i class="fas fa-magnifying-glass"></i> Search</button>
    <a href="<?php echo SITE_URL; ?>/user/clients.php" class="btn-user btn-ghost"><i class="fas fa-rotate"></i></a>
    <span class="page-info ms-auto"><?php echo (int)$totalRows; ?> client(s)</span>
</form>

<div class="panel">
    <div class="table-responsive-wrap">
        <table class="table-salon">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Gender</th>
                    <th>Visits</th>
                    <th>Last Visit</th>
                    <th>Spent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="8">
                        <div class="table-empty">
                            <i class="fas fa-user-slash"></i>
                            <p><?php echo $search ? 'No clients found matching your search.' : 'No clients found.'; ?></p>
                            <a href="<?php echo SITE_URL; ?>/user/client-add.php" class="btn-user btn-cyan"><i class="fas fa-user-plus"></i> Add the first client</a>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($clients as $c): ?>
                <tr>
                    <td class="cell-main"><?php echo sanitize($c['first_name'] . ' ' . $c['last_name']); ?></td>
                    <td><?php echo sanitize($c['phone'] ?: '—'); ?></td>
                    <td><?php echo sanitize($c['email'] ?: '—'); ?></td>
                    <td><?php echo ucfirst($c['gender'] ?? '—'); ?></td>
                    <td><span class="status-badge completed"><?php echo (int)$c['visits']; ?></span></td>
                    <td><?php echo $c['last_visit'] ? formatDate($c['last_visit'], 'M d, Y') : '—'; ?></td>
                    <td class="stat-inline"><?php echo formatCurrency($c['total_spent']); ?></td>
                    <td>
                        <div class="tbl-actions">
                            <a href="<?php echo SITE_URL; ?>/user/client-view.php?client_id=<?php echo (int)$c['client_id']; ?>" title="View profile"><i class="fas fa-eye"></i></a>
                            <a href="<?php echo SITE_URL; ?>/user/client-edit.php?client_id=<?php echo (int)$c['client_id']; ?>" title="Edit"><i class="fas fa-pen-to-square"></i></a>
                            <a href="<?php echo SITE_URL; ?>/user/appointments.php?q=<?php echo urlencode($c['first_name'] . ' ' . $c['last_name']); ?>" title="Appointment history"><i class="fas fa-clock-rotate-left"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination-salon">
        <a href="?page=<?php echo max(1, $page - 1); ?>&q=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a>
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>" class="<?php echo $p === $page ? 'cur' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
        <a href="?page=<?php echo min($totalPages, $page + 1); ?>&q=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>