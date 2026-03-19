<?php error_reporting(E_ALL); ini_set('display_errors', 1); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mitra — Installer</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:#0d1117;color:#e6edf3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.box{width:100%;max-width:560px}
.card{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:36px 32px}
.logo{text-align:center;margin-bottom:28px}
.logo-icon{width:56px;height:56px;background:linear-gradient(135deg,#2f81f7,#a371f7);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;font-weight:900;color:white;margin-bottom:12px}
h1{font-size:1.4rem;font-weight:700;margin-bottom:4px}
.sub{font-size:0.85rem;color:#8b949e}
.step{background:#21262d;border:1px solid #30363d;border-radius:8px;padding:12px 14px;margin-bottom:10px;display:flex;align-items:flex-start;gap:12px}
.step-icon{font-size:17px;flex-shrink:0;margin-top:1px}
.step-title{font-weight:600;margin-bottom:2px;font-size:0.9rem}
.step-body{font-size:0.8rem;color:#8b949e}
.step.ok .step-title{color:#3fb950}
.step.fail .step-title{color:#f85149}
.step.warn .step-title{color:#d29922}
.form-group{margin-bottom:13px}
label{display:block;font-size:0.8rem;font-weight:600;color:#8b949e;margin-bottom:5px}
input,select{width:100%;background:#21262d;border:1px solid #30363d;border-radius:8px;padding:9px 12px;color:#e6edf3;font-family:inherit;font-size:0.88rem}
input:focus,select:focus{outline:none;border-color:#2f81f7;background:#1c2128}
.btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 20px;border-radius:8px;font-family:inherit;font-size:0.9rem;font-weight:700;cursor:pointer;border:none;width:100%;margin-top:6px}
.btn-primary{background:#2f81f7;color:white}
.btn-primary:hover{background:#1f6feb}
.alert{padding:12px 16px;border-radius:8px;border:1px solid;margin-bottom:14px;font-size:0.86rem}
.alert-success{background:rgba(63,185,80,.12);border-color:#3fb950;color:#3fb950}
.alert-error{background:rgba(248,81,73,.12);border-color:#f85149;color:#f85149}
.divider{border-top:1px solid #30363d;margin:18px 0}
code{font-family:monospace;background:#21262d;padding:2px 7px;border-radius:4px;font-size:0.82rem;color:#a371f7}
.section-label{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#656d76;margin:18px 0 12px;padding-bottom:8px;border-bottom:1px solid #21262d}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
</style>
</head>
<body>
<?php
$configFile = __DIR__ . '/includes/config.php';

$checks  = [];
$canProc = true;

$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
$checks['php']    = ['label'=>'PHP '.PHP_VERSION,'ok'=>$phpOk,'msg'=>$phpOk?'PHP 7.4+ ✓':'PHP 7.4 or newer required.'];
if (!$phpOk) $canProc = false;

$pdoOk = extension_loaded('pdo') && extension_loaded('pdo_mysql');
$checks['pdo']    = ['label'=>'PDO MySQL','ok'=>$pdoOk,'msg'=>$pdoOk?'PDO MySQL available ✓':'PDO MySQL extension required.'];
if (!$pdoOk) $canProc = false;

$uploadDir = __DIR__ . '/uploads';
$uploadOk  = is_dir($uploadDir) ? is_writable($uploadDir) : @mkdir($uploadDir, 0755, true);
$checks['upload'] = ['label'=>'Uploads Directory','ok'=>$uploadOk,'msg'=>$uploadOk?'Directory writable ✓':'Cannot create /uploads/ — chmod 755.'];

$inclOk = is_writable(__DIR__ . '/includes');
$checks['incl']   = ['label'=>'Includes Writable','ok'=>$inclOk,'msg'=>$inclOk?'Can write config ✓':'Set /includes/ chmod to 755.','warn'=>!$inclOk];

$error = $success = $generatedConfig = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canProc) {
    $dbHost    = trim($_POST['db_host']      ?? 'localhost');
    $dbName    = trim($_POST['db_name']      ?? '');
    $dbUser    = trim($_POST['db_user']      ?? '');
    $dbPass    = $_POST['db_pass']            ?? '';
    $baseUrl   = rtrim(trim($_POST['base_url'] ?? ''), '/');
    $adminPass = $_POST['admin_password']     ?? '';
    $coName    = trim($_POST['company_name']  ?? 'Mitra');
    $coPhone   = trim($_POST['company_phone'] ?? '');
    $coEmail   = trim($_POST['company_email'] ?? '');
    $taxRate   = (float)($_POST['tax_rate']   ?? 5.00);
    $currency  = $_POST['currency']            ?? 'CAD';

    if (!$dbName || !$dbUser)        $error = 'Database name and username are required.';
    elseif (strlen($adminPass) < 8)  $error = 'Admin password must be at least 8 characters.';
    else {
        try {
            $dsn = "mysql:host=$dbHost;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");

            // Run schemas — prefer merged file
            $schemas = [];
            if (file_exists(__DIR__.'/install_full.sql'))  $schemas[] = 'install_full.sql';
            else {
                if (file_exists(__DIR__.'/install.sql'))    $schemas[] = 'install.sql';
                if (file_exists(__DIR__.'/pos_schema.sql')) $schemas[] = 'pos_schema.sql';
            }

            foreach ($schemas as $schemaFile) {
                $sql   = file_get_contents(__DIR__.'/'.$schemaFile);
                $stmts = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($stmts as $stmt) {
                    if (!$stmt) continue;
                    try { $pdo->exec($stmt); }
                    catch (PDOException $e) { if ($e->getCode() != 23000) throw $e; }
                }
            }

            // Admin password
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $pdo->exec("UPDATE users SET password='$hash' WHERE email='admin@mitra.local'");

            // Company settings
            $sMap = [
                'company_name'       => $coName,
                'company_email'      => $coEmail,
                'company_phone'      => $coPhone,
                'pos_tax_rate'       => $taxRate,
                'pos_currency'       => $currency,
                'pos_invoice_footer' => "$coName — $coEmail",
            ];
            foreach ($sMap as $k => $v) {
                $st = $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
                $st->execute([$v, $k]);
            }

            // Write config
            $secret = bin2hex(random_bytes(32));
            $config  = "<?php\n";
            $config .= "/**\n";
            $config .= " * Mitra Business Suite\n";
            $config .= " * @author    d991d\n";
            $config .= " * @copyright 2024 d991d. All rights reserved.\n";
            $config .= " */\n\n";
            $config .= "define('DB_HOST',      " . var_export($dbHost, true) . ");\n";
            $config .= "define('DB_NAME',      " . var_export($dbName, true) . ");\n";
            $config .= "define('DB_USER',      " . var_export($dbUser, true) . ");\n";
            $config .= "define('DB_PASS',      " . var_export($dbPass, true) . ");\n";
            $config .= "define('DB_CHARSET',   'utf8mb4');\n";
            $config .= "define('BASE_URL',     " . var_export($baseUrl, true) . ");\n";
            $config .= "define('SITE_NAME',    " . var_export($coName . ' Support', true) . ");\n";
            $config .= "define('UPLOAD_DIR',   __DIR__ . '/../uploads/');\n";
            $config .= "define('UPLOAD_MAX_MB', 10);\n";
            $config .= "define('SESSION_NAME', 'mitra_session');\n";
            $config .= "define('SECRET_KEY',   " . var_export($secret, true) . ");\n";
            $config .= "define('MAIL_FROM',    " . var_export($coEmail ?: 'support@mitra.local', true) . ");\n";
            $config .= "define('MAIL_FROM_NAME', " . var_export($coName . ' Support', true) . ");\n";

            if ($inclOk) {
                file_put_contents($configFile, $config);
                $success = 'installed';
            } else {
                $success = 'manual';
                $generatedConfig = $config;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<div class="box">
<div class="card">
  <div class="logo">
    <div class="logo-icon">Y</div>
    <h1>Mitra Business Suite</h1>
    <div class="sub">Support Ticketing · POS · Invoicing — v2.0 Installer</div>
  </div>

  <?php if ($success === 'installed'): ?>
    <div class="alert alert-success" style="text-align:center;font-size:1rem;padding:16px">✅ Installation Complete!</div>
    <div style="background:#21262d;border-radius:8px;padding:16px;margin:14px 0;font-size:0.85rem;line-height:2">
      <strong style="color:#e6edf3">Admin Email:</strong> admin@mitra.local<br>
      <strong style="color:#e6edf3">Password:</strong> The one you just set
    </div>
    <div style="background:#21262d;border:1px solid #d29922;border-radius:8px;padding:13px;font-size:0.82rem;color:#d29922;margin-bottom:18px">
      ⚠️ <strong>Action required:</strong> Delete <code>install.php</code> and <code>install_full.sql</code> from your server immediately.
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <a href="index.php" style="background:#2f81f7;color:white;text-decoration:none;padding:12px;border-radius:8px;text-align:center;font-weight:700;font-size:1rem">🚀 Launch Portal</a>
      <a href="pos/dashboard.php" style="background:#21262d;border:1px solid #30363d;color:#e6edf3;text-decoration:none;padding:10px;border-radius:8px;text-align:center;font-size:0.85rem">💳 Go to POS Dashboard →</a>
    </div>

  <?php elseif ($success === 'manual'): ?>
    <div class="alert alert-success">✅ Database ready. Create config file manually.</div>
    <p style="font-size:0.82rem;color:#8b949e;margin-bottom:8px">Create <code>includes/config.php</code>:</p>
    <textarea style="width:100%;height:240px;font-family:monospace;font-size:0.75rem;background:#21262d;border:1px solid #30363d;color:#e6edf3;padding:10px;border-radius:8px" readonly><?= htmlspecialchars($generatedConfig) ?></textarea>
    <a href="index.php" class="btn btn-primary" style="margin-top:12px;text-decoration:none">Go to Login →</a>

  <?php else: ?>

  <div style="margin-bottom:18px">
    <div class="section-label">System Check</div>
    <?php foreach ($checks as $c): ?>
    <div class="step <?= $c['ok']?'ok':(($c['warn']??false)?'warn':'fail') ?>">
      <div class="step-icon"><?= $c['ok']?'✅':(($c['warn']??false)?'⚠️':'❌') ?></div>
      <div><div class="step-title"><?= htmlspecialchars($c['label']) ?></div><div class="step-body"><?= htmlspecialchars($c['msg']) ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (!$canProc): ?>
    <div class="alert alert-error">Fix the errors above before installing.</div>
  <?php else: ?>

  <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

  <form method="post">
    <div class="section-label">🗄️ Database</div>
    <div class="form-row">
      <div class="form-group"><label>Host</label><input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host']??'localhost') ?>" required></div>
      <div class="form-group"><label>Database Name</label><input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name']??'') ?>" placeholder="mitra" required></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Username</label><input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user']??'') ?>" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="db_pass"></div>
    </div>

    <div class="section-label">🌐 Site</div>
    <div class="form-group">
      <label>Base URL <span style="color:#656d76;font-weight:400">(no trailing slash)</span></label>
      <input type="text" name="base_url" value="<?= htmlspecialchars($_POST['base_url'] ?? ((isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').rtrim(dirname($_SERVER['SCRIPT_NAME']),'/\\'))) ?>">
    </div>
    <div class="form-group">
      <label>Admin Password <span style="color:#f85149">*</span></label>
      <input type="password" name="admin_password" required placeholder="Minimum 8 characters">
      <div style="font-size:0.72rem;color:#656d76;margin-top:3px">Admin login email: admin@mitra.local</div>
    </div>

    <div class="section-label">🏢 Company</div>
    <div class="form-group"><label>Company Name</label><input type="text" name="company_name" value="<?= htmlspecialchars($_POST['company_name']??'Mitra') ?>"></div>
    <div class="form-row">
      <div class="form-group"><label>Support Email</label><input type="email" name="company_email" value="<?= htmlspecialchars($_POST['company_email']??'') ?>" placeholder="support@company.com"></div>
      <div class="form-group"><label>Phone</label><input type="tel" name="company_phone" value="<?= htmlspecialchars($_POST['company_phone']??'') ?>"></div>
    </div>

    <div class="section-label">💳 POS Settings</div>
    <div class="form-row">
      <div class="form-group">
        <label>Default Tax Rate (%)</label>
        <input type="number" name="tax_rate" value="<?= htmlspecialchars($_POST['tax_rate']??'5.00') ?>" min="0" max="100" step="0.01">
        <div style="font-size:0.72rem;color:#656d76;margin-top:3px">e.g. 5 for GST, 13 for HST</div>
      </div>
      <div class="form-group">
        <label>Currency</label>
        <select name="currency">
          <?php foreach (['CAD'=>'CAD — Canadian Dollar','USD'=>'USD — US Dollar','EUR'=>'EUR — Euro','GBP'=>'GBP — British Pound'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($_POST['currency']??'CAD')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-top:12px">🚀 Install Mitra Suite</button>
  </form>
  <?php endif; ?>
  <?php endif; ?>
</div>
<p style="text-align:center;margin-top:14px;font-size:0.72rem;color:#656d76">&copy; <?= date('Y') ?> Mitra — Business Suite v1.0</p>
</div>
</body>
</html>
