<?php
/**
 * Admin – Add Product
 * Creates a new inventory product. Optional opening stock is recorded as a
 * stock-in transaction so the audit trail stays complete.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Add Product';
$activeMenu      = 'inventory';
$inventoryActive = 'product-add';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];
$old = [
    'item_name'    => '',
    'sku'          => '',
    'barcode'      => '',
    'category_id'  => 0,
    'description'  => '',
    'unit'         => 'pcs',
    'quantity'     => 0,
    'cost_price'   => '',
    'selling_price'=> '',
    'minimum_stock'=> 5,
    'maximum_stock'=> 50,
    'reorder_level'=> 5,
    'expiry_date'  => '',
    'supplier_id'  => 0,
    'status'       => 'active',
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
        $old['quantity']      = (int)($_POST['quantity'] ?? 0);
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
        if ($old['sku'] !== '' && !preg_match('/^[A-Za-z0-9\-_]{2,40}$/', $old['sku'])) {
            $errors[] = 'SKU may only contain letters, numbers, dashes and underscores (2-40 chars).';
        }
        if ($old['barcode'] !== '' && !preg_match('/^[A-Za-z0-9\-]{6,40}$/', $old['barcode'])) {
            $errors[] = 'Barcode must be 6-40 characters (letters and numbers only).';
        }
        if ($old['category_id'] < 0) {
            $errors[] = 'Please choose a valid category.';
        }
        if ($old['quantity'] < 0) {
            $errors[] = 'Opening stock cannot be negative.';
        }
        if ($old['cost_price'] < 0 || $old['selling_price'] < 0) {
            $errors[] = 'Prices cannot be negative.';
        }
        if ($old['selling_price'] > 0 && $old['cost_price'] > 0 && $old['selling_price'] <= $old['cost_price']) {
            $errors[] = 'Selling price should be set above the cost price.';
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
        if ($old['supplier_id'] > 0) {
            $chk = $db->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = :id");
            $chk->execute([':id' => $old['supplier_id']]);
            if (!$chk->fetch()) {
                $errors[] = 'The selected supplier does not exist.';
            }
        }
        if ($old['category_id'] > 0) {
            $chk = $db->prepare("SELECT id FROM inventory_categories WHERE id = :id");
            $chk->execute([':id' => $old['category_id']]);
            if (!$chk->fetch()) {
                $errors[] = 'The selected category does not exist.';
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                if ($old['sku'] === '') {
                    $old['sku'] = 'SKU-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
                }
                if ($old['reorder_level'] === 0) {
                    $old['reorder_level'] = max(1, $old['minimum_stock']);
                }

                $stmt = $db->prepare("
                    INSERT INTO inventory
                        (item_name, sku, barcode, description, category_id, category, quantity,
                         minimum_stock, maximum_stock, reorder_level, unit, cost_price, selling_price,
                         unit_price, expiry_date, supplier_id, status)
                    VALUES
                        (:name, :sku, :barcode, :desc, :cat_id, :cat_name, :qty,
                         :min, :max, :reorder, :unit, :cost, :sell, :cost, :expiry, :supplier, :status)
                ");

                $catName = null;
                if ($old['category_id'] > 0) {
                    $catStmt = $db->prepare("SELECT name FROM inventory_categories WHERE id = :id");
                    $catStmt->execute([':id' => $old['category_id']]);
                    $catName = $catStmt->fetchColumn();
                }

                $stmt->execute([
                    ':name'     => $old['item_name'],
                    ':sku'      => $old['sku'],
                    ':barcode'  => $old['barcode'] !== '' ? $old['barcode'] : null,
                    ':desc'     => $old['description'] !== '' ? $old['description'] : null,
                    ':cat_id'   => $old['category_id'] > 0 ? $old['category_id'] : null,
                    ':cat_name' => $catName,
                    ':qty'      => $old['quantity'],
                    ':min'      => $old['minimum_stock'],
                    ':max'      => $old['maximum_stock'],
                    ':reorder'  => $old['reorder_level'],
                    ':unit'     => $old['unit'] !== '' ? $old['unit'] : 'pcs',
                    ':cost'     => $old['cost_price'],
                    ':sell'     => $old['selling_price'],
                    ':expiry'   => $old['expiry_date'] !== '' ? $old['expiry_date'] : null,
                    ':supplier' => $old['supplier_id'] > 0 ? $old['supplier_id'] : null,
                    ':status'   => $old['status'],
                ]);

                $newId = (int)$db->lastInsertId();

                // Opening stock is tracked through the normal stock engine.
                if ($old['quantity'] > 0) {
                    invApplyStockChange(
                        $db,
                        $newId,
                        currentUserId(),
                        'stock_in',
                        $old['quantity'],
                        null,
                        $old['supplier_id'] > 0 ? $old['supplier_id'] : null,
                        null,
                        (float)$old['cost_price'],
                        'INITIAL',
                        'Opening stock at product creation',
                        'Opened product with ' . $old['quantity'] . ' ' . $old['unit']
                    );
                }

                $db->commit();
                setFlash('success', 'Product "' . $old['item_name'] . '" created successfully.');
                redirect('admin/inventory/product-view.php?id=' . $newId);
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ((int)$e->getCode() === 23000 && stripos($e->getMessage(), 'sku') !== false) {
                    $errors[] = 'That SKU is already in use. Choose a different SKU or leave blank to auto-generate.';
                } else {
                    $errors[] = 'Could not save the product: ' . $e->getMessage();
                }
            }
        }
    }
}

$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM inventory_categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}
$suppliers = [];
try {
    $suppliers = $db->query("SELECT supplier_id, COALESCE(supplier_name, company_name) AS name FROM suppliers WHERE status = 'active' ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $suppliers = [];
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-box-open"></i> Add New Product</h1>
        <p>Create a product and optionally record opening stock.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back to Products</a>
</div>

<div class="row justify-content-center">
    <div class="col-xl-10 col-lg-11">
        <div class="admin-card">
            <div class="card-body-custom">
                <?php if (!empty($errors)): ?>
                <div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
                <?php endif; ?>

                <form method="post" action="" id="product-form">
                    <?php echo csrfField(); ?>

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
                                <label for="sku">SKU</label>
                                <input type="text" id="sku" name="sku" class="form-control-admin" value="<?php echo sanitize($old['sku']); ?>" maxlength="40">
                                <div class="form-text-admin">Blank = auto-generated</div>
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
                                <div class="form-text-admin"><a href="<?php echo SITE_URL; ?>/admin/inventory/categories.php" style="color:var(--accent-bright);">Manage categories</a></div>
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
                                <input type="text" id="barcode" name="barcode" class="form-control-admin" value="<?php echo sanitize($old['barcode']); ?>" maxlength="40">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control-admin" rows="3"><?php echo sanitize($old['description']); ?></textarea>
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
                                    <input type="number" id="cost_price" name="cost_price" class="form-control-admin" value="<?php echo $old['cost_price'] !== '' ? $old['cost_price'] : '0.00'; ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="selling_price">Selling Price ($) <span class="req">*</span></label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-dollar-sign"></i>
                                    <input type="number" id="selling_price" name="selling_price" class="form-control-admin" value="<?php echo $old['selling_price'] !== '' ? $old['selling_price'] : '0.00'; ?>" min="0" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section-title" style="margin-top:1.2rem;"><i class="fas fa-warehouse"></i> Stock Levels</div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="quantity">Opening Quantity</label>
                                <input type="number" id="quantity" name="quantity" class="form-control-admin" value="<?php echo (int)$old['quantity']; ?>" min="0" step="1">
                                <div class="form-text-admin">Records a stock-in transaction.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="minimum_stock">Minimum Stock <span class="req">*</span></label>
                                <input type="number" id="minimum_stock" name="minimum_stock" class="form-control-admin" value="<?php echo (int)$old['minimum_stock']; ?>" min="0" step="1" required>
                                <div class="form-text-admin">Alert when quantity reaches this.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="maximum_stock">Maximum Stock</label>
                                <input type="number" id="maximum_stock" name="maximum_stock" class="form-control-admin" value="<?php echo (int)$old['maximum_stock']; ?>" min="0" step="1">
                                <div class="form-text-admin">Used for suggested PO quantities.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="reorder_level">Reorder Level</label>
                                <input type="number" id="reorder_level" name="reorder_level" class="form-control-admin" value="<?php echo (int)$old['reorder_level']; ?>" min="0" step="1">
                                <div class="form-text-admin">Defaults to minimum stock.</div>
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
                                        Active immediately
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn-admin btn-cyan"><i class="fas fa-check"></i> Create Product</button>
                        <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>