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

// Handle sale submission (AJAX or POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_sale') {
    header('Content-Type: application/json');
    if (!verifyCsrf($_POST['csrf'] ?? '')) { echo json_encode(['error'=>'Invalid CSRF']); exit; }

    $items    = json_decode($_POST['items'] ?? '[]', true);
    $clientId = (int)($_POST['client_id'] ?? 0) ?: null;
    $ticketId = (int)($_POST['ticket_id'] ?? 0) ?: null;
    $method   = $_POST['payment_method'] ?? 'cash';
    $tendered = (float)($_POST['tendered'] ?? 0);
    $note     = trim($_POST['note'] ?? '');
    $discount = (float)($_POST['discount'] ?? 0);

    if (empty($items)) { echo json_encode(['error'=>'No items in cart']); exit; }

    // Calculate totals
    $subtotal = 0; $taxTotal = 0;
    foreach ($items as &$item) {
        $sub      = round($item['qty'] * $item['price'], 2);
        $tax      = round($sub * ($item['tax_rate'] / 100), 2);
        $item['subtotal']   = $sub;
        $item['tax_amount'] = $tax;
        $subtotal += $sub;
        $taxTotal += $tax;
    }
    $total     = max(0, $subtotal + $taxTotal - $discount);
    $changeDue = max(0, $tendered - $total);
    $saleNum   = generateSaleNumber();

    $saleId = DB::insert(
        "INSERT INTO pos_sales (sale_number,client_id,ticket_id,subtotal,tax_total,discount,total,tendered,change_due,payment_method,note,served_by,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
        [$saleNum,$clientId,$ticketId,$subtotal,$taxTotal,$discount,$total,$tendered,$changeDue,$method,$note,$currentUser['id']]
    );

    foreach ($items as $item) {
        $pid = (int)($item['product_id'] ?? 0) ?: null;
        DB::insert(
            "INSERT INTO pos_sale_items (sale_id,product_id,description,quantity,unit_price,tax_rate,tax_amount,subtotal)
             VALUES (?,?,?,?,?,?,?,?)",
            [$saleId,$pid,$item['name'],$item['qty'],$item['price'],$item['tax_rate'],$item['tax_amount'],$item['subtotal']]
        );
        if ($pid) deductStock($pid, $item['qty']);
    }

    logActivity($currentUser['id'], 'pos_sale', 'sale', $saleId, "Sale $saleNum — " . moneyRaw($total));

    echo json_encode([
        'success'    => true,
        'sale_id'    => $saleId,
        'sale_number'=> $saleNum,
        'total'      => $total,
        'change'     => $changeDue,
        'receipt_url'=> BASE_URL . '/pos/sale-view.php?id=' . $saleId . '&print=1',
    ]);
    exit;
}

// Load products for the grid
$products = DB::fetchAll(
    "SELECT p.*, c.name as cat_name, c.color as cat_color
     FROM pos_products p
     LEFT JOIN pos_product_categories c ON p.category_id = c.id
     WHERE p.is_active = 1
     ORDER BY p.type, p.name"
);

$categories = DB::fetchAll("SELECT * FROM pos_product_categories");
$clients    = DB::fetchAll("SELECT id, name, company FROM users WHERE role='client' AND is_active=1 ORDER BY name");
$openTickets= DB::fetchAll("SELECT id, ticket_number, subject FROM tickets WHERE status IN ('open','pending') ORDER BY ticket_number DESC LIMIT 50");

$defaultTax = DB::setting('pos_tax_rate') ?: 5.00;
$taxName    = DB::setting('pos_tax_name') ?: 'GST';

$pageTitle = 'Quick Sale — POS';
$activeNav = 'pos-sale';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* POS Terminal Styles */
.pos-layout { display: grid; grid-template-columns: 1fr 360px; gap: 0; height: calc(100vh - 110px); min-height: 600px; border: 1px solid var(--b1); border-radius: var(--r-lg); overflow: hidden; }
.pos-left { display: flex; flex-direction: column; background: var(--s1); overflow: hidden; }
.pos-right { background: var(--s0); border-left: 1px solid var(--b1); display: flex; flex-direction: column; }

