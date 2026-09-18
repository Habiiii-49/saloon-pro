<?php
/**
 * AJAX - Record / refund payments (POST, admin).
 * Used by the payments create form and refund action.
 */

require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../../../includes/finance.php';

$db        = getDBConnection();
$action    = (string)($_POST['action'] ?? '');
$adminId   = currentUserId();

try {
    if ($action === 'create') {
        $token = trim((string)($_POST['request_token'] ?? ''));
        if ($token === '' || strlen($token) > 64) {
            finJson(['ok' => false, 'error' => 'Missing payment request token.'], 400);
        }

        $result = recordPayment($db, [
            'invoice_id'      => (int)($_POST['invoice_id'] ?? 0),
            'client_id'       => '',
            'amount'          => $_POST['amount'] ?? '',
            'method'          => $_POST['method'] ?? 'cash',
            'status'          => ($_POST['status'] ?? 'completed') === 'pending' ? 'pending' : 'completed',
            'transaction_ref' => $_POST['transaction_ref'] ?? '',
            'paid_at'         => $_POST['paid_at'] ?? '',
            'notes'           => $_POST['notes'] ?? '',
            'received_by'     => $adminId,
            'request_token'   => $token,
        ]);

        if (!$result['ok']) {
            finJson(['ok' => false, 'error' => $result['error']], 422);
        }
        finJson([
            'ok'             => true,
            'duplicate'      => $result['duplicate'] ?? false,
            'payment_id'     => $result['payment_id'],
            'receipt_number' => $result['receipt_number'] ?? null,
            'payment_status' => $result['payment_status'],
            'message'        => $result['message'] ?? 'Payment recorded successfully.',
        ]);

    } elseif ($action === 'refund') {
        $result = processRefund($db, [
            'payment_id'   => (int)($_POST['payment_id'] ?? 0),
            'amount'       => $_POST['amount'] ?? '',
            'reason'       => $_POST['reason'] ?? '',
            'reference'    => $_POST['reference'] ?? '',
            'processed_by' => $adminId,
        ]);
        if (!$result['ok']) {
            finJson(['ok' => false, 'error' => $result['error']], 422);
        }
        finJson(['ok' => true, 'refund_id' => $result['refund_id'], 'payment_status' => $result['payment_status']]);
    }

    finJson(['ok' => false, 'error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    finJson(['ok' => false, 'error' => 'Unexpected error processing the request.'], 500);
}