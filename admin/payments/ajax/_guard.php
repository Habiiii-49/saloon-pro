<?php
/**
 * Shared guard for billing AJAX endpoints (Part 6).
 * Enforces: valid session, admin role, POST method and CSRF token.
 */

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/finance.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/**
 * Emit a JSON response and stop.
 */
function finJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/* ---- Authentication / authorisation ---- */
if (!isLoggedIn()) {
    finJson(['ok' => false, 'error' => 'Your session has expired. Please sign in again.'], 401);
}
if (!isAdmin()) {
    finJson(['ok' => false, 'error' => 'You do not have permission to perform this action.'], 403);
}

/* ---- Method ---- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    finJson(['ok' => false, 'error' => 'Invalid request method.'], 405);
}

/* ---- CSRF ---- */
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCSRFToken($token)) {
    finJson(['ok' => false, 'error' => 'Security token mismatch. Refresh the page and try again.'], 403);
}