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

// Redirect admins/agents
if (in_array($currentUser['role'], ['admin','agent'])) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$uid = $currentUser['id'];

$stats = [];
foreach (['open','pending','resolved','closed'] as $s) {
    $r = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE user_id=? AND status=?", [$uid, $s]);
    $stats[$s] = $r['c'];
}
$stats['total'] = array_sum($stats);

$recent = DB::fetchAll(
    "SELECT t.*, a.name as agent_name FROM tickets t
     LEFT JOIN users a ON t.agent_id = a.id
     WHERE t.user_id = ?
     ORDER BY t.updated_at DESC LIMIT 5",
    [$uid]
);

$pageTitle = 'My Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Welcome, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?> 👋</div>
    <div class="page-subtitle">Track and manage your support requests</div>
  </div>
  <a href="<?= BASE_URL ?>/client/new-ticket.php" class="btn btn-primary">➕ Open a Ticket</a>
</div>

<div class="stats-grid">
  <div class="stat-card total">
    <div class="stat-number"><?= $stats['total'] ?></div>
    <div class="stat-label">Total Tickets</div>
  </div>
  <div class="stat-card open">
    <div class="stat-number"><?= $stats['open'] ?></div>
    <div class="stat-label">Open</div>
  </div>
  <div class="stat-card pending">
    <div class="stat-number"><?= $stats['pending'] ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card resolved">
    <div class="stat-number"><?= $stats['resolved'] + $stats['closed'] ?></div>
    <div class="stat-label">Resolved</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Tickets</div>
    <a href="<?= BASE_URL ?>/client/tickets.php" class="btn btn-outline btn-sm">View All</a>
  </div>

  <?php if (empty($recent)): ?>
  <div class="empty-state">
    <div class="empty-icon">🎫</div>
    <div class="empty-title">No tickets yet</div>
    <div class="empty-sub">Having an IT issue? <a href="<?= BASE_URL ?>/client/new-ticket.php">Open your first ticket</a></div>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Subject</th><th>Priority</th><th>Status</th><th>Agent</th><th>Last Update</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $t): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/client/ticket.php?id=<?= $t['id'] ?>" class="ticket-num"><?= htmlspecialchars($t['ticket_number']) ?></a></td>
          <td><a href="<?= BASE_URL ?>/client/ticket.php?id=<?= $t['id'] ?>" class="ticket-link"><?= htmlspecialchars(substr($t['subject'],0,50)) ?><?= strlen($t['subject'])>50?'…':'' ?></a></td>
          <td><?= getPriorityBadge($t['priority']) ?></td>
          <td><?= getStatusBadge($t['status']) ?></td>
          <td style="font-size:0.85rem;color:var(--t3)"><?= $t['agent_name'] ? htmlspecialchars($t['agent_name']) : 'Awaiting assignment' ?></td>
          <td class="text-muted text-sm"><?= timeAgo($t['updated_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Info box -->
<div class="card" style="margin-top:16px;background:var(--brand-dim);border-color:var(--brand-h)">
  <div style="display:flex;gap:14px;align-items:flex-start">
    <div style="font-size:1.5rem">ℹ️</div>
    <div>
      <div style="font-weight:600;color:var(--t1);margin-bottom:4px">Need help faster?</div>
      <div style="font-size:0.88rem;color:var(--t2)">
        For critical issues, mark your ticket as <strong>Critical</strong> priority when submitting.
        Our team monitors critical tickets around the clock.
        You can also call us at <strong><?= htmlspecialchars(DB::setting('company_phone') ?? '') ?></strong>.
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
