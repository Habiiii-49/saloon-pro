<?php
/**
 * Admin – Inventory Products
 * List, activate/deactivate and delete inventory products.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Products';
$activeMenu      = 'inventory';
$inventoryActive = 'products';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Your session expired. Please try again.');
        redirect('admin/inventory/products.php');
    }

    $action   = $_POST['action'] ?? '';
    $productId = (int)($_POST['id'] ?? 0);

    if ($productId <= 0) {
        setFlash('error', 'Invalid product selected.');
        redirect('admin/inventory/products.php');
    }

    $stmt = $db->prepare("SELECT * FROM inventory WHERE inventory_id = :id");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        setFlash('error', 'Product not found.');
        redirect('admin/inventory/products.php');
    }

    if ($action === 'toggle_status') {
        $stmt = $db->prepare("UPDATE inventory SET status = IF(status = 'active', 'inactive', 'active') WHERE inventory_id = :id");
        $stmt->execute([':id' => $productId]);
        setFlash('success', 'Product status updated.');
    } elseif ($action === 'delete') {
        // Only allow deletion when no transactions / PO items exist (keeps audit trail intact).
        $trn = $db->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE product_id = :id");
        $trn->execute([':id' => $productId]);
        $poi = $db->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE product_id = :id");
        $poi->execute([':id' => $productId]);
        $trnCount = (int)$trn->fetchColumn();
        $poiCount = (int)$poi->fetchColumn();

        if ($trnCount > 0 || $poiCount > 0) {
            setFlash('error', $trnCount > 0
                ? 'This product has stock history and cannot be deleted. Deactivate it instead.'
                : 'This product is used in purchase orders and cannot be deleted. Deactivate it instead.');
        } else {
            $stmt = $db->prepare("DELETE FROM inventory WHERE inventory_id = :id");
            $stmt->execute([':id' => $productId]);
            setFlash('success', 'Product "' . $product['item_name'] . '" deleted successfully.');
        }
    }

    redirect('admin/inventory/products.php');
}

/* ---------- Filters ---------- */
$search   = trim($_GET['search'] ?? '');
$catId    = (int)($_GET['category'] ?? 0);
$status   = $_GET['status'] ?? '';
if (!in_array($status, ['', 'active', 'inactive'], true)) {
    $status = '';
}

$sql = "
    SELECT i.inventory_id, i.item_name, i.sku, i.barcode, i.quantity, i.minimum_stock,
           i.maximum_stock, i.unit, i.cost_price, i.selling_price, i.expiry_date, i.status,
           i.category_id, COALESCE(c.name, i.category, 'Uncategorized') AS category_name,
           COALESCE(s.supplier_name, s.company_name) AS supplier_name
    FROM inventory i
    LEFT JOIN inventory_categories c ON c.id = i.category_id
    LEFT JOIN suppliers s ON s.supplier_id = i.supplier_id
