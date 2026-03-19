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
if (file_exists(__DIR__ . '/pos/functions_pos.php')) require_once __DIR__ . '/pos/functions_pos.php';
$currentUser = requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $name    = trim($_POST['name']    ?? '');
            $company = trim($_POST['company'] ?? '');
            $phone   = trim($_POST['phone']   ?? '');
            if (!$name) { $errors[] = 'Name is required.'; }
            else {
                DB::query("UPDATE users SET name=?,company=?,phone=? WHERE id=?",
                          [$name,$company,$phone,$currentUser['id']]);
                flash('success', 'Profile updated.');
                header('Location: profile.php'); exit;
            }
        } elseif ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!password_verify($current, $currentUser['password'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($new) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $errors[] = 'New passwords do not match.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                DB::query("UPDATE users SET password=? WHERE id=?", [$hash, $currentUser['id']]);
                flash('success', 'Password updated successfully.');
                header('Location: profile.php'); exit;
            }
        }
    }
}

// Reload
$currentUser = DB::fetch("SELECT * FROM users WHERE id=?", [$currentUser['id']]);

$pageTitle = 'My Profile';
$activeNav = 'profile';
include __DIR__ . '/includes/header.php';
?>

<div class="page-title" style="margin-bottom:20px">My Profile</div>

<div style="max-width:560px;display:flex;flex-direction:column;gap:16px">

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<!-- Profile info -->
<div class="card">
  <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px">
    <div class="user-avatar" style="width:52px;height:52px;font-size:1.3rem"><?= strtoupper(substr($currentUser['name'],0,1)) ?></div>
    <div>
      <div style="font-size:1.05rem;font-weight:700"><?= htmlspecialchars($currentUser['name']) ?></div>
      <div style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($currentUser['email']) ?> · <?= ucfirst($currentUser['role']) ?></div>
    </div>
  </div>

  <div class="card-title" style="margin-bottom:14px">Update Profile</div>
  <form method="post">
    <input type="hidden" name="csrf"   value="<?= csrf() ?>">
    <input type="hidden" name="action" value="profile">

    <div class="form-group">
      <label class="form-label">Full Name <span>*</span></label>
      <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($currentUser['name']) ?>">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Company</label>
        <input type="text" name="company" class="form-control" value="<?= htmlspecialchars($currentUser['company'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Phone</label>
        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Email</label>
      <input type="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" disabled style="opacity:0.6">
      <div class="form-hint">Contact support to change your email address</div>
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
</div>

<!-- Change password -->
<div class="card">
  <div class="card-title" style="margin-bottom:14px">Change Password</div>
  <form method="post">
    <input type="hidden" name="csrf"   value="<?= csrf() ?>">
    <input type="hidden" name="action" value="password">
    <div class="form-group">
      <label class="form-label">Current Password</label>
      <input type="password" name="current_password" class="form-control" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" required placeholder="Min 8 characters">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Change Password</button>
  </form>
</div>

<!-- Account info -->
<div class="card">
  <div class="card-title" style="margin-bottom:12px">Account Info</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.85rem">
    <div style="color:var(--t3)">Member Since</div>
    <div><?= date('F j, Y', strtotime($currentUser['created_at'])) ?></div>
    <div style="color:var(--t3)">Last Login</div>
    <div><?= $currentUser['last_login'] ? date('M j, Y g:i A', strtotime($currentUser['last_login'])) : 'N/A' ?></div>
    <div style="color:var(--t3)">Account Role</div>
    <div><?= ucfirst($currentUser['role']) ?></div>
  </div>
</div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
