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

$editId  = (int)($_GET['edit'] ?? 0);
$invoice = null;
$items   = [];

if ($editId) {
    $invoice = DB::fetch("SELECT * FROM pos_invoices WHERE id=?", [$editId]);
    if (!$invoice) { flash('error','Invoice not found.'); header('Location: invoices.php'); exit; }
    $items = DB::fetchAll("SELECT * FROM pos_invoice_items WHERE invoice_id=? ORDER BY sort_order,id", [$editId]);
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf']??'')) { flash('error','Invalid request.'); header('Location: invoice-new.php'); exit; }

    $clientId  = (int)($_POST['client_id'] ?? 0);
    $ticketId  = (int)($_POST['ticket_id'] ?? 0) ?: null;
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $dueDate   = $_POST['due_date']   ?? null;
    $status    = $_POST['status']     ?? 'draft';
    $notes     = trim($_POST['notes'] ?? '');
    $terms     = trim($_POST['terms'] ?? '');
    $discType  = $_POST['discount_type']  ?? 'fixed';
    $discVal   = (float)($_POST['discount_value'] ?? 0);
    $currency  = $_POST['currency'] ?? 'CAD';

    $rowDesc  = $_POST['item_desc']     ?? [];
    $rowQty   = $_POST['item_qty']      ?? [];
    $rowPrice = $_POST['item_price']    ?? [];
    $rowTax   = $_POST['item_tax']      ?? [];
    $rowPid   = $_POST['item_pid']      ?? [];

    if (!$clientId) { flash('error','Select a client.'); header('Location: '.$_SERVER['REQUEST_URI']); exit; }

    if ($editId) {
        DB::query("UPDATE pos_invoices SET client_id=?,ticket_id=?,issue_date=?,due_date=?,status=?,notes=?,terms=?,discount_type=?,discount_value=?,currency=?,updated_at=NOW() WHERE id=?",
                  [$clientId,$ticketId,$issueDate,$dueDate?:null,$status,$notes,$terms,$discType,$discVal,$currency,$editId]);
        DB::query("DELETE FROM pos_invoice_items WHERE invoice_id=?", [$editId]);
        $invId = $editId;
    } else {
        $invNum = generateInvoiceNumber();
        $invId  = DB::insert(
            "INSERT INTO pos_invoices (invoice_number,client_id,ticket_id,issue_date,due_date,status,notes,terms,discount_type,discount_value,currency,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$invNum,$clientId,$ticketId,$issueDate,$dueDate?:null,$status,$notes,$terms,$discType,$discVal,$currency,$currentUser['id']]
        );
    }

    // Insert line items
    foreach ($rowDesc as $i => $desc) {
        if (!trim($desc)) continue;
        $qty   = (float)($rowQty[$i]   ?? 1);
        $price = (float)($rowPrice[$i] ?? 0);
        $tax   = (float)($rowTax[$i]   ?? 0);
        $pid   = (int)($rowPid[$i]     ?? 0) ?: null;
        $sub   = round($qty * $price, 2);
        $taxAmt= round($sub * $tax / 100, 2);
        DB::insert("INSERT INTO pos_invoice_items (invoice_id,product_id,description,quantity,unit_price,tax_rate,tax_amount,subtotal,sort_order) VALUES (?,?,?,?,?,?,?,?,?)",
                   [$invId,$pid,$desc,$qty,$price,$tax,$taxAmt,$sub,$i]);
    }

    recalcInvoice($invId);
    logActivity($currentUser['id'], $editId?'updated_invoice':'created_invoice', 'invoice', $invId);
    flash('success', $editId ? 'Invoice updated.' : 'Invoice created.');
    header('Location: ' . BASE_URL . '/pos/invoice.php?id=' . $invId);
    exit;
}

$clients    = DB::fetchAll("SELECT id, name, company, email FROM users WHERE role='client' AND is_active=1 ORDER BY name");
$products   = DB::fetchAll("SELECT * FROM pos_products WHERE is_active=1 ORDER BY type,name");
$openTickets= DB::fetchAll("SELECT id, ticket_number, subject FROM tickets WHERE status IN ('open','pending') ORDER BY id DESC LIMIT 60");
$defaultTax = DB::setting('pos_tax_rate') ?: 5.00;
$defTerms   = DB::setting('pos_invoice_terms') ?: '';

