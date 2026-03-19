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

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: invoices.php'); exit; }

$inv = DB::fetch(
    "SELECT i.*, u.name as client_name, u.email as client_email, u.company as client_company,
            u.phone as client_phone, cb.name as created_by_name
     FROM pos_invoices i
     JOIN users u ON i.client_id = u.id
     LEFT JOIN users cb ON i.created_by = cb.id
     WHERE i.id = ?", [$id]
);
if (!$inv) { flash('error','Invoice not found.'); header('Location: invoices.php'); exit; }

$items    = DB::fetchAll("SELECT * FROM pos_invoice_items WHERE invoice_id=? ORDER BY sort_order,id", [$id]);
$payments = DB::fetchAll("SELECT p.*, u.name as rec_by FROM pos_payments p LEFT JOIN users u ON p.recorded_by=u.id WHERE p.invoice_id=? ORDER BY p.payment_date DESC", [$id]);

// Handle add payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf']??'')) { flash('error','Invalid request.'); header("Location: ?id=$id"); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'payment') {
        $amount  = (float)($_POST['amount']  ?? 0);
        $method  = $_POST['method']    ?? 'cash';
        $date    = $_POST['pay_date']  ?? date('Y-m-d');
        $ref     = trim($_POST['ref']  ?? '');
        $note    = trim($_POST['note'] ?? '');
        if ($amount > 0) {
            DB::insert("INSERT INTO pos_payments (invoice_id,amount,method,reference,note,payment_date,recorded_by) VALUES (?,?,?,?,?,?,?)",
                       [$id,$amount,$method,$ref,$note,$date,$currentUser['id']]);
            recalcInvoice($id);
            logActivity($currentUser['id'], 'payment_recorded', 'invoice', $id, moneyRaw($amount) . ' via ' . $method);
            flash('success', 'Payment of ' . moneyRaw($amount) . ' recorded.');
        }
    } elseif ($action === 'void') {
        DB::query("UPDATE pos_invoices SET status='void', updated_at=NOW() WHERE id=?", [$id]);
        flash('success', 'Invoice voided.');
    } elseif ($action === 'status') {
        $s = $_POST['new_status'] ?? '';
        if (in_array($s, ['draft','sent','paid','partial','overdue','void'])) {
            DB::query("UPDATE pos_invoices SET status=?,updated_at=NOW() WHERE id=?", [$s,$id]);
        }
    }
    header("Location: ?id=$id"); exit;
}

$companyName  = DB::setting('company_name')      ?: 'Mitra';
$companyPhone = DB::setting('company_phone')     ?: '';
$companyEmail = DB::setting('company_email')     ?: '';
$companyAddr  = DB::setting('company_address')   ?: '';
$taxName      = DB::setting('pos_tax_name')      ?: 'GST';
$invFooter    = DB::setting('pos_invoice_footer') ?: '';
// Branding
$brandLogo    = DB::setting('branding_logo')        ?: '';
$brandLogoW   = DB::setting('branding_logo_width')  ?: '180';
$brandFont    = DB::setting('branding_font')        ?: 'DM Sans';
$invHeaderBg  = DB::setting('invoice_header_bg')    ?: '#1a1a2e';
$invHeaderTxt = DB::setting('invoice_header_text')  ?: '#ffffff';
$invAccent    = DB::setting('invoice_accent')       ?: '#2f81f7';
$invTagline   = DB::setting('invoice_tagline')      ?: '';
$showLogo     = DB::setting('invoice_show_logo')    !== '0';
$showTagline  = DB::setting('invoice_show_tagline') !== '0';
$logoAbsPath  = $brandLogo ? __DIR__ . '/../../' . $brandLogo : '';
$logoUrl      = ($brandLogo && file_exists($logoAbsPath)) ? BASE_URL . '/' . $brandLogo : '';

$isPrint = isset($_GET['print']);

$pageTitle = $inv['invoice_number'];
$activeNav = 'pos-invoices';
if (!$isPrint) include __DIR__ . '/../includes/header.php';
?>

