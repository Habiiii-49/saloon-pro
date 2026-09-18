<?php
/**
 * Admin – Purchase Order View
 * Printable purchase order with status, items and totals.
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

$pageTitle       = 'Purchase Order';
$activeMenu      = 'inventory';
$inventoryActive = 'purchase-orders';
$extraCss        = 'admin/inventory/assets/css/inventory.css';
$extraJs         = 'admin/inventory/assets/js/inventory.js';

$db = getDBConnection();

$poId = (int)($_GET['id'] ?? 0);
$po   = invGetPurchaseOrder($poId);

if (!$po) {
    setFlash('error', 'Purchase order not found.');
    redirect('admin/inventory/purchase-orders.php');
}

$items    = invGetPOItems($poId);
$supplier = $po['supplier_id'] ? invGetSupplier((int)$po['supplier_id']) : null;
$creator  = null;
if (!empty($po['created_by'])) {
    $cs = $db->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM users WHERE user_id = :id");
    $cs->execute([':id' => (int)$po['created_by']]);
    $creator = $cs->fetchColumn() ?: null;
}

$canEdit    = invPOEditable($po['status']) && (int)($po['status'] === 'draft' || true);
$canReceive = in_array($po['status'], ['ordered', 'partially_received'], true);
$allReceived = true;
$totalOrdered = 0;
$totalReceived = 0;
foreach ($items as $it) {
    $totalOrdered  += (int)$it['ordered_quantity'];
    $totalReceived += (int)$it['received_quantity'];
    if ((int)$it['received_quantity'] < (int)$it['ordered_quantity']) {
        $allReceived = false;
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="page-head no-print">
    <div class="page-head-title">
        <h1><i class="fas fa-file-signature"></i> <?php echo sanitize($po['po_number']); ?></h1>
        <p><span class="po-status <?php echo $po['status']; ?>"><?php echo str_replace('_', ' ', ucwords($po['status'])); ?></span></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canReceive): ?>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-receive.php?id=<?php echo $poId; ?>" class="btn-admin btn-cyan"><i class="fas fa-truck-ramp-box"></i> Receive Stock</a>
        <?php endif; ?>
        <?php if (invPOEditable($po['status']) && $totalReceived === 0): ?>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-order-edit.php?id=<?php echo $poId; ?>" class="btn-admin btn-outline"><i class="fas fa-pen"></i> Edit</a>
        <?php endif; ?>
        <button type="button" class="btn-admin btn-outline" data-print-doc><i class="fas fa-print"></i> Print</button>
        <a href="<?php echo SITE_URL; ?>/admin/inventory/purchase-orders.php" class="btn-admin btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="admin-card po-printable">
    <div class="card-body-custom">
        <!-- PO header -->
        <div class="po-header">
            <div>
                <div class="po-brand">ELEGANCE SALON</div>
                <div class="cell-sub">Purchase Order</div>
            </div>
            <div class="text-end">
                <div class="po-number"><?php echo sanitize($po['po_number']); ?></div>
                <div class="cell-sub">Issued: <?php echo formatDate($po['created_at'], 'M d, Y'); ?></div>
            </div>
        </div>

        <div class="po-meta-grid">
            <div class="info-item">
                <div class="lbl">Supplier</div>
                <div class="val">
                    <?php if ($supplier): ?>
                    <?php echo sanitize(invSupplierDisplayName($supplier)); ?>
                    <?php if ($supplier['contact_person']): ?><div class="cell-sub"><?php echo sanitize($supplier['contact_person']); ?></div><?php endif; ?>
                    <?php if ($supplier['phone']): ?><div class="cell-sub"><?php echo sanitize($supplier['phone']); ?></div><?php endif; ?>
                    <?php if ($supplier['email']): ?><div class="cell-sub"><?php echo sanitize($supplier['email']); ?></div><?php endif; ?>
                    <?php else: ?>—<?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="lbl">Order Date</div>
                <div class="val"><?php echo formatDate($po['order_date'], 'M d, Y'); ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Expected Delivery</div>
                <div class="val"><?php echo $po['expected_date'] && $po['expected_date'] !== '0000-00-00' ? formatDate($po['expected_date'], 'M d, Y') : '—'; ?></div>
            </div>
            <div class="info-item">
                <div class="lbl">Created By</div>
                <div class="val"><?php echo $creator ? sanitize($creator) : '—'; ?></div>
            </div>
        </div>

        <!-- Items -->
        <div class="table-scroll mt-3">
            <table class="admin-table po-print-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th class="text-end">Ordered</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $it): ?>
                    <tr>
                        <td class="cell-sub"><?php echo $idx + 1; ?></td>
                        <td class="cell-strong"><?php echo sanitize($it['item_name']); ?></td>
                        <td class="cell-sub"><?php echo sanitize($it['sku'] ?? '—'); ?></td>
                        <td class="text-end"><?php echo (int)$it['ordered_quantity']; ?> <?php echo strtoupper(sanitize($it['unit'] ?? '')); ?></td>
                        <td class="text-end">
                            <span class="<?php echo (int)$it['received_quantity'] >= (int)$it['ordered_quantity'] ? 'status-badge completed' : ((int)$it['received_quantity'] > 0 ? 'status-badge pending' : 'status-badge inactive'); ?>">
                                <?php echo (int)$it['received_quantity']; ?>
                            </span>
                        </td>
                        <td class="text-end">$<?php echo number_format((float)$it['unit_cost'], 2); ?></td>
                        <td class="text-end">$<?php echo number_format((float)$it['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($items)): ?>
                    <tr><td colspan="7" class="text-center cell-sub">No items on this order.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="po-totals po-totals-print">
            <div class="po-totals-row"><span>Subtotal</span><strong>$<?php echo number_format((float)$po['subtotal'], 2); ?></strong></div>
            <div class="po-totals-row"><span>Tax</span><strong>$<?php echo number_format((float)$po['tax'], 2); ?></strong></div>
            <div class="po-totals-row"><span>Discount</span><strong>−$<?php echo number_format((float)$po['discount'], 2); ?></strong></div>
            <div class="po-totals-row po-grand"><span>Grand Total</span><strong>$<?php echo number_format((float)$po['grand_total'], 2); ?></strong></div>
            <div class="po-totals-row"><span>Received Progress</span><strong><?php echo $totalOrdered > 0 ? round($totalReceived / $totalOrdered * 100) : 0; ?>%</strong></div>
        </div>

        <?php if (!empty($po['notes'])): ?>
        <div class="mt-3">
            <div class="lbl mb-1">Notes</div>
            <div class="cell-sub"><?php echo nl2br(sanitize($po['notes'])); ?></div>
        </div>
        <?php endif; ?>

        <div class="po-signatures">
            <div class="po-sign-line"><span>Prepared By</span></div>
            <div class="po-sign-line"><span>Supplier Acknowledgement</span></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>