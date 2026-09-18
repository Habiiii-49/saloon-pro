<?php
/**
 * Elegance Salon  |  PART 6  |  Financial core helpers.
 * Shared by the admin billing module and the receptionist panel.
 *
 * All money math is done server-side. Browser-supplied amounts are
 * validated, never trusted. Every write goes through a transaction
 * and is written to financial_audit_logs. Duplicate posts are stopped
 * with a unique request_token.
 */
require_once __DIR__ . '/functions.php';

/* Payment rows that actually represent collected money. */
function financeValidPaymentStatuses(): array
{
    return ['paid', 'completed'];
}

/* ------------------------------------------------------------------ */
/* Formatting                                                          */
/* ------------------------------------------------------------------ */

/** Currency symbols keyed by setting value. Used for receipts/invoices. */
function financeCurrencySymbols(): array
{
    return [
        'PKR' => 'Rs ',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'AED' => 'AED ',
        'SAR' => 'SAR ',
        'CAD' => 'C$',
        'AUD' => 'A$',
    ];
}

/** Format an amount in the salon's configured currency. */
function formatMoney($amount, int $decimals = 2): string
{
    $currency = getSetting('financial_currency', 'PKR');
    $symbols  = financeCurrencySymbols();
    $prefix   = $symbols[$currency] ?? (strtoupper($currency) . ' ');
    return $prefix . number_format((float)$amount, $decimals);
}

/** Parse a user-supplied money string into a non-negative float, or null. */
function moneyToFloat($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    $value = trim((string)$value);
    if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
        return null;
    }
    if (str_contains($value, '-') || str_contains($value, "\u{2212}")) {
        return null;
    }
    $value = str_replace([',', ' '], '', $value);
    $clean = preg_replace('/[^0-9.]/', '', $value);
    if ($clean === '' || $clean === '.') {
        return null;
    }
    $number = (float)$clean;
    return $number < 0 ? null : round($number, 2);
}

/* ------------------------------------------------------------------ */
/* Sequential number generation (server-side, collision-safe)          */
/* ------------------------------------------------------------------ */

/**
 * Build INV-YYYY-NNNNNN / RCT-YYYY-NNNNNN unique numbers.
 * The legacy rows use plain "INV-{apptId}" so the year-prefixed series
 * is guaranteed not to collide with old data.
 */
function financeNextNumber(PDO $db, string $entity, string $prefixKey, string $column): string
{
    $prefix = getSetting($prefixKey, $entity === 'receipt' ? 'RCT' : 'INV');
    $year   = date('Y');
    $like   = $prefix . '-' . $year . '-%';
    $table  = $entity === 'receipt' ? 'receipts' : 'invoices';

    $stmt = $db->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE :like");
    $stmt->execute([':like' => $like]);
    $maxSeq = 0;
    foreach ($stmt->fetchAll() as $row) {
        $suffix = substr((string)$row[$column], strlen($prefix) + 6);
        $seq    = (int)$suffix;
        if ($seq > $maxSeq) {
            $maxSeq = $seq;
        }
    }

    $next = $maxSeq + 1;
    while ($next > 0) {
        $candidate = $prefix . '-' . $year . '-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        $check = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :col");
        $check->execute([':col' => $candidate]);
        if ((int)$check->fetchColumn() === 0) {
            return $candidate;
        }
        $next++;
    }
    throw new RuntimeException('Unable to allocate a unique ' . $table . ' number.');
}

function generateInvoiceNumber(PDO $db): string
{
    return financeNextNumber($db, 'invoice', 'invoice_prefix', 'invoice_number');
}

function generateReceiptNumber(PDO $db): string
{
    return financeNextNumber($db, 'receipt', 'receipt_prefix', 'receipt_number');
}

/* ------------------------------------------------------------------ */
/* Invoice financial summary                                           */
/* ------------------------------------------------------------------ */

/**
 * Return [paid, refunded, net_paid, balance, payment_status] for an invoice.
 * paid    = sum of valid (collected) payment amounts
 * refunded= total refunded against those payments
 * net_paid= paid - refunded
 * balance = outstanding amount (total - net_paid), never negative
 */
