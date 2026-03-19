<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

// Show errors during initial setup — remove in production if desired
// error_reporting(E_ALL); ini_set('display_errors', 1);

require_once __DIR__ . '/includes/functions.php';

startSession();
$error = '';

// Redirect if already logged in
$user = getCurrentUser();
if ($user) {
    $dest = in_array($user['role'], ['admin','agent']) ? 'admin/dashboard.php' : 'client/dashboard.php';
    header('Location: ' . BASE_URL . '/' . $dest);
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $u = login($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($u) {
            $dest = in_array($u['role'], ['admin','agent']) ? 'admin/dashboard.php' : 'client/dashboard.php';
            header('Location: ' . BASE_URL . '/' . $dest);
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

// Load registration setting safely
$allowReg = DB::setting('allow_registration');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Mitra</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-card">

      <div class="login-logo">
        <?php
        // Show company logo if uploaded
        $logo    = DB::setting('branding_logo');
        $logoUrl = ($logo && file_exists(__DIR__ . '/' . $logo))
                   ? BASE_URL . '/' . $logo : '';
        if ($logoUrl): ?>
          <img src="<?= htmlspecialchars($logoUrl) ?>"
               style="max-height:64px;max-width:200px;object-fit:contain;margin-bottom:10px;display:block;margin-left:auto;margin-right:auto"
               alt="Logo">
        <?php else: ?>
          <div class="login-icon">M</div>
        <?php endif; ?>
        <div class="login-title"><?= htmlspecialchars(DB::setting('company_name') ?: 'Mitra') ?></div>
        <div class="login-sub">Business Suite</div>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php $s = flash('success'); if ($s): ?>
      <div class="alert alert-success"><?= htmlspecialchars($s) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control"
                 required autofocus autocomplete="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="you@company.com">
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-control"
                 required autocomplete="current-password" placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary w-full"
                style="margin-top:6px;padding:10px;font-size:0.88rem">
          Sign In →
        </button>
      </form>

      <?php if ($allowReg === '1'): ?>
      <p style="text-align:center;margin-top:20px;font-size:0.85rem;color:var(--t3)">
        No account? <a href="<?= BASE_URL ?>/register.php">Create one</a>
      </p>
      <?php endif; ?>

    </div>

    <p class="login-footer">
      &copy; <?= date('Y') ?> d991d &mdash; Mitra Business Suite
    </p>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
