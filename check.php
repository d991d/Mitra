<?php
/**
 * Mitra Business Suite — System Diagnostic
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 *
 * Visit this page if you see a blank screen or errors.
 * DELETE this file after diagnosing the issue.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Mitra Diagnostics</title>';
echo '<style>body{font-family:monospace;background:#0d1117;color:#e6edf3;padding:32px;line-height:1.8}';
echo '.ok{color:#3fb950}.fail{color:#f85149}.warn{color:#d29922}';
echo 'h1{color:#2f81f7;margin-bottom:16px}h2{color:#8b949e;margin:20px 0 8px;font-size:1rem}';
echo 'pre{background:#161b22;border:1px solid #30363d;padding:12px;border-radius:6px;overflow-x:auto}</style></head><body>';
echo '<h1>🔍 Mitra Diagnostics</h1>';

// PHP version
$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
echo '<h2>PHP</h2>';
echo '<span class="' . ($phpOk ? 'ok' : 'fail') . '">' . ($phpOk ? '✅' : '❌') . ' PHP ' . PHP_VERSION . '</span><br>';

// Extensions
foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'fileinfo'] as $ext) {
    $ok = extension_loaded($ext);
    echo '<span class="' . ($ok ? 'ok' : 'fail') . '">' . ($ok ? '✅' : '❌') . ' ' . $ext . '</span><br>';
}

// Config file
echo '<h2>Config File</h2>';
$configPath = __DIR__ . '/includes/config.php';
if (file_exists($configPath)) {
    echo '<span class="ok">✅ includes/config.php exists</span><br>';
    require_once $configPath;
    echo '<span class="ok">✅ Config loaded — DB_HOST: ' . htmlspecialchars(DB_HOST) . '</span><br>';
    echo '<span class="ok">✅ DB_NAME: ' . htmlspecialchars(DB_NAME) . '</span><br>';
    echo '<span class="ok">✅ BASE_URL: ' . htmlspecialchars(BASE_URL ?: '(empty — OK for root install)') . '</span><br>';
} else {
    echo '<span class="fail">❌ includes/config.php NOT FOUND — run install.php first</span><br>';
    echo '</body></html>';
    exit;
}

// Database connection
echo '<h2>Database</h2>';
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo '<span class="ok">✅ Connected to MySQL successfully</span><br>';

    // Check tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users','tickets','settings','pos_invoices','pos_products','pos_sales'];
    foreach ($required as $t) {
        $ok = in_array($t, $tables);
        echo '<span class="' . ($ok ? 'ok' : 'fail') . '">' . ($ok ? '✅' : '❌') . ' Table: ' . $t . '</span><br>';
    }

    // Check admin user
    $admin = $pdo->query("SELECT id, name, email, role FROM users WHERE role='admin' LIMIT 1")->fetch();
    if ($admin) {
        echo '<span class="ok">✅ Admin user: ' . htmlspecialchars($admin['email']) . '</span><br>';
    } else {
        echo '<span class="warn">⚠️ No admin user found — run install.php</span><br>';
    }

} catch (PDOException $e) {
    echo '<span class="fail">❌ DB Connection FAILED: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
    echo '<br><strong>Fix:</strong> Check DB_HOST, DB_NAME, DB_USER, DB_PASS in <code>includes/config.php</code>';
}

// Uploads dir
echo '<h2>Directories</h2>';
$dirs = ['uploads', 'uploads/branding'];
foreach ($dirs as $d) {
    $path = __DIR__ . '/' . $d;
    $exists   = is_dir($path);
    $writable = $exists && is_writable($path);
    if (!$exists) echo '<span class="warn">⚠️ Missing: ' . $d . ' — will be created on first use</span><br>';
    elseif (!$writable) echo '<span class="fail">❌ Not writable: ' . $d . ' — chmod 755</span><br>';
    else echo '<span class="ok">✅ ' . $d . ' — writable</span><br>';
}

// Session
echo '<h2>Session</h2>';
if (session_status() === PHP_SESSION_NONE) {
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'mitra_session');
    session_start();
}
echo '<span class="ok">✅ Sessions working — ID: ' . session_id() . '</span><br>';

echo '<h2>Done</h2>';
echo '<p>If everything shows ✅, visit <a href="index.php" style="color:#2f81f7">index.php</a> to log in.</p>';
echo '<p class="warn">⚠️ Delete check.php from your server after diagnosing.</p>';
echo '</body></html>';