function invoicePaymentSummary(PDO $db, int $invoiceId): array
{
    $stmt = $db->prepare("
        SELECT i.total,
               COALESCE(SUM(CASE WHEN p.payment_status IN ('paid','completed')
                                 THEN p.amount ELSE 0 END), 0) AS paid,
               COALESCE(SUM(p.refunded_amount), 0)              AS refunded
        FROM invoices i
        LEFT JOIN payments p ON p.invoice_id = i.invoice_id
        WHERE i.invoice_id = :id
        GROUP BY i.invoice_id
    ");
    $stmt->execute([':id' => $invoiceId]);
    $row   = $stmt->fetch();
    if (!$row) {
        return [
            'total'          => 0.0,
            'paid'           => 0.0,
            'refunded'       => 0.0,
            'net_paid'       => 0.0,
            'balance'        => 0.0,
            'payment_status' => 'unpaid',
        ];
    }
    $total = (float)$row['total'];
    $paid  = (float)$row['paid'];
    $ref   = (float)$row['refunded'];
    $net   = max(0.0, $paid - $ref);

    if ($net >= $total - 0.005 && $total > 0) {
        $status = 'paid';
    } elseif ($net > 0) {
        $status = 'partially_paid';
    } elseif ($ref > 0) {
        $status = 'refunded';
    } elseif ($total > 0) {
        $status = 'unpaid';
    } else {
        $status = 'paid';
    }

    return [
        'total'          => $total,
        'paid'           => round($paid, 2),
        'refunded'       => round($ref, 2),
        'net_paid'       => round($net, 2),
        'balance'        => round(max(0.0, $total - $net), 2),
        'payment_status' => $status,
    ];
}

/**
 * Recompose an invoice's stored paid_amount/payment_status from its
 * payments. Returns the payment summary used.
 */
function syncInvoiceFinancials(PDO $db, int $invoiceId): array
{
    $summary = invoicePaymentSummary($db, $invoiceId);
    $stmt = $db->prepare("
        UPDATE invoices
        SET paid_amount    = :paid,
            payment_status = :ps
        WHERE invoice_id   = :id
    ");
    $stmt->execute([
        ':paid' => $summary['net_paid'],
        ':ps'   => $summary['payment_status'],
        ':id'   => $invoiceId,
    ]);
    return $summary;
}

/* ------------------------------------------------------------------ */
/* Badges                                                              */
/* ------------------------------------------------------------------ */

function financePaymentStatusBadge(?string $status): string
{
    if (empty($status)) {
        return '<span class="badge text-bg-secondary">Not Billed</span>';
    }
    $map = [
        'unpaid'          => ['secondary', 'Unpaid'],
        'partially_paid'  => ['warning', 'Partially Paid'],
        'paid'            => ['success', 'Paid'],
        'refunded'        => ['info', 'Refunded'],
        'cancelled'       => ['danger', 'Cancelled'],
    ];
    [$class, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge text-bg-' . $class . '">' . htmlspecialchars($label) . '</span>';
}

function financeInvoiceStatusBadge(?string $status): string
{
    if (empty($status)) {
        return '<span class="badge text-bg-secondary">—</span>';
    }
    $map = [
        'draft'    => ['secondary', 'Draft'],
        'sent'     => ['info', 'Sent'],
        'paid'     => ['success', 'Paid'],
        'overdue'  => ['danger', 'Overdue'],
        'cancelled'=> ['dark', 'Cancelled'],
    ];
    [$class, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge text-bg-' . $class . '">' . htmlspecialchars($label) . '</span>';
}

function financePaymentRecordBadge(?string $status): string
{
    if (empty($status)) {
        return '<span class="badge text-bg-secondary">—</span>';
    }
    $map = [
        'pending'   => ['warning', 'Pending'],
        'completed' => ['success', 'Completed'],
        'paid'      => ['success', 'Paid'],
        'failed'    => ['danger', 'Failed'],
        'refunded'  => ['info', 'Refunded'],
        'partial'   => ['warning', 'Partial'],
    ];
    [$class, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge text-bg-' . $class . '">' . htmlspecialchars($label) . '</span>';
}

/* ------------------------------------------------------------------ */
/* Auditing + notifications                                            */
/* ------------------------------------------------------------------ */

/** Insert a financial audit log row. Never overrides, never deletes. */
function logFinancialAudit(PDO $db, int $userId, string $entityType, int $entityId,
                           string $action, ?string $oldValue = null,
                           ?string $newValue = null, ?string $reason = null): void
{
    $stmt = $db->prepare("
        INSERT INTO financial_audit_logs
            (user_id, entity_type, entity_id, action, old_value, new_value, reason, ip_address)
        VALUES
            (:u, :et, :eid, :a, :old, :new, :r, :ip)
    ");
    $stmt->execute([
        ':u'   => $userId,
        ':et'  => $entityType,
        ':eid' => $entityId,
        ':a'   => $action,
        ':old' => $oldValue,
        ':new' => $newValue,
        ':r'   => $reason,
        ':ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}

/** Create a notification for every admin + receptionist user. */
function notifyFinanceStaff(PDO $db, string $title, string $message, string $type = 'info'): void
{
    $stmt = $db->query("
        SELECT u.user_id
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE r.role_name IN ('admin','receptionist') AND u.is_active = 1
    ");
    foreach ($stmt->fetchAll() as $row) {
        createNotification((int)$row['user_id'], $title, $message, $type);
    }
}

/* ------------------------------------------------------------------ */
/* Payments                                                            */
/* ------------------------------------------------------------------ */

/** Collision-safe insert of a receipt for an existing payment. */
function financeReceiptForPayment(PDO $db, int $paymentId, int $byUserId): array
{
    $stmt = $db->prepare("
        SELECT payment_id FROM payments
        WHERE payment_id = :id
          AND NOT EXISTS (SELECT 1 FROM receipts WHERE receipts.payment_id = :id2)
        LIMIT 1
    ");
    $stmt->execute([':id' => $paymentId, ':id2' => $paymentId]);
    if (!$stmt->fetch()) {
        return ['ok' => false, 'error' => 'Payment not found or receipt already exists.'];
    }

    $receiptNumber = generateReceiptNumber($db);
    $stmt = $db->prepare("
        INSERT INTO receipts (payment_id, receipt_number, generated_by)
        VALUES (:pid, :num, :by)
    ");
    $stmt->execute([':pid' => $paymentId, ':num' => $receiptNumber, ':by' => $byUserId]);
    return ['ok' => true, 'receipt_number' => $receiptNumber];
}

/**
 * Record a payment against an invoice (transactional + audited).
 *
 * @param array $args invoice_id, amount, method, status, paid_at, transaction_ref,
 *                    notes, received_by, request_token (idempotency), record_receipt
 * @return array ['ok'=>bool,'error'=>string|'payment_id'=>int,'receipt_number'=>?, 'payment_status'=>string]
 */
function recordPayment(PDO $db, array $args): array
{
    $invoiceId   = (int)($args['invoice_id'] ?? 0);
    $amount      = moneyToFloat($args['amount'] ?? null);
    $method      = (string)($args['method'] ?? 'cash');
    $status      = (string)($args['status'] ?? 'completed');
    $ref         = trim((string)($args['transaction_ref'] ?? ''));
    $notes       = trim((string)($args['notes'] ?? ''));
    $paidAt      = trim((string)($args['paid_at'] ?? ''));
    $receivedBy  = (int)($args['received_by'] ?? 0);
    $token       = trim((string)($args['request_token'] ?? ''));
    $withReceipt = (bool)($args['record_receipt'] ?? true);

    $validMethods = ['cash', 'card', 'online', 'other', 'bank_transfer', 'jazzcash', 'easypaisa'];
    $validStatus  = ['pending', 'completed', 'paid'];
    $status       = in_array($status, ['completed', 'paid'], true) ? 'completed' : 'pending';

    if ($invoiceId <= 0) {
        return ['ok' => false, 'error' => 'Please choose an invoice to pay.'];
    }
    if ($amount === null || $amount <= 0) {
        return ['ok' => false, 'error' => 'Enter a valid payment amount.'];
    }
    if (!in_array($method, $validMethods, true)) {
        return ['ok' => false, 'error' => 'Invalid payment method.'];
    }
    if ($status === 'completed' && empty($paidAt)) {
        $paidAt = date('Y-m-d H:i:s');
    }

    if ($token !== '' && $token !== '0' && strlen($token) > 0 && strlen($token) <= 64) {
        $dup = $db->prepare("SELECT payment_id FROM payments WHERE request_token = :t LIMIT 1");
        $dup->execute([':t' => $token]);
        $existing = $dup->fetch();
        if ($existing) {
            return [
                'ok' => true, 'duplicate' => true,
                'payment_id' => (int)$existing['payment_id'],
                'message'    => 'This payment was already recorded.',
            ];
        }
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM invoices WHERE invoice_id = :id FOR UPDATE");
        $stmt->execute([':id' => $invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Invoice not found.'];
        }
        if ($invoice['status'] === 'cancelled') {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Cannot record a payment for a cancelled invoice.'];
        }

        $summary = invoicePaymentSummary($db, $invoiceId);
        $outstanding = $summary['balance'];

        if ($amount > $outstanding + 0.005) {
            $db->rollBack();
            return [
                'ok'          => false,
                'error'       => 'Amount exceeds the outstanding balance '
                                . formatMoney($outstanding) . '.',
                'outstanding' => $outstanding,
            ];
        }

        $paidAtSql = $status === 'completed' ? $paidAt : null;

        $stmt = $db->prepare("
            INSERT INTO payments
                (invoice_id, client_id, appointment_id, amount, refunded_amount, payment_method,
                 payment_status, transaction_ref, paid_at, notes, received_by, request_token)
            VALUES
                (:inv, :cid, :aid, :amt, 0.00, :m, :st, :ref, :paid, :notes, :rb, :tok)
        ");
        $stmt->execute([
            ':inv'   => $invoiceId,
            ':cid'   => $invoice['client_id'],
            ':aid'   => $invoice['appointment_id'],
            ':amt'   => round($amount, 2),
            ':m'     => $method,
            ':st'    => $status,
            ':ref'   => $ref !== '' ? $ref : null,
            ':paid'  => $paidAtSql,
            ':notes' => $notes !== '' ? $notes : null,
            ':rb'    => $receivedBy > 0 ? $receivedBy : null,
            ':tok'   => ($token !== '' && $token !== '0') ? $token : null,
        ]);
        $paymentId = (int)$db->lastInsertId();

        $receiptNumber = null;
        if ($status === 'completed' && $withReceipt) {
            $receiptNumber = generateReceiptNumber($db);
            $stmt = $db->prepare("
                INSERT INTO receipts (payment_id, receipt_number, generated_by)
                VALUES (:pid, :num, :by)
            ");
            $stmt->execute([
                ':pid' => $paymentId,
                ':num' => $receiptNumber,
                ':by'  => $receivedBy > 0 ? $receivedBy : null,
            ]);
        }

        $paymentStatus = syncInvoiceFinancials($db, $invoiceId)['payment_status'];

        logFinancialAudit($db, $receivedBy, 'payment', $paymentId, 'payment.recorded',
                          null, json_encode([
                              'invoice_id'   => $invoiceId,
                              'amount'       => round($amount, 2),
                              'method'       => $method,
                              'status'       => $paidAtSql ? 'completed' : 'pending',
                          ]), 'Payment of ' . formatMoney($amount));

        $db->commit();

        if ($status === 'completed') {
            notifyFinanceStaff($db,
                'Payment received',
                'Payment of ' . formatMoney($amount) . ' received for invoice '
                . $invoice['invoice_number'] . '.',
                'success');
        }

        return [
            'ok'             => true,
            'duplicate'      => false,
            'payment_id'     => $paymentId,
            'receipt_number' => $receiptNumber,
            'payment_status' => $paymentStatus,
        ];
    } catch (RuntimeException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'Database error recording payment.'];
    }
}

/* ------------------------------------------------------------------ */
/* Refunds                                                             */
/* ------------------------------------------------------------------ */

/**
 * Refund part (or all) of a valid payment.
 * Original payment row is kept; a refunds row is the audit trail.
 * Also writes a reversal receipt-numbered reference. Admin only.
 *
 * @param array $args payment_id, amount, reason, reference, processed_by
 */
function processRefund(PDO $db, array $args): array
{
    $paymentId  = (int)($args['payment_id'] ?? 0);
    $amount     = moneyToFloat($args['amount'] ?? null);
    $reason     = trim((string)($args['reason'] ?? ''));
    $reference  = trim((string)($args['reference'] ?? ''));
    $byUserId   = (int)($args['processed_by'] ?? 0);

    if ($paymentId <= 0) {
        return ['ok' => false, 'error' => 'Choose a payment to refund.'];
    }
    if ($amount === null || $amount <= 0) {
        return ['ok' => false, 'error' => 'Enter a valid refund amount.'];
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT * FROM payments WHERE payment_id = :id FOR UPDATE");
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Payment not found.'];
        }
        if (!in_array($payment['payment_status'], ['paid', 'completed', 'partial'], true)) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Only collected payments can be refunded.'];
        }

        $netRefundable = round((float)$payment['amount'] - (float)$payment['refunded_amount'], 2);
        if ($amount > $netRefundable + 0.005) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Refund exceeds the refundable amount of '
                                              . formatMoney($netRefundable) . '.'];
        }

        $stmt = $db->prepare("
            INSERT INTO refunds (payment_id, invoice_id, amount, refund_reason, refund_reference, status, processed_by)
            VALUES (:pid, :iid, :amt, :rr, :rf, 'completed', :pb)
        ");
        $stmt->execute([
            ':pid' => $paymentId,
            ':iid' => $payment['invoice_id'],
            ':amt' => round($amount, 2),
            ':rr'  => $reason !== '' ? $reason : null,
            ':rf'  => $reference !== '' ? $reference : null,
            ':pb'  => $byUserId > 0 ? $byUserId : null,
        ]);

        $newRefunded = round((float)$payment['refunded_amount'] + $amount, 2);
        $newStatus   = $newRefunded + 0.005 >= (float)$payment['amount'] ? 'refunded' : $payment['payment_status'];

        $stmt = $db->prepare("
            UPDATE payments
            SET refunded_amount = :ra, payment_status = :ps
            WHERE payment_id    = :id
        ");
        $stmt->execute([
            ':ra' => $newRefunded,
            ':ps' => $newStatus,
            ':id' => $paymentId,
        ]);

        if (!$payment['invoice_id']) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Payment is not attached to an invoice.'];
        }

        $refundId = (int)$db->lastInsertId();
        $paymentStatus = syncInvoiceFinancials($db, (int)$payment['invoice_id'])['payment_status'];

        logFinancialAudit($db, $byUserId, 'refund', $refundId, 'refund.created',
                          json_encode(['payment_id' => $paymentId, 'amount' => round($amount, 2), 'status' => $newStatus]),
                          json_encode(['payment_status' => $paymentStatus]), 'Refund issued');

        $db->commit();

        notifyFinanceStaff($db,
            'Refund issued',
            'A refund of ' . formatMoney($amount) . ' was issued for payment #' . $paymentId . '.',
            'warning');

        return ['ok' => true, 'refund_id' => $refundId, 'payment_status' => $paymentStatus];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'Database error processing refund.'];
    }
}

/* ------------------------------------------------------------------ */
/* Server-side invoice totals                                          */
/* ------------------------------------------------------------------ */

/**
 * Recompute invoice money from line items. Every figure is derived
 * from validated server-side inputs only.
 *
 * @param array $items  [['service_name','quantity','unit_price','discount'], ...]
 * @param float $taxRate percent
 * @param float $discount invoice-level discount
 */
function composeInvoiceTotals(array $items, float $taxRate, float $discount): array
{
    $subtotal = 0.0;
    $taxAmt   = 0.0;
    $lines    = [];
    foreach ($items as $it) {
        $qty    = max(1, (int)($it['quantity'] ?? 1));
        $price  = max(0.0, moneyToFloat($it['unit_price'] ?? 0) ?? 0.0);
        $disc   = max(0.0, moneyToFloat($it['discount'] ?? 0) ?? 0.0);
        $name   = trim((string)($it['service_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $raw       = round($price * $qty, 2);
        $lineDiscount = min($disc, $raw);
        $taxable   = max(0.0, $raw - $lineDiscount);
        $lineTax   = round($taxable * ($taxRate / 100), 2);
        $lineTotal = round($raw - $lineDiscount + $lineTax, 2);
        $subtotal += $raw;
        $taxAmt   += $lineTax;
        $lines[]   = [
            'service_id'   => $it['service_id'] ?? null,
            'service_name' => $name,
            'quantity'     => $qty,
            'unit_price'   => round($price, 2),
            'discount'     => round($lineDiscount, 2),
            'tax'          => round($lineTax, 2),
            'line_total'   => $lineTotal,
        ];
    }
    $subtotal = round($subtotal, 2);
    $invoiceDisc = min($discount, $subtotal);
    $taxAmt   = round($taxAmt, 2);
    $total    = round($subtotal - $invoiceDisc + $taxAmt, 2);
    if ($total < 0) {
        $total = 0.0;
    }
    return [
        'items'    => $lines,
        'subtotal' => $subtotal,
        'tax_rate' => round($taxRate, 2),
        'tax'      => $taxAmt,
        'discount' => round($invoiceDisc, 2),
        'total'    => $total,
    ];
}

/* ------------------------------------------------------------------ */
/* Revenue                                                        (P6) */
/* ------------------------------------------------------------------ */

/** Normalize a user date range into [from, to-exclusive] SQL binds. */
function financeNormalizeRange(string $from, string $to): array
{
    $from = $from !== '' ? date('Y-m-d', strtotime($from)) : date('Y-m-01');
    $to   = $to !== '' ? date('Y-m-d', strtotime($to)) : date('Y-m-d');
    return [$from, $to];
}

/**
 * Revenue figures for a date range (inclusive of both ends). Only real,
 * collected payments count (certified=paid/completed, date=paid_at).
 */
function financeRevenueRange(PDO $db, string $from, string $to): array
{
    [$from, $to] = financeNormalizeRange($from, $to);

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(p.amount), 0)                           AS gross,
               COALESCE(SUM(p.amount - p.refunded_amount), 0)       AS net,
               COUNT(p.payment_id)                                  AS payments
        FROM payments p
        WHERE p.payment_status IN ('paid','completed')
          AND p.paid_at >= :from AND p.paid_at < DATE_ADD(:to, INTERVAL 1 DAY)
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $revenue = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(r.amount), 0) AS refunds, COUNT(r.refund_id) AS count
        FROM refunds r
        WHERE r.status = 'completed'
          AND r.created_at >= :from AND r.created_at < DATE_ADD(:to, INTERVAL 1 DAY)
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $re = $stmt->fetch();

    $stmt = $db->prepare("
        SELECT COUNT(*) AS issued
        FROM invoices
        WHERE status <> 'cancelled'
          AND issued_at >= :from AND issued_at <= :to
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $issued = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total - paid_amount), 0) AS outstanding
        FROM invoices
        WHERE status <> 'cancelled' AND payment_status IN ('unpaid','partially_paid')
    ");
    $stmt->execute();
    $outstanding = (float)$stmt->fetchColumn();

    return [
        'from'         => $from,
        'to'           => $to,
        'gross'        => round((float)$revenue['gross'], 2),
        'net'          => round((float)$revenue['net'], 2),
        'refunds'      => round((float)$re['refunds'], 2),
        'refund_count' => (int)$re['count'],
        'payments'     => (int)$revenue['payments'],
        'invoices'     => $issued,
        'outstanding'  => round($outstanding, 2),
    ];
}

/**
 * Grouped revenue series for the charts. $group: day|month|method|service|stylist.
 */
function financeRevenueGroup(PDO $db, string $from, string $to, string $group): array
{
    [$from, $to] = financeNormalizeRange($from, $to);
    $group = in_array($group, ['day', 'month', 'method', 'service', 'stylist'], true) ? $group : 'day';

    if ($group === 'day') {
        $sql = "SELECT DATE(p.paid_at) AS label, SUM(p.amount - p.refunded_amount) AS value
                FROM payments p
                WHERE p.payment_status IN ('paid','completed')
                  AND p.paid_at >= :from AND p.paid_at < DATE_ADD(:to, INTERVAL 1 DAY)
                GROUP BY DATE(p.paid_at) ORDER BY label ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll();
    }

    if ($group === 'month') {
        $sql = "SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS label, SUM(p.amount - p.refunded_amount) AS value
                FROM payments p
                WHERE p.payment_status IN ('paid','completed')
                  AND p.paid_at >= :from AND p.paid_at < DATE_ADD(:to, INTERVAL 1 DAY)
                GROUP BY DATE_FORMAT(p.paid_at, '%Y-%m') ORDER BY label ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll();
    }

    if ($group === 'method') {
        $sql = "SELECT p.payment_method AS label, SUM(p.amount - p.refunded_amount) AS value
                FROM payments p
                WHERE p.payment_status IN ('paid','completed')
                  AND p.paid_at >= :from AND p.paid_at < DATE_ADD(:to, INTERVAL 1 DAY)
                GROUP BY p.payment_method ORDER BY value DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll();
    }

    if ($group === 'service') {
        $sql = "
            SELECT COALESCE(s.service_name, ii.service_name) AS label,
                   SUM(ii.line_total / NULLIF(i.total, 0) * agg.net_received) AS value
            FROM invoice_items ii
            JOIN invoices i ON i.invoice_id = ii.invoice_id
            LEFT JOIN services s ON s.service_id = ii.service_id
            JOIN (
                SELECT invoice_id, SUM(amount - refunded_amount) AS net_received
                FROM payments
                WHERE payment_status IN ('paid','completed')
                  AND paid_at >= :from AND paid_at < DATE_ADD(:to, INTERVAL 1 DAY)
                GROUP BY invoice_id
            ) agg ON agg.invoice_id = i.invoice_id
            GROUP BY label
            ORDER BY value DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll();
    }

    $sql = "
        SELECT COALESCE(CONCAT(us.first_name, ' ', us.last_name), 'Unassigned') AS label,
               SUM(p.amount - p.refunded_amount) AS value
        FROM payments p
        JOIN invoices i ON i.invoice_id = p.invoice_id
        LEFT JOIN staff st ON st.staff_id = i.stylist_id
        LEFT JOIN users us ON us.user_id = st.user_id
        WHERE p.payment_status IN ('paid','completed')
          AND p.paid_at >= :from AND p.paid_at < DATE_ADD(:to, INTERVAL 1 DAY)
        GROUP BY i.stylist_id, label
        ORDER BY value DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':from' => $from, ':to' => $to]);
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------------ */
/* Invoice datasets (shared by admin + receptionist + print)           */
/* ------------------------------------------------------------------ */

function getInvoiceFull(PDO $db, int $invoiceId): ?array
{
    $stmt = $db->prepare("
        SELECT i.*,
               CONCAT(c.first_name, ' ', c.last_name) AS client_name,
               c.phone AS client_phone, c.email AS client_email, c.address AS client_address,
               CONCAT(su.first_name, ' ', su.last_name) AS stylist_name,
               CONCAT(u.first_name, ' ', u.last_name) AS created_by_name,
               a.appointment_date, a.appointment_time, a.status AS appointment_status
        FROM invoices i
        JOIN clients c     ON c.client_id = i.client_id
        LEFT JOIN appointments a ON a.appointment_id = i.appointment_id
        LEFT JOIN services svc   ON svc.service_id = a.service_id
        LEFT JOIN staff st       ON st.staff_id = i.stylist_id
        LEFT JOIN users su       ON su.user_id = st.user_id
        LEFT JOIN users u        ON u.user_id = i.created_by
        WHERE i.invoice_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $invoiceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getInvoiceItemsFull(PDO $db, int $invoiceId): array
{
    $stmt = $db->prepare("
        SELECT ii.*
        FROM invoice_items ii
        WHERE ii.invoice_id = :id
        ORDER BY ii.invoice_item_id ASC
    ");
    $stmt->execute([':id' => $invoiceId]);
    return $stmt->fetchAll();
}

function getInvoicePaymentsFull(PDO $db, int $invoiceId): array
{
    $stmt = $db->prepare("
        SELECT p.*,
               CONCAT(u.first_name, ' ', u.last_name) AS received_by_name,
               r.receipt_id, r.receipt_number
        FROM payments p
        LEFT JOIN users u ON u.user_id = p.received_by
        LEFT JOIN receipts r ON r.payment_id = p.payment_id
        WHERE p.invoice_id = :id
        ORDER BY p.created_at ASC, p.payment_id ASC
    ");
    $stmt->execute([':id' => $invoiceId]);
    return $stmt->fetchAll();
}