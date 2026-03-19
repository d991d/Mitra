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
$currentUser = requireRole('admin');

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0); // 0 = full year

// Revenue by month
$revByMonth = DB::fetchAll(
    "SELECT DATE_FORMAT(payment_date,'%Y-%m') m, SUM(amount) rev, COUNT(*) cnt
     FROM pos_payments WHERE YEAR(payment_date)=? GROUP BY m ORDER BY m ASC", [$year]
);

// Sales by month (POS)
$salesByMonth = DB::fetchAll(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') m, SUM(total) rev, COUNT(*) cnt
     FROM pos_sales WHERE YEAR(created_at)=? GROUP BY m ORDER BY m ASC", [$year]
);

// Revenue by payment method
$byMethod = DB::fetchAll(
    "SELECT method, SUM(amount) total, COUNT(*) cnt FROM pos_payments WHERE YEAR(payment_date)=? GROUP BY method ORDER BY total DESC", [$year]
);

// Top clients
$topClients = DB::fetchAll(
    "SELECT u.name, u.company, SUM(p.amount) total_paid, COUNT(DISTINCT i.id) inv_count
     FROM pos_payments p JOIN pos_invoices i ON p.invoice_id=i.id JOIN users u ON i.client_id=u.id
     WHERE YEAR(p.payment_date)=? GROUP BY u.id ORDER BY total_paid DESC LIMIT 8", [$year]
);

// Top products by revenue
$topProducts = DB::fetchAll(
    "SELECT COALESCE(si.description, 'Unknown') name, SUM(si.subtotal) rev, SUM(si.quantity) qty
     FROM pos_sale_items si JOIN pos_sales s ON si.sale_id=s.id WHERE YEAR(s.created_at)=?
     GROUP BY si.description ORDER BY rev DESC LIMIT 8", [$year]
);

// Outstanding invoices
$outstanding = DB::fetchAll(
    "SELECT i.invoice_number, u.name client, i.total, i.balance, i.due_date, i.status
     FROM pos_invoices i JOIN users u ON i.client_id=u.id
     WHERE i.status IN ('sent','partial','overdue') ORDER BY i.due_date ASC LIMIT 10"
);

// Totals
$yearRevenue = DB::fetch("SELECT COALESCE(SUM(amount),0) s FROM pos_payments WHERE YEAR(payment_date)=?",[$year])['s'];
$yearSales   = DB::fetch("SELECT COALESCE(SUM(total),0) s FROM pos_sales WHERE YEAR(created_at)=?",[$year])['s'];
$yearInvs    = DB::fetch("SELECT COUNT(*) c FROM pos_invoices WHERE YEAR(created_at)=?",[$year])['c'];
$yearOutst   = DB::fetch("SELECT COALESCE(SUM(balance),0) s FROM pos_invoices WHERE status IN ('sent','partial','overdue')")['s'];

$availYears = DB::fetchAll("SELECT DISTINCT YEAR(payment_date) y FROM pos_payments ORDER BY y DESC");
if (empty($availYears)) $availYears = [['y'=>date('Y')]];

$pageTitle = 'Financial Reports';
$activeNav = 'pos-reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Financial Reports</div>
  <form method="get" style="display:flex;gap:8px;align-items:center">
    <select name="year" class="form-control" style="padding:6px 10px" onchange="this.form.submit()">
      <?php foreach ($availYears as $y): ?>
      <option value="<?= $y['y'] ?>" <?= $year==$y['y']?'selected':'' ?>><?= $y['y'] ?></option>
      <?php endforeach; ?>
      <option value="<?= date('Y') ?>" <?= $year==date('Y')?'selected':'' ?>><?= date('Y') ?></option>
    </select>
  </form>
</div>

<!-- Year summary -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card resolved"><div class="stat-number"><?= moneyRaw($yearRevenue) ?></div><div class="stat-label">Invoiced Revenue <?= $year ?></div></div>
  <div class="stat-card open"><div class="stat-number"><?= moneyRaw($yearSales) ?></div><div class="stat-label">POS Sales <?= $year ?></div></div>
  <div class="stat-card total"><div class="stat-number"><?= $yearInvs ?></div><div class="stat-label">Invoices Created</div></div>
  <div class="stat-card pending"><div class="stat-number"><?= moneyRaw($yearOutst) ?></div><div class="stat-label">Outstanding Balance</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

<!-- Invoice Revenue by month -->
<div class="card">
  <div class="card-title" style="margin-bottom:14px">💳 Invoice Revenue by Month (<?= $year ?>)</div>
  <?php
  $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  $monthMap = [];
  foreach ($revByMonth as $r) $monthMap[$r['m']] = $r['rev'];
  $maxR = max(array_values($monthMap) ?: [1]);
  for ($m = 1; $m <= 12; $m++):
    $key = $year . '-' . str_pad($m,2,'0',STR_PAD_LEFT);
    $val = $monthMap[$key] ?? 0;
    $pct = $maxR > 0 ? round($val/$maxR*100) : 0;
  ?>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
    <span style="font-size:0.78rem;color:var(--t3);width:28px"><?= $months[$m-1] ?></span>
    <div style="flex:1;height:16px;background:var(--s2);border-radius:3px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--ok),var(--brand-h));border-radius:3px"></div>
    </div>
    <span style="font-size:0.78rem;font-weight:700;width:64px;text-align:right"><?= $val>0 ? moneyRaw($val) : '—' ?></span>
  </div>
  <?php endfor; ?>
