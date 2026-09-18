<?php
/**
 * Admin – Stock Adjustment
 * Reconcile physical stock against the system (absolute target quantity).
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Stock Adjustment';
$activeMenu      = 'inventory';
$inventoryActive = 'stock-adjustment';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();
$errors = [];

$presetId = (int)($_GET['id'] ?? 0);
$old = [
    'product_id' => $presetId,
    'target_qty' => '',
    'reason'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $old['product_id'] = (int)($_POST['product_id'] ?? 0);
        $old['target_qty'] = (int)($_POST['target_qty'] ?? -1);
        $old['reason']     = trim($_POST['reason'] ?? '');

        $product = null;
        if ($old['product_id'] > 0) {
            $product = invGetProduct($old['product_id']);
        }
        if (!$product) {
            $errors[] = 'Please select a valid product.';
        }
        if ($old['target_qty'] < 0) {
            $errors[] = 'Actual quantity cannot be negative.';
        }
        if ((int)$product['quantity'] === $old['target_qty']) {
            $errors[] = 'No adjustment needed — actual quantity already matches the system.';
        }
        if (mb_strlen($old['reason']) > 255) {
            $errors[] = 'Reason must be 255 characters or less.';
        }

        if (empty($errors)) {
            $result = invApplyStockChange(
                $db,
                $old['product_id'],
                currentUserId(),
                'adjustment',
                0,
                $old['target_qty'],
                $product['supplier_id'] ? (int)$product['supplier_id'] : null,
                null,
                invProductCost($product),
                'ADJUST',
                $old['reason'] !== '' ? $old['reason'] : 'Stock adjustment',
                'Adjusted to actual quantity'
            );

            if ($result['ok']) {
                setFlash('success', 'Stock adjusted: ' . $product['item_name'] . ' is now at ' . $result['new_stock'] . ' units.');
                redirect('admin/inventory/stock-adjustment.php');
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
        SELECT t.id, t.quantity, t.previous_stock, t.new_stock, t.reason, t.created_at,
               i.item_name, i.unit,
               CONCAT(u.first_name,' ',u.last_name) AS user_name
        FROM inventory_transactions t
        JOIN inventory i ON i.inventory_id = t.product_id
        LEFT JOIN users u ON u.user_id = t.user_id
        WHERE t.transaction_type IN ('adjustment','manual_correction')
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
        <h1><i class="fas fa-sliders"></i> Stock Adjustment</h1>
        <p>Reconcile system records with physical stock counts.</p>
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/inventory/products.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Products</a>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-arrows-rotate"></i> Perform Adjustment</h5>
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
                                <?php echo sanitize($p['item_name']); ?> (system: <?php echo (int)$p['quantity']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text-admin" id="current-stock-hint">
                            <?php if ($selectedProduct): ?>
                            System quantity: <strong style="color:var(--accent-bright);"><?php echo (int)$selectedProduct['quantity']; ?></strong> <?php echo strtoupper(sanitize($selectedProduct['unit'] ?? 'pcs')); ?>
                            <?php else: ?>
                            Select a product to see its system quantity.
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="target_qty">Actual Quantity on Hand <span class="req">*</span></label>
                                <input type="number" id="target_qty" name="target_qty" class="form-control-admin" value="<?php echo (int)$old['target_qty']; ?>" min="0" step="1" required>
                                <div class="form-text-admin">The system will be updated to this exact number.</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="reason">Reason <span class="req">*</span></label>
                                <textarea id="reason" name="reason" class="form-control-admin" rows="2" placeholder="e.g. Physical count, damaged stock" maxlength="255" required><?php echo sanitize($old['reason']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-admin btn-gold w-100 mt-3"><i class="fas fa-sliders"></i> Apply Adjustment</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="admin-card">
            <div class="card-header-custom">
                <h5><i class="fas fa-clock-rotate-left"></i> Recent Adjustments</h5>
                <a href="<?php echo SITE_URL; ?>/admin/inventory/transactions.php" class="btn-admin btn-outline btn-xs">All Transactions</a>
            </div>
            <?php if (empty($recent)): ?>
            <div class="empty-state"><i class="fas fa-sliders"></i><h5>No adjustments yet</h5><p>Performed adjustments will appear here.</p></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Product</th><th>Change</th><th>Result</th><th>Reason</th><th>By</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $tx): ?>
                        <tr>
                            <td class="cell-strong"><?php echo sanitize($tx['item_name']); ?></td>
                            <td>
                                <span class="<?php echo (int)$tx['quantity'] >= 0 ? 'status-badge completed' : 'status-badge cancelled'; ?>">
                                    <?php echo (int)$tx['quantity'] >= 0 ? '+' : ''; ?><?php echo (int)$tx['quantity']; ?>
                                </span>
                            </td>
                            <td><span class="cell-sub"><?php echo (int)$tx['previous_stock']; ?> → <?php echo (int)$tx['new_stock']; ?></span></td>
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
            hint.innerHTML = 'System quantity: <strong style="color:var(--accent-bright);">' + stockByProduct[id] + '</strong>';
        } else {
            hint.innerHTML = 'Select a product to see its system quantity.';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>