<?php if ($isPrint): ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title><?= htmlspecialchars($inv['invoice_number']) ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Inter:wght@400;600;700&family=Roboto:wght@400;600;700&family=Open+Sans:wght@400;600;700&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --inv-bg:  <?= htmlspecialchars($invHeaderBg) ?>;
  --inv-txt: <?= htmlspecialchars($invHeaderTxt) ?>;
  --inv-acc: <?= htmlspecialchars($invAccent) ?>;
}
body{font-family:<?= htmlspecialchars($brandFont) ?>,'DM Sans',sans-serif;background:white;color:#1a1a2e;font-size:13px;padding:0}
.inv-header{display:flex;justify-content:space-between;align-items:center;padding:24px 32px;background:var(--inv-bg);color:var(--inv-txt)}
.company-logo{max-height:54px;max-width:<?= htmlspecialchars($brandLogoW) ?>px;object-fit:contain;display:block}
.company-name{font-size:1.5rem;font-weight:800;color:var(--inv-txt)}
.company-sub{font-size:0.75rem;opacity:0.7;margin-top:3px}
.inv-badge{text-align:right}
.invoice-title{font-size:2.2rem;font-weight:900;color:var(--inv-txt);line-height:1}
.invoice-num{font-size:0.88rem;opacity:0.7;margin-top:4px}
.status-pill{display:inline-block;background:var(--inv-acc);color:white;padding:3px 12px;border-radius:20px;font-size:0.68rem;font-weight:700;text-transform:uppercase;margin-top:6px;letter-spacing:0.06em}
.accent-bar{height:4px;background:var(--inv-acc)}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;padding:20px 32px;background:#f8f9fc;margin-bottom:0}
.party-label{font-size:0.68rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#999;margin-bottom:5px}
.party-name{font-weight:700;font-size:0.95rem;margin-bottom:2px}
.party-meta{font-size:0.8rem;color:#666}
.meta-row{display:grid;grid-template-columns:1fr 1fr;gap:0 24px;padding:12px 32px;background:#f0f2f5}
.meta-item{font-size:0.78rem;color:#555}
.meta-label{font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#aaa;margin-bottom:2px}
table{width:100%;border-collapse:collapse}
thead th{background:var(--inv-bg);color:var(--inv-txt);padding:9px 14px;text-align:left;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.06em}
tbody td{padding:9px 14px;border-bottom:1px solid #eee;font-size:0.84rem}
tbody tr:last-child td{border-bottom:none}
.totals-section{padding:16px 32px;display:flex;justify-content:flex-end}
.totals-table{width:280px}
.total-row-p{display:flex;justify-content:space-between;padding:4px 0;font-size:0.85rem;color:#555}
.grand-total{display:flex;justify-content:space-between;padding:10px 0 0;font-size:1.05rem;font-weight:800;border-top:2px solid var(--inv-bg);margin-top:6px;color:var(--inv-bg)}
.notes-section{padding:16px 32px;display:grid;grid-template-columns:1fr 1fr;gap:16px}
.notes-box{border-left:3px solid var(--inv-acc);background:#f8f9fc;padding:10px 14px;border-radius:4px}
.notes-label{font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:#999;margin-bottom:5px}
.notes-body{font-size:0.82rem;color:#444;line-height:1.6}
.inv-footer{padding:12px 32px;border-top:1px solid #eee;text-align:center;font-size:0.75rem;color:#999;background:#fafafa}
@media print{body{padding:0}@page{margin:8mm}}
</style>
</head><body>
<?php endif; ?>

<?php if ($isPrint): ?>
<div class="inv-header">
  <div>
    <?php if ($showLogo && $logoUrl): ?>
      <img src="<?= htmlspecialchars($logoUrl) ?>" class="company-logo" alt="<?= htmlspecialchars($companyName) ?>">
    <?php else: ?>
      <div class="company-name"><?= htmlspecialchars($companyName) ?></div>
    <?php endif; ?>
    <?php if ($showTagline && $invTagline): ?>
      <div class="company-sub"><?= htmlspecialchars($invTagline) ?></div>
    <?php endif; ?>
    <?php if ($companyAddr): ?><div class="company-sub"><?= htmlspecialchars($companyAddr) ?></div><?php endif; ?>
    <?php if ($companyPhone || $companyEmail): ?>
      <div class="company-sub"><?= htmlspecialchars(implode(' · ', array_filter([$companyPhone, $companyEmail]))) ?></div>
    <?php endif; ?>
  </div>
  <div class="inv-badge">
    <div class="invoice-title">INVOICE</div>
    <div class="invoice-num"><?= htmlspecialchars($inv['invoice_number']) ?></div>
    <div><span class="status-pill"><?= strtoupper($inv['status']) ?></span></div>
  </div>
</div>
<div class="accent-bar"></div>

<div class="parties">
  <div>
    <div class="party-label">From</div>
    <div class="party-name"><?= htmlspecialchars($companyName) ?></div>
    <?php if ($companyAddr): ?><div class="party-meta"><?= htmlspecialchars($companyAddr) ?></div><?php endif; ?>
    <?php if ($companyEmail): ?><div class="party-meta"><?= htmlspecialchars($companyEmail) ?></div><?php endif; ?>
  </div>
  <div>
    <div class="party-label">Bill To</div>
    <div class="party-name"><?= htmlspecialchars($inv['client_name']) ?></div>
    <?php if ($inv['client_company']): ?><div class="party-meta"><?= htmlspecialchars($inv['client_company']) ?></div><?php endif; ?>
    <div class="party-meta"><?= htmlspecialchars($inv['client_email']) ?></div>
    <?php if ($inv['client_phone']): ?><div class="party-meta"><?= htmlspecialchars($inv['client_phone']) ?></div><?php endif; ?>
  </div>
</div>
<div class="meta-row">
  <div><div class="meta-label">Issue Date</div><div class="meta-item"><?= date('F j, Y', strtotime($inv['issue_date'])) ?></div></div>
  <?php if ($inv['due_date']): ?><div><div class="meta-label">Due Date</div><div class="meta-item" style="<?= $inv['due_date'] < date('Y-m-d') && !in_array($inv['status'],['paid','void']) ? 'color:#d73a3a;font-weight:700' : '' ?>"><?= date('F j, Y', strtotime($inv['due_date'])) ?></div></div><?php endif; ?>
  <?php if ($inv['ticket_id']): ?><div><div class="meta-label">Ref. Ticket</div><div class="meta-item">#<?= $inv['ticket_id'] ?></div></div><?php endif; ?>
</div>

<?php else: ?>
<!-- Web view header -->
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a href="<?= BASE_URL ?>/pos/invoices.php" class="btn btn-outline btn-sm">← Back</a>
    <span class="ticket-num" style="font-size:0.9rem"><?= htmlspecialchars($inv['invoice_number']) ?></span>
    <?= getInvoiceStatusBadge($inv['status']) ?>
  </div>
  <div style="display:flex;gap:8px">
    <a href="?id=<?= $id ?>&print=1" target="_blank" class="btn btn-outline btn-sm">🖨 Print / PDF</a>
    <a href="<?= BASE_URL ?>/pos/invoice-new.php?edit=<?= $id ?>" class="btn btn-outline btn-sm">✏️ Edit</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">
<div>
<?php endif; ?>

<!-- Invoice body (shared print + screen) -->
<div <?= !$isPrint ? 'class="card" style="margin-bottom:16px"' : '' ?>>
  <?php if (!$isPrint): ?>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:1.3rem;font-weight:800;color:var(--t1)"><?= htmlspecialchars($companyName) ?></div>
      <div style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($companyAddr) ?> · <?= htmlspecialchars($companyPhone) ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:1.4rem;font-weight:900;color:var(--t1)">INVOICE</div>
      <div class="ticket-num"><?= htmlspecialchars($inv['invoice_number']) ?></div>
      <div style="font-size:0.8rem;color:var(--t3);margin-top:4px">Issued: <?= date('M j, Y', strtotime($inv['issue_date'])) ?></div>
      <?php if ($inv['due_date']): ?><div style="font-size:0.8rem;color:<?= $inv['due_date'] < date('Y-m-d') && !in_array($inv['status'],['paid','void']) ? 'var(--err)':'var(--t3)' ?>">Due: <?= date('M j, Y', strtotime($inv['due_date'])) ?></div><?php endif; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;padding:16px;background:var(--s2);border-radius:var(--r-md)">
    <div>
      <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--t3);margin-bottom:6px">Bill From</div>
      <div style="font-weight:600"><?= htmlspecialchars($companyName) ?></div>
    </div>
    <div>
      <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--t3);margin-bottom:6px">Bill To</div>
      <div style="font-weight:600"><?= htmlspecialchars($inv['client_name']) ?></div>
      <?php if ($inv['client_company']): ?><div style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($inv['client_company']) ?></div><?php endif; ?>
      <div style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($inv['client_email']) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Line items table -->
  <table <?= $isPrint ? '' : 'style="margin-top:0"' ?>>
    <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Tax</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['description']) ?></td>
        <td><?= rtrim(rtrim(number_format($item['quantity'],3),'0'),'.') ?></td>
        <td><?= moneyRaw($item['unit_price']) ?></td>
        <td><?= $item['tax_rate'] > 0 ? $item['tax_rate'].'%' : '—' ?></td>
        <td style="text-align:right;font-weight:600"><?= moneyRaw($item['subtotal']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <div <?= $isPrint ? 'class="totals-section"' : 'style="display:flex;justify-content:flex-end;margin-top:12px"' ?>>
    <div <?= $isPrint ? 'class="totals-table"' : 'style="min-width:260px"' ?>>
      <div class="<?= $isPrint?'total-row-p':'total-row' ?>"><span>Subtotal</span><span><?= moneyRaw($inv['subtotal']) ?></span></div>
      <div class="<?= $isPrint?'total-row-p':'total-row' ?>"><span><?= $taxName ?></span><span><?= moneyRaw($inv['tax_total']) ?></span></div>
      <?php if ($inv['discount_total'] > 0): ?>
      <div class="<?= $isPrint?'total-row-p':'total-row' ?>" style="color:<?= $isPrint?'green':'var(--ok)' ?>"><span>Discount</span><span>-<?= moneyRaw($inv['discount_total']) ?></span></div>
      <?php endif; ?>
      <div class="<?= $isPrint?'grand-total':'total-row grand' ?>"><span>TOTAL</span><span><?= moneyRaw($inv['total']) ?></span></div>
      <?php if ($inv['amount_paid'] > 0): ?>
      <div class="<?= $isPrint?'total-row-p':'total-row' ?>" style="color:<?= $isPrint?'green':'var(--ok)' ?>"><span>Amount Paid</span><span><?= moneyRaw($inv['amount_paid']) ?></span></div>
      <div class="<?= $isPrint?'grand-total':'total-row grand' ?>" style="color:<?= $isPrint?'#e63c3c':'var(--orange)' ?>"><span>Balance Due</span><span><?= moneyRaw($inv['balance']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($inv['notes'] || $inv['terms']): ?>
  <div style="margin-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <?php if ($inv['notes']): ?>
    <div <?= $isPrint?'class="notes-box"':'style="background:var(--s2);border-left:3px solid var(--brand-h);padding:12px 14px;border-radius:4px"' ?>>
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:<?= $isPrint?'#999':'var(--t3)' ?>;margin-bottom:6px">Notes</div>
      <div style="font-size:0.85rem"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($inv['terms']): ?>
    <div <?= $isPrint?'class="notes-box"':'style="background:var(--s2);border-left:3px solid var(--warn);padding:12px 14px;border-radius:4px"' ?>>
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:<?= $isPrint?'#999':'var(--t3)' ?>;margin-bottom:6px">Terms</div>
      <div style="font-size:0.85rem"><?= nl2br(htmlspecialchars($inv['terms'])) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($isPrint): ?>
<div class="inv-footer">
  <?php if ($invFooter): ?><?= htmlspecialchars($invFooter) ?><?php else: ?>
  <?= htmlspecialchars(implode(' · ', array_filter([$companyName, $companyEmail, $companyPhone]))) ?>
  <?php endif; ?>
</div>
<script>window.onload=()=>window.print();</script>
</body></html>
<?php exit; endif; ?>

<!-- Payment history (web only) -->
<?php if (!empty($payments)): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-title" style="margin-bottom:12px">Payment History</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th><th>Recorded By</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= date('M j, Y', strtotime($p['payment_date'])) ?></td>
          <td style="font-weight:700;color:var(--ok)"><?= moneyRaw($p['amount']) ?></td>
          <td><span class="badge badge-low" style="text-transform:capitalize"><?= str_replace('_',' ',$p['method']) ?></span></td>
          <td style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($p['reference'] ?: '—') ?></td>
          <td style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($p['rec_by'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
</div>

<!-- Sidebar: record payment + actions -->
<div style="display:flex;flex-direction:column;gap:14px">

  <?php if (!in_array($inv['status'],['paid','void']) && $inv['balance'] > 0): ?>
  <div class="card">
    <div class="card-title" style="margin-bottom:14px">Record Payment</div>
    <form method="post">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="payment">
      <div class="form-group">
        <label class="form-label">Amount</label>
        <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="<?= $inv['balance'] ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Method</label>
        <select name="method" class="form-control">
          <?php foreach (['cash'=>'💵 Cash','credit_card'=>'💳 Credit','debit'=>'💳 Debit','etransfer'=>'📱 e-Transfer','cheque'=>'📝 Cheque','other'=>'Other'] as $v=>$l): ?>
          <option value="<?= $v ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Date</label>
        <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Reference / Note</label>
        <input type="text" name="ref" class="form-control" placeholder="Cheque #, txn ID…">
      </div>
      <button type="submit" class="btn btn-success w-full" style="justify-content:center">Record Payment</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Status & actions -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px">Invoice Info</div>
    <div class="detail-meta">
      <div class="meta-item"><div class="meta-label">Status</div><div><?= getInvoiceStatusBadge($inv['status']) ?></div></div>
      <div class="meta-item"><div class="meta-label">Total</div><div class="meta-value" style="font-size:1.1rem;font-weight:800"><?= moneyRaw($inv['total']) ?></div></div>
      <div class="meta-item"><div class="meta-label">Paid</div><div class="meta-value" style="color:var(--ok)"><?= moneyRaw($inv['amount_paid']) ?></div></div>
      <div class="meta-item"><div class="meta-label">Balance</div><div class="meta-value" style="color:<?= $inv['balance']>0?'var(--orange)':'var(--t3)' ?>;font-weight:700"><?= moneyRaw($inv['balance']) ?></div></div>
      <?php if ($inv['ticket_id']): ?>
      <div class="meta-item"><div class="meta-label">Linked Ticket</div><div><a href="<?= BASE_URL ?>/admin/ticket.php?id=<?= $inv['ticket_id'] ?>" class="ticket-num">View Ticket</a></div></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Change status -->
  <div class="card">
    <div class="card-title" style="margin-bottom:10px">Change Status</div>
    <form method="post" style="display:flex;gap:6px">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="status">
      <select name="new_status" class="form-control" style="flex:1;padding:6px 8px;font-size:0.85rem">
        <?php foreach (['draft','sent','paid','partial','overdue','void'] as $s): ?>
        <option value="<?= $s ?>" <?= $inv['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline btn-sm">Set</button>
    </form>
  </div>

</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