</div>

<!-- POS Sales by month -->
<div class="card">
  <div class="card-title" style="margin-bottom:14px">🛒 POS Sales by Month (<?= $year ?>)</div>
  <?php
  $salesMap = [];
  foreach ($salesByMonth as $r) $salesMap[$r['m']] = $r['rev'];
  $maxS = max(array_values($salesMap) ?: [1]);
  for ($m = 1; $m <= 12; $m++):
    $key = $year . '-' . str_pad($m,2,'0',STR_PAD_LEFT);
    $val = $salesMap[$key] ?? 0;
    $pct = $maxS > 0 ? round($val/$maxS*100) : 0;
  ?>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
    <span style="font-size:0.78rem;color:var(--t3);width:28px"><?= $months[$m-1] ?></span>
    <div style="flex:1;height:16px;background:var(--s2);border-radius:3px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--purple),var(--brand-h));border-radius:3px"></div>
    </div>
    <span style="font-size:0.78rem;font-weight:700;width:64px;text-align:right"><?= $val>0 ? moneyRaw($val) : '—' ?></span>
  </div>
  <?php endfor; ?>
</div>

<!-- By payment method -->
<div class="card">
  <div class="card-title" style="margin-bottom:14px">Payment Methods (<?= $year ?>)</div>
  <?php $methodTotal = array_sum(array_column($byMethod,'total')) ?: 1; ?>
  <?php foreach ($byMethod as $row):
    $pct = round($row['total']/$methodTotal*100);
  ?>
  <div style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px">
      <span><?= ucwords(str_replace('_',' ',$row['method'])) ?> <span style="color:var(--t3)">(<?= $row['cnt'] ?> txns)</span></span>
      <strong><?= moneyRaw($row['total']) ?> (<?= $pct ?>%)</strong>
    </div>
    <div style="height:6px;background:var(--s2);border-radius:3px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:var(--brand-h);border-radius:3px"></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($byMethod)): ?><div style="color:var(--t3);font-size:0.85rem">No payment data</div><?php endif; ?>
</div>

<!-- Top clients -->
<div class="card">
  <div class="card-title" style="margin-bottom:14px">Top Clients by Revenue (<?= $year ?>)</div>
  <?php if (empty($topClients)): ?><div style="color:var(--t3);font-size:0.85rem">No data</div>
  <?php else: $clientMax = max(array_column($topClients,'total_paid')) ?: 1; ?>
  <?php foreach ($topClients as $cl):
    $pct = round($cl['total_paid']/$clientMax*100);
  ?>
  <div style="margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:3px">
      <span><?= htmlspecialchars($cl['name']) ?><?= $cl['company'] ? ' <span style="color:var(--t3)">('.$cl['company'].')</span>' : '' ?></span>
      <strong><?= moneyRaw($cl['total_paid']) ?></strong>
    </div>
    <div style="height:5px;background:var(--s2);border-radius:3px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:var(--purple);border-radius:3px"></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<!-- Top products -->
<div class="card">
  <div class="card-title" style="margin-bottom:14px">Top Products/Services by Revenue (<?= $year ?>)</div>
  <?php if (empty($topProducts)): ?><div style="color:var(--t3);font-size:0.85rem">No POS sales data</div>
  <?php else: $prodMax = max(array_column($topProducts,'rev')) ?: 1; ?>
  <?php foreach ($topProducts as $pr):
    $pct = round($pr['rev']/$prodMax*100);
  ?>
  <div style="margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:3px">
      <span style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($pr['name']) ?></span>
      <strong><?= moneyRaw($pr['rev']) ?></strong>
    </div>
    <div style="height:5px;background:var(--s2);border-radius:3px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:var(--orange);border-radius:3px"></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Outstanding invoices -->
<div class="card">
  <div class="card-title" style="margin-bottom:12px">Outstanding Invoices</div>
  <?php if (empty($outstanding)): ?>
  <div style="color:var(--ok);font-size:0.88rem">✅ All invoices are paid!</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Client</th><th>Balance</th><th>Due</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($outstanding as $inv): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/pos/invoice.php?id=" class="ticket-num"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
          <td style="font-size:0.82rem"><?= htmlspecialchars($inv['client']) ?></td>
          <td style="font-weight:700;color:var(--orange)"><?= moneyRaw($inv['balance']) ?></td>
          <td style="font-size:0.78rem;color:<?= $inv['due_date']<date('Y-m-d')?'var(--err)':'var(--t3)' ?>"><?= $inv['due_date'] ? date('M j', strtotime($inv['due_date'])) : '—' ?></td>
          <td><?= getInvoiceStatusBadge($inv['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
