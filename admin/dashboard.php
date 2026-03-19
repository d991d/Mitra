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

$stats = getTicketStats();

// POS quick stats (if module installed)
$posRevToday = 0;
$posInvPending = 0;
try {
    $r = DB::fetch("SELECT COALESCE(SUM(total),0) s FROM pos_sales WHERE DATE(created_at)=CURDATE()");
    $posRevToday = $r['s'];
    $r2 = DB::fetch("SELECT COUNT(*) c FROM pos_invoices WHERE status IN ('sent','overdue','partial')");
    $posInvPending = $r2['c'];
} catch (Exception $e) {}

// Recent tickets
$recent = DB::fetchAll(
    "SELECT t.*, u.name as client_name, a.name as agent_name
     FROM tickets t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN users a ON t.agent_id = a.id
     ORDER BY t.updated_at DESC LIMIT 8"
);

// Tickets by priority
$critical = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE priority='critical' AND status NOT IN ('closed','resolved')")['c'];
$high     = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE priority='high'    AND status NOT IN ('closed','resolved')")['c'];

// Today's activity
$todayNew = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE DATE(created_at) = CURDATE()")['c'];

// Unassigned
$unassigned = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE agent_id IS NULL AND status='open'")['c'];

// Top agents
$agents = DB::fetchAll(
    "SELECT u.name, COUNT(t.id) as cnt
     FROM users u
     LEFT JOIN tickets t ON t.agent_id = u.id AND t.status NOT IN ('closed')
     WHERE u.role IN ('admin','agent')
     GROUP BY u.id ORDER BY cnt DESC LIMIT 5"
);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Welcome back, <?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?> 👋</div>
    <div class="page-subtitle">Here's what's happening with your support queue today.</div>
  </div>
  <a href="<?= BASE_URL ?>/admin/new-ticket.php" class="btn btn-primary">➕ New Ticket</a>
</div>

<!-- Stats -->
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
    <div class="stat-number"><?= $stats['resolved'] ?></div>
    <div class="stat-label">Resolved</div>
  </div>
  <div class="stat-card closed">
    <div class="stat-number"><?= $stats['closed'] ?></div>
    <div class="stat-label">Closed</div>
  </div>
  <div class="stat-card critical">
    <div class="stat-number"><?= $critical ?></div>
    <div class="stat-label">Critical</div>
  </div>
  <?php if ($posRevToday > 0 || $posInvPending >= 0): ?>
  <div class="stat-card resolved" style="border-top-color:var(--ok)">
    <div class="stat-number" style="color:var(--ok);font-size:1.4rem">$<?= number_format($posRevToday,0) ?></div>
    <div class="stat-label">POS Revenue Today</div>
  </div>
  <div class="stat-card pending" style="border-top-color:var(--orange)">
    <div class="stat-number" style="color:var(--orange)"><?= $posInvPending ?></div>
    <div class="stat-label">Invoices Pending</div>
  </div>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start">

<!-- Recent tickets -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Tickets</div>
    <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ticket</th>
          <th>Subject</th>
          <th>Client</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--t3);padding:24px">No tickets yet</td></tr>
        <?php else: ?>
        <?php foreach ($recent as $t): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/admin/ticket.php?id=<?= $t['id'] ?>" class="ticket-num"><?= htmlspecialchars($t['ticket_number']) ?></a></td>
          <td><a href="<?= BASE_URL ?>/admin/ticket.php?id=<?= $t['id'] ?>" class="ticket-link"><?= htmlspecialchars(substr($t['subject'], 0, 45)) ?><?= strlen($t['subject']) > 45 ? '…' : '' ?></a></td>
          <td><?= htmlspecialchars($t['client_name']) ?></td>
          <td><?= getPriorityBadge($t['priority']) ?></td>
          <td><?= getStatusBadge($t['status']) ?></td>
          <td class="text-muted text-sm"><?= timeAgo($t['updated_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Side panels -->
<div style="display:flex;flex-direction:column;gap:14px">

  <div class="card">
    <div class="card-title" style="margin-bottom:14px">📊 Today</div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.88rem">
        <span style="color:var(--t2)">New Tickets</span>
        <strong style="color:var(--brand-h)"><?= $todayNew ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.88rem">
        <span style="color:var(--t2)">Unassigned</span>
        <strong style="color:<?= $unassigned > 0 ? 'var(--orange)' : 'var(--ok)' ?>"><?= $unassigned ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.88rem">
        <span style="color:var(--t2)">High Priority Open</span>
        <strong style="color:<?= $high > 0 ? 'var(--orange)' : 'var(--t3)' ?>"><?= $high ?></strong>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.88rem">
        <span style="color:var(--t2)">Critical Open</span>
        <strong style="color:<?= $critical > 0 ? 'var(--err)' : 'var(--t3)' ?>"><?= $critical ?></strong>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:14px">👤 Agent Load</div>
    <?php foreach ($agents as $ag): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;margin-bottom:8px">
      <span style="color:var(--t2)"><?= htmlspecialchars($ag['name']) ?></span>
      <span class="badge badge-medium" style="min-width:28px;justify-content:center"><?= $ag['cnt'] ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($agents)): ?>
    <div style="color:var(--t3);font-size:0.85rem">No agents yet</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:14px">⚡ Quick Links</div>
    <div style="display:flex;flex-direction:column;gap:6px">
      <a href="<?= BASE_URL ?>/admin/tickets.php?status=open" class="btn btn-outline btn-sm" style="justify-content:flex-start">🔵 Open Tickets</a>
      <a href="<?= BASE_URL ?>/admin/tickets.php?priority=critical" class="btn btn-outline btn-sm" style="justify-content:flex-start">🔴 Critical Tickets</a>
      <a href="<?= BASE_URL ?>/admin/tickets.php?agent=unassigned" class="btn btn-outline btn-sm" style="justify-content:flex-start">❓ Unassigned</a>
      <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline btn-sm" style="justify-content:flex-start">👥 Manage Users</a>
      <div style="border-top:1px solid var(--b1);margin:6px 0"></div>
      <a href="<?= BASE_URL ?>/pos/sale.php" class="btn btn-primary btn-sm" style="justify-content:flex-start">🛒 Quick POS Sale</a>
      <a href="<?= BASE_URL ?>/pos/invoice-new.php" class="btn btn-outline btn-sm" style="justify-content:flex-start">📄 New Invoice</a>
      <a href="<?= BASE_URL ?>/pos/invoices.php?status=overdue" class="btn btn-outline btn-sm" style="justify-content:flex-start">🔴 Overdue Invoices</a>
    </div>
  </div>

</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
