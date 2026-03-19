<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

require_once __DIR__ . '/../includes/functions.php';
if (file_exists(__DIR__ . '/../pos/functions_pos.php')) require_once __DIR__ . '/../pos/functions_pos.php';
$currentUser = requireAdmin();

$errors = [];
$success = '';

// Add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $pass    = $_POST['password']     ?? '';
        $role    = $_POST['role']         ?? 'client';
        $company = trim($_POST['company'] ?? '');
        $phone   = trim($_POST['phone']   ?? '');

        if (!$name)  $errors[] = 'Name required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
        if (strlen($pass) < 8) $errors[] = 'Password min 8 chars.';
        if (!in_array($role, ['admin','agent','client'])) $errors[] = 'Invalid role.';

        if (empty($errors)) {
            if (DB::fetch("SELECT id FROM users WHERE email=?", [$email])) {
                $errors[] = 'Email already in use.';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                DB::insert("INSERT INTO users (name,email,password,role,company,phone) VALUES (?,?,?,?,?,?)",
                           [$name,$email,$hash,$role,$company,$phone]);
                flash('success', 'User created successfully.');
                header('Location: users.php');
                exit;
            }
        }
    }
}

// Toggle active
if (isset($_GET['toggle']) && $_GET['_csrf'] ?? '' === ($_SESSION['csrf'] ?? '')) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== $currentUser['id']) {
        DB::query("UPDATE users SET is_active = 1-is_active WHERE id=?", [$uid]);
    }
    header('Location: users.php');
    exit;
}

// Delete user
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== $currentUser['id']) {
        DB::query("DELETE FROM users WHERE id=?", [$uid]);
        flash('success', 'User deleted.');
    }
    header('Location: users.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$where  = $search ? "WHERE name LIKE ? OR email LIKE ? OR company LIKE ?" : "";
$params = $search ? ["%$search%","%$search%","%$search%"] : [];
$users  = DB::fetchAll("SELECT u.*, (SELECT COUNT(*) FROM tickets t WHERE t.user_id=u.id) as ticket_count FROM users u $where ORDER BY u.created_at DESC", $params);

$pageTitle = 'Users';
$activeNav = 'users';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Users <span style="color:var(--t3);font-weight:400;font-size:1rem">(<?= count($users) ?>)</span></div>
  <button class="btn btn-primary" onclick="document.getElementById('addUserModal').style.display='flex'">➕ Add User</button>
</div>

<div class="filter-bar">
  <form method="get" style="display:flex;gap:8px;align-items:center">
    <input type="text" name="search" placeholder="Search name, email, company…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    <?php if ($search): ?><a href="users.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
<div class="table-wrap">
<table>
  <thead>
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Company</th><th>Tickets</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div class="user-avatar" style="width:28px;height:28px;font-size:0.72rem"><?= strtoupper(substr($u['name'],0,1)) ?></div>
          <?= htmlspecialchars($u['name']) ?>
          <?php if ($u['id'] == $currentUser['id']): ?><span style="font-size:0.7rem;color:var(--brand-h);margin-left:4px">(you)</span><?php endif; ?>
        </div>
      </td>
      <td style="font-size:0.85rem"><?= htmlspecialchars($u['email']) ?></td>
      <td>
        <span class="badge <?= $u['role']==='admin'?'badge-critical':($u['role']==='agent'?'badge-medium':'badge-low') ?>">
          <?= ucfirst($u['role']) ?>
        </span>
      </td>
      <td style="font-size:0.85rem;color:var(--t3)"><?= htmlspecialchars($u['company'] ?? '—') ?></td>
      <td style="text-align:center"><?= $u['ticket_count'] ?></td>
      <td><?= $u['is_active'] ? '<span class="status-badge status-resolved">Active</span>' : '<span class="status-badge status-closed">Inactive</span>' ?></td>
      <td class="text-muted text-sm"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
      <td>
        <div style="display:flex;gap:4px">
          <?php if ($u['id'] != $currentUser['id']): ?>
          <a href="users.php?toggle=<?= $u['id'] ?>&_csrf=<?= csrf() ?>" class="btn btn-outline btn-xs" title="Toggle active"><?= $u['is_active'] ? '🚫' : '✅' ?></a>
          <a href="users.php?delete=<?= $u['id'] ?>" class="btn btn-danger btn-xs"
             data-confirm="Delete user <?= htmlspecialchars($u['name']) ?>? This cannot be undone.">🗑️</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?><tr><td colspan="8" style="text-align:center;padding:28px;color:var(--t3)">No users found</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>

<!-- Add user modal -->
<div id="addUserModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:500;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:500px;max-height:90vh;overflow-y:auto;position:relative">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <div class="card-title">Add New User</div>
      <button onclick="document.getElementById('addUserModal').style.display='none'" style="background:none;border:none;color:var(--t3);font-size:20px;cursor:pointer">✕</button>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="post">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="add">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name <span>*</span></label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Role <span>*</span></label>
          <select name="role" class="form-control">
            <option value="client" <?= ($_POST['role']??'client')==='client'?'selected':'' ?>>Client</option>
            <option value="agent"  <?= ($_POST['role']??'')==='agent' ?'selected':'' ?>>Agent</option>
            <option value="admin"  <?= ($_POST['role']??'')==='admin' ?'selected':'' ?>>Admin</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Email <span>*</span></label>
        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Company</label>
          <input type="text" name="company" class="form-control" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Password <span>*</span></label>
        <input type="password" name="password" class="form-control" required placeholder="Min 8 characters">
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Create User</button>
        <button type="button" onclick="document.getElementById('addUserModal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
<?php if (!empty($errors)): ?>
document.getElementById('addUserModal').style.display = 'flex';
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
