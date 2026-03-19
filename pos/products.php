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

$errors = [];

// Handle form posts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf']??'')) { flash('error','Invalid request.'); header('Location: products.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pid   = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name']  ?? '');
        $sku   = trim($_POST['sku']   ?? '');
        $type  = $_POST['type']       ?? 'product';
        $price = (float)($_POST['price'] ?? 0);
        $cost  = (float)($_POST['cost']  ?? 0);
        $tax   = (float)($_POST['tax_rate'] ?? 0);
        $stock = trim($_POST['stock_qty'] ?? '') !== '' ? (int)$_POST['stock_qty'] : null;
        $unit  = trim($_POST['unit']  ?? 'ea');
        $catId = (int)($_POST['category_id'] ?? 0) ?: null;
        $desc  = trim($_POST['description'] ?? '');

        if (!$name) { $errors[] = 'Product name is required.'; }
        else {
            if ($pid) {
                DB::query("UPDATE pos_products SET sku=?,name=?,description=?,category_id=?,type=?,price=?,cost=?,tax_rate=?,stock_qty=?,unit=?,updated_at=NOW() WHERE id=?",
                          [$sku,$name,$desc,$catId,$type,$price,$cost,$tax,$stock,$unit,$pid]);
                flash('success', 'Product updated.');
            } else {
                DB::insert("INSERT INTO pos_products (sku,name,description,category_id,type,price,cost,tax_rate,stock_qty,unit) VALUES (?,?,?,?,?,?,?,?,?,?)",
                           [$sku,$name,$desc,$catId,$type,$price,$cost,$tax,$stock,$unit]);
                flash('success', 'Product added.');
            }
            header('Location: products.php'); exit;
        }
    } elseif ($action === 'toggle') {
        DB::query("UPDATE pos_products SET is_active=1-is_active WHERE id=?", [(int)$_POST['id']]);
        header('Location: products.php'); exit;
    } elseif ($action === 'delete') {
        DB::query("DELETE FROM pos_products WHERE id=?", [(int)$_POST['id']]);
        flash('success','Product deleted.');
        header('Location: products.php'); exit;
    } elseif ($action === 'stock') {
        $pid = (int)$_POST['id'];
        $qty = (int)$_POST['qty'];
        DB::query("UPDATE pos_products SET stock_qty=? WHERE id=?", [$qty,$pid]);
        flash('success','Stock updated.');
        header('Location: products.php'); exit;
    }
}

// Edit?
$editProduct = null;
if (isset($_GET['edit'])) {
    $editProduct = DB::fetch("SELECT * FROM pos_products WHERE id=?", [(int)$_GET['edit']]);
}

$search    = trim($_GET['search'] ?? '');
$typeF     = $_GET['type'] ?? '';
$where     = ['1=1'];
$params    = [];
if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $s='%'.$search.'%'; $params=array_merge($params,[$s,$s]); }
if ($typeF)  { $where[] = 'p.type=?'; $params[] = $typeF; }

$products   = DB::fetchAll("SELECT p.*, c.name cat_name FROM pos_products p LEFT JOIN pos_product_categories c ON p.category_id=c.id WHERE ".implode(' AND ',$where)." ORDER BY p.type,p.name",$params);
$categories = DB::fetchAll("SELECT * FROM pos_product_categories");
$defaultTax = DB::setting('pos_tax_rate') ?: 5.00;

$pageTitle = 'Products & Services';
$activeNav = 'pos-products';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div class="page-title">Products & Services</div>
  <button class="btn btn-primary" onclick="document.getElementById('product-modal').style.display='flex'">➕ Add Product</button>
</div>

<div class="filter-bar">
  <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;width:100%">
    <input type="text" name="search" placeholder="Search name, SKU…" value="<?= htmlspecialchars($search) ?>">
    <select name="type">
      <option value="">All Types</option>
      <option value="product" <?= $typeF==='product'?'selected':'' ?>>Products</option>
      <option value="service" <?= $typeF==='service'?'selected':'' ?>>Services</option>
      <option value="labour"  <?= $typeF==='labour' ?'selected':'' ?>>Labour</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="products.php" class="btn btn-outline btn-sm">Clear</a>
  </form>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div class="card" style="padding:0;overflow:hidden">
