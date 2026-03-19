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
$currentUser = requireLogin('index.php');
if (in_array($currentUser['role'], ['admin','agent'])) { header('Location: ' . BASE_URL . '/admin/new-ticket.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $errors[] = 'Invalid request.'; }
    else {
        $subject  = trim($_POST['subject']      ?? '');
        $desc     = trim($_POST['description']  ?? '');
        $priority = $_POST['priority']           ?? 'medium';
        $category = trim($_POST['category']     ?? '');
        $deptId   = (int)($_POST['department_id'] ?? 0) ?: null;

        if (!$subject) $errors[] = 'Subject is required.';
        if (!$desc)    $errors[] = 'Please describe your issue.';

        if (empty($errors)) {
            $t = createTicket([
                'subject'       => $subject,
                'description'   => $desc,
                'priority'      => $priority,
                'category'      => $category,
                'department_id' => $deptId,
            ], $currentUser['id']);

            if (!empty($_FILES['attachment']['name'])) {
                uploadAttachment($_FILES['attachment'], $t['id'], $currentUser['id']);
            }

            flash('success', 'Ticket ' . $t['number'] . ' submitted. Our team will be in touch shortly.');
            header('Location: ' . BASE_URL . '/client/ticket.php?id=' . $t['id']);
            exit;
        }
    }
}

$departments = DB::fetchAll("SELECT * FROM departments WHERE is_active=1");
$categories  = DB::fetchAll("SELECT * FROM categories WHERE is_active=1");

$pageTitle = 'Open a Ticket';
$activeNav = 'new-ticket';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Open a Support Ticket</div>
    <div class="page-subtitle">Describe your issue and our team will get back to you as soon as possible</div>
  </div>
  <a href="<?= BASE_URL ?>/client/tickets.php" class="btn btn-outline">← My Tickets</a>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div style="max-width:760px">
<div class="card">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">

    <div class="form-group">
      <label class="form-label">Subject <span>*</span></label>
      <input type="text" name="subject" class="form-control" required maxlength="200"
             placeholder="e.g. Cannot connect to VPN, Printer not working, Password reset needed"
             value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label class="form-label">Description <span>*</span></label>
      <textarea name="description" class="form-control" rows="7" required
                placeholder="Please describe the issue in detail:&#10;- What happened?&#10;- When did it start?&#10;- Steps to reproduce&#10;- Any error messages?"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-row three">
      <div class="form-group">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-control">
          <option value="low"      <?= ($_POST['priority']??'medium')==='low'      ?'selected':''?>>Low — Not urgent</option>
          <option value="medium"   <?= ($_POST['priority']??'medium')==='medium'   ?'selected':''?>>Medium — Normal</option>
          <option value="high"     <?= ($_POST['priority']??'medium')==='high'     ?'selected':''?>>High — Impacts work</option>
          <option value="critical" <?= ($_POST['priority']??'medium')==='critical' ?'selected':''?>>Critical — System down</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category" class="form-control">
          <option value="">— Select —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat['name']) ?>" <?= ($_POST['category']??'') === $cat['name'] ? 'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Department</label>
        <select name="department_id" class="form-control">
          <option value="">— Not sure —</option>
          <?php foreach ($departments as $d): ?>
          <option value="<?= $d['id'] ?>" <?= ($_POST['department_id']??'') == $d['id'] ? 'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Attachment <span style="color:var(--t3);font-weight:400">(optional)</span></label>
      <input type="file" name="attachment" class="form-control" style="padding:5px">
      <div class="form-hint">Max <?= UPLOAD_MAX_MB ?>MB — images, PDF, Word, Excel, ZIP</div>
    </div>

    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="submit" class="btn btn-primary">Submit Ticket</button>
      <a href="<?= BASE_URL ?>/client/tickets.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<div class="card" style="margin-top:14px;padding:16px;display:flex;gap:14px;align-items:flex-start">
  <div style="font-size:1.4rem">⏱️</div>
  <div>
    <div style="font-weight:600;font-size:0.9rem;margin-bottom:4px">Response Times</div>
    <div style="font-size:0.83rem;color:var(--t3);line-height:1.8">
      🔴 Critical — Within 1 hour &nbsp;|&nbsp;
      🟠 High — Within 4 hours &nbsp;|&nbsp;
      🟡 Medium — Within 1 business day &nbsp;|&nbsp;
      🟢 Low — Within 2–3 business days
    </div>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
