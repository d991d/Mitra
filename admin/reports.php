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
$currentUser = requireRole('admin');

// Tickets by month (last 6 months)
$byMonth = DB::fetchAll(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as cnt
     FROM tickets WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month ORDER BY month ASC"
);

// By status
$byStatus = DB::fetchAll("SELECT status, COUNT(*) as cnt FROM tickets GROUP BY status");

// By priority
$byPriority = DB::fetchAll("SELECT priority, COUNT(*) as cnt FROM tickets GROUP BY priority");

// By department
$byDept = DB::fetchAll(
    "SELECT d.name, COUNT(t.id) as cnt FROM departments d
     LEFT JOIN tickets t ON t.department_id=d.id
     GROUP BY d.id ORDER BY cnt DESC"
);

// By category
$byCat = DB::fetchAll(
    "SELECT category, COUNT(*) as cnt FROM tickets WHERE category IS NOT NULL AND category != ''
     GROUP BY category ORDER BY cnt DESC LIMIT 8"
);

// Avg resolution time (resolved/closed)
$avgRes = DB::fetch(
    "SELECT AVG(TIMESTAMPDIFF(HOUR,created_at,closed_at)) as avg_h FROM tickets WHERE closed_at IS NOT NULL"
);
$avgHours = round($avgRes['avg_h'] ?? 0);

// Total stats
$total    = DB::fetch("SELECT COUNT(*) as c FROM tickets")['c'];
$resolved = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE status IN ('resolved','closed')")['c'];
$rate     = $total ? round($resolved / $total * 100) : 0;

$pageTitle = 'Reports';
$activeNav = 'reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-title" style="margin-bottom:20px">Reports & Analytics</div>

<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card total">
    <div class="stat-number"><?= $total ?></div>
    <div class="stat-label">Total Tickets</div>
  </div>
  <div class="stat-card resolved">
    <div class="stat-number"><?= $rate ?>%</div>
    <div class="stat-label">Resolution Rate</div>
  </div>
  <div class="stat-card pending">
    <div class="stat-number"><?= $avgHours ?>h</div>
    <div class="stat-label">Avg. Resolution Time</div>
  </div>
  <div class="stat-card open">
    <div class="stat-number"><?= $resolved ?></div>
    <div class="stat-label">Resolved / Closed</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

  <div class="card">
    <div class="card-title" style="margin-bottom:16px">Tickets by Status</div>
    <?php foreach ($byStatus as $row): ?>
    <?php $pct = $total ? round($row['cnt']/$total*100) : 0; ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px">
        <span><?= ucfirst($row['status']) ?></span>
        <strong><?= $row['cnt'] ?> (<?= $pct ?>%)</strong>
      </div>
      <div style="height:6px;background:var(--s2);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--brand-h);border-radius:3px;transition:width 0.6s"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:16px">Tickets by Priority</div>
    <?php
    $pColors = ['critical'=>'var(--err)','high'=>'var(--orange)','medium'=>'var(--warn)','low'=>'var(--ok)'];
    foreach ($byPriority as $row):
      $pct = $total ? round($row['cnt']/$total*100) : 0;
      $col = $pColors[$row['priority']] ?? 'var(--brand-h)';
    ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px">
        <span><?= ucfirst($row['priority']) ?></span>
        <strong><?= $row['cnt'] ?> (<?= $pct ?>%)</strong>
      </div>
      <div style="height:6px;background:var(--s2);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:<?= $col ?>;border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:16px">By Department</div>
    <?php $deptTotal = array_sum(array_column($byDept,'cnt')) ?: 1; ?>
    <?php foreach ($byDept as $row): ?>
    <?php $pct = round($row['cnt']/$deptTotal*100); ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px">
        <span><?= htmlspecialchars($row['name']) ?></span>
        <strong><?= $row['cnt'] ?></strong>
      </div>
      <div style="height:6px;background:var(--s2);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--purple);border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-title" style="margin-bottom:16px">Top Categories</div>
    <?php $catTotal = array_sum(array_column($byCat,'cnt')) ?: 1; ?>
    <?php foreach ($byCat as $row): ?>
    <?php $pct = round($row['cnt']/$catTotal*100); ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:4px">
        <span><?= htmlspecialchars($row['category']) ?></span>
        <strong><?= $row['cnt'] ?></strong>
      </div>
      <div style="height:6px;background:var(--s2);border-radius:3px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--ok);border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div>

<!-- Monthly trend -->
<div class="card" style="margin-top:20px">
  <div class="card-title" style="margin-bottom:16px">Monthly Ticket Volume (Last 6 Months)</div>
  <?php
  $max = max(array_column($byMonth,'cnt') ?: [1]);
  foreach ($byMonth as $row):
    $pct = round($row['cnt']/$max*100);
  ?>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
    <span style="font-size:0.82rem;color:var(--t3);width:60px"><?= $row['month'] ?></span>
    <div style="flex:1;height:20px;background:var(--s2);border-radius:4px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--brand-h),var(--purple));border-radius:4px"></div>
    </div>
    <span style="font-size:0.85rem;font-weight:700;width:30px"><?= $row['cnt'] ?></span>
  </div>
  <?php endforeach; ?>
  <?php if (empty($byMonth)): ?><div style="color:var(--t3);font-size:0.85rem">No data yet</div><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
