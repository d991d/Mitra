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

$id   = (int)($_GET['id'] ?? 0);
$sale = DB::fetch("SELECT s.*,u.name client_name,u.email client_email,u.company client_co,a.name served_name FROM pos_sales s LEFT JOIN users u ON s.client_id=u.id LEFT JOIN users a ON s.served_by=a.id WHERE s.id=?",[$id]);
if (!$sale) { flash('error','Sale not found.'); header('Location: sales.php'); exit; }
$items = DB::fetchAll("SELECT * FROM pos_sale_items WHERE sale_id=? ORDER BY id", [$id]);

$companyName  = DB::setting('company_name')     ?: 'Mitra';
$companyPhone = DB::setting('company_phone')    ?: '';
$companyEmail = DB::setting('company_email')    ?: '';
$taxName      = DB::setting('pos_tax_name')     ?: 'GST';
$brandLogo    = DB::setting('branding_logo')    ?: '';
$brandLogoW   = DB::setting('branding_logo_width') ?: '160';
$invHeaderBg  = DB::setting('invoice_header_bg')   ?: '#1a1a2e';
$invHeaderTxt = DB::setting('invoice_header_text')  ?: '#ffffff';
$invAccent    = DB::setting('invoice_accent')       ?: '#2f81f7';
$brandFont    = DB::setting('branding_font')        ?: 'DM Sans';
$showLogo     = DB::setting('invoice_show_logo') !== '0';
$logoAbsPath  = $brandLogo ? __DIR__ . '/../../' . $brandLogo : '';
$logoUrl      = ($brandLogo && file_exists($logoAbsPath)) ? BASE_URL . '/' . $brandLogo : '';
$isPrint      = isset($_GET['print']);

if (!$isPrint) {
    $pageTitle = $sale['sale_number'];
    $activeNav = 'pos-sale';
    include __DIR__ . '/../includes/header.php';
}
?>

