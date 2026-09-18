<?php
/**
 * Core Functions
 * Elegance Salon
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit;
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}

function setFlash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function getFlash() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function getSetting($key, $default = '') {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2);
}

function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

function getActiveServices($limit = null) {
    try {
        $db = getDBConnection();
        $sql = "SELECT * FROM services WHERE is_active = 1 ORDER BY is_popular DESC, service_name ASC";
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        $stmt = $db->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getGalleryItems($category = 'All') {
    try {
        $db = getDBConnection();
        if ($category === 'All') {
            $stmt = $db->query("SELECT * FROM gallery WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
        } else {
            $stmt = $db->prepare("SELECT * FROM gallery WHERE is_active = 1 AND category = :category ORDER BY sort_order ASC, created_at DESC");
            $stmt->execute([':category' => $category]);
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getTeamMembers() {
    try {
        $db = getDBConnection();
        $stmt = $db->query("
            SELECT s.*, u.first_name, u.last_name, u.email, u.avatar, u.phone
            FROM staff s
            JOIN users u ON s.user_id = u.user_id
            WHERE u.is_active = 1
            ORDER BY s.experience_years DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function getCurrentPage() {
    $page = basename($_SERVER['PHP_SELF'], '.php');
    return $page === 'index' ? 'home' : $page;
}

/**
 * Find an existing client by email or create a new client row.
 * Returns the client_id.
 */
function findOrCreateClient(string $firstName, string $lastName, string $email, string $phone, ?int $userId = null): int
{
    $db = getDBConnection();

    if ($userId !== null) {
        $stmt = $db->prepare("SELECT client_id FROM clients WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }
    }

    if ($email !== '') {
        $stmt = $db->prepare("SELECT client_id FROM clients WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }
    }

    $stmt = $db->prepare(
        "INSERT INTO clients (user_id, first_name, last_name, email, phone) VALUES (:uid, :f, :l, :e, :p)"
    );
    $stmt->execute([
        ':uid' => $userId,
        ':f'   => $firstName,
        ':l'   => $lastName,
        ':e'   => $email !== '' ? $email : null,
        ':p'   => $phone !== '' ? $phone : null,
    ]);
    return (int)$db->lastInsertId();
}

/** Fetch a service row by id (null if missing / inactive). */
function getServiceById(int $serviceId): ?array
{
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM services WHERE service_id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute([':id' => $serviceId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Find an available stylist for a booking (first available = least booked today). */
function nextAvailableStylist(?string $appointmentDate = null): ?int
{
    $db = getDBConnection();
    $date = $appointmentDate ?: date('Y-m-d');
    try {
        $stmt = $db->prepare("
            SELECT s.staff_id, COUNT(a.appointment_id) AS booked
            FROM staff s
            LEFT JOIN appointments a
                ON a.staff_id = s.staff_id
               AND a.appointment_date = :date
               AND a.status IN ('pending', 'confirmed', 'in_progress')
            WHERE s.is_available = 1
            GROUP BY s.staff_id
            ORDER BY booked ASC, s.staff_id ASC
            LIMIT 1
        ");
        $stmt->execute([':date' => $date]);
        $staffId = $stmt->fetchColumn();
        return $staffId ? (int)$staffId : null;
    } catch (PDOException $e) {
        return null;
    }
}

/** Insert a row into the notifications table (admin broadcasts). */
function createNotification(?int $userId, string $title, string $message, string $type = 'info'): void
{
    if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) {
        $type = 'info';
    }
    try {
        $db = getDBConnection();
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :t, :m, :ty)"
        );
        $stmt->execute([':uid' => $userId, ':t' => $title, ':m' => $message, ':ty' => $type]);
    } catch (PDOException $e) {
        // Notifications must never break the request flow.
    }
}
