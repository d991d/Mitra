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

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/tickets.php'); exit; }

$ticket = DB::fetch(
    "SELECT t.*, u.name as client_name, u.email as client_email, u.company as client_company,
            a.name as agent_name, d.name as dept_name
     FROM tickets t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN users a ON t.agent_id = a.id
     LEFT JOIN departments d ON t.department_id = d.id
     WHERE t.id = ?", [$id]
);
if (!$ticket) { flash('error', 'Ticket not found.'); header('Location: ' . BASE_URL . '/admin/tickets.php'); exit; }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error', 'Invalid request.'); header("Location: ?id=$id"); exit; }

    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $msg      = trim($_POST['message'] ?? '');
        $internal = isset($_POST['internal']) ? 1 : 0;
        $newStatus = $_POST['status'] ?? null;
        if ($msg) {
            $rid = DB::insert(
                "INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal) VALUES (?,?,?,?)",
                [$id, $currentUser['id'], $msg, $internal]
            );
            if (!empty($_FILES['attachment']['name'])) {
                uploadAttachment($_FILES['attachment'], $id, $currentUser['id'], $rid);
            }
            if ($newStatus && in_array($newStatus, ['open','pending','resolved','closed'])) {
                $closed = $newStatus === 'closed' ? ', closed_at=NOW()' : '';
                DB::query("UPDATE tickets SET status=?, updated_at=NOW()$closed WHERE id=?", [$newStatus, $id]);
            } else {
                DB::query("UPDATE tickets SET updated_at=NOW() WHERE id=?", [$id]);
            }
            logActivity($currentUser['id'], 'replied', 'ticket', $id);
            flash('success', 'Reply added.');
        }
    } elseif ($action === 'assign') {
        $agentId = (int)($_POST['agent_id'] ?? 0) ?: null;
        DB::query("UPDATE tickets SET agent_id=?, updated_at=NOW() WHERE id=?", [$agentId, $id]);
        logActivity($currentUser['id'], 'assigned', 'ticket', $id);
        flash('success', 'Ticket assigned.');
    } elseif ($action === 'update') {
        $status   = $_POST['status'] ?? $ticket['status'];
        $priority = $_POST['priority'] ?? $ticket['priority'];
        $dept     = (int)($_POST['department_id'] ?? 0) ?: null;
        $due      = $_POST['due_date'] ?? null;
        $closed   = $status === 'closed' ? ', closed_at=NOW()' : '';
        DB::query("UPDATE tickets SET status=?,priority=?,department_id=?,due_date=?,updated_at=NOW()$closed WHERE id=?",
                  [$status, $priority, $dept, $due ?: null, $id]);
        logActivity($currentUser['id'], 'updated', 'ticket', $id, "Status: $status, Priority: $priority");
        flash('success', 'Ticket updated.');
    }

    header("Location: ?id=$id");
    exit;
}

// Reload
$ticket = DB::fetch(
    "SELECT t.*, u.name as client_name, u.email as client_email, u.company as client_company,
            a.name as agent_name, d.name as dept_name
     FROM tickets t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN users a ON t.agent_id = a.id
     LEFT JOIN departments d ON t.department_id = d.id
     WHERE t.id = ?", [$id]
);

$replies = DB::fetchAll(
    "SELECT r.*, u.name, u.role FROM ticket_replies r JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC",
    [$id]
);

$attachments = DB::fetchAll("SELECT * FROM ticket_attachments WHERE ticket_id = ?", [$id]);
$agents      = DB::fetchAll("SELECT id, name FROM users WHERE role IN ('admin','agent') AND is_active=1");
$departments = DB::fetchAll("SELECT * FROM departments WHERE is_active=1");

$pageTitle = htmlspecialchars($ticket['ticket_number']) . ' — ' . htmlspecialchars(substr($ticket['subject'], 0, 40));
$activeNav = 'tickets';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:12px">
    <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn btn-outline btn-sm">← Back</a>
    <span class="ticket-num" style="font-size:0.9rem"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
    <?= getPriorityBadge($ticket['priority']) ?>
    <?= getStatusBadge($ticket['status']) ?>
  </div>
</div>

<div class="ticket-detail-layout">