/* Product search bar */
.pos-search-bar { padding: 12px 16px; border-bottom: 1px solid var(--b1); display: flex; gap: 10px; align-items: center; }
.pos-search { flex: 1; background: var(--s2); border: 1px solid var(--b1); border-radius: var(--r-md); padding: 8px 12px; color: var(--t1); font-family: inherit; font-size: 0.9rem; }
.pos-search:focus { outline: none; border-color: var(--brand-h); }

/* Category filters */
.pos-cats { padding: 8px 14px; border-bottom: 1px solid var(--b1); display: flex; gap: 6px; flex-wrap: wrap; }
.cat-pill { background: var(--s2); border: 1px solid var(--b1); border-radius: 20px; padding: 3px 12px; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: var(--base); color: var(--t2); white-space: nowrap; }
.cat-pill:hover, .cat-pill.active { background: var(--brand-h); border-color: var(--brand-h); color: white; }

/* Product grid */
.pos-grid { flex: 1; overflow-y: auto; padding: 12px; display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; align-content: start; }
.product-tile {
  background: var(--s2); border: 1px solid var(--b1); border-radius: var(--r-lg);
  padding: 14px 10px; text-align: center; cursor: pointer; transition: var(--base);
  display: flex; flex-direction: column; gap: 6px; align-items: center;
}
.product-tile:hover { border-color: var(--brand-h); background: var(--brand-dim); transform: translateY(-1px); }
.product-tile.out-of-stock { opacity: 0.45; pointer-events: none; }
.product-icon { font-size: 1.8rem; line-height: 1; }
.product-name { font-size: 0.8rem; font-weight: 600; color: var(--t1); line-height: 1.3; }
.product-price { font-size: 0.88rem; font-weight: 700; color: var(--ok); }
.product-stock { font-size: 0.7rem; color: var(--t3); }
.product-type-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 10px; font-weight: 700; text-transform: uppercase; }

/* Cart */
.cart-header { padding: 14px 16px; border-bottom: 1px solid var(--b1); display: flex; align-items: center; justify-content: space-between; }
.cart-title { font-weight: 700; font-size: 0.95rem; }
.cart-body { flex: 1; overflow-y: auto; }
.cart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--t3); font-size: 0.88rem; gap: 8px; }
.cart-item { padding: 10px 14px; border-bottom: 1px solid var(--b1); display: flex; align-items: center; gap: 8px; }
.cart-item-name { flex: 1; font-size: 0.85rem; font-weight: 500; color: var(--t1); }
.cart-qty { display: flex; align-items: center; gap: 4px; }
.qty-btn { width: 22px; height: 22px; background: var(--s2); border: 1px solid var(--b1); border-radius: 4px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; color: var(--t1); transition: var(--base); padding: 0; }
.qty-btn:hover { background: var(--brand-h); border-color: var(--brand-h); color: white; }
.qty-num { width: 28px; text-align: center; font-size: 0.85rem; font-weight: 700; }
.cart-line-total { font-size: 0.85rem; font-weight: 700; color: var(--t1); width: 64px; text-align: right; }
.cart-remove { color: var(--t3); cursor: pointer; font-size: 14px; background: none; border: none; padding: 2px 4px; }
.cart-remove:hover { color: var(--err); }

/* Cart footer */
.cart-footer { border-top: 1px solid var(--b1); padding: 14px; }
.cart-totals { margin-bottom: 12px; }
.total-row { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; }
.total-row.grand { font-size: 1rem; font-weight: 700; color: var(--t1); border-top: 1px solid var(--b1); padding-top: 8px; margin-top: 4px; }
.total-label { color: var(--t3); }

.pay-section { display: flex; flex-direction: column; gap: 8px; }
.pay-methods { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }
.pay-method-btn { background: var(--s2); border: 1px solid var(--b1); border-radius: var(--r-md); padding: 7px 4px; font-size: 0.74rem; font-weight: 600; cursor: pointer; transition: var(--base); text-align: center; color: var(--t2); }
.pay-method-btn:hover { border-color: var(--brand-h); color: var(--brand-h); }
.pay-method-btn.selected { background: var(--brand-h); border-color: var(--brand-h); color: white; }

