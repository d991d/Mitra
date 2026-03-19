<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

if (!defined('DB_HOST')) {
    define('DB_HOST',      'localhost');
    define('DB_NAME',      'mitra');
    define('DB_USER',      'root');
    define('DB_PASS',      '');
    define('DB_CHARSET',   'utf8mb4');
    define('BASE_URL',     '');
    define('SITE_NAME',    'Mitra Support');
    define('UPLOAD_DIR',   __DIR__ . '/../uploads/');
    define('UPLOAD_MAX_MB', 10);
    define('SESSION_NAME', 'mitra_session');
    define('SECRET_KEY',   'change_this_secret_key_after_install');
    define('MAIL_FROM',    'support@mitra.local');
    define('MAIL_FROM_NAME', 'Mitra Support');
}