<!-- Main column -->
<div>
  <div class="card" style="margin-bottom:16px">
    <h2 style="font-size:1.1rem;font-weight:700;color:var(--t1);margin-bottom:8px"><?= htmlspecialchars($ticket['subject']) ?></h2>
    <div style="font-size:0.82rem;color:var(--t3);margin-bottom:14px">
      Opened by <strong style="color:var(--t2)"><?= htmlspecialchars($ticket['client_name']) ?></strong>
      <?php if ($ticket['client_company']): ?> · <?= htmlspecialchars($ticket['client_company']) ?><?php endif; ?>
      · <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?>
      <?php if ($ticket['category']): ?> · Category: <em><?= htmlspecialchars($ticket['category']) ?></em><?php endif; ?>
    </div>
    <div style="font-size:0.92rem;color:var(--t2);line-height:1.8;white-space:pre-wrap;background:var(--s2);padding:14px;border-radius:var(--r-md);border:1px solid var(--b1)"><?= htmlspecialchars($ticket['description']) ?></div>

    <?php if (!empty($attachments)): ?>
    <div style="margin-top:14px">
      <div style="font-size:0.78rem;font-weight:600;color:var(--t3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px">Attachments</div>
      <div class="attachments-list">
        <?php foreach ($attachments as $att): ?>
        <a href="<?= BASE_URL ?>/uploads/<?= urlencode($att['filename']) ?>" target="_blank" class="attachment-chip">
          📎 <?= htmlspecialchars($att['original_name']) ?>
          <span style="color:var(--t3)">(<?= formatFileSize($att['file_size']) ?>)</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Thread -->
  <div class="ticket-thread">
    <?php foreach ($replies as $r): ?>
    <?php $isAgent = in_array($r['role'], ['admin','agent']); ?>
    <div class="reply-item <?= $r['is_internal'] ? 'internal' : '' ?>">
      <div class="reply-header">
        <div class="reply-avatar <?= !$isAgent ? 'client' : '' ?>"><?= strtoupper(substr($r['name'], 0, 1)) ?></div>
        <div>
          <div class="reply-name"><?= htmlspecialchars($r['name']) ?></div>
          <div style="font-size:0.72rem;color:var(--t3)"><?= $isAgent ? ucfirst($r['role']) : 'Client' ?></div>
        </div>
        <?php if ($r['is_internal']): ?>
        <span class="internal-note">🔒 Internal Note</span>
        <?php endif; ?>
        <div class="reply-meta"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></div>
      </div>
      <div class="reply-body"><p><?= nl2br(htmlspecialchars($r['message'])) ?></p></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Reply form -->
  <?php if ($ticket['status'] !== 'closed'): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-title" style="margin-bottom:14px">Add Reply</div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="reply">

      <div class="form-group">
        <textarea name="message" class="form-control" rows="5" placeholder="Type your reply…" required></textarea>
      </div>

      <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="flex:1;min-width:160px;margin:0">
          <label class="form-label">Change Status</label>
          <select name="status" class="form-control">
            <option value="">— Keep current —</option>
            <?php foreach (['open','pending','resolved','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group" style="flex:1;min-width:160px;margin:0">
          <label class="form-label">Attachment</label>
          <input type="file" name="attachment" class="form-control" style="padding:5px">
        </div>

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:0;padding-bottom:0">
          <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;color:var(--t2);cursor:pointer">
            <input type="checkbox" name="internal"> Internal Note
          </label>
        </div>
      </div>

      <div style="margin-top:14px;display:flex;gap:10px">
        <button type="submit" class="btn btn-primary">Send Reply</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="alert alert-info" style="margin-top:16px">🔒 This ticket is closed. Reopen it from the sidebar to add replies.</div>
  <?php endif; ?>
</div>

<!-- Sidebar -->
<div style="display:flex;flex-direction:column;gap:12px">

  <!-- Update ticket -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px">Ticket Properties</div>
    <form method="post">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="update">

      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
          <?php foreach (['open','pending','resolved','closed'] as $s): ?>
          <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Priority</label>
        <select name="priority" class="form-control">
          <?php foreach (['low','medium','high','critical'] as $p): ?>
          <option value="<?= $p ?>" <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Department</label>
        <select name="department_id" class="form-control">
          <option value="">— None —</option>
          <?php foreach ($departments as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $ticket['department_id'] == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Due Date</label>
        <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($ticket['due_date'] ?? '') ?>">
      </div>

      <button type="submit" class="btn btn-primary btn-sm w-full" style="justify-content:center">Update</button>
    </form>
  </div>

  <!-- Assign -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px">Assignment</div>
    <div style="font-size:0.82rem;color:var(--t3);margin-bottom:10px">
      Currently: <strong style="color:<?= $ticket['agent_name'] ? 'var(--t1)' : 'var(--orange)' ?>"><?= $ticket['agent_name'] ?? 'Unassigned' ?></strong>
    </div>
    <form method="post">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="assign">
      <div style="display:flex;gap:6px">
        <select name="agent_id" class="form-control" style="flex:1">
          <option value="">Unassigned</option>
          <?php foreach ($agents as $ag): ?>
          <option value="<?= $ag['id'] ?>" <?= $ticket['agent_id'] == $ag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ag['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline btn-sm">Assign</button>
      </div>
    </form>
  </div>

  <!-- Client info -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px">Client Info</div>
    <div class="detail-meta">
      <div class="meta-item">
        <div class="meta-label">Name</div>
        <div class="meta-value"><?= htmlspecialchars($ticket['client_name']) ?></div>
      </div>
      <div class="meta-item">
        <div class="meta-label">Email</div>
        <div class="meta-value"><a href="mailto:<?= htmlspecialchars($ticket['client_email']) ?>"><?= htmlspecialchars($ticket['client_email']) ?></a></div>
      </div>
      <?php if ($ticket['client_company']): ?>
      <div class="meta-item">
        <div class="meta-label">Company</div>
        <div class="meta-value"><?= htmlspecialchars($ticket['client_company']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Timeline -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px">Timeline</div>
    <div style="font-size:0.82rem;color:var(--t3)">
      <div style="margin-bottom:6px">📬 Opened <?= timeAgo($ticket['created_at']) ?></div>
      <div style="margin-bottom:6px">🔄 Updated <?= timeAgo($ticket['updated_at']) ?></div>
      <?php if ($ticket['due_date']): ?>
      <div style="margin-bottom:6px;color:<?= strtotime($ticket['due_date']) < time() ? 'var(--err)' : 'var(--t3)' ?>">
        ⏰ Due <?= date('M j, Y', strtotime($ticket['due_date'])) ?>
      </div>
      <?php endif; ?>
      <?php if ($ticket['closed_at']): ?>
      <div>✅ Closed <?= timeAgo($ticket['closed_at']) ?></div>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