.charge-btn { background: var(--ok); color: white; border: none; border-radius: var(--r-md); padding: 13px; font-family: inherit; font-size: 1rem; font-weight: 700; cursor: pointer; width: 100%; transition: var(--base); }
.charge-btn:hover { background: #35a247; }
.charge-btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Receipt modal */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 600; display: flex; align-items: center; justify-content: center; }
.modal-box { background: var(--s1); border: 1px solid var(--b1); border-radius: var(--r-lg); width: 100%; max-width: 400px; overflow: hidden; }
.modal-header { padding: 16px 20px; border-bottom: 1px solid var(--b1); display: flex; align-items: center; justify-content: space-between; }
.modal-body { padding: 20px; }
.receipt-line { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 6px; }
.receipt-total { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 700; border-top: 1px solid var(--b1); padding-top: 10px; margin-top: 6px; }
.change-display { background: var(--ok-dim); border: 1px solid var(--ok); border-radius: var(--r-md); padding: 12px; text-align: center; margin: 12px 0; }
.change-amount { font-size: 1.8rem; font-weight: 800; color: var(--ok); }
</style>

<div class="page-header" style="margin-bottom:14px">
  <div class="page-title">🛒 POS Terminal</div>
  <div style="display:flex;gap:8px">
    <a href="<?= BASE_URL ?>/pos/sales.php" class="btn btn-outline btn-sm">Sales History</a>
    <a href="<?= BASE_URL ?>/pos/invoice-new.php" class="btn btn-outline btn-sm">New Invoice</a>
  </div>
</div>

