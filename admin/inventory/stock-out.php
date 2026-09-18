<?php
/**
 * Admin – Stock Out
 * Remove stock from inventory (used in styling, wastage, breakage or transfers).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Stock Out';
$activeMenu      = 'inventory';
$inventoryActive = 'stock-out';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

$presetId = (int)($_GET['id'] ?? 0);
$old = [
    'product_id' => $presetId,
    'quantity'   => '',
    'reference'  => '',
    'reason'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['product_id'] = (int)($_POST['product_id'] ?? 0);
        $old['quantity']   = (int)($_POST['quantity'] ?? 0);
        $old['reference']  = trim($_POST['reference'] ?? '');
        $old['reason']     = trim($_POST['reason'] ?? '');

        $product = null;
        if ($old['product_id'] > 0) {
            $product = invGetProduct($old['product_id']);
        }
        if (!$product) {
            $errors[] = 'Please select a valid product.';
        } elseif ((int)$product['quantity'] <= 0) {
            $errors[] = 'This product is already out of stock.';
        } elseif ($old['quantity'] <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        } elseif ($old['quantity'] > (int)$product['quantity']) {
            $errors[] = 'Cannot remove ' . $old['quantity'] . ' units — only ' . (int)$product['quantity'] . ' available.';
        }
        if (mb_strlen($old['reason']) > 255) {
            $errors[] = 'Reason must be 255 characters or less.';
        }

        if (empty($errors)) {
            $result = invApplyStockChange(
                $db,
                $old['product_id'],
                currentUserId(),
                'stock_out',
                $old['quantity'],
                null,
                $product['supplier_id'] ? (int)$product['supplier_id'] : null,
                null,
                invProductCost($product),
                $old['reference'] !== '' ? $old['reference'] : 'STK-OUT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
                $old['reason'] !== '' ? $old['reason'] : 'Stock out',
                'Manual stock-out of ' . $old['quantity'] . ' units'
            );

            if ($result['ok']) {
                setFlash('success', $result['message'] . ' ' . $product['item_name'] . ' now has ' . $result['new_stock'] . ' units.');
                redirect('admin/inventory/stock-out.php');
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}

$products = [];
try {
    $products = $db->query("SELECT inventory_id, item_name, sku, quantity, unit, status FROM inventory WHERE status = 'active' ORDER BY item_name ASC")->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

$recent = [];
try {
    $recent = $db->query("
        SELECT t.id, t.quantity, t.reference, t.reason, t.created_at,
               i.item_name, i.unit,
               CONCAT(u.first_name,' ',u.last_name) AS user_name
        FROM inventory_transactions t
        JOIN inventory i ON i.inventory_id = t.product_id
        LEFT JOIN users u ON u.user_id = t.user_id
        WHERE t.transaction_type = 'stock_out'
        ORDER BY t.id DESC LIMIT 8
    ")->fetchAll();
} catch (PDOException $e) {
    $recent = [];
}

$selectedProduct = $old['product_id'] > 0 ? invGetProduct($old['product_id']) : null;
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head">
    <div class="page-head-title">
        <h1><i class="fas fa-arrow-up-from-bracket"></i> Stock Out</h1>
        <p>Record products used in services, wasted or transferred out.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Products</a>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-cart-minus"></i> Remove Stock</h5>
            </div>
            <div class="card-body-custom">
                <?php if (!empty($errors)): ?>
                <div class="alert-admin error"><i class="fas fa-circle-xmark"></i><div><?php echo implode('<br>', array_map('sanitize', $errors)); ?></div><button type="button" class="alert-close"><i class="fas fa-xmark"></i></button></div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label for="product_id">Product <span class="req">*</span></label>
                        <select id="product_id" name="product_id" class="form-control-admin" required>
                            <option value="0">— Select product —</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?php echo (int)$p['inventory_id']; ?>" <?php echo $old['product_id'] === (int)$p['inventory_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($p['item_name']); ?> (in stock: <?php echo (int)$p['quantity']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text-admin" id="current-stock-hint">
                            <?php if ($selectedProduct): ?>
                            Current stock: <strong style="color:var(--accent-bright);"><?php echo (int)$selectedProduct['quantity']; ?></strong> <?php echo strtoupper(sanitize($selectedProduct['unit'] ?? 'pcs')); ?>
                            <?php else: ?>
                            Select a product to see its current stock level.
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="quantity">Quantity <span class="req">*</span></label>
                                <input type="number" id="quantity" name="quantity" class="form-control-admin" value="<?php echo (int)$old['quantity']; ?>" min="1" step="1" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="reference">Reference</label>
                                <input type="text" id="reference" name="reference" class="form-control-admin" value="<?php echo sanitize($old['reference']); ?>" placeholder="Sale, clinic use, transfer..." maxlength="80">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="reason">Reason <span class="req">*</span></label>
                                <textarea id="reason" name="reason" class="form-control-admin" rows="2" placeholder="e.g. Used in client service" maxlength="255" required><?php echo sanitize($old['reason']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-admin btn-danger w-100 mt-3"><i class="fas fa-check"></i> Complete Stock Out</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-clock-rotate-left"></i> Recent Stock-Outs</h5>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/transactions.php" class="btn-admin btn-outline btn-xs">All Transactions</a>
            </div>
            <?php if (empty($recent)): ?>
            <div class="empty-state"><i class="fas fa-arrow-up-from-bracket"></i><h5>No stock-outs yet</h5><p>Your first stock-out will appear here.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Qty</th><th>Reason</th><th>By</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $tx): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($tx['item_name']); ?></td>
                            <td><span class="status-badge cancelled">−<?php echo (int)$tx['quantity']; ?> <?php echo strtoupper(sanitize($tx['unit'] ?? 'pcs')); ?></span></td>
                            <td class="cell-sub"><?php echo sanitize($tx['reason'] ?? '—'); ?></td>
                            <td class="cell-sub"><?php echo $tx['user_name'] ? sanitize($tx['user_name']) : '—'; ?></td>
                            <td><span class="cell-sub"><?php echo formatDate($tx['created_at'], 'M d, g:i A'); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('product_id');
    var hint = document.getElementById('current-stock-hint');
    if (!sel || !hint) return;

    var stockByProduct = <?php echo json_encode(array_column($products, 'quantity', 'inventory_id')); ?>;
    sel.addEventListener('change', function () {
        var id = parseInt(sel.value, 10) || 0;
        if (id && stockByProduct.hasOwnProperty(id)) {
            hint.innerHTML = 'Current stock: <strong style="color:var(--accent-bright);">' + stockByProduct[id] + '</strong>';
        } else {
            hint.innerHTML = 'Select a product to see its current stock level.';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>