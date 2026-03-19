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

$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if (!empty($_GET['search'])) { $where[] = '(s.sale_number LIKE ? OR u.name LIKE ?)'; $q='%'.$_GET['search'].'%'; $params=array_merge($params,[$q,$q]); }
if (!empty($_GET['method']))  { $where[] = 's.payment_method=?'; $params[] = $_GET['method']; }
if (!empty($_GET['date']))    { $where[] = 'DATE(s.created_at)=?'; $params[] = $_GET['date']; }

$wStr  = implode(' AND ', $where);
$total = DB::fetch("SELECT COUNT(*) c FROM pos_sales s LEFT JOIN users u ON s.client_id=u.id WHERE $wStr", $params)['c'];
$sales = DB::fetchAll("SELECT s.*, u.name client_name FROM pos_sales s LEFT JOIN users u ON s.client_id=u.id WHERE $wStr ORDER BY s.created_at DESC LIMIT $perPage OFFSET $offset", $params);

// Today totals
$tDate  = date('Y-m-d');
$tStats = DB::fetch("SELECT COUNT(*) cnt, COALESCE(SUM(total),0) rev FROM pos_sales WHERE DATE(created_at)=?", [$tDate]);

$pageTitle = 'Sales History';
$activeNav = 'pos-sale';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Sales History <span style="color:var(--t3);font-weight:400;font-size:1rem">(<?= $total ?>)</span></div>
  <a href="<?= BASE_URL ?>/pos/sale.php" class="btn btn-primary">🛒 New Sale</a>
</div>

<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">
  <div style="background:var(--s1);border:1px solid var(--b1);border-radius:var(--r-md);padding:12px 20px;flex:1;min-width:140px">
    <div style="font-size:1.2rem;font-weight:700;color:var(--ok)"><?= moneyRaw($tStats['rev']) ?></div>
    <div style="font-size:0.75rem;color:var(--t3);font-weight:600;text-transform:uppercase">Revenue Today</div>
  </div>
  <div style="background:var(--s1);border:1px solid var(--b1);border-radius:var(--r-md);padding:12px 20px;flex:1;min-width:140px">
    <div style="font-size:1.2rem;font-weight:700;color:var(--brand-h)"><?= $tStats['cnt'] ?></div>
    <div style="font-size:0.75rem;color:var(--t3);font-weight:600;text-transform:uppercase">Transactions Today</div>
  </div>
</div>

<div class="filter-bar">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;width:100%">
    <input type="text"  name="search" placeholder="Sale #, client…" value="<?= htmlspecialchars($_GET['search']??'') ?>">
    <input type="date"  name="date"   value="<?= htmlspecialchars($_GET['date']??'') ?>">
    <select name="method">
      <option value="">All Methods</option>
      <?php foreach (['cash','credit_card','debit','etransfer','cheque','other'] as $m): ?>
      <option value="<?= $m ?>" <?= ($_GET['method']??'')===$m?'selected':'' ?>><?= ucwords(str_replace('_',' ',$m)) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="sales.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<div class="card" style="padding:0;overflow:hidden">
<div class="table-wrap">
<table>
  <thead><tr><th>Sale #</th><th>Client</th><th>Items</th><th>Total</th><th>Payment</th><th>Time</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if (empty($sales)): ?>
    <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">🛒</div><div class="empty-title">No sales yet</div><div class="empty-sub"><a href="sale.php">Make your first sale</a></div></div></td></tr>
    <?php else: ?>
    <?php foreach ($sales as $s):
      $itemCount = DB::fetch("SELECT COUNT(*) c FROM pos_sale_items WHERE sale_id=?",[$s['id']])['c'];
    ?>
    <tr>
      <td><a href="sale-view.php?id=<?= $s['id'] ?>" class="ticket-num"><?= htmlspecialchars($s['sale_number']) ?></a></td>
      <td style="font-size:0.85rem"><?= $s['client_name'] ? htmlspecialchars($s['client_name']) : '<span class="text-muted">Walk-in</span>' ?></td>
      <td style="color:var(--t3);font-size:0.82rem"><?= $itemCount ?> item<?= $itemCount!=1?'s':'' ?></td>
      <td style="font-weight:700;color:var(--ok)"><?= moneyRaw($s['total']) ?></td>
      <td><span class="badge badge-low" style="text-transform:capitalize"><?= str_replace('_',' ',$s['payment_method']) ?></span></td>
      <td class="text-muted text-sm"><?= date('M j, Y g:i A', strtotime($s['created_at'])) ?></td>
      <td><div style="display:flex;gap:4px"><a href="sale-view.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-xs">View</a><a href="sale-view.php?id=<?= $s['id'] ?>&print=1" target="_blank" class="btn btn-outline btn-xs">🖨</a></div></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?= paginate($total, $perPage, $page, BASE_URL . '/pos/sales.php?' . http_build_query(array_filter(['search'=>$_GET['search']??'','method'=>$_GET['method']??'','date'=>$_GET['date']??'']))) ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
