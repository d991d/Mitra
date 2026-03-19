<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

require_once __DIR__ . '/includes/functions.php';
startSession();

if (DB::setting('allow_registration') !== '1') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$user = getCurrentUser();
if ($user) {
    header('Location: ' . BASE_URL . '/client/dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';
        $co    = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (strlen($name) < 2)     $errors[] = 'Name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (strlen($pass) < 8)     $errors[] = 'Password must be at least 8 characters.';
        if ($pass !== $pass2)      $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $exists = DB::fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if ($exists) {
                $errors[] = 'An account with that email already exists.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $id = DB::insert(
                    "INSERT INTO users (name, email, password, role, company, phone) VALUES (?,?,?,'client',?,?)",
                    [$name, $email, $hash, $co, $phone]
                );
                logActivity($id, 'register', 'user', $id);
                flash('success', 'Account created! Please sign in.');
                header('Location: ' . BASE_URL . '/index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — Mitra Support</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-box" style="max-width:480px">
    <div class="login-card">
      <div class="login-logo">
        <div class="login-icon">Y</div>
        <div class="login-title">Create Account</div>
        <div class="login-sub">Mitra Support Portal</div>
      </div>

      <?php foreach ($errors as $e): ?>
      <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name <span>*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Company</label>
            <input type="text" name="company" class="form-control" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email <span>*</span></label>
          <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password <span>*</span></label>
            <input type="password" name="password" class="form-control" required placeholder="Min 8 chars">
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password <span>*</span></label>
            <input type="password" name="password2" class="form-control" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" style="justify-content:center;margin-top:4px;padding:11px;">
          Create Account
        </button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:0.85rem;color:var(--t3);">
        Already have an account? <a href="<?= BASE_URL ?>/index.php">Sign in</a>
      </p>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
