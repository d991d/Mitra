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

$stats = getPosStats();

// Recent invoices
$recentInvoices = DB::fetchAll(
    "SELECT i.*, u.name as client_name FROM pos_invoices i
     JOIN users u ON i.client_id = u.id
     ORDER BY i.updated_at DESC LIMIT 6"
);

// Recent sales
$recentSales = DB::fetchAll(
    "SELECT s.*, u.name as client_name FROM pos_sales s
     LEFT JOIN users u ON s.client_id = u.id
     ORDER BY s.created_at DESC LIMIT 6"
);

// Revenue last 6 months
$revMonths = DB::fetchAll(
    "SELECT DATE_FORMAT(payment_date,'%Y-%m') as m, SUM(amount) as rev
     FROM pos_payments
     WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY m ORDER BY m ASC"
);

// Low stock
$lowStock = DB::fetchAll(
    "SELECT * FROM pos_products WHERE stock_qty IS NOT NULL AND stock_qty <= 3 AND is_active=1 ORDER BY stock_qty ASC LIMIT 5"
);

$pageTitle = 'POS Overview';
$activeNav = 'pos-dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">POS & Billing Overview</div>
    <div class="page-subtitle">Sales, invoices, and financial snapshot</div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="<?= BASE_URL ?>/pos/sale.php" class="btn btn-primary">🛒 Quick Sale</a>
    <a href="<?= BASE_URL ?>/pos/invoice-new.php" class="btn btn-outline">📄 New Invoice</a>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
  <div class="stat-card total">
    <div class="stat-number"><?= moneyRaw($stats['revenue_month']) ?></div>
    <div class="stat-label">Revenue This Month</div>
  </div>
  <div class="stat-card open">
    <div class="stat-number"><?= moneyRaw($stats['outstanding']) ?></div>
    <div class="stat-label">Outstanding</div>
  </div>
  <div class="stat-card pending">
    <div class="stat-number"><?= $stats['invoices_unpaid'] ?></div>
    <div class="stat-label">Unpaid Invoices</div>
  </div>
  <div class="stat-card critical">
    <div class="stat-number"><?= $stats['overdue'] ?></div>
    <div class="stat-label">Overdue</div>
  </div>
  <div class="stat-card resolved">
    <div class="stat-number"><?= moneyRaw($stats['revenue_today']) ?></div>
    <div class="stat-label">Today's Sales</div>
  </div>
  <div class="stat-card total">
    <div class="stat-number"><?= $stats['sales_today'] ?></div>
    <div class="stat-label">Transactions Today</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

<!-- Recent Invoices -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Recent Invoices</div>
    <a href="<?= BASE_URL ?>/pos/invoices.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Client</th><th>Total</th><th>Status</th><th>Due</th></tr></thead>
      <tbody>
        <?php if (empty($recentInvoices)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--t3);padding:20px">No invoices yet</td></tr>
        <?php else: ?>
        <?php foreach ($recentInvoices as $inv): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/pos/invoice.php?id=<?= $inv['id'] ?>" class="ticket-num"><?= htmlspecialchars($inv['invoice_number']) ?></a></td>
          <td style="font-size:0.85rem"><?= htmlspecialchars($inv['client_name']) ?></td>
          <td style="font-weight:600;font-size:0.88rem"><?= moneyRaw($inv['total']) ?></td>
          <td><?= getInvoiceStatusBadge($inv['status']) ?></td>
          <td class="text-muted text-sm"><?= $inv['due_date'] ? date('M j', strtotime($inv['due_date'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent Sales -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Recent POS Sales</div>
    <a href="<?= BASE_URL ?>/pos/sales.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Client</th><th>Total</th><th>Payment</th><th>Time</th></tr></thead>
      <tbody>
        <?php if (empty($recentSales)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--t3);padding:20px">No sales yet</td></tr>
        <?php else: ?>
        <?php foreach ($recentSales as $sale): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/pos/sale-view.php?id=<?= $sale['id'] ?>" class="ticket-num"><?= htmlspecialchars($sale['sale_number']) ?></a></td>
          <td style="font-size:0.85rem"><?= $sale['client_name'] ? htmlspecialchars($sale['client_name']) : '<span class="text-muted">Walk-in</span>' ?></td>
          <td style="font-weight:600;font-size:0.88rem"><?= moneyRaw($sale['total']) ?></td>
          <td><span class="badge badge-low" style="text-transform:capitalize"><?= str_replace('_',' ',$sale['payment_method']) ?></span></td>
          <td class="text-muted text-sm"><?= timeAgo($sale['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

<!-- Revenue chart -->
<div class="card">
  <div class="card-title" style="margin-bottom:16px">Monthly Revenue (Last 6 Months)</div>
  <?php
  $maxRev = max(array_column($revMonths, 'rev') ?: [1]);
  foreach ($revMonths as $row):
    $pct = $maxRev > 0 ? round($row['rev'] / $maxRev * 100) : 0;
  ?>
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
    <span style="font-size:0.82rem;color:var(--t3);width:62px;flex-shrink:0"><?= $row['m'] ?></span>
    <div style="flex:1;height:22px;background:var(--s2);border-radius:4px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--ok),var(--brand-h));border-radius:4px;transition:width 0.6s"></div>
    </div>
    <span style="font-size:0.85rem;font-weight:700;width:72px;text-align:right"><?= moneyRaw($row['rev']) ?></span>
  </div>
  <?php endforeach; ?>
  <?php if (empty($revMonths)): ?><div style="color:var(--t3);font-size:0.85rem">No payment data yet</div><?php endif; ?>
</div>

<!-- Low stock & quick actions -->
<div style="display:flex;flex-direction:column;gap:14px">
  <?php if (!empty($lowStock)): ?>
  <div class="card">
    <div class="card-title" style="margin-bottom:12px">⚠️ Low Stock</div>
    <?php foreach ($lowStock as $p): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;margin-bottom:8px">
      <span style="color:var(--t2)"><?= htmlspecialchars($p['name']) ?></span>
      <strong style="color:<?= $p['stock_qty']<=1?'var(--err)':'var(--orange)' ?>"><?= $p['stock_qty'] ?> left</strong>
    </div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/pos/products.php" class="btn btn-outline btn-sm w-full" style="justify-content:center;margin-top:8px">Manage Stock</a>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-title" style="margin-bottom:12px">⚡ Quick Actions</div>
    <div style="display:flex;flex-direction:column;gap:6px">
      <a href="<?= BASE_URL ?>/pos/sale.php" class="btn btn-primary btn-sm" style="justify-content:flex-start">🛒 Start POS Sale</a>
      <a href="<?= BASE_URL ?>/pos/invoice-new.php" class="btn btn-outline btn-sm" style="justify-content:flex-start">📄 Create Invoice</a>
      <a href="<?= BASE_URL ?>/pos/invoices.php?status=overdue" class="btn btn-outline btn-sm" style="justify-content:flex-start">🔴 View Overdue</a>
      <a href="<?= BASE_URL ?>/pos/products.php" class="btn btn-outline btn-sm" style="justify-content:flex-start">📦 Products & Services</a>
      <a href="<?= BASE_URL ?>/pos/reports.php" class="btn btn-outline btn-sm" style="justify-content:flex-start">💰 Financial Reports</a>
    </div>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
