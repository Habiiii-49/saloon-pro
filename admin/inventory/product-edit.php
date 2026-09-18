<?php
/**
 * Admin – Edit Product
 * Update product details, pricing and stock thresholds. Stock levels always
 * change through stock-in / stock-out / adjustments (never edited directly).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Edit Product';
$activeMenu      = 'inventory';
$inventoryActive = 'products';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

$productId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$stmt = $db->prepare("SELECT * FROM inventory WHERE inventory_id = :id");
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlash('error', 'Product not found.');
    redirect('admin/inventory/products.php');
}

$old = [
    'item_name'     => $product['item_name'],
    'sku'           => $product['sku'],
    'barcode'       => $product['barcode'],
    'category_id'   => (int)$product['category_id'],
    'description'   => $product['description'],
    'unit'          => $product['unit'] ?? 'pcs',
    'cost_price'    => number_format((float)$product['cost_price'], 2, '.', ''),
    'selling_price' => number_format((float)$product['selling_price'], 2, '.', ''),
    'minimum_stock' => (int)$product['minimum_stock'],
    'maximum_stock' => (int)$product['maximum_stock'],
    'reorder_level' => (int)$product['reorder_level'],
    'expiry_date'   => $product['expiry_date'] && $product['expiry_date'] !== '0000-00-00' ? $product['expiry_date'] : '',
    'supplier_id'   => (int)$product['supplier_id'],
    'status'        => $product['status'] ?? 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['item_name']     = trim($_POST['item_name'] ?? '');
        $old['sku']           = strtoupper(trim($_POST['sku'] ?? ''));
        $old['barcode']       = trim($_POST['barcode'] ?? '');
        $old['category_id']   = (int)($_POST['category_id'] ?? 0);
        $old['description']   = trim($_POST['description'] ?? '');
        $old['unit']          = trim($_POST['unit'] ?? 'pcs');
        $old['cost_price']    = (float)($_POST['cost_price'] ?? 0);
        $old['selling_price'] = (float)($_POST['selling_price'] ?? 0);
        $old['minimum_stock'] = (int)($_POST['minimum_stock'] ?? 0);
        $old['maximum_stock'] = (int)($_POST['maximum_stock'] ?? 0);
        $old['reorder_level'] = (int)($_POST['reorder_level'] ?? 0);
        $old['expiry_date']   = trim($_POST['expiry_date'] ?? '');
        $old['supplier_id']   = (int)($_POST['supplier_id'] ?? 0);
        $old['status']        = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        if ($old['item_name'] === '') {
            $errors[] = 'Product name is required.';
        }
        if (mb_strlen($old['item_name']) > 120) {
            $errors[] = 'Product name must be 120 characters or less.';
        }
        if ($old['sku'] === '') {
            $errors[] = 'SKU is required for existing products.';
        }
        if (!preg_match('/^[A-Za-z0-9\-_]{2,40}$/', $old['sku'])) {
            $errors[] = 'SKU may only contain letters, numbers, dashes and underscores (2-40 chars).';
        }
        if ($old['barcode'] !== '' && !preg_match('/^[A-Za-z0-9\-]{6,40}$/', $old['barcode'])) {
            $errors[] = 'Barcode must be 6-40 characters (letters and numbers only).';
        }
        if ($old['cost_price'] < 0 || $old['selling_price'] < 0) {
            $errors[] = 'Prices cannot be negative.';
        }
        if ($old['minimum_stock'] < 0 || $old['maximum_stock'] < 0 || $old['reorder_level'] < 0) {
            $errors[] = 'Stock thresholds cannot be negative.';
        }
        if ($old['maximum_stock'] > 0 && $old['minimum_stock'] > $old['maximum_stock']) {
            $errors[] = 'Minimum stock cannot exceed maximum stock.';
        }
        if ($old['expiry_date'] !== '') {
            $d = date_parse($old['expiry_date']);
            if (!checkdate((int)$d['month'], (int)$d['day'], (int)$d['year'])) {
                $errors[] = 'Please enter a valid expiry date.';
            }
        }
        if ($old['category_id'] > 0) {
            $chk = $db->prepare("SELECT id FROM inventory_categories WHERE id = :id");
            $chk->execute([':id' => $old['category_id']]);
            if (!$chk->fetch()) {
                $errors[] = 'The selected category does not exist.';
            }
        }
        if ($old['supplier_id'] > 0) {
            $chk = $db->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = :id");
            $chk->execute([':id' => $old['supplier_id']]);
            if (!$chk->fetch()) {
                $errors[] = 'The selected supplier does not exist.';
            }
        }

        if (empty($errors)) {
            try {
                // SKU uniqueness (exclude self)
                $chk = $db->prepare("SELECT inventory_id FROM inventory WHERE sku = :sku AND inventory_id <> :id LIMIT 1");
                $chk->execute([':sku' => $old['sku'], ':id' => $productId]);
                if ($chk->fetch()) {
                    $errors[] = 'That SKU is already in use by another product.';
                }
            } catch (PDOException $e) {
                // okay; DB constraint will still guard.
            }

            if (empty($errors)) {
                try {
                    $catName = null;
                    if ($old['category_id'] > 0) {
                        $catStmt = $db->prepare("SELECT name FROM inventory_categories WHERE id = :id");
                        $catStmt->execute([':id' => $old['category_id']]);
                        $catName = $catStmt->fetchColumn();
                    }

                    $stmt = $db->prepare("
                        UPDATE inventory SET
                            item_name = :name, sku = :sku, barcode = :barcode, description = :desc,
                            category_id = :cat_id, category = :cat_name, unit = :unit,
                            cost_price = :cost, selling_price = :sell, unit_price = :cost,
                            minimum_stock = :min, maximum_stock = :max, reorder_level = :reorder,
                            expiry_date = :expiry, supplier_id = :supplier, status = :status
                        WHERE inventory_id = :id
                    ");
                    $stmt->execute([
                        ':name'     => $old['item_name'],
                        ':sku'      => $old['sku'],
                        ':barcode'  => $old['barcode'] !== '' ? $old['barcode'] : null,
                        ':desc'     => $old['description'] !== '' ? $old['description'] : null,
                        ':cat_id'   => $old['category_id'] > 0 ? $old['category_id'] : null,
                        ':cat_name' => $catName,
                        ':unit'     => $old['unit'] !== '' ? $old['unit'] : 'pcs',
                        ':cost'     => $old['cost_price'],
                        ':sell'     => $old['selling_price'],
                        ':min'      => $old['minimum_stock'],
                        ':max'      => $old['maximum_stock'],
                        ':reorder'  => $old['reorder_level'],
                        ':expiry'   => $old['expiry_date'] !== '' ? $old['expiry_date'] : null,
                        ':supplier' => $old['supplier_id'] > 0 ? $old['supplier_id'] : null,
                        ':status'   => $old['status'],
                        ':id'       => $productId,
                    ]);

                    // Threshold changes may instantly flag a product as low stock.
                    invSyncLowStockAlerts($db);

                    setFlash('success', 'Product updated successfully.');
                    redirect('admin/inventory/product-view.php?id=' . $productId);
                } catch (PDOException $e) {
                    if ((int)$e->getCode() === 23000 && stripos($e->getMessage(), 'sku') !== false) {
                        $errors[] = 'That SKU is already in use by another product.';
                    } else {
                        $errors[] = 'Could not update the product: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM inventory_categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}
$suppliers = [];
try {
    $suppliers = $db->query("SELECT supplier_id, COALESCE(supplier_name, company_name) AS name FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
}
$stockLevel = (int)$product['quantity'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-pen-to-square"></i> Edit Product</h1>
        <p><?php echo sanitize($product['item_name']); ?></p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/product-view.php?id=<?php echo $productId; ?>" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Product</a>
</div>

<div class="row justify-content-center">
    <div class="col-xl-10 col-lg-11">
        <?php if ($stockLevel > 0): ?>
        <div class="alert-admin info"><i class="fas fa-circle-info"></i><div>Current stock is <strong><?php echo $stockLevel; ?></strong>. Stock levels must be changed via <a href="<?php echo SITE_URL; ?>/admin/inventory/stock-in.php?id=<?php echo $productId; ?>">Stock In</a> / <a href="<?php echo SITE_URL; ?>/admin/inventory/stock-out.php?id=<?php echo $productId; ?>">Stock Out</a> / <a href="<?php echo SITE_URL; ?>/admin/inventory/stock-adjustment.php?id=<?php echo $productId; ?>">Adjustment</a> to keep the audit trail accurate.</div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="card-body-custom">
                <?php if (!empty($errors)): ?>
                <div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" value="<?php echo $productId; ?>">

                    <div class="form-section-title"><i class="fas fa-tag"></i> Product Details</div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="item_name">Product Name <span class="req">*</span></label>
                                <input type="text" id="item_name" name="item_name" class="form-control-admin" value="<?php echo sanitize($old['item_name']); ?>" required maxlength="120">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="sku">SKU <span class="req">*</span></label>
                                <input type="text" id="sku" name="sku" class="form-control-admin" value="<?php echo sanitize($old['sku']); ?>" required maxlength="40">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="category_id">Category</label>
                                <select id="category_id" name="category_id" class="form-control-admin">
                                    <option value="0">— Uncategorized —</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo (int)$cat['id']; ?>" <?php echo $old['category_id'] === (int)$cat['id'] ? 'selected' : ''; ?>><?php echo sanitize($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="unit">Unit</label>
                                <select id="unit" name="unit" class="form-control-admin">
                                    <?php foreach (['pcs','ml','L','g','kg','pack','box','bottle','tube','set'] as $unit): ?>
                                    <option value="<?php echo $unit; ?>" <?php echo $old['unit'] === $unit ? 'selected' : ''; ?>><?php echo strtoupper($unit); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="barcode">Barcode</label>
                                <input type="text" id="barcode" name="barcode" class="form-control-admin" value="<?php echo sanitize($old['barcode'] ?? ''); ?>" maxlength="40">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control-admin" rows="3"><?php echo sanitize($old['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title" style="margin-top:1.2rem;"><i class="fas fa-money-bill-wave"></i> Pricing</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cost_price">Cost Price ($) <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-dollar-sign"></i>
                                    <input type="number" id="cost_price" name="cost_price" class="form-control-admin" value="<?php echo $old['cost_price']; ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="selling_price">Selling Price ($) <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-dollar-sign"></i>
                                    <input type="number" id="selling_price" name="selling_price" class="form-control-admin" value="<?php echo $old['selling_price']; ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title" style="margin-top:1.2rem;"><i class="fas fa-warehouse"></i> Stock Thresholds</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="minimum_stock">Minimum Stock <span class="req">*</span></label>
                                <input type="number" id="minimum_stock" name="minimum_stock" class="form-control-admin" value="<?php echo (int)$old['minimum_stock']; ?>" min="0" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="maximum_stock">Maximum Stock</label>
                                <input type="number" id="maximum_stock" name="maximum_stock" class="form-control-admin" value="<?php echo (int)$old['maximum_stock']; ?>" min="0" step="1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="reorder_level">Reorder Level</label>
                                <input type="number" id="reorder_level" name="reorder_level" class="form-control-admin" value="<?php echo (int)$old['reorder_level']; ?>" min="0" step="1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control-admin" value="<?php echo sanitize($old['expiry_date']); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="supplier_id">Preferred Supplier</label>
                                <select id="supplier_id" name="supplier_id" class="form-control-admin">
                                    <option value="0">— None —</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo (int)$sup['supplier_id']; ?>" <?php echo $old['supplier_id'] === (int)$sup['supplier_id'] ? 'selected' : ''; ?>><?php echo sanitize($sup['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Status</label>
                                <div style="display:flex;gap:1.2rem;align-items:center;padding-top:.45rem;">
                                    <label class="remember" style="color:#c3cfe0;font-size:.86rem;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer;">
                                        <input type="checkbox" name="status" value="active" style="accent-color:#00C2D9;width:16px;height:16px;" <?php echo $old['status'] === 'active' ? 'checked' : ''; ?>>
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Save Changes</button>
                        <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>