<div class="table-wrap">
<table>
  <thead><tr><th>SKU</th><th>Name</th><th>Type</th><th>Category</th><th>Price</th><th>Tax</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
    <?php if (empty($products)): ?>
    <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📦</div><div class="empty-title">No products yet</div><div class="empty-sub">Add your first product or service</div></div></td></tr>
    <?php else: ?>
    <?php foreach ($products as $p): ?>
    <?php $typeIcon = $p['type']==='labour'?'🔧':($p['type']==='service'?'💼':'📦'); ?>
    <tr>
      <td><span class="monospace" style="font-size:0.78rem;color:var(--t3)"><?= htmlspecialchars($p['sku'] ?: '—') ?></span></td>
      <td style="font-weight:500"><?= htmlspecialchars($p['name']) ?></td>
      <td><span class="badge badge-low"><?= $typeIcon ?> <?= ucfirst($p['type']) ?></span></td>
      <td style="font-size:0.82rem;color:var(--t3)"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
      <td style="font-weight:700;color:var(--ok)"><?= moneyRaw($p['price']) ?></td>
      <td style="font-size:0.82rem"><?= $p['tax_rate'] ?>%</td>
      <td>
        <?php if ($p['stock_qty'] === null): ?>
        <span style="color:var(--t3);font-size:0.8rem">∞</span>
        <?php else: ?>
        <form method="post" style="display:inline;display:flex;align-items:center;gap:4px">
          <input type="hidden" name="csrf"   value="<?= csrf() ?>">
          <input type="hidden" name="action" value="stock">
          <input type="hidden" name="id"     value="<?= $p['id'] ?>">
          <input type="number" name="qty" value="<?= $p['stock_qty'] ?>" min="0" style="width:56px;background:var(--s2);border:1px solid var(--b1);border-radius:4px;padding:3px 6px;color:<?= $p['stock_qty']<=3?'var(--err)':'var(--t1)' ?>;font-size:0.82rem;font-family:inherit">
          <button type="submit" class="btn btn-outline btn-xs">↵</button>
        </form>
        <?php endif; ?>
      </td>
      <td><?= $p['is_active'] ? '<span class="status-badge status-resolved">Active</span>' : '<span class="status-badge status-closed">Inactive</span>' ?></td>
      <td>
        <div style="display:flex;gap:4px">
          <a href="?edit=<?= $p['id'] ?>" class="btn btn-outline btn-xs">✏️</a>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-outline btn-xs"><?= $p['is_active']?'🚫':'✅' ?></button>
          </form>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn btn-danger btn-xs" data-confirm="Delete <?= htmlspecialchars($p['name']) ?>?">🗑️</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
</div>
</div>

<!-- Add/Edit product modal -->
<div id="product-modal" style="display:<?= ($editProduct||!empty($errors))?'flex':'none' ?>;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:500;align-items:center;justify-content:center">
  <div class="card" style="width:100%;max-width:560px;max-height:90vh;overflow-y:auto;position:relative">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <div class="card-title"><?= $editProduct ? 'Edit Product' : 'Add Product / Service' ?></div>
      <a href="products.php" style="background:none;border:none;color:var(--t3);font-size:20px;cursor:pointer;text-decoration:none">✕</a>
    </div>
    <form method="post">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id"     value="<?= $editProduct['id'] ?? 0 ?>">

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Name <span>*</span></label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editProduct['name'] ?? ($_POST['name']??'')) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">SKU</label>
          <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($editProduct['sku'] ?? ($_POST['sku']??'')) ?>">
        </div>
      </div>

      <div class="form-row three">
        <div class="form-group">
          <label class="form-label">Type</label>
          <select name="type" class="form-control">
            <?php foreach (['product'=>'📦 Product','service'=>'💼 Service','labour'=>'🔧 Labour'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($editProduct['type']??'product')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control">
            <option value="">— None —</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($editProduct['category_id']??'')==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <input type="text" name="unit" class="form-control" value="<?= htmlspecialchars($editProduct['unit'] ?? 'ea') ?>" placeholder="ea, hr, visit…">
        </div>
      </div>

      <div class="form-row three">
        <div class="form-group">
          <label class="form-label">Sale Price ($) <span>*</span></label>
          <input type="number" name="price" class="form-control" min="0" step="0.01" required value="<?= $editProduct['price'] ?? 0 ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Cost ($)</label>
          <input type="number" name="cost" class="form-control" min="0" step="0.01" value="<?= $editProduct['cost'] ?? 0 ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Tax Rate (%)</label>
          <input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.01" value="<?= $editProduct['tax_rate'] ?? $defaultTax ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Stock Qty <span style="color:var(--t3);font-weight:400">(leave blank for unlimited/services)</span></label>
        <input type="number" name="stock_qty" class="form-control" min="0" value="<?= isset($editProduct['stock_qty']) && $editProduct['stock_qty'] !== null ? $editProduct['stock_qty'] : '' ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary"><?= $editProduct ? '💾 Save Changes' : 'Add Product' ?></button>
        <a href="products.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