<!-- Optional: link to ticket -->
<div style="display:flex;gap:12px;margin-bottom:14px;align-items:center;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:8px;font-size:0.85rem">
    <label style="color:var(--t3);font-weight:600;white-space:nowrap">Client:</label>
    <select id="client-select" class="form-control" style="min-width:200px;padding:6px 10px">
      <option value="">Walk-in / Anonymous</option>
      <?php foreach ($clients as $c): ?>
      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?><?= $c['company'] ? ' ('.$c['company'].')' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="display:flex;align-items:center;gap:8px;font-size:0.85rem">
    <label style="color:var(--t3);font-weight:600;white-space:nowrap">Link Ticket:</label>
    <select id="ticket-select" class="form-control" style="min-width:220px;padding:6px 10px">
      <option value="">— None —</option>
      <?php foreach ($openTickets as $t): ?>
      <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['ticket_number']) ?> — <?= htmlspecialchars(substr($t['subject'],0,35)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="pos-layout">
  <!-- LEFT: Product Grid -->
  <div class="pos-left">
    <div class="pos-search-bar">
      <input type="text" class="pos-search" id="product-search" placeholder="🔍 Search products & services…" autocomplete="off">
    </div>
    <div class="pos-cats" id="cat-bar">
      <div class="cat-pill active" data-cat="">All</div>
      <div class="cat-pill" data-cat="product">Products</div>
      <div class="cat-pill" data-cat="service">Services</div>
      <div class="cat-pill" data-cat="labour">Labour</div>
      <?php foreach ($categories as $cat): ?>
      <div class="cat-pill" data-cat-id="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="pos-grid" id="product-grid">
      <?php foreach ($products as $p):
        $typeIcon = $p['type'] === 'labour' ? '🔧' : ($p['type'] === 'service' ? '💼' : '📦');
        $typeColor = $p['type'] === 'labour' ? 'var(--ok)' : ($p['type'] === 'service' ? 'var(--purple)' : 'var(--brand-h)');
        $outOfStock = $p['stock_qty'] !== null && $p['stock_qty'] <= 0;
      ?>
      <div class="product-tile <?= $outOfStock ? 'out-of-stock' : '' ?>"
           data-id="<?= $p['id'] ?>"
           data-name="<?= htmlspecialchars($p['name']) ?>"
           data-price="<?= $p['price'] ?>"
           data-tax="<?= $p['tax_rate'] ?>"
           data-type="<?= $p['type'] ?>"
           data-cat-id="<?= $p['category_id'] ?>"
           data-sku="<?= htmlspecialchars($p['sku'] ?? '') ?>"
           onclick="addToCart(this)">
        <div class="product-icon"><?= $typeIcon ?></div>
        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="product-price">$<?= number_format($p['price'], 2) ?></div>
        <?php if ($p['stock_qty'] !== null): ?>
        <div class="product-stock"><?= $outOfStock ? '❌ Out of stock' : $p['stock_qty'].' in stock' ?></div>
        <?php endif; ?>
        <span class="product-type-badge" style="background:<?= $typeColor ?>22;color:<?= $typeColor ?>"><?= $p['unit'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-right">
    <div class="cart-header">
      <span class="cart-title">🛒 Cart</span>
      <button onclick="clearCart()" class="btn btn-outline btn-xs" id="clear-btn" style="display:none">Clear</button>
    </div>

    <div class="cart-body" id="cart-body">
      <div class="cart-empty" id="cart-empty">
        <div style="font-size:2rem">🛒</div>
        <div>Cart is empty</div>
        <div style="font-size:0.78rem">Click products to add them</div>
      </div>
      <div id="cart-items"></div>
    </div>

    <div class="cart-footer">
      <!-- Discount -->
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
        <label style="font-size:0.78rem;color:var(--t3);font-weight:600;white-space:nowrap">Discount $:</label>
        <input type="number" id="discount-input" min="0" step="0.01" value="0" class="form-control" style="padding:5px 8px;font-size:0.85rem" onchange="updateTotals()">
      </div>

      <!-- Totals -->
      <div class="cart-totals" id="cart-totals">
        <div class="total-row"><span class="total-label">Subtotal</span><span id="t-sub">$0.00</span></div>
        <div class="total-row"><span class="total-label"><?= $taxName ?> (<?= $defaultTax ?>%)</span><span id="t-tax">$0.00</span></div>
        <div class="total-row"><span class="total-label">Discount</span><span id="t-disc" style="color:var(--ok)">-$0.00</span></div>
        <div class="total-row grand"><span>TOTAL</span><span id="t-total">$0.00</span></div>
      </div>

      <!-- Cash tendered (shown for cash) -->
      <div id="cash-section" style="margin-bottom:10px">
        <div style="display:flex;align-items:center;gap:8px">
          <label style="font-size:0.78rem;color:var(--t3);font-weight:600;white-space:nowrap">Tendered:</label>
          <input type="number" id="tendered-input" min="0" step="0.01" value="0" class="form-control" style="padding:5px 8px;font-size:0.85rem" onchange="calcChange()">
          <span id="change-label" style="font-size:0.85rem;font-weight:700;color:var(--ok);white-space:nowrap">Change: $0.00</span>
        </div>
      </div>

      <!-- Payment method -->
      <div class="pay-section">
        <div class="pay-methods" id="pay-methods">
          <button class="pay-method-btn selected" data-method="cash"     onclick="selectMethod(this)">💵 Cash</button>
          <button class="pay-method-btn"           data-method="debit"    onclick="selectMethod(this)">💳 Debit</button>
          <button class="pay-method-btn"           data-method="credit_card" onclick="selectMethod(this)">💳 Credit</button>
          <button class="pay-method-btn"           data-method="etransfer" onclick="selectMethod(this)">📱 e-Transfer</button>
          <button class="pay-method-btn"           data-method="cheque"   onclick="selectMethod(this)">📝 Cheque</button>
          <button class="pay-method-btn"           data-method="other"    onclick="selectMethod(this)">⋯ Other</button>
        </div>
        <button class="charge-btn" id="charge-btn" disabled onclick="completeSale()">
          Charge $0.00
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Sale Complete Modal -->
<div class="modal-backdrop" id="receipt-modal" style="display:none">
  <div class="modal-box">
    <div class="modal-header">
      <strong>✅ Sale Complete!</strong>
      <button onclick="closeReceipt()" style="background:none;border:none;color:var(--t3);font-size:20px;cursor:pointer">✕</button>
    </div>
    <div class="modal-body">
      <div id="receipt-content"></div>
      <div style="display:flex;gap:8px;margin-top:16px">
        <a id="receipt-link" href="#" target="_blank" class="btn btn-outline btn-sm" style="flex:1;justify-content:center">🖨 Print Receipt</a>
        <button onclick="newSale()" class="btn btn-primary btn-sm" style="flex:1;justify-content:center">🛒 New Sale</button>
      </div>
    </div>
  </div>
</div>

<script>
const cart = {};
const CSRF = '<?= csrf() ?>';
const BASE = '<?= BASE_URL ?>';

const productTiles = document.querySelectorAll('.product-tile');
const cartItems    = document.getElementById('cart-items');
const cartEmpty    = document.getElementById('cart-empty');
const clearBtn     = document.getElementById('clear-btn');
const chargeBtn    = document.getElementById('charge-btn');

// ─── Category filter ────────────────────────────────────────
document.querySelectorAll('.cat-pill').forEach(pill => {
  pill.addEventListener('click', () => {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    const type  = pill.dataset.cat || '';
    const catId = pill.dataset.catId || '';
    filterProducts(type, catId, document.getElementById('product-search').value);
  });
});

document.getElementById('product-search').addEventListener('input', e => {
  const active = document.querySelector('.cat-pill.active');
  filterProducts(active?.dataset.cat || '', active?.dataset.catId || '', e.target.value);
});

function filterProducts(type, catId, query) {
  productTiles.forEach(tile => {
    const matchType  = !type   || tile.dataset.type === type;
    const matchCat   = !catId  || tile.dataset.catId === catId;
    const q = query.toLowerCase();
    const matchQuery = !q || tile.dataset.name.toLowerCase().includes(q) || (tile.dataset.sku||'').toLowerCase().includes(q);
    tile.style.display = (matchType && matchCat && matchQuery) ? '' : 'none';
  });
}

// ─── Cart logic ─────────────────────────────────────────────
function addToCart(tile) {
  const id = tile.dataset.id;
  if (cart[id]) {
    cart[id].qty += 1;
  } else {
    cart[id] = {
      id:         id,
      product_id: id,
      name:       tile.dataset.name,
      price:      parseFloat(tile.dataset.price),
      tax_rate:   parseFloat(tile.dataset.tax),
      qty:        1,
    };
  }
  renderCart();
}

function addCustomItem() {
  const name  = prompt('Item description:');
  if (!name) return;
  const price = parseFloat(prompt('Unit price:') || '0');
  if (isNaN(price) || price < 0) return;
  const key = 'custom_' + Date.now();
  cart[key] = { id: key, product_id: null, name, price, tax_rate: <?= $defaultTax ?>, qty: 1 };
  renderCart();
}

function changeQty(id, delta) {
  if (!cart[id]) return;
  cart[id].qty += delta;
  if (cart[id].qty <= 0) delete cart[id];
  renderCart();
}

function removeFromCart(id) {
  delete cart[id];
  renderCart();
}

function clearCart() {
  Object.keys(cart).forEach(k => delete cart[k]);
  document.getElementById('discount-input').value = 0;
  renderCart();
}

function renderCart() {
  const keys = Object.keys(cart);
  cartEmpty.style.display = keys.length === 0 ? 'flex' : 'none';
  clearBtn.style.display  = keys.length > 0 ? '' : 'none';

  cartItems.innerHTML = keys.map(k => {
    const item = cart[k];
    const lineTotal = (item.qty * item.price).toFixed(2);
    return `<div class="cart-item">
      <div class="cart-item-name">${escHtml(item.name)}</div>
      <div class="cart-qty">
        <button class="qty-btn" onclick="changeQty('${k}',-1)">−</button>
        <span class="qty-num">${item.qty}</span>
        <button class="qty-btn" onclick="changeQty('${k}',1)">+</button>
      </div>
      <div class="cart-line-total">$${lineTotal}</div>
      <button class="cart-remove" onclick="removeFromCart('${k}')">✕</button>
    </div>`;
  }).join('');

  updateTotals();
}

function updateTotals() {
  let subtotal = 0, taxTotal = 0;
  Object.values(cart).forEach(item => {
    const sub = item.qty * item.price;
    subtotal += sub;
    taxTotal += sub * item.tax_rate / 100;
  });
  const discount = Math.max(0, parseFloat(document.getElementById('discount-input').value) || 0);
  const total    = Math.max(0, subtotal + taxTotal - discount);

  document.getElementById('t-sub').textContent   = '$' + subtotal.toFixed(2);
  document.getElementById('t-tax').textContent   = '$' + taxTotal.toFixed(2);
  document.getElementById('t-disc').textContent  = '-$' + discount.toFixed(2);
  document.getElementById('t-total').textContent = '$' + total.toFixed(2);

  chargeBtn.disabled     = Object.keys(cart).length === 0;
  chargeBtn.textContent  = 'Charge $' + total.toFixed(2);

  calcChange();
}

function calcChange() {
  const totalText = document.getElementById('t-total').textContent.replace('$','');
  const total     = parseFloat(totalText) || 0;
  const tendered  = parseFloat(document.getElementById('tendered-input').value) || 0;
  const change    = Math.max(0, tendered - total);
  document.getElementById('change-label').textContent = 'Change: $' + change.toFixed(2);
}

function selectMethod(btn) {
  document.querySelectorAll('.pay-method-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  const isCash = btn.dataset.method === 'cash';
  document.getElementById('cash-section').style.display = isCash ? '' : 'none';
}

// ─── Complete sale ───────────────────────────────────────────
async function completeSale() {
  if (Object.keys(cart).length === 0) return;
  chargeBtn.disabled = true;
  chargeBtn.textContent = 'Processing…';

  const method   = document.querySelector('.pay-method-btn.selected')?.dataset.method || 'cash';
  const tendered = parseFloat(document.getElementById('tendered-input').value) || 0;
  const discount = parseFloat(document.getElementById('discount-input').value) || 0;
  const clientId = document.getElementById('client-select').value;
  const ticketId = document.getElementById('ticket-select').value;

  const items = Object.values(cart).map(i => ({
    product_id: i.product_id,
    name:       i.name,
    qty:        i.qty,
    price:      i.price,
    tax_rate:   i.tax_rate,
  }));

  const fd = new FormData();
  fd.append('action', 'complete_sale');
  fd.append('csrf', CSRF);
  fd.append('items', JSON.stringify(items));
  fd.append('client_id', clientId);
  fd.append('ticket_id', ticketId);
  fd.append('payment_method', method);
  fd.append('tendered', tendered);
  fd.append('discount', discount);

  try {
    const res  = await fetch(window.location.href, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showReceipt(data);
    } else {
      alert('Error: ' + (data.error || 'Unknown error'));
      chargeBtn.disabled = false;
      chargeBtn.textContent = 'Charge $' + document.getElementById('t-total').textContent.replace('$','');
    }
  } catch (e) {
    alert('Network error. Please try again.');
    chargeBtn.disabled = false;
  }
}

function showReceipt(data) {
  const changeHtml = data.change > 0
    ? `<div class="change-display"><div style="font-size:0.8rem;color:var(--t3);margin-bottom:4px">CHANGE DUE</div><div class="change-amount">$${data.change.toFixed(2)}</div></div>`
    : '';
  document.getElementById('receipt-content').innerHTML = `
    <div style="text-align:center;margin-bottom:14px">
      <div style="font-size:1.8rem">✅</div>
      <div style="font-weight:700;font-size:1.1rem;margin-top:4px">Sale Complete!</div>
      <div class="ticket-num" style="margin-top:6px">${data.sale_number}</div>
    </div>
    ${changeHtml}
    <div class="receipt-total"><span>Total Charged</span><span>$${parseFloat(data.total).toFixed(2)}</span></div>
  `;
  document.getElementById('receipt-link').href = data.receipt_url;
  document.getElementById('receipt-modal').style.display = 'flex';
}

function closeReceipt() {
  document.getElementById('receipt-modal').style.display = 'none';
  updateTotals();
}

function newSale() {
  clearCart();
  document.getElementById('client-select').value = '';
  document.getElementById('ticket-select').value = '';
  document.getElementById('receipt-modal').style.display = 'none';
  chargeBtn.disabled = true;
  chargeBtn.textContent = 'Charge $0.00';
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
renderCart();
</script>

<!-- Custom item button floating -->
<div style="position:fixed;bottom:24px;right:24px;z-index:50">
  <button onclick="addCustomItem()" class="btn btn-outline" style="border-radius:50%;width:48px;height:48px;padding:0;font-size:1.4rem;box-shadow:var(--sh-md)" title="Add custom item">✏️</button>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