";
$params = [];
$where  = [];
if ($search !== '') {
    $where[] = "(i.item_name LIKE :s1 OR i.sku LIKE :s2 OR i.barcode LIKE :s3)";
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
    $params[':s3'] = '%' . $search . '%';
}
if ($catId > 0) {
    $where[] = "i.category_id = :cat";
    $params[':cat'] = $catId;
}
if ($status !== '') {
    $where[] = "i.status = :st";
    $params[':st'] = $status;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY i.item_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM inventory_categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-boxes-stacked"></i> Products</h1>
        <p>Manage your salon product catalogue, stock levels and pricing.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/product-add.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> Add Product</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
<?php endif; ?>

<div class="admin-card">
    <div class="card-header-custom">
        <h5><i class="fas fa-box"></i> All Products <span class="status-badge confirmed" style="margin-left:.5rem;"><?php echo count($products); ?></span></h5>
        <form method="get" action="" class="d-flex gap-2 flex-wrap">
            <div class="filter-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search name, SKU or barcode..." class="form-control-admin form-control-sm">
            </div>
            <select name="category" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>" <?php echo $catId === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control-admin form-control-sm" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button type="submit" class="btn-admin btn-cyan btn-xs"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search !== '' || $catId > 0 || $status !== ''): ?>
            <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline btn-xs">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($products)): ?>
    <div class="empty-state">
        <i class="fas fa-box-open"></i>
        <h5>No products found</h5>
        <p>Add your first product (e.g. hair colours, skincare, retail items) to start tracking stock.</p>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/product-add.php" class="btn-admin btn-cyan"><i class="fas fa-plus"></i> Add Product</a>
    </div>
    <?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU / Barcode</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Cost</th>
                    <th>Selling</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <?php
                $stockStatus = 'ok';
                if ((int)$p['quantity'] === 0) {
                    $stockStatus = 'out';
                } elseif ((int)$p['quantity'] <= (int)$p['minimum_stock']) {
                    $stockStatus = 'low';
                }
                $expiring = !empty($p['expiry_date'] && $p['expiry_date'] !== '0000-00-00')
                    && strtotime($p['expiry_date']) <= strtotime('+90 days') ? true : false;
                ?>
                <tr>
                    <td>
                        <span class="cell-strong"><?php echo sanitize($p['item_name']); ?></span>
                        <?php if (!empty($p['unit'])): ?><span class="cell-sub">(<?php echo sanitize($p['unit']); ?>)</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="cell-sub"><?php echo $p['sku'] ? sanitize($p['sku']) : '—'; ?></span>
                        <?php if (!empty($p['barcode'])): ?><div class="cell-sub"><?php echo sanitize($p['barcode']); ?></div><?php endif; ?>
                    </td>
                    <td><span class="status-badge pending"><?php echo sanitize($p['category_name'] !== 'Uncategorized' ? $p['category_name'] : 'Uncategorized'); ?></span></td>
                    <td>
                        <span class="stock-count <?php echo $stockStatus === 'out' ? 'stock-count-out' : ($stockStatus === 'low' ? 'stock-count-low' : ''); ?>"><?php echo (int)$p['quantity']; ?> <?php echo sanitize($p['unit'] ?? ''); ?></span>
                        <div class="stock-meter" title="Threshold: <?php echo (int)$p['minimum_stock']; ?>">
                            <?php $pct = (int)$p['maximum_stock'] > 0 ? (int)min(100, floor(((int)$p['quantity']) / ((int)$p['maximum_stock']) * 100)) : 0; ?>
                            <span class="stock-meter-fill <?php echo $stockStatus === 'out' ? 'fill-out' : ($stockStatus === 'low' ? 'fill-low' : 'fill-ok'); ?>" style="width:<?php echo $pct; ?>%;"></span>
                        </div>
                        <?php if ($expiring): ?>
                        <div class="cell-sub" style="color:#f59e0b;"><i class="fas fa-clock"></i> Exp: <?php echo formatDate($p['expiry_date'], 'M d, Y'); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="cell-sub">$<?php echo number_format((float)$p['cost_price'], 2); ?></td>
                    <td class="cell-sub">$<?php echo number_format((float)$p['selling_price'], 2); ?></td>
                    <td class="cell-sub"><?php echo $p['supplier_name'] ? sanitize($p['supplier_name']) : '—'; ?></td>
                    <td>
                        <span class="status-badge <?php echo $p['status'] === 'active' ? 'active' : 'inactive'; ?>">
                            <?php echo $p['status'] === 'active' ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/product-view.php?id=<?php echo (int)$p['inventory_id']; ?>" class="btn-admin btn-outline btn-xs" title="View"><i class="fas fa-eye"></i></a>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/stock-in.php?id=<?php echo (int)$p['inventory_id']; ?>" class="btn-admin btn-outline btn-xs" title="Add Stock"><i class="fas fa-plus"></i></a>
                            <a href="<?php echo SITE_URL; ?>/admin/inventory/product-edit.php?id=<?php echo (int)$p['inventory_id']; ?>" class="btn-admin btn-outline btn-xs" title="Edit"><i class="fas fa-pen"></i></a>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo (int)$p['inventory_id']; ?>">
                                <button type="submit"
                                        data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                            'title'       => $p['status'] === 'active' ? 'Deactivate product?' : 'Activate product?',
                                            'message'     => ($p['status'] === 'active' ? 'Deactivating' : 'Activating') . ' "' . $p['item_name'] . '" ' . ($p['status'] === 'active' ? 'hides it from purchasing.' : 'makes it available again.'),
                                            'confirmText' => $p['status'] === 'active' ? 'Deactivate' : 'Activate',
                                        ]), ENT_QUOTES); ?>'
                                        class="btn-admin btn-outline btn-xs"
                                        title="<?php echo $p['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas <?php echo $p['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                </button>
                            </form>
                            <form method="post" action="" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int)$p['inventory_id']; ?>">
                                <button type="submit"
                                        data-confirm-form='<?php echo htmlspecialchars(json_encode([
                                            'title'       => 'Delete product?',
                                            'message'     => 'This will permanently remove "' . $p['item_name'] . '". Products with stock history are protected.',
                                            'confirmText' => 'Delete',
                                        ]), ENT_QUOTES); ?>'
                                        class="btn-admin btn-danger-outline btn-xs"
                                        title="Delete">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>