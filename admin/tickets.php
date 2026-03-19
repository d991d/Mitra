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

$perPage = (int)(DB::setting('tickets_per_page') ?: 15);
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if (!empty($_GET['status'])) {
    $where[] = 't.status = ?';
    $params[] = $_GET['status'];
}
if (!empty($_GET['priority'])) {
    $where[] = 't.priority = ?';
    $params[] = $_GET['priority'];
}
if (!empty($_GET['department'])) {
    $where[] = 't.department_id = ?';
    $params[] = $_GET['department'];
}
if (isset($_GET['agent'])) {
    if ($_GET['agent'] === 'unassigned') {
        $where[] = 't.agent_id IS NULL';
    } elseif ($_GET['agent'] !== '') {
        $where[] = 't.agent_id = ?';
        $params[] = $_GET['agent'];
    }
}
if (!empty($_GET['search'])) {
    $where[] = '(t.subject LIKE ? OR t.ticket_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $s = '%' . $_GET['search'] . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
}

$whereStr = implode(' AND ', $where);

$totalRow = DB::fetch(
    "SELECT COUNT(*) as c FROM tickets t JOIN users u ON t.user_id = u.id WHERE $whereStr",
    $params
);
$total = $totalRow['c'];

$tickets = DB::fetchAll(
    "SELECT t.*, u.name as client_name, a.name as agent_name, d.name as dept_name
     FROM tickets t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN users a ON t.agent_id = a.id
     LEFT JOIN departments d ON t.department_id = d.id
     WHERE $whereStr
     ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.updated_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$departments = DB::fetchAll("SELECT * FROM departments WHERE is_active=1");
$agents      = DB::fetchAll("SELECT id, name FROM users WHERE role IN ('admin','agent') AND is_active=1");

$pageTitle = 'All Tickets';
$activeNav = 'tickets';
$filterUrl = '?' . http_build_query(array_filter(['status'=>$_GET['status']??'','priority'=>$_GET['priority']??'','search'=>$_GET['search']??'','department'=>$_GET['department']??'','agent'=>$_GET['agent']??'']));

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">All Tickets <span style="color:var(--t3);font-size:1rem;font-weight:400">(<?= $total ?>)</span></div>
  </div>
  <a href="<?= BASE_URL ?>/admin/new-ticket.php" class="btn btn-primary">➕ New Ticket</a>
</div>

<!-- Filters -->
<div class="filter-bar">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;width:100%">
    <input type="text" name="search" placeholder="Search…" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="min-width:180px">

    <select name="status">
      <option value="">All Status</option>
      <?php foreach (['open','pending','resolved','closed'] as $s): ?>
      <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="priority">
      <option value="">All Priority</option>
      <?php foreach (['critical','high','medium','low'] as $p): ?>
      <option value="<?= $p ?>" <?= ($_GET['priority'] ?? '') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="department">
      <option value="">All Departments</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= $d['id'] ?>" <?= ($_GET['department'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <select name="agent">
      <option value="">All Agents</option>
      <option value="unassigned" <?= ($_GET['agent'] ?? '') === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
      <?php foreach ($agents as $ag): ?>
      <option value="<?= $ag['id'] ?>" <?= ($_GET['agent'] ?? '') == $ag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ag['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= BASE_URL ?>/admin/tickets.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Subject</th>
          <th>Client</th>
          <th>Department</th>
          <th>Agent</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Created</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tickets)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <div class="empty-icon">🎫</div>
            <div class="empty-title">No tickets found</div>
            <div class="empty-sub">Try adjusting your filters or <a href="<?= BASE_URL ?>/admin/new-ticket.php">create a new ticket</a></div>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($tickets as $t): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/admin/ticket.php?id=<?= $t['id'] ?>" class="ticket-num"><?= htmlspecialchars($t['ticket_number']) ?></a></td>
          <td style="max-width:260px">
            <a href="<?= BASE_URL ?>/admin/ticket.php?id=<?= $t['id'] ?>" class="ticket-link">
              <?= htmlspecialchars(substr($t['subject'], 0, 50)) ?><?= strlen($t['subject']) > 50 ? '…' : '' ?>
            </a>
            <?php if ($t['category']): ?><div style="font-size:0.73rem;color:var(--t3);margin-top:2px"><?= htmlspecialchars($t['category']) ?></div><?php endif; ?>
          </td>
          <td style="font-size:0.85rem"><?= htmlspecialchars($t['client_name']) ?></td>
          <td style="font-size:0.82rem;color:var(--t3)"><?= $t['dept_name'] ? htmlspecialchars($t['dept_name']) : '<span class="text-muted">—</span>' ?></td>
          <td style="font-size:0.85rem"><?= $t['agent_name'] ? htmlspecialchars($t['agent_name']) : '<span style="color:var(--orange);font-size:0.78rem">Unassigned</span>' ?></td>
          <td><?= getPriorityBadge($t['priority']) ?></td>
          <td><?= getStatusBadge($t['status']) ?></td>
          <td class="text-muted text-sm"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
          <td class="text-muted text-sm"><?= timeAgo($t['updated_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= paginate($total, $perPage, $page, BASE_URL . '/admin/tickets.php' . $filterUrl) ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
