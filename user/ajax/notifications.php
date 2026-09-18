<?php
/**
 * Elegance Salon - AJAX: notifications
 *  GET  user/ajax/notifications.php?action=list   -> JSON unread+recent
 *  POST user/ajax/notifications.php { action: mark_read|mark_all, id?, csrf_token }
 */
require_once __DIR__ . '/_guard.php';

$db = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = getJsonPayload();
    $action  = $payload['action'] ?? '';
    $token   = $payload['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
        exit;
    }

    try {
        if ($action === 'mark_read') {
            $id = (int)($payload['id'] ?? 0);
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :id AND (user_id = :uid OR user_id IS NULL)");
            $stmt->execute([':id' => $id, ':uid' => currentUserId()]);
            echo json_encode(['ok' => true]);
        } elseif ($action === 'mark_all') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (user_id = :uid OR user_id IS NULL)");
            $stmt->execute([':uid' => currentUserId()]);
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Notification update failed.']);
    }
    exit;
}

// GET: return list
try {
    $stmt = $db->prepare("
        SELECT notification_id, title, message, type, is_read, created_at
        FROM notifications
        WHERE user_id = :uid OR user_id IS NULL
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute([':uid' => currentUserId()]);
    $list = $stmt->fetchAll();

    $unread = 0;
    foreach ($list as $n) {
        if ((int)$n['is_read'] === 0) $unread++;
    }
    echo json_encode(['ok' => true, 'unread' => $unread, 'notifications' => $list]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load notifications.']);
}