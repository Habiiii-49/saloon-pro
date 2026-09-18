<?php
/**
 * Inventory AJAX – Inventory notifications.
 * Actions: list | read | read_all
 */
require_once __DIR__ . '/_guard.php';

$db = getDBConnection();

$action = $_POST['action'] ?? 'list';

switch ($action) {

    case 'list':
        $stmt = $db->prepare("
            SELECT n.id, n.product_id, n.type, n.message, n.is_read, n.created_at,
                   i.item_name
            FROM inventory_notifications n
            LEFT JOIN inventory i ON i.inventory_id = n.product_id
            ORDER BY n.id DESC
            LIMIT 50
        ");
        $stmt->execute();
        invJson(['ok' => true, 'data' => $stmt->fetchAll()]);

    case 'read':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            invJson(['ok' => false, 'error' => 'Notification ID is required.']);
        }
        $db->prepare("UPDATE inventory_notifications SET is_read = 1 WHERE id = :id")->execute([':id' => $id]);
        invJson(['ok' => true]);

    case 'read_all':
        $db->exec("UPDATE inventory_notifications SET is_read = 1 WHERE is_read = 0");
        invJson(['ok' => true]);

    default:
        invJson(['ok' => false, 'error' => 'Unknown action.'], 400);
}