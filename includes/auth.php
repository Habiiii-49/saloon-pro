<?php
/**
 * Authentication & Authorization Helpers
 * Elegance Salon - PART 2
 *
 * Session keys used:
 *   user_id, role_id, role (string), name, first_name, last_name,
 *   email, username, is_logged_in
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Attempt to authenticate a user against the database.
 * Accepts email or username as the identifier.
 *
 * @param string $identifier Email or username
 * @param string $password   Plain password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticateUser(string $identifier, string $password): array
{
    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.password,
               u.phone, u.avatar, u.role_id, u.is_active, r.role_name
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.email = :login OR u.username = :login_name
        LIMIT 1
    ");
    $stmt->execute([':login' => $identifier, ':login_name' => $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string)$user['password'])) {
        return ['success' => false, 'message' => 'Invalid email/username or password.'];
    }

    if ((int)$user['is_active'] !== 1) {
        return ['success' => false, 'message' => 'Your account has been deactivated. Please contact the administrator.'];
    }

    // Prevent session fixation
    session_regenerate_id(true);

    $_SESSION['is_logged_in'] = true;
    $_SESSION['user_id']      = (int)$user['user_id'];
    $_SESSION['role_id']      = (int)$user['role_id'];
    $_SESSION['role']         = $user['role_name'];
    $_SESSION['first_name']   = $user['first_name'];
    $_SESSION['last_name']    = $user['last_name'];
    $_SESSION['name']         = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['email']        = $user['email'];
    $_SESSION['username']     = $user['username'];

    // Update last login timestamp
    $upd = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id");
    $upd->execute([':id' => $user['user_id']]);

    return ['success' => true, 'message' => 'Login successful.', 'user' => $user];
}

/** Check whether the current user is authenticated. */
function isLoggedIn(): bool
{
    return !empty($_SESSION['is_logged_in']) && !empty($_SESSION['user_id']);
}

/** Get the logged-in user's id or null. */
function currentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

/** Get the logged-in user's display name. */
function currentUserName(): string
{
    return trim($_SESSION['name'] ?? '');
}

function currentUserFirstName(): string
{
    return $_SESSION['first_name'] ?? '';
}

function currentUserLastName(): string
{
    return $_SESSION['last_name'] ?? '';
}

function currentUserEmail(): string
{
    return $_SESSION['email'] ?? '';
}

function currentUserUsername(): string
{
    return $_SESSION['username'] ?? '';
}

function currentUserRoleId()
{
    return $_SESSION['role_id'] ?? null;
}

/** Get the logged-in user's role string ('admin', 'stylist', 'receptionist', 'client'). */
function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/** Alias kept for PART 1 compatibility. */
function getUserRole(): ?string
{
    return currentRole();
}

function isAdmin(): bool
{
    return currentRole() === 'admin';
}

function isReceptionist(): bool
{
    return currentRole() === 'receptionist';
}

function isStylist(): bool
{
    return currentRole() === 'stylist';
}

function isClient(): bool
{
    return currentRole() === 'client';
}

/** Redirect to login page if the user is not authenticated. */
function requireLogin(): void
{
    if (!isLoggedIn() && !empty($_COOKIE[REMEMBER_COOKIE])) {
        attemptRememberLogin();
    }
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

/**
 * Require the current user to have one of the given roles.
 * Unauthenticated users are sent to login; wrong role → access denied.
 */
function requireRole(...$roles): void
{
    requireLogin();
    if (!in_array(currentRole(), $roles, true)) {
        header('Location: ' . SITE_URL . '/access-denied.php');
        exit;
    }
}

/** Return the dashboard URL for the current role. */
function roleHome(): string
{
    switch (currentRole()) {
        case 'admin':
            return 'admin/index.php';
        case 'receptionist':
            return 'user/dashboard.php';
        case 'stylist':
            return 'stylist/dashboard.php';
        case 'client':
            return 'index.php';
        default:
            return 'login.php';
    }
}

/** Redirect to the appropriate dashboard for the current role. */
function redirectToRoleHome(): void
{
    redirect(roleHome());
}

/** Destroy the session and its cookie. */
function performLogout(): void
{
    // Invalidate the remember-me token (DB + cookie) while the user is still known.
    clearRememberMe();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

define('REMEMBER_COOKIE', 'salon_remember');
define('REMEMBER_LIFETIME', 60 * 60 * 24 * 30); // 30 days

/**
 * Generate a random raw remember-me token and store only its hash in the DB.
 * Sets a HttpOnly, SameSite=Lax cookie containing the raw token.
 */
function establishRememberMe(int $userId): void
{
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE users SET remember_token = :token, remember_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE user_id = :id");
    $stmt->execute([':token' => $hash, ':id' => $userId]);

    setcookie(REMEMBER_COOKIE, $raw, [
        'expires'  => time() + REMEMBER_LIFETIME,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Attempt to restore a session from a valid remember-me cookie.
 * Returns true when a session was established.
 */
function attemptRememberLogin(): bool
{
    if (isLoggedIn() || empty($_COOKIE[REMEMBER_COOKIE])) {
        return false;
    }

    $raw = trim($_COOKIE[REMEMBER_COOKIE]);
    if (strlen($raw) !== 64) {
        clearRememberMe();
        return false;
    }

    $hash = hash('sha256', $raw);
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT u.user_id, u.username, u.first_name, u.last_name, u.email,
                   u.role_id, u.is_active, r.role_name
            FROM users u
            JOIN roles r ON r.role_id = u.role_id
            WHERE u.remember_token = :token AND u.is_active = 1
              AND u.remember_expires > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $hash]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        clearRememberMe();
        return false;
    }

    if (!$user) {
        clearRememberMe();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['is_logged_in'] = true;
    $_SESSION['user_id']      = (int)$user['user_id'];
    $_SESSION['role_id']      = (int)$user['role_id'];
    $_SESSION['role']         = $user['role_name'];
    $_SESSION['first_name']   = $user['first_name'];
    $_SESSION['last_name']    = $user['last_name'];
    $_SESSION['name']         = trim($user['first_name'] . ' ' . $user['last_name']);
    $_SESSION['email']        = $user['email'];
    $_SESSION['username']     = $user['username'];

    $upd = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id");
    $upd->execute([':id' => $user['user_id']]);

    return true;
}

/** Remove the remember-me token and cookie. */
function clearRememberMe(): void
{
    $userId = currentUserId();
    if ($userId) {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE user_id = :id");
            $stmt->execute([':id' => $userId]);
        } catch (PDOException $e) {
            // ignore - session cleanup must still proceed
        }
    }
    setcookie(REMEMBER_COOKIE, '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Get a fresh user row (by id) from the database.
 * Returns null if not found.
 */
function getUserById(int $userId): ?array
{
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.phone,
               u.avatar, u.role_id, u.is_active, u.last_login, u.created_at, u.updated_at,
               r.role_name
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.user_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/** Human readable role label. */
function roleLabel(string $role): string
{
    switch ($role) {
        case 'admin':        return 'Admin';
        case 'receptionist': return 'Receptionist';
        case 'stylist':      return 'Stylist';
        case 'client':       return 'Client';
        default:             return ucfirst($role);
    }
}