$pageTitle = $editId ? 'Edit Invoice' : 'New Invoice';
$activeNav = 'pos-invoice-new';
include __DIR__ . '/../includes/header.php';
?>

<style>
.line-items-table { width: 100%; border-collapse: collapse; }
.line-items-table th { background: var(--s2); padding: 8px 10px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--t3); text-align: left; }
.line-items-table td { padding: 6px 6px; vertical-align: middle; border-bottom: 1px solid var(--b1); }
.line-items-table tr:last-child td { border-bottom: none; }
.li-input { background: var(--s2); border: 1px solid var(--b1); border-radius: var(--r-md); padding: 7px 9px; color: var(--t1); font-family: inherit; font-size: 0.85rem; width: 100%; }
.li-input:focus { outline: none; border-color: var(--brand-h); }
</style>

<div class="page-header">
  <div>
    <div class="page-title"><?= $editId ? 'Edit Invoice' : 'New Invoice' ?></div>
    <?php if ($editId): ?><div class="page-subtitle"><?= htmlspecialchars($invoice['invoice_number']) ?></div><?php endif; ?>
  </div>
  <a href="<?= BASE_URL ?>/pos/invoices.php" class="btn btn-outline">← Invoices</a>
</div>

<form method="post" id="invoice-form">
<input type="hidden" name="csrf" value="<?= csrf() ?>">

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

