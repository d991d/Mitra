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
require_once __DIR__ . '/functions_pos.php';
$currentUser = requireAdmin();

$perPage = 15;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if (!empty($_GET['status']))  { $where[] = 'i.status = ?';     $params[] = $_GET['status']; }
if (!empty($_GET['client']))  { $where[] = 'i.client_id = ?';  $params[] = (int)$_GET['client']; }
if (!empty($_GET['search']))  { $where[] = '(i.invoice_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; $s='%'.$_GET['search'].'%'; $params=array_merge($params,[$s,$s,$s]); }

$wStr  = implode(' AND ', $where);
$total = DB::fetch("SELECT COUNT(*) c FROM pos_invoices i JOIN users u ON i.client_id=u.id WHERE $wStr", $params)['c'];

$invoices = DB::fetchAll(
    "SELECT i.*, u.name as client_name, u.company as client_company
     FROM pos_invoices i JOIN users u ON i.client_id=u.id
     WHERE $wStr ORDER BY i.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

// Totals summary
$summary = DB::fetch("SELECT COALESCE(SUM(total),0) total_sum, COALESCE(SUM(amount_paid),0) paid_sum, COALESCE(SUM(balance),0) bal_sum FROM pos_invoices WHERE status NOT IN ('void','draft')");

$clients = DB::fetchAll("SELECT id, name FROM users WHERE role='client' ORDER BY name");
$fUrl    = '?'.http_build_query(array_filter(['status'=>$_GET['status']??'','client'=>$_GET['client']??'','search'=>$_GET['search']??'']));

$pageTitle = 'Invoices';
$activeNav = 'pos-invoices';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Invoices <span style="color:var(--t3);font-weight:400;font-size:1rem">(<?= $total ?>)</span></div>
  <a href="<?= BASE_URL ?>/pos/invoice-new.php" class="btn btn-primary">📄 New Invoice</a>
</div>

<!-- Summary strip -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
  <?php
  $summStats = [
    ['Total Invoiced', $summary['total_sum'], 'var(--t1)'],
    ['Total Paid',     $summary['paid_sum'],  'var(--ok)'],
    ['Outstanding',    $summary['bal_sum'],   'var(--orange)'],
  ];
  foreach ($summStats as [$label,$val,$color]): ?>
  <div style="background:var(--s1);border:1px solid var(--b1);border-radius:var(--r-md);padding:12px 20px;flex:1;min-width:140px">
    <div style="font-size:1.2rem;font-weight:700;color:<?= $color ?>"><?= moneyRaw($val) ?></div>
    <div style="font-size:0.75rem;color:var(--t3);font-weight:600;text-transform:uppercase;letter-spacing:0.06em"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="filter-bar">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;width:100%">
    <input type="text" name="search" placeholder="Invoice #, client…" value="<?= htmlspecialchars($_GET['search']??'') ?>" style="min-width:160px">
    <select name="status">
      <option value="">All Status</option>
      <?php foreach (['draft','sent','paid','partial','overdue','void'] as $s): ?>
      <option value="<?= $s ?>" <?= ($_GET['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="client">
      <option value="">All Clients</option>
      <?php foreach ($clients as $c): ?>
      <option value="<?= $c['id'] ?>" <?= ($_GET['client']??'')==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= BASE_URL ?>/pos/invoices.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
<div class="table-wrap">
<table>
  <thead>
    <tr><th>Invoice #</th><th>Client</th><th>Issue Date</th><th>Due Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
  </thead>
  <tbody>
    <?php if (empty($invoices)): ?>
    <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">🧾</div><div class="empty-title">No invoices found</div><div class="empty-sub"><a href="<?= BASE_URL ?>/pos/invoice-new.php">Create your first invoice</a></div></div></td></tr>
    <?php else: ?>
    <?php foreach ($invoices as $inv): ?>
    <tr>
      <td><a href="<?= BASE_URL ?>/pos/invoice.php?id=<?= $inv['id'] ?>" class="ticket-num"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
      <td>
        <div style="font-size:0.88rem;font-weight:500"><?= htmlspecialchars($inv['client_name']) ?></div>
        <?php if ($inv['client_company']): ?><div style="font-size:0.75rem;color:var(--t3)"><?= htmlspecialchars($inv['client_company']) ?></div><?php endif; ?>
      </td>
      <td class="text-sm text-muted"><?= date('M j, Y', strtotime($inv['issue_date'])) ?></td>
      <td class="text-sm" style="color:<?= $inv['due_date'] && $inv['due_date'] < date('Y-m-d') && !in_array($inv['status'],['paid','void']) ? 'var(--err)':'var(--t3)' ?>">
        <?= $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '—' ?>
      </td>
      <td style="font-weight:700"><?= moneyRaw($inv['total']) ?></td>
      <td style="color:var(--ok)"><?= moneyRaw($inv['amount_paid']) ?></td>
      <td style="font-weight:600;color:<?= $inv['balance']>0?'var(--orange)':'var(--t3)' ?>"><?= moneyRaw($inv['balance']) ?></td>
      <td><?= getInvoiceStatusBadge($inv['status']) ?></td>
      <td>
        <div style="display:flex;gap:4px">
          <a href="<?= BASE_URL ?>/pos/invoice.php?id=<?= $inv['id'] ?>" class="btn btn-outline btn-xs">View</a>
          <a href="<?= BASE_URL ?>/pos/invoice.php?id=<?= $inv['id'] ?>&print=1" target="_blank" class="btn btn-outline btn-xs">🖨</a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?= paginate($total, $perPage, $page, BASE_URL . '/pos/invoices.php' . $fUrl) ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
