<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

// =============================================================
// Mitra Ticketing - Configuration
// Rename this file to config.php and fill in your DB details
// =============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', 'https://yourdomain.com/ticketing'); // No trailing slash
define('SITE_NAME', 'Mitra Support');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_MB', 10);

define('SESSION_NAME', 'mitra_session');
define('SECRET_KEY', 'CHANGE_THIS_TO_A_RANDOM_64_CHAR_STRING');

// Email (optional - requires PHP mail or SMTP)
define('MAIL_FROM', 'support@mitra.local');
define('MAIL_FROM_NAME', 'Mitra Support');
