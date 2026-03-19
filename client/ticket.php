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
if (in_array($currentUser['role'], ['admin','agent'])) { header('Location: ' . BASE_URL . '/admin/ticket.php?id=' . (int)($_GET['id']??0)); exit; }

$id = (int)($_GET['id'] ?? 0);
$ticket = DB::fetch(
    "SELECT t.*, a.name as agent_name, a.email as agent_email FROM tickets t
     LEFT JOIN users a ON t.agent_id = a.id
     WHERE t.id = ? AND t.user_id = ?",
    [$id, $currentUser['id']]
);
if (!$ticket) { flash('error', 'Ticket not found.'); header('Location: ' . BASE_URL . '/client/tickets.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error', 'Invalid request.'); header("Location: ?id=$id"); exit; }
    $msg = trim($_POST['message'] ?? '');
    if ($msg && $ticket['status'] !== 'closed') {
        $rid = DB::insert(
            "INSERT INTO ticket_replies (ticket_id, user_id, message, is_internal) VALUES (?,?,?,0)",
            [$id, $currentUser['id'], $msg]
        );
        if (!empty($_FILES['attachment']['name'])) {
            uploadAttachment($_FILES['attachment'], $id, $currentUser['id'], $rid);
        }
        // Re-open if resolved/pending
        if (in_array($ticket['status'], ['resolved','pending'])) {
            DB::query("UPDATE tickets SET status='open', updated_at=NOW() WHERE id=?", [$id]);
        } else {
            DB::query("UPDATE tickets SET updated_at=NOW() WHERE id=?", [$id]);
        }
        logActivity($currentUser['id'], 'replied', 'ticket', $id);
        flash('success', 'Reply sent.');
    }
    header("Location: ?id=$id"); exit;
}

$replies     = DB::fetchAll(
    "SELECT r.*, u.name, u.role FROM ticket_replies r
     JOIN users u ON r.user_id = u.id
     WHERE r.ticket_id = ? AND (r.is_internal = 0 OR u.id = ?)
     ORDER BY r.created_at ASC",
    [$id, $currentUser['id']]
);
$attachments = DB::fetchAll("SELECT * FROM ticket_attachments WHERE ticket_id=?", [$id]);

$pageTitle = htmlspecialchars($ticket['ticket_number']);
$activeNav = 'tickets';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="margin-bottom:16px">
  <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/client/tickets.php" class="btn btn-outline btn-sm">← Back</a>
    <span class="ticket-num" style="font-size:0.9rem"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
    <?= getPriorityBadge($ticket['priority']) ?>
    <?= getStatusBadge($ticket['status']) ?>
  </div>
</div>

<div class="ticket-detail-layout">
<div>
  <!-- Original ticket -->
  <div class="card" style="margin-bottom:16px">
    <h2 style="font-size:1.05rem;font-weight:700;color:var(--t1);margin-bottom:8px"><?= htmlspecialchars($ticket['subject']) ?></h2>
    <div style="font-size:0.8rem;color:var(--t3);margin-bottom:14px">Submitted <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></div>
    <div style="font-size:0.92rem;color:var(--t2);line-height:1.8;white-space:pre-wrap;background:var(--s2);padding:14px;border-radius:var(--r-md);border:1px solid var(--b1)"><?= htmlspecialchars($ticket['description']) ?></div>

    <?php if (!empty($attachments)): ?>
    <div style="margin-top:14px">
      <div style="font-size:0.78rem;color:var(--t3);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em">Attachments</div>
      <div class="attachments-list">
        <?php foreach ($attachments as $att): ?>
        <a href="<?= BASE_URL ?>/uploads/<?= urlencode($att['filename']) ?>" target="_blank" class="attachment-chip">
          📎 <?= htmlspecialchars($att['original_name']) ?> <span style="color:var(--t3)">(<?= formatFileSize($att['file_size']) ?>)</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Thread -->
  <?php if (!empty($replies)): ?>
  <div class="ticket-thread">
    <?php foreach ($replies as $r): ?>
    <?php $isSupport = in_array($r['role'], ['admin','agent']); ?>
    <div class="reply-item">
      <div class="reply-header">
        <div class="reply-avatar <?= !$isSupport ? 'client' : '' ?>"><?= strtoupper(substr($r['name'],0,1)) ?></div>
        <div>
          <div class="reply-name"><?= htmlspecialchars($r['name']) ?></div>
          <div style="font-size:0.72rem;color:var(--t3)"><?= $isSupport ? 'Mitra Support' : 'You' ?></div>
        </div>
        <div class="reply-meta"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></div>
      </div>
      <div class="reply-body"><p><?= nl2br(htmlspecialchars($r['message'])) ?></p></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Reply -->
  <?php if ($ticket['status'] !== 'closed'): ?>
  <div class="card" style="margin-top:16px">
    <div class="card-title" style="margin-bottom:14px">Add a Reply</div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <div class="form-group">
        <textarea name="message" class="form-control" rows="5" required placeholder="Provide additional information or follow up on the issue…"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Attachment <span style="color:var(--t3);font-weight:400">(optional)</span></label>
        <input type="file" name="attachment" class="form-control" style="padding:5px">
      </div>
      <button type="submit" class="btn btn-primary">Send Reply</button>
    </form>
  </div>
  <?php else: ?>
  <div class="alert alert-info" style="margin-top:16px">🔒 This ticket is closed. To reopen it, please <a href="<?= BASE_URL ?>/client/new-ticket.php">submit a new ticket</a> referencing this one.</div>
  <?php endif; ?>
</div>

<!-- Meta sidebar -->
<div style="display:flex;flex-direction:column;gap:12px">
  <div class="card">
    <div class="card-title" style="margin-bottom:14px">Ticket Details</div>
    <div class="detail-meta">
      <div class="meta-item">
        <div class="meta-label">Status</div>
        <div class="meta-value"><?= getStatusBadge($ticket['status']) ?></div>
      </div>
      <div class="meta-item">
        <div class="meta-label">Priority</div>
        <div class="meta-value"><?= getPriorityBadge($ticket['priority']) ?></div>
      </div>
      <?php if ($ticket['category']): ?>
      <div class="meta-item">
        <div class="meta-label">Category</div>
        <div class="meta-value"><?= htmlspecialchars($ticket['category']) ?></div>
      </div>
      <?php endif; ?>
      <div class="meta-item">
        <div class="meta-label">Assigned To</div>
        <div class="meta-value"><?= $ticket['agent_name'] ? htmlspecialchars($ticket['agent_name']) : '<span style="color:var(--t3)">Pending assignment</span>' ?></div>
      </div>
      <div class="meta-item">
        <div class="meta-label">Opened</div>
        <div class="meta-value" style="font-size:0.82rem"><?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></div>
      </div>
      <div class="meta-item">
        <div class="meta-label">Last Update</div>
        <div class="meta-value" style="font-size:0.82rem"><?= timeAgo($ticket['updated_at']) ?></div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:10px">Need Urgent Help?</div>
    <div style="font-size:0.83rem;color:var(--t3);line-height:1.7">
      📞 <?= htmlspecialchars(DB::setting('company_phone') ?? '') ?><br>
      ✉️ <?= htmlspecialchars(DB::setting('company_email') ?? '') ?>
    </div>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
