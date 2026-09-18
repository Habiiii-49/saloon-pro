<?php
/**
 * Admin – Inventory Categories
 * Create, rename, activate/deactivate and delete product categories.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Categories';
$activeMenu      = 'inventory';
$inventoryActive = 'categories';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

$editId = (int)($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/inventory/categories.php');
    }

    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $catId  = (int)($_POST['id'] ?? 0);

    if ($action === 'add' || $action === 'update') {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            setFlash('error', 'Category name must be between 2 and 60 characters.');
        } else {
            // Duplicate name check (case-insensitive-ish).
            $chk = $db->prepare("SELECT id FROM inventory_categories WHERE LOWER(name) = LOWER(:n) AND (:eid = 0 OR id <> :eid) LIMIT 1");
            $chk->execute([':n' => $name, ':eid' => $action === 'update' ? $catId : 0]);
            if ($chk->fetch()) {
                setFlash('error', 'A category with that name already exists.');
            } elseif ($action === 'update') {
                $stmt = $db->prepare("UPDATE inventory_categories SET name = :n WHERE id = :id");
                $stmt->execute([':n' => $name, ':id' => $catId]);
                setFlash('success', 'Category renamed successfully.');
            } else {
                $stmt = $db->prepare("INSERT INTO inventory_categories (name, status) VALUES (:n, 'active')");
                $stmt->execute([':n' => $name]);
                setFlash('success', 'Category "' . $name . '" created.');
            }
        }
    } elseif ($action === 'toggle_status' && $catId > 0) {
        $stmt = $db->prepare("UPDATE inventory_categories SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id");
        $stmt->execute([':id' => $catId]);
        setFlash('success', 'Category status updated.');
    } elseif ($action === 'delete' && $catId > 0) {
        $prodCount = $db->prepare("SELECT COUNT(*) FROM inventory WHERE category_id = :id");
        $prodCount->execute([':id' => $catId]);
        if ((int)$prodCount->fetchColumn() > 0) {
            setFlash('error', 'This category still has products assigned. Move them to another category first.');
        } else {
            $stmt = $db->prepare("DELETE FROM inventory_categories WHERE id = :id");
            $stmt->execute([':id' => $catId]);
            setFlash('success', 'Category deleted.');
        }
    }

    redirect('admin/inventory/categories.php');
}

$categories = [];
try {
    $categories = $db->query("
        SELECT c.id, c.name, c.status,
               (SELECT COUNT(*) FROM inventory i WHERE i.category_id = c.id) AS product_count,
               (SELECT COALESCE(SUM(i.quantity),0) FROM inventory i WHERE i.category_id = c.id) AS total_units,
               (SELECT COALESCE(SUM(i.quantity * i.cost_price),0) FROM inventory i WHERE i.category_id = c.id) AS stock_value
        FROM inventory_categories c
        ORDER BY c.name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    setFlash('error', 'Could not load categories: ' . $e->getMessage());
    $categories = [];
}

$editing = null;
if ($editId > 0) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $editId) {
            $editing = $cat;
            break;
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-tags"></i> Inventory Categories</h1>
        <p>Keep your catalogue organised with clear product groups.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Products</a>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-plus"></i> <?php echo $editing ? 'Edit Category' : 'Add Category'; ?></h5>
            </div>
            <div class="card-body-custom">
                <form method="post" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'add'; ?>">
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?php echo (int)$editing['id']; ?>"><?php endif; ?>

                    <div class="form-group">
                        <label for="name">Category Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" class="form-control-admin"
                               value="<?php echo $editing ? sanitize($editing['name']) : ''; ?>"
                               placeholder="e.g. Hair Colour, Skincare, Retail" required maxlength="60" autofocus>
                    </div>
                    <?php if ($editing): ?>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Save Changes</button>
                        <a href="<?php echo SITE_URL; ?>/admin/inventory/categories.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                    <?php else: ?>
                    <button type="submit" class="btn-admin btn-cyan w-100 mt-3"><i class="fas fa-plus"></i> Add Category</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-list"></i> Categories <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($categories); ?></span></h5>
            </div>
            <?php if (empty($categories)): ?>
            <div class="empty-state"><i class="fas fa-tags"></i><h5>No categories yet</h5><p>Create your first category to group your product catalogue.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr><th>Category</th><th>Products</th><th>Units</th><th>Stock Value</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($cat['name']); ?></td>
                            <td><?php echo (int)$cat['product_count']; ?></td>
                            <td class="cell-sub"><?php echo (int)$cat['total_units']; ?></td>
                            <td class="cell-sub">$<?php echo number_format((float)$cat['stock_value'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $cat['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                    <?php echo $cat['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a href="<?php echo SITE_URL; ?>/admin/inventory/categories.php?edit=<?php echo (int)$cat['id']; ?>" class="btn-admin btn-outline btn-xs" title="Edit"><i class="fas fa-pen"></i></a>
                                    <form method="post" action="" class="d-inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                        <button type="submit" class="btn-admin btn-outline btn-xs" title="<?php echo $cat['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas <?php echo $cat['status'] === 'active' ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="post" action="" class="d-inline">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                        <button type="submit"
                                                data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                                    'title'       => 'Delete category?',
                                                    'message'     => 'This permanently removes "' . $cat['name'] . '". Categories with assigned products are protected.',
                                                    'confirmText' => 'Delete',
                                                ]), ENT_QUOTES); ?>'
                                                class="btn-admin btn-danger-outline btn-xs" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>