<!-- Left: Line items -->
<div>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <div class="card-title">Line Items</div>
      <button type="button" class="btn btn-outline btn-sm" onclick="addRow()">+ Add Line</button>
    </div>
    <div class="table-wrap">
      <table class="line-items-table">
        <thead>
          <tr>
            <th style="width:36%">Description</th>
            <th style="width:10%">Qty</th>
            <th style="width:14%">Unit Price</th>
            <th style="width:10%">Tax %</th>
            <th style="width:13%">Amount</th>
            <th style="width:5%"></th>
          </tr>
        </thead>
        <tbody id="line-body">
          <?php if (!empty($items)): ?>
          <?php foreach ($items as $idx => $item): ?>
          <tr class="line-row">
            <td>
              <input type="hidden" name="item_pid[]" class="item-pid" value="<?= $item['product_id'] ?>">
              <input type="text" name="item_desc[]" class="li-input item-desc" value="<?= htmlspecialchars($item['description']) ?>" placeholder="Description" required>
            </td>
            <td><input type="number" name="item_qty[]" class="li-input item-qty" value="<?= $item['quantity'] ?>" min="0.001" step="any" onchange="calcRow(this)"></td>
            <td><input type="number" name="item_price[]" class="li-input item-price" value="<?= $item['unit_price'] ?>" min="0" step="0.01" onchange="calcRow(this)"></td>
            <td><input type="number" name="item_tax[]" class="li-input item-tax" value="<?= $item['tax_rate'] ?>" min="0" max="100" step="0.01" onchange="calcRow(this)"></td>
            <td><input type="text" class="li-input item-subtotal" value="<?= number_format($item['subtotal'],2) ?>" readonly style="background:var(--s1)"></td>
            <td><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:var(--t3);cursor:pointer;font-size:16px;padding:4px">✕</button></td>
          </tr>
          <?php endforeach; ?>
          <?php else: ?>
          <!-- Default blank row -->
          <tr class="line-row">
            <td><input type="hidden" name="item_pid[]" class="item-pid" value=""><input type="text" name="item_desc[]" class="li-input item-desc" placeholder="Description" required></td>
            <td><input type="number" name="item_qty[]" class="li-input item-qty" value="1" min="0.001" step="any" onchange="calcRow(this)"></td>
            <td><input type="number" name="item_price[]" class="li-input item-price" value="0" min="0" step="0.01" onchange="calcRow(this)"></td>
            <td><input type="number" name="item_tax[]" class="li-input item-tax" value="<?= $defaultTax ?>" min="0" step="0.01" onchange="calcRow(this)"></td>
            <td><input type="text" class="li-input item-subtotal" value="0.00" readonly style="background:var(--s1)"></td>
            <td><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:var(--t3);cursor:pointer;font-size:16px;padding:4px">✕</button></td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Product quick-add -->
    <div style="padding:10px 0 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <select id="quick-product" class="form-control" style="max-width:260px;padding:6px 10px;font-size:0.85rem">
        <option value="">+ Add from product catalog</option>
        <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['price'] ?>" data-tax="<?= $p['tax_rate'] ?>">
          <?= htmlspecialchars($p['name']) ?> — $<?= number_format($p['price'],2) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="button" class="btn btn-outline btn-sm" onclick="addFromCatalog()">Add</button>
    </div>
  </div>

  <!-- Notes -->
  <div class="card">
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Notes to Client</label>
        <textarea name="notes" class="form-control" rows="3" placeholder="Notes visible on the invoice…"><?= htmlspecialchars($invoice['notes'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Payment Terms</label>
        <textarea name="terms" class="form-control" rows="3" placeholder="e.g. Net 30…"><?= htmlspecialchars($invoice['terms'] ?? $defTerms) ?></textarea>
      </div>
    </div>
  </div>
</div>

<!-- Right: Invoice meta -->
<div style="display:flex;flex-direction:column;gap:14px">

  <div class="card">
    <div class="card-title" style="margin-bottom:14px">Invoice Details</div>

    <div class="form-group">
      <label class="form-label">Client <span>*</span></label>
      <select name="client_id" class="form-control" required>
        <option value="">Select client…</option>
        <?php foreach ($clients as $c): ?>
        <option value="<?= $c['id'] ?>" <?= ($invoice['client_id']??'') == $c['id'] ? 'selected':'' ?>>
          <?= htmlspecialchars($c['name']) ?><?= $c['company'] ? ' ('.$c['company'].')':'' ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Link to Ticket</label>
      <select name="ticket_id" class="form-control">
        <option value="">— None —</option>
        <?php foreach ($openTickets as $t): ?>
        <option value="<?= $t['id'] ?>" <?= ($invoice['ticket_id']??'') == $t['id']?'selected':'' ?>><?= htmlspecialchars($t['ticket_number']) ?> — <?= htmlspecialchars(substr($t['subject'],0,30)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label class="form-label">Status</label>
      <select name="status" class="form-control">
        <?php foreach (['draft','sent','paid','partial','overdue','void'] as $s): ?>
        <option value="<?= $s ?>" <?= ($invoice['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Issue Date</label>
        <input type="date" name="issue_date" class="form-control" value="<?= htmlspecialchars($invoice['issue_date'] ?? date('Y-m-d')) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Due Date</label>
        <input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Discount Type</label>
        <select name="discount_type" class="form-control" onchange="updateInvoiceTotals()">
          <option value="fixed"   <?= ($invoice['discount_type']??'fixed')==='fixed'  ?'selected':'' ?>>Fixed ($)</option>
          <option value="percent" <?= ($invoice['discount_type']??'')==='percent'?'selected':'' ?>>Percent (%)</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Discount Value</label>
        <input type="number" name="discount_value" id="disc-val" class="form-control" min="0" step="0.01"
               value="<?= htmlspecialchars($invoice['discount_value'] ?? 0) ?>" onchange="updateInvoiceTotals()">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Currency</label>
      <select name="currency" class="form-control">
        <?php foreach (['CAD','USD','EUR','GBP'] as $cur): ?>
        <option value="<?= $cur ?>" <?= ($invoice['currency']??'CAD')===$cur?'selected':'' ?>><?= $cur ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Running totals -->
  <div class="card" id="totals-card">
    <div class="card-title" style="margin-bottom:12px">Invoice Total</div>
    <div style="display:flex;flex-direction:column;gap:6px;font-size:0.88rem">
      <div style="display:flex;justify-content:space-between"><span style="color:var(--t3)">Subtotal</span><span id="inv-sub">$0.00</span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:var(--t3)">Tax</span><span id="inv-tax">$0.00</span></div>
      <div style="display:flex;justify-content:space-between"><span style="color:var(--t3)">Discount</span><span id="inv-disc" style="color:var(--ok)">-$0.00</span></div>
      <div style="display:flex;justify-content:space-between;font-size:1.1rem;font-weight:800;border-top:1px solid var(--b1);padding-top:8px;margin-top:4px">
        <span>Total</span><span id="inv-total">$0.00</span>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary w-full" style="justify-content:center;padding:12px">
    <?= $editId ? '💾 Save Changes' : '📄 Create Invoice' ?>
  </button>
  <a href="<?= BASE_URL ?>/pos/invoices.php" class="btn btn-outline w-full" style="justify-content:center">Cancel</a>
</div>
</div>
</form>

<script>
const DEFAULT_TAX = <?= $defaultTax ?>;

function addRow(desc='', qty=1, price=0, tax=DEFAULT_TAX, pid='') {
  const tbody = document.getElementById('line-body');
  const tr    = document.createElement('tr');
  tr.className = 'line-row';
  tr.innerHTML = `
    <td>
      <input type="hidden" name="item_pid[]" class="item-pid" value="${pid}">
      <input type="text" name="item_desc[]" class="li-input item-desc" value="${escHtml(desc)}" placeholder="Description" required>
    </td>
    <td><input type="number" name="item_qty[]" class="li-input item-qty" value="${qty}" min="0.001" step="any" onchange="calcRow(this)"></td>
    <td><input type="number" name="item_price[]" class="li-input item-price" value="${price}" min="0" step="0.01" onchange="calcRow(this)"></td>
    <td><input type="number" name="item_tax[]" class="li-input item-tax" value="${tax}" min="0" step="0.01" onchange="calcRow(this)"></td>
    <td><input type="text" class="li-input item-subtotal" value="0.00" readonly style="background:var(--s1)"></td>
    <td><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:var(--t3);cursor:pointer;font-size:16px;padding:4px">✕</button></td>`;
  tbody.appendChild(tr);
  if (price > 0) calcRow(tr.querySelector('.item-qty'));
  updateInvoiceTotals();
}

function removeRow(btn) {
  btn.closest('.line-row').remove();
  updateInvoiceTotals();
}

function calcRow(input) {
  const row   = input.closest('.line-row');
  const qty   = parseFloat(row.querySelector('.item-qty').value)   || 0;
  const price = parseFloat(row.querySelector('.item-price').value) || 0;
  const sub   = qty * price;
  row.querySelector('.item-subtotal').value = sub.toFixed(2);
  updateInvoiceTotals();
}

function updateInvoiceTotals() {
  let subtotal = 0, taxTotal = 0;
  document.querySelectorAll('.line-row').forEach(row => {
    const qty   = parseFloat(row.querySelector('.item-qty')?.value)   || 0;
    const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
    const tax   = parseFloat(row.querySelector('.item-tax')?.value)   || 0;
    const sub   = qty * price;
    subtotal += sub;
    taxTotal += sub * tax / 100;
  });
  const discType = document.querySelector('[name=discount_type]').value;
  const discVal  = parseFloat(document.getElementById('disc-val').value) || 0;
  const disc     = discType === 'percent' ? subtotal * discVal / 100 : discVal;
  const total    = Math.max(0, subtotal + taxTotal - disc);
  document.getElementById('inv-sub').textContent   = '$' + subtotal.toFixed(2);
  document.getElementById('inv-tax').textContent   = '$' + taxTotal.toFixed(2);
  document.getElementById('inv-disc').textContent  = '-$' + disc.toFixed(2);
  document.getElementById('inv-total').textContent = '$' + total.toFixed(2);
}

function addFromCatalog() {
  const sel = document.getElementById('quick-product');
  const opt = sel.options[sel.selectedIndex];
  if (!opt.value) return;
  addRow(opt.dataset.name, 1, opt.dataset.price, opt.dataset.tax, opt.value);
  sel.value = '';
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Recalc on load
document.querySelectorAll('.item-qty').forEach(el => calcRow(el));
updateInvoiceTotals();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
