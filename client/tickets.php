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
if (in_array($currentUser['role'], ['admin','agent'])) { header('Location: ' . BASE_URL . '/admin/tickets.php'); exit; }

$uid     = $currentUser['id'];
$perPage = (int)(DB::setting('tickets_per_page') ?: 15);
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = ['t.user_id = ?'];
$params = [$uid];

if (!empty($_GET['status'])) { $where[] = 't.status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['priority'])){ $where[] = 't.priority = ?'; $params[] = $_GET['priority']; }
if (!empty($_GET['search'])) {
    $where[] = '(t.subject LIKE ? OR t.ticket_number LIKE ?)';
    $s = '%'.$_GET['search'].'%';
    $params[] = $s; $params[] = $s;
}

$whereStr = implode(' AND ', $where);
$total    = DB::fetch("SELECT COUNT(*) as c FROM tickets t WHERE $whereStr", $params)['c'];
$tickets  = DB::fetchAll(
    "SELECT t.*, a.name as agent_name FROM tickets t
     LEFT JOIN users a ON t.agent_id = a.id
     WHERE $whereStr
     ORDER BY t.updated_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$pageTitle = 'My Tickets';
$activeNav = 'tickets';
$filterUrl = '?' . http_build_query(array_filter(['status'=>$_GET['status']??'','priority'=>$_GET['priority']??'','search'=>$_GET['search']??'']));
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">My Tickets <span style="color:var(--t3);font-weight:400;font-size:1rem">(<?= $total ?>)</span></div>
  <a href="<?= BASE_URL ?>/client/new-ticket.php" class="btn btn-primary">➕ Open Ticket</a>
</div>

<div class="filter-bar">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;width:100%">
    <input type="text" name="search" placeholder="Search tickets…" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
    <select name="status">
      <option value="">All Status</option>
      <?php foreach (['open','pending','resolved','closed'] as $s): ?>
      <option value="<?= $s ?>" <?= ($_GET['status']??'') === $s ? 'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="priority">
      <option value="">All Priority</option>
      <?php foreach (['critical','high','medium','low'] as $p): ?>
      <option value="<?= $p ?>" <?= ($_GET['priority']??'') === $p ? 'selected':'' ?>><?= ucfirst($p) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= BASE_URL ?>/client/tickets.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Agent</th><th>Opened</th><th>Updated</th></tr>
      </thead>
      <tbody>
        <?php if (empty($tickets)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <div class="empty-icon">🎫</div>
            <div class="empty-title">No tickets found</div>
            <div class="empty-sub"><a href="<?= BASE_URL ?>/client/new-ticket.php">Open your first ticket</a></div>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/client/ticket.php?id=<?= $t['id'] ?>" class="ticket-num"><?= htmlspecialchars($t['ticket_number']) ?></a></td>
          <td><a href="<?= BASE_URL ?>/client/ticket.php?id=<?= $t['id'] ?>" class="ticket-link"><?= htmlspecialchars(substr($t['subject'],0,48)) ?><?= strlen($t['subject'])>48?'…':'' ?></a></td>
          <td style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($t['category'] ?? '—') ?></td>
          <td><?= getPriorityBadge($t['priority']) ?></td>
          <td><?= getStatusBadge($t['status']) ?></td>
          <td style="font-size:0.85rem;color:var(--t3)"><?= $t['agent_name'] ? htmlspecialchars($t['agent_name']) : '—' ?></td>
          <td class="text-muted text-sm"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
          <td class="text-muted text-sm"><?= timeAgo($t['updated_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= paginate($total, $perPage, $page, BASE_URL . '/client/tickets.php' . $filterUrl) ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
