<?php
/**
 * Elegance Salon - shared AJAX guard for receptionist endpoints.
 * Returns JSON 401/403 instead of a redirect so fetch() can handle it.
 */
require_once __DIR__ . '/../../includes/functions.php';

function ajaxGuard(): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
        exit;
    }
    if (!isReceptionist()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
}

/** Read JSON body (or fall back to POST fields) for POST endpoints. */
function getJsonPayload(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        return $_POST;
    }
    return $data;
}