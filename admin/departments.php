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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $name  = trim($_POST['name']  ?? '');
            $email = trim($_POST['email'] ?? '');
            if (!$name) { $errors[] = 'Name required.'; }
            else {
                DB::insert("INSERT INTO departments (name,email) VALUES (?,?)", [$name, $email]);
                flash('success', 'Department added.');
                header('Location: departments.php'); exit;
            }
        } elseif ($action === 'delete') {
            DB::query("UPDATE tickets SET department_id=NULL WHERE department_id=?", [(int)$_POST['id']]);
            DB::query("DELETE FROM departments WHERE id=?", [(int)$_POST['id']]);
            flash('success', 'Department deleted.');
            header('Location: departments.php'); exit;
        } elseif ($action === 'toggle') {
            DB::query("UPDATE departments SET is_active=1-is_active WHERE id=?", [(int)$_POST['id']]);
            header('Location: departments.php'); exit;
        }
    }
}

$depts = DB::fetchAll(
    "SELECT d.*, (SELECT COUNT(*) FROM tickets t WHERE t.department_id=d.id) as ticket_count FROM departments d ORDER BY d.name"
);

$pageTitle = 'Departments';
$activeNav = 'departments';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Departments</div>
  <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">➕ Add Department</button>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div class="card" style="padding:0;overflow:hidden">
<div class="table-wrap">
<table>
  <thead>
    <tr><th>Name</th><th>Email</th><th>Tickets</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php foreach ($depts as $d): ?>
    <tr>
      <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
      <td style="font-size:0.85rem;color:var(--t3)"><?= htmlspecialchars($d['email'] ?? '—') ?></td>
      <td><?= $d['ticket_count'] ?></td>
      <td><?= $d['is_active'] ? '<span class="status-badge status-resolved">Active</span>' : '<span class="status-badge status-closed">Inactive</span>' ?></td>
      <td>
        <div style="display:flex;gap:6px">
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf"   value="<?= csrf() ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id"     value="<?= $d['id'] ?>">
            <button type="submit" class="btn btn-outline btn-xs"><?= $d['is_active'] ? '🚫 Disable' : '✅ Enable' ?></button>
          </form>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf"   value="<?= csrf() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id"     value="<?= $d['id'] ?>">
            <button type="submit" class="btn btn-danger btn-xs" data-confirm="Delete this department?">🗑️ Delete</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<!-- Add modal -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:500;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:440px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div class="card-title">Add Department</div>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;color:var(--t3);font-size:20px;cursor:pointer">✕</button>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label">Department Name <span>*</span></label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">Contact Email</label>
        <input type="email" name="email" class="form-control">
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Add Department</button>
        <button type="button" onclick="document.getElementById('addModal').style.display='none'" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
