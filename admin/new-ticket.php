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
        $subject  = trim($_POST['subject']  ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $category = trim($_POST['category'] ?? '');
        $deptId   = (int)($_POST['department_id'] ?? 0) ?: null;
        $agentId  = (int)($_POST['agent_id']  ?? 0) ?: null;
        $userId   = (int)($_POST['user_id']   ?? 0);
        $dueDate  = $_POST['due_date'] ?? null;

        if (!$subject) $errors[] = 'Subject is required.';
        if (!$desc)    $errors[] = 'Description is required.';
        if (!$userId)  $errors[] = 'Please select a client.';

        if (empty($errors)) {
            $t = createTicket([
                'subject'       => $subject,
                'description'   => $desc,
                'priority'      => $priority,
                'category'      => $category,
                'department_id' => $deptId,
                'due_date'      => $dueDate ?: null,
            ], $userId);

            if ($agentId) {
                DB::query("UPDATE tickets SET agent_id=? WHERE id=?", [$agentId, $t['id']]);
            }

            if (!empty($_FILES['attachment']['name'])) {
                uploadAttachment($_FILES['attachment'], $t['id'], $currentUser['id']);
            }

            flash('success', 'Ticket ' . $t['number'] . ' created successfully.');
            header('Location: ' . BASE_URL . '/admin/ticket.php?id=' . $t['id']);
            exit;
        }
    }
}

$clients     = DB::fetchAll("SELECT id, name, email, company FROM users WHERE role='client' AND is_active=1 ORDER BY name");
$agents      = DB::fetchAll("SELECT id, name FROM users WHERE role IN ('admin','agent') AND is_active=1 ORDER BY name");
$departments = DB::fetchAll("SELECT * FROM departments WHERE is_active=1");
$categories  = DB::fetchAll("SELECT * FROM categories WHERE is_active=1");

$pageTitle = 'New Ticket';
$activeNav = 'new-ticket';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Create New Ticket</div>
    <div class="page-subtitle">Submit a support ticket on behalf of a client</div>
  </div>
  <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn btn-outline">← Back</a>
</div>

<?php foreach ($errors as $e): ?>
<div class="alert alert-error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div style="max-width:780px">
<div class="card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">

    <div class="form-group">
      <label class="form-label">Client <span>*</span></label>
      <select name="user_id" class="form-control" required>
        <option value="">Select client…</option>
        <?php foreach ($clients as $c): ?>
        <option value="<?= $c['id'] ?>" <?= ($_POST['user_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['name']) ?> — <?= htmlspecialchars($c['email']) ?>
          <?= $c['company'] ? '(' . htmlspecialchars($c['company']) . ')' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Subject <span>*</span></label>
      <input type="text" name="subject" class="form-control" required placeholder="Brief description of the issue"
             value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label class="form-label">Description <span>*</span></label>
      <textarea name="description" class="form-control" rows="6" required
                placeholder="Describe the issue in detail, including steps to reproduce, error messages, etc."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-row three">
      <div class="form-group">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-control">
          <?php foreach (['low','medium','high','critical'] as $p): ?>
          <option value="<?= $p ?>" <?= ($_POST['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category" class="form-control">
          <option value="">— Select —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat['name']) ?>" <?= ($_POST['category'] ?? '') === $cat['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Due Date</label>
        <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Department</label>
        <select name="department_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($departments as $d): ?>
          <option value="<?= $d['id'] ?>" <?= ($_POST['department_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Assign To</label>
        <select name="agent_id" class="form-control">
          <option value="">— Unassigned —</option>
          <?php foreach ($agents as $ag): ?>
          <option value="<?= $ag['id'] ?>" <?= ($_POST['agent_id'] ?? '') == $ag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ag['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Attachment</label>
      <input type="file" name="attachment" class="form-control" style="padding:5px">
      <div class="form-hint">Max <?= UPLOAD_MAX_MB ?>MB. Supported: images, PDF, Word, Excel, ZIP</div>
    </div>

    <div style="display:flex;gap:10px;margin-top:4px">
      <button type="submit" class="btn btn-primary">Create Ticket</button>
      <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
