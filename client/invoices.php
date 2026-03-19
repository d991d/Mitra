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
require_once __DIR__ . '/../pos/functions_pos.php';
$currentUser = requireLogin('index.php');
if (in_array($currentUser['role'], ['admin','agent'])) { header('Location: ' . BASE_URL . '/pos/invoices.php'); exit; }

$uid     = $currentUser['id'];
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$total    = DB::fetch("SELECT COUNT(*) c FROM pos_invoices WHERE client_id=?", [$uid])['c'];
$invoices = DB::fetchAll("SELECT * FROM pos_invoices WHERE client_id=? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", [$uid]);

$totalOutstanding = DB::fetch("SELECT COALESCE(SUM(balance),0) s FROM pos_invoices WHERE client_id=? AND status IN ('sent','partial','overdue')", [$uid])['s'];
$totalPaid        = DB::fetch("SELECT COALESCE(SUM(amount_paid),0) s FROM pos_invoices WHERE client_id=?", [$uid])['s'];

$pageTitle = 'My Invoices';
$activeNav = 'client-invoices';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">My Invoices</div>
</div>

<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <div style="background:var(--s1);border:1px solid var(--b1);border-radius:var(--r-md);padding:14px 20px;flex:1;min-width:140px">
    <div style="font-size:1.2rem;font-weight:700;color:var(--ok)"><?= moneyRaw($totalPaid) ?></div>
    <div style="font-size:0.75rem;color:var(--t3);font-weight:600;text-transform:uppercase">Total Paid</div>
  </div>
  <div style="background:var(--s1);border:1px solid var(--b1);border-radius:var(--r-md);padding:14px 20px;flex:1;min-width:140px">
    <div style="font-size:1.2rem;font-weight:700;color:<?= $totalOutstanding>0?'var(--orange)':'var(--t3)' ?>"><?= moneyRaw($totalOutstanding) ?></div>
    <div style="font-size:0.75rem;color:var(--t3);font-weight:600;text-transform:uppercase">Outstanding</div>
  </div>
</div>

<div class="card" style="padding:0;overflow:hidden">
<div class="table-wrap">
<table>
  <thead><tr><th>Invoice #</th><th>Date</th><th>Due</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead>
  <tbody>
    <?php if (empty($invoices)): ?>
    <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">🧾</div><div class="empty-title">No invoices yet</div></div></td></tr>
    <?php else: ?>
    <?php foreach ($invoices as $inv): ?>
    <tr>
      <td><span class="ticket-num"><?= htmlspecialchars($inv['invoice_number']) ?></span></td>
      <td class="text-sm text-muted"><?= date('M j, Y', strtotime($inv['issue_date'])) ?></td>
      <td class="text-sm" style="color:<?= $inv['due_date'] && $inv['due_date']<date('Y-m-d') && !in_array($inv['status'],['paid','void'])?'var(--err)':'var(--t3)' ?>">
        <?= $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '—' ?></td>
      <td style="font-weight:700"><?= moneyRaw($inv['total']) ?></td>
      <td style="font-weight:600;color:<?= $inv['balance']>0?'var(--orange)':'var(--t3)' ?>"><?= moneyRaw($inv['balance']) ?></td>
      <td><?= getInvoiceStatusBadge($inv['status']) ?></td>
      <td><a href="<?= BASE_URL ?>/pos/invoice.php?id=<?= $inv['id'] ?>&print=1" target="_blank" class="btn btn-outline btn-xs">🖨 View</a></td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</div>
</div>
<?= paginate($total, $perPage, $page, BASE_URL . '/client/invoices.php?') ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