<?php if ($isPrint): ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title><?= htmlspecialchars($sale['sale_number']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Inter:wght@400;600;700&display=swap');
:root{--acc:<?= htmlspecialchars($invAccent) ?>;--bg:<?= htmlspecialchars($invHeaderBg) ?>;--txt:<?= htmlspecialchars($invHeaderTxt) ?>;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:<?= htmlspecialchars($brandFont) ?>,'DM Sans',sans-serif;max-width:320px;margin:0 auto;font-size:12px;background:white;color:#1a1a2e}
.receipt-header{background:var(--s0);color:var(--txt);padding:16px;text-align:center}
.receipt-logo{max-height:44px;max-width:140px;object-fit:contain;margin-bottom:6px}
.receipt-company{font-size:1rem;font-weight:800}
.receipt-sub{font-size:0.72rem;opacity:0.75;margin-top:2px}
.accent-bar{height:3px;background:var(--acc)}
.receipt-body{padding:12px 16px}
.center{text-align:center}
hr{border:none;border-top:1px dashed #ccc;margin:8px 0}
.row{display:flex;justify-content:space-between;margin-bottom:3px;font-size:0.82rem}
.grand{font-size:1rem;font-weight:800;border-top:2px solid var(--s0);padding-top:6px;margin-top:4px;color:var(--s0)}
.receipt-footer{padding:10px 16px;border-top:1px dashed #ccc;text-align:center;font-size:0.72rem;color:#888;margin-top:4px}
@media print{body{padding:0}@page{margin:4mm}}
</style></head><body>
<div class="receipt-header">
  <?php if ($showLogo && $logoUrl): ?>
    <img src="<?= htmlspecialchars($logoUrl) ?>" class="receipt-logo" alt="<?= htmlspecialchars($companyName) ?>">
  <?php else: ?>
    <div class="receipt-company"><?= htmlspecialchars($companyName) ?></div>
  <?php endif; ?>
  <?php if ($companyPhone || $companyEmail): ?>
    <div class="receipt-sub"><?= htmlspecialchars(implode(' · ', array_filter([$companyPhone, $companyEmail]))) ?></div>
  <?php endif; ?>
</div>
<div class="accent-bar"></div>
<div class="receipt-body">
<div class="center" style="font-weight:700;margin-bottom:2px"><?= htmlspecialchars($sale['sale_number']) ?></div>
<div class="center" style="color:#666;font-size:0.78rem"><?= date('M j, Y g:i A', strtotime($sale['created_at'])) ?></div>
<?php if ($sale['client_name']): ?><div class="center" style="margin-top:3px;font-weight:600"><?= htmlspecialchars($sale['client_name']) ?></div><?php endif; ?>
<hr>
<?php foreach ($items as $it): ?>
<div><?= htmlspecialchars($it['description']) ?> x<?= rtrim(rtrim(number_format($it['quantity'],3),'0'),'.') ?></div>
<div class="row"><span></span><span>$<?= number_format($it['subtotal'],2) ?></span></div>
<?php endforeach; ?>
<hr>
<div class="row"><span>Subtotal</span><span>$<?= number_format($sale['subtotal'],2) ?></span></div>
<div class="row"><span><?= $taxName ?></span><span>$<?= number_format($sale['tax_total'],2) ?></span></div>
<?php if ($sale['discount']>0): ?><div class="row"><span>Discount</span><span>-$<?= number_format($sale['discount'],2) ?></span></div><?php endif; ?>
<hr>
<div class="row grand"><span>TOTAL</span><span>$<?= number_format($sale['total'],2) ?></span></div>
<div class="row"><span>Tendered</span><span>$<?= number_format($sale['tendered'],2) ?></span></div>
<div class="row grand"><span>Change</span><span>$<?= number_format($sale['change_due'],2) ?></span></div>
<hr>
<div class="center">Payment: <?= strtoupper(str_replace('_',' ',$sale['payment_method'])) ?></div>
</div><!-- /receipt-body -->
<div class="receipt-footer">Thank you for your business!</div>
<script>window.onload=()=>window.print()</script>
</body></html>
<?php exit; endif; ?>

<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a href="sales.php" class="btn btn-outline btn-sm">← Back</a>
    <span class="ticket-num"><?= htmlspecialchars($sale['sale_number']) ?></span>
    <span class="status-badge status-resolved">Complete</span>
  </div>
  <a href="?id=<?= $id ?>&print=1" target="_blank" class="btn btn-outline btn-sm">🖨 Print Receipt</a>
</div>

<div style="max-width:680px">
<div class="card">
  <div style="display:flex;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
    <div>
      <div style="font-size:1.1rem;font-weight:700"><?= htmlspecialchars($companyName) ?></div>
      <div style="font-size:0.8rem;color:var(--t3)"><?= htmlspecialchars($companyPhone) ?> · <?= htmlspecialchars($companyEmail) ?></div>
    </div>
    <div style="text-align:right">
      <div class="ticket-num"><?= htmlspecialchars($sale['sale_number']) ?></div>
      <div style="font-size:0.8rem;color:var(--t3)"><?= date('M j, Y g:i A', strtotime($sale['created_at'])) ?></div>
      <?php if ($sale['client_name']): ?><div style="font-size:0.85rem;font-weight:600"><?= htmlspecialchars($sale['client_name']) ?></div><?php endif; ?>
    </div>
  </div>

  <table>
    <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Tax</th><th style="text-align:right">Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['description']) ?></td>
        <td><?= rtrim(rtrim(number_format($it['quantity'],3),'0'),'.') ?></td>
        <td><?= moneyRaw($it['unit_price']) ?></td>
        <td><?= $it['tax_rate']>0 ? $it['tax_rate'].'%' : '—' ?></td>
        <td style="text-align:right;font-weight:600"><?= moneyRaw($it['subtotal']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="display:flex;justify-content:flex-end;margin-top:16px">
    <div style="min-width:240px">
      <div style="display:flex;justify-content:space-between;font-size:0.88rem;margin-bottom:5px"><span style="color:var(--t3)">Subtotal</span><span><?= moneyRaw($sale['subtotal']) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:0.88rem;margin-bottom:5px"><span style="color:var(--t3)"><?= $taxName ?></span><span><?= moneyRaw($sale['tax_total']) ?></span></div>
      <?php if ($sale['discount']>0): ?>
      <div style="display:flex;justify-content:space-between;font-size:0.88rem;margin-bottom:5px;color:var(--ok)"><span>Discount</span><span>-<?= moneyRaw($sale['discount']) ?></span></div>
      <?php endif; ?>
      <div style="display:flex;justify-content:space-between;font-size:1.05rem;font-weight:800;border-top:1px solid var(--b1);padding-top:8px;margin-top:4px"><span>TOTAL</span><span><?= moneyRaw($sale['total']) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:0.88rem;margin-top:6px;color:var(--t3)"><span>Tendered</span><span><?= moneyRaw($sale['tendered']) ?></span></div>
      <div style="display:flex;justify-content:space-between;font-size:0.88rem;color:var(--ok);font-weight:600"><span>Change</span><span><?= moneyRaw($sale['change_due']) ?></span></div>
    </div>
  </div>

  <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--b1);display:flex;gap:20px;flex-wrap:wrap;font-size:0.85rem">
    <div><span style="color:var(--t3)">Payment:</span> <strong><?= ucwords(str_replace('_',' ',$sale['payment_method'])) ?></strong></div>
    <?php if ($sale['served_name']): ?><div><span style="color:var(--t3)">Served by:</span> <strong><?= htmlspecialchars($sale['served_name']) ?></strong></div><?php endif; ?>
    <?php if ($sale['ticket_id']): ?><div><span style="color:var(--t3)">Ticket:</span> <a href="<?= BASE_URL ?>/admin/ticket.php?id=<?= $sale['ticket_id'] ?>" class="ticket-num">View</a></div><?php endif; ?>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
