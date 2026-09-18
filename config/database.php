<?php
/**
 * Database Configuration
 * Elegance Salon - db-saloon
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'db-saloon');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Auto-detect base URL so the site works regardless of folder name
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/'));
$scriptDir = str_replace('\\', '/', dirname(__DIR__));
$relativePath = str_replace($documentRoot, '', $scriptDir);
define('SITE_URL', rtrim($protocol . '://' . $_SERVER['HTTP_HOST'] . $relativePath, '/'));
define('SITE_NAME', 'Elegance Salon');
define('SITE_VERSION', '1.0.0');

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Setup Required - Elegance Salon</title></head>'
                . '<body style="font-family:Arial,sans-serif;background:#071827;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;">'
                . '<div style="max-width:560px;text-align:center;padding:32px;">'
                . '<h1 style="margin:0 0 12px;font-size:28px;">Database Setup Required</h1>'
                . '<p style="line-height:1.7;color:#94A3B8;">Elegance Salon could not connect to the database. '
                . 'Please import the database file <code style="background:#0B2235;padding:2px 8px;border-radius:4px;">database/db-saloon.sql</code> '
                . 'using <a href="http://localhost/phpmyadmin/" style="color:#00C2D9;">phpMyAdmin</a>, then refresh this page.</p>'
                . '</div></body></html>';
            exit;
        }
    }
    return $pdo;
}
