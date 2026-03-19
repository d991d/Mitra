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

$errors  = [];

// ── Handle logo upload ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_logo') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error', 'Invalid request.'); header('Location: branding.php'); exit; }

    if (!empty($_FILES['logo']['name'])) {
        $allowed = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif'];
        $mime    = mime_content_type($_FILES['logo']['tmp_name']);

        if (!in_array($mime, $allowed)) {
            $errors[] = 'Logo must be PNG, JPG, SVG, WEBP, or GIF.';
        } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Logo must be under 2MB.';
        } else {
            $logoDir = __DIR__ . '/../uploads/branding/';
            if (!is_dir($logoDir)) mkdir($logoDir, 0755, true);

            // Remove old logo
            $oldLogo = DB::setting('branding_logo');
            if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
                @unlink(__DIR__ . '/../' . $oldLogo);
            }

            $ext     = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $stored  = 'logo_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
            $dest    = $logoDir . $stored;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                $logoPath = 'uploads/branding/' . $stored;
                DB::query("UPDATE settings SET setting_value=? WHERE setting_key='branding_logo'", [$logoPath]);
                flash('success', 'Logo uploaded successfully.');
            } else {
                $errors[] = 'Upload failed — check folder permissions.';
            }
        }
    }
    if (empty($errors)) { header('Location: branding.php'); exit; }
}

// ── Handle logo delete ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_logo') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error', 'Invalid request.'); header('Location: branding.php'); exit; }
    $oldLogo = DB::setting('branding_logo');
    if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) @unlink(__DIR__ . '/../' . $oldLogo);
    DB::query("UPDATE settings SET setting_value='' WHERE setting_key='branding_logo'");
    flash('success', 'Logo removed.');
    header('Location: branding.php'); exit;
}

// ── Handle settings save ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error', 'Invalid request.'); header('Location: branding.php'); exit; }

    $keys = [
        'branding_logo_width', 'branding_primary_color', 'branding_accent_color',
        'branding_font', 'invoice_show_logo', 'invoice_show_tagline', 'invoice_tagline',
        'invoice_color_scheme', 'invoice_header_bg', 'invoice_header_text', 'invoice_accent',
        'quote_prefix', 'quote_validity_days', 'quote_default_notes',
        'company_name', 'company_email', 'company_phone', 'company_address',
    ];
    foreach ($keys as $k) {
        $val      = trim($_POST[$k] ?? '');
        $existing = DB::fetch("SELECT id FROM settings WHERE setting_key=?", [$k]);
        if ($existing) DB::query("UPDATE settings SET setting_value=? WHERE setting_key=?", [$val, $k]);
        else           DB::insert("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)", [$k, $val]);
    }
    flash('success', 'Branding settings saved.');
    header('Location: branding.php'); exit;
}

// ── Load settings ───────────────────────────────────────────
$settings = [];
$rows = DB::fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

$logoPath   = $settings['branding_logo']   ?? '';
$logoExists = $logoPath && file_exists(__DIR__ . '/../' . $logoPath);
$logoUrl    = $logoExists ? BASE_URL . '/' . $logoPath : '';

$pageTitle = 'Branding & Document Design';
$activeNav = 'branding';
include __DIR__ . '/../includes/header.php';

// Live preview colors
$prevBg     = $settings['invoice_header_bg']   ?? '#1a1a2e';
$prevText   = $settings['invoice_header_text'] ?? '#ffffff';
$prevAccent = $settings['invoice_accent']      ?? '#2f81f7';
$scheme     = $settings['invoice_color_scheme'] ?? 'dark';
?>

<style>
.design-grid   { display: grid; grid-template-columns: 1fr 420px; gap: 22px; align-items: start; }
.color-swatch  { display: flex; align-items: center; gap: 10px; }
.color-swatch input[type=color] { width: 44px; height: 36px; border-radius: 6px; border: 1px solid var(--b1); cursor: pointer; padding: 2px 3px; background: var(--s2); }
.color-swatch input[type=text]  { flex: 1; font-family: monospace; }
.preview-wrap  { position: sticky; top: 70px; }
.scheme-btn    { background: var(--s2); border: 1px solid var(--b1); border-radius: var(--r-md); padding: 10px 14px; cursor: pointer; font-family: inherit; font-size: 0.82rem; font-weight: 600; color: var(--t2); transition: var(--base); text-align: center; }
.scheme-btn:hover  { border-color: var(--brand-h); color: var(--brand-h); }
.scheme-btn.active { background: var(--brand-dim); border-color: var(--brand-h); color: var(--brand-h); }
.logo-dropzone { border: 2px dashed var(--b2); border-radius: var(--r-lg); padding: 28px; text-align: center; cursor: pointer; transition: var(--base); position: relative; }
.logo-dropzone:hover, .logo-dropzone.dragover { border-color: var(--brand-h); background: var(--brand-dim); }
.logo-dropzone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.font-option   { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.font-btn      { background: var(--s2); border: 1px solid var(--b1); border-radius: var(--r-md); padding: 10px; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: var(--base); text-align: center; color: var(--t2); }
.font-btn:hover  { border-color: var(--brand-h); }
.font-btn.active { background: var(--brand-dim); border-color: var(--brand-h); color: var(--brand-h); }

/* ── Invoice preview ── */
.inv-preview { background: white; border-radius: var(--r-lg); overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.4); font-family: var(--prev-font, 'DM Sans'), sans-serif; color: #1a1a2e; font-size: 12px; }
.inv-prev-head { padding: 18px 20px; display: flex; justify-content: space-between; align-items: center; }
.inv-prev-logo { font-size: 1rem; font-weight: 800; }
.inv-prev-badge { font-size: 1.1rem; font-weight: 900; letter-spacing: -0.02em; }
.inv-prev-meta { padding: 10px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #f8f9fc; font-size: 11px; }
.inv-prev-meta-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #999; margin-bottom: 2px; }
.inv-prev-meta-val { font-weight: 600; color: #333; }
.inv-prev-table { width: 100%; border-collapse: collapse; }
.inv-prev-table th { padding: 7px 12px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; text-align: left; }
.inv-prev-table td { padding: 7px 12px; font-size: 11px; border-bottom: 1px solid #f0f0f0; }
.inv-prev-total { padding: 12px 20px; text-align: right; font-size: 11px; }
.inv-prev-total .grand { font-size: 1rem; font-weight: 800; margin-top: 4px; }
.inv-prev-footer { padding: 10px 20px; border-top: 1px solid #f0f0f0; font-size: 10px; color: #999; text-align: center; }
</style>

<div class="page-header">
  <div>
    <div class="page-title">🎨 Branding & Document Design</div>
    <div class="page-subtitle">Customize your logo, colors, and invoice/quote layout</div>
  </div>
  <a href="<?= BASE_URL ?>/pos/invoices.php" class="btn btn-outline">← Invoices</a>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-error"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

<div class="design-grid">

<!-- ── LEFT: Settings ── -->
<div style="display:flex;flex-direction:column;gap:16px">

  <!-- Logo upload -->
  <div class="card">
    <div class="card-title" style="margin-bottom:16px">🖼️ Company Logo</div>

    <?php if ($logoExists): ?>
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;padding:14px;background:var(--s2);border-radius:var(--r-md);border:1px solid var(--b1)">
      <img src="<?= htmlspecialchars($logoUrl) ?>" style="max-height:60px;max-width:180px;object-fit:contain;" id="logo-preview" alt="Logo">
      <div>
        <div style="font-size:0.82rem;font-weight:600;color:var(--t1);margin-bottom:4px">Current Logo</div>
        <div style="font-size:0.75rem;color:var(--t3)"><?= basename($logoPath) ?></div>
        <form method="post" style="display:inline;margin-top:8px">
          <input type="hidden" name="csrf"   value="<?= csrf() ?>">
          <input type="hidden" name="action" value="delete_logo">
          <button type="submit" class="btn btn-danger btn-xs" data-confirm="Remove logo?" style="margin-top:6px">🗑️ Remove</button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div style="background:var(--s2);border:1px dashed var(--b2);border-radius:var(--r-md);padding:14px;margin-bottom:14px;text-align:center;color:var(--t3);font-size:0.85rem">
      No logo uploaded yet
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="upload_logo">

      <div class="logo-dropzone" id="logo-drop" ondragover="this.classList.add('dragover')" ondragleave="this.classList.remove('dragover')">
        <input type="file" name="logo" accept="image/*" onchange="previewLogo(this)">
        <div style="font-size:2rem;margin-bottom:8px">📁</div>
        <div style="font-weight:600;color:var(--t1);margin-bottom:4px">Click or drag to upload logo</div>
        <div style="font-size:0.78rem;color:var(--t3)">PNG, JPG, SVG, WEBP — max 2MB</div>
        <div id="logo-drop-name" style="font-size:0.82rem;color:var(--brand-h);margin-top:8px;display:none"></div>
      </div>

      <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px">⬆️ Upload Logo</button>
    </form>
  </div>

  <!-- Company info (for document headers) -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px">🏢 Company Information</div>
    <form method="post" id="main-form">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="save">

      <div class="form-group">
        <label class="form-label">Company Name</label>
        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? 'Mitra') ?>" oninput="updatePreview()">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="tel" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Address</label>
        <input type="text" name="company_address" class="form-control" value="<?= htmlspecialchars($settings['company_address'] ?? '') ?>" placeholder="Street, City, Province/State">
      </div>
      <div class="form-group">
        <label class="form-label">Tagline <span style="color:var(--t3);font-weight:400">(shown on documents)</span></label>
        <input type="text" name="invoice_tagline" class="form-control" value="<?= htmlspecialchars($settings['invoice_tagline'] ?? '') ?>" placeholder="e.g. Professional IT Services" oninput="updatePreview()">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="hidden" name="invoice_show_logo"    value="0">
          <input type="checkbox" name="invoice_show_logo"    value="1" <?= ($settings['invoice_show_logo']    ?? '1') === '1' ? 'checked' : '' ?> onchange="updatePreview()">
          <span class="form-label" style="margin:0">Show logo on documents</span>
        </label>
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="hidden" name="invoice_show_tagline" value="0">
          <input type="checkbox" name="invoice_show_tagline" value="1" <?= ($settings['invoice_show_tagline'] ?? '1') === '1' ? 'checked' : '' ?> onchange="updatePreview()">
          <span class="form-label" style="margin:0">Show tagline on documents</span>
        </label>
      </div>
    </form>
  </div>

  <!-- Color & font -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px">🎨 Document Color Scheme</div>

    <div class="form-group">
      <label class="form-label">Quick Presets</label>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px">
        <?php
        $presets = [
          'dark'       => ['Dark Navy',    '#1a1a2e', '#ffffff', '#2f81f7'],
          'midnight'   => ['Midnight',     '#0d1117', '#e6edf3', '#3fb950'],
          'slate'      => ['Slate Blue',   '#1e3a5f', '#ffffff', '#60a5fa'],
          'forest'     => ['Forest',       '#1a2e1a', '#ffffff', '#3fb950'],
          'burgundy'   => ['Burgundy',     '#2e1a1a', '#ffffff', '#f0883e'],
          'clean'      => ['Clean White',  '#ffffff', '#1a1a2e', '#2f81f7'],
          'minimal'    => ['Minimal Grey', '#f8f9fc', '#1a1a2e', '#64748b'],
          'gold'       => ['Executive',    '#1a1600', '#ffffff', '#d4a017'],
          'teal'       => ['Teal',         '#0f2830', '#ffffff', '#14b8a6'],
        ];
        foreach ($presets as $key => [$label, $bg, $txt, $acc]):
        ?>
        <button type="button" class="scheme-btn <?= $scheme===$key?'active':'' ?>"
                data-scheme="<?= $key ?>" data-bg="<?= $bg ?>" data-text="<?= $txt ?>" data-accent="<?= $acc ?>"
                onclick="applyPreset(this)"
                style="background:<?= $bg ?>;color:<?= $txt ?>;border-color:<?= $acc ?>44">
          <div style="font-size:0.75rem;font-weight:700"><?= $label ?></div>
          <div style="display:flex;gap:3px;justify-content:center;margin-top:4px">
            <div style="width:10px;height:10px;border-radius:50%;background:<?= $bg ?>;border:1px solid rgba(255,255,255,0.3)"></div>
            <div style="width:10px;height:10px;border-radius:50%;background:<?= $acc ?>"></div>
            <div style="width:10px;height:10px;border-radius:50%;background:<?= $txt ?>44"></div>
          </div>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <input type="hidden" name="invoice_color_scheme" id="inv-scheme-input" value="<?= htmlspecialchars($scheme) ?>" form="main-form">

    <div class="form-group">
      <label class="form-label">Header Background</label>
      <div class="color-swatch">
        <input type="color" id="cp-bg" value="<?= $prevBg ?>" oninput="syncColor(this,'inv-bg-text');updatePreview()">
        <input type="text" id="inv-bg-text" name="invoice_header_bg" class="form-control monospace" value="<?= htmlspecialchars($prevBg) ?>" form="main-form" oninput="syncText(this,'cp-bg');updatePreview()">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Header Text Color</label>
      <div class="color-swatch">
        <input type="color" id="cp-txt" value="<?= $prevText ?>" oninput="syncColor(this,'inv-txt-text');updatePreview()">
        <input type="text" id="inv-txt-text" name="invoice_header_text" class="form-control monospace" value="<?= htmlspecialchars($prevText) ?>" form="main-form" oninput="syncText(this,'cp-txt');updatePreview()">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Accent Color</label>
      <div class="color-swatch">
        <input type="color" id="cp-acc" value="<?= $prevAccent ?>" oninput="syncColor(this,'inv-acc-text');updatePreview()">
        <input type="text" id="inv-acc-text" name="invoice_accent" class="form-control monospace" value="<?= htmlspecialchars($prevAccent) ?>" form="main-form" oninput="syncText(this,'cp-acc');updatePreview()">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Logo Display Width (px)</label>
      <input type="number" name="branding_logo_width" class="form-control" min="60" max="400" value="<?= htmlspecialchars($settings['branding_logo_width'] ?? '180') ?>" form="main-form" oninput="updatePreview()">
    </div>

    <div class="form-group">
      <label class="form-label">Document Font</label>
      <div class="font-option">
        <?php
        $fonts = [
          'DM Sans'         => 'DM Sans',
          'Inter'           => 'Inter',
          'Roboto'          => 'Roboto',
          'Open Sans'       => 'Open Sans',
          'Georgia, serif'  => 'Georgia',
          'Courier New, monospace' => 'Courier',
        ];
        $curFont = $settings['branding_font'] ?? 'DM Sans';
        foreach ($fonts as $val => $label): ?>
        <button type="button" class="font-btn <?= $curFont===$val?'active':'' ?>"
                style="font-family:<?= $val ?>" data-font="<?= htmlspecialchars($val) ?>"
                onclick="selectFont(this)">
          <?= htmlspecialchars($label) ?>
        </button>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="branding_font" id="font-input" value="<?= htmlspecialchars($curFont) ?>" form="main-form">
    </div>
  </div>

  <!-- Quote settings -->
  <div class="card">
    <div class="card-title" style="margin-bottom:14px">📋 Quote Settings</div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Quote Number Prefix</label>
        <input type="text" name="quote_prefix" class="form-control" maxlength="8" value="<?= htmlspecialchars($settings['quote_prefix'] ?? 'QUO') ?>" form="main-form">
      </div>
      <div class="form-group">
        <label class="form-label">Valid For (days)</label>
        <input type="number" name="quote_validity_days" class="form-control" min="1" max="365" value="<?= htmlspecialchars($settings['quote_validity_days'] ?? '30') ?>" form="main-form">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Default Quote Notes</label>
      <textarea name="quote_default_notes" class="form-control" rows="3" form="main-form"><?= htmlspecialchars($settings['quote_default_notes'] ?? '') ?></textarea>
    </div>
  </div>

  <button type="submit" form="main-form" class="btn btn-primary" style="justify-content:center;padding:12px">
    💾 Save All Branding Settings
  </button>

</div>

<!-- ── RIGHT: Live Preview ── -->
<div class="preview-wrap">
  <div class="card" style="margin-bottom:14px;padding:14px 16px">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <div class="card-title">📄 Live Preview</div>
      <div style="display:flex;gap:6px">
        <button type="button" class="btn btn-outline btn-xs" onclick="setPreviewType('invoice')" id="btn-inv" style="background:var(--brand-dim);border-color:var(--brand-h);color:var(--brand-h)">Invoice</button>
        <button type="button" class="btn btn-outline btn-xs" onclick="setPreviewType('quote')"   id="btn-quo">Quote</button>
        <button type="button" class="btn btn-outline btn-xs" onclick="setPreviewType('receipt')" id="btn-rec">Receipt</button>
      </div>
    </div>
  </div>

  <!-- Preview document -->
  <div class="inv-preview" id="doc-preview">
    <!-- Header -->
    <div class="inv-prev-head" id="prev-head" style="background:<?= $prevBg ?>;color:<?= $prevText ?>">
      <div>
        <?php if ($logoExists): ?>
        <img id="prev-logo-img" src="<?= htmlspecialchars($logoUrl) ?>"
             style="max-height:50px;max-width:<?= $settings['branding_logo_width'] ?? 180 ?>px;object-fit:contain;display:block" alt="Logo">
        <?php else: ?>
        <div class="inv-prev-logo" id="prev-company-name"><?= htmlspecialchars($settings['company_name'] ?? 'Mitra') ?></div>
        <?php endif; ?>
        <div id="prev-tagline" style="font-size:10px;opacity:0.75;margin-top:3px"><?= htmlspecialchars($settings['invoice_tagline'] ?? 'Professional IT Services') ?></div>
      </div>
      <div style="text-align:right">
        <div class="inv-prev-badge" id="prev-doc-type">INVOICE</div>
        <div style="font-size:10px;opacity:0.7;margin-top:3px" id="prev-doc-num">#INV-01001</div>
        <div style="margin-top:6px;display:inline-block;background:<?= $prevAccent ?>;color:white;padding:3px 10px;border-radius:20px;font-size:9px;font-weight:700" id="prev-status-chip">DRAFT</div>
      </div>
    </div>

    <!-- Billing parties -->
    <div class="inv-prev-meta">
      <div>
        <div class="inv-prev-meta-label">From</div>
        <div class="inv-prev-meta-val" id="prev-from"><?= htmlspecialchars($settings['company_name'] ?? 'Mitra') ?></div>
        <div style="font-size:10px;color:#666"><?= htmlspecialchars($settings['company_email'] ?? '') ?></div>
      </div>
      <div>
        <div class="inv-prev-meta-label">Bill To</div>
        <div class="inv-prev-meta-val">Sample Client</div>
        <div style="font-size:10px;color:#666">client@example.com</div>
      </div>
    </div>

    <!-- Line items -->
    <table class="inv-prev-table">
      <thead>
        <tr style="background:#f0f0f0">
          <th style="width:45%">Description</th>
          <th>Qty</th>
          <th>Price</th>
          <th>Tax</th>
          <th style="text-align:right">Total</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>Laptop Repair — Labour</td><td>2</td><td>$125.00</td><td>5%</td><td style="text-align:right;font-weight:600">$250.00</td></tr>
        <tr><td>SSD 500GB — Samsung</td><td>1</td><td>$89.99</td><td>5%</td><td style="text-align:right;font-weight:600">$89.99</td></tr>
        <tr><td>Antivirus License 1yr</td><td>2</td><td>$49.99</td><td>5%</td><td style="text-align:right;font-weight:600">$99.98</td></tr>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="inv-prev-total">
      <div style="color:#666">Subtotal: $439.97</div>
      <div style="color:#666">GST (5%): $22.00</div>
      <div class="grand" id="prev-total-line" style="color:<?= $prevBg ?>">TOTAL: $461.97 CAD</div>
    </div>

    <!-- Accent bar -->
    <div style="height:4px;background:<?= $prevAccent ?>" id="prev-accent-bar"></div>

    <!-- Footer -->
    <div class="inv-prev-footer" id="prev-footer">
      <?= htmlspecialchars($settings['company_name'] ?? 'Mitra') ?> ·
      <?= htmlspecialchars($settings['company_email'] ?? '') ?> ·
      <?= htmlspecialchars($settings['company_phone'] ?? '') ?>
    </div>
  </div>

  <div style="margin-top:10px;text-align:center;font-size:0.75rem;color:var(--t3)">
    This is a live preview — changes reflect instantly
  </div>
</div>

</div><!-- /design-grid -->

<script>
const LOGO_URL  = '<?= addslashes($logoUrl) ?>';

// ── Color sync helpers ──────────────────────────────────────
function syncColor(picker, textId) {
  document.getElementById(textId).value = picker.value;
}
function syncText(input, pickerId) {
  const v = input.value.trim();
  if (/^#[0-9a-fA-F]{6}$/.test(v)) document.getElementById(pickerId).value = v;
}

// ── Preset applier ──────────────────────────────────────────
function applyPreset(btn) {
  document.querySelectorAll('.scheme-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const bg  = btn.dataset.bg;
  const txt = btn.dataset.text;
  const acc = btn.dataset.accent;
  document.getElementById('cp-bg').value       = bg;
  document.getElementById('inv-bg-text').value  = bg;
  document.getElementById('cp-txt').value      = txt;
  document.getElementById('inv-txt-text').value = txt;
  document.getElementById('cp-acc').value      = acc;
  document.getElementById('inv-acc-text').value = acc;
  document.getElementById('inv-scheme-input').value = btn.dataset.scheme;
  updatePreview();
}

// ── Font selector ───────────────────────────────────────────
function selectFont(btn) {
  document.querySelectorAll('.font-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('font-input').value = btn.dataset.font;
  document.getElementById('doc-preview').style.fontFamily = btn.dataset.font;
}

// ── Logo preview ────────────────────────────────────────────
function previewLogo(input) {
  if (!input.files.length) return;
  const file   = input.files[0];
  const reader = new FileReader();
  reader.onload = e => {
    const logoBox = document.getElementById('prev-logo-img');
    if (logoBox) {
      logoBox.src = e.target.result;
    } else {
      // Replace text with image in preview
      const nameEl = document.getElementById('prev-company-name');
      if (nameEl) {
        const img = document.createElement('img');
        img.id    = 'prev-logo-img';
        img.style = 'max-height:50px;max-width:180px;object-fit:contain;display:block';
        nameEl.replaceWith(img);
        img.src = e.target.result;
      }
    }
    document.getElementById('logo-drop-name').textContent = file.name;
    document.getElementById('logo-drop-name').style.display = 'block';
  };
  reader.readAsDataURL(file);
}

// ── Live preview update ─────────────────────────────────────
function updatePreview() {
  const bg    = document.getElementById('inv-bg-text').value  || '#1a1a2e';
  const txt   = document.getElementById('inv-txt-text').value || '#ffffff';
  const acc   = document.getElementById('inv-acc-text').value || '#2f81f7';
  const cName = document.querySelector('[name=company_name]').value || 'Mitra';
  const tag   = document.querySelector('[name=invoice_tagline]').value;

  document.getElementById('prev-head').style.background = bg;
  document.getElementById('prev-head').style.color      = txt;
  document.getElementById('prev-accent-bar').style.background = acc;
  document.getElementById('prev-total-line').style.color = bg;
  const statusChip = document.getElementById('prev-status-chip');
  if (statusChip) statusChip.style.background = acc;

  const nameEl = document.getElementById('prev-company-name');
  if (nameEl) nameEl.textContent = cName;

  const tagEl = document.getElementById('prev-tagline');
  if (tagEl) tagEl.textContent = tag;

  const fromEl = document.getElementById('prev-from');
  if (fromEl) fromEl.textContent = cName;

  const footerEl = document.getElementById('prev-footer');
  if (footerEl) {
    const em = document.querySelector('[name=company_email]').value;
    const ph = document.querySelector('[name=company_phone]').value;
    footerEl.textContent = [cName, em, ph].filter(Boolean).join(' · ');
  }
}

// ── Preview type switcher ───────────────────────────────────
function setPreviewType(type) {
  const labels  = { invoice: 'INVOICE', quote: 'QUOTE', receipt: 'RECEIPT' };
  const nums    = { invoice: '#INV-01001', quote: '#QUO-00042', receipt: '#SALE-00199' };
  const statuses= { invoice: 'DRAFT', quote: 'VALID', receipt: 'PAID' };

  document.getElementById('prev-doc-type').textContent = labels[type];
  document.getElementById('prev-doc-num').textContent  = nums[type];
  document.getElementById('prev-status-chip').textContent = statuses[type];

  ['btn-inv','btn-quo','btn-rec'].forEach(id => {
    const el = document.getElementById(id);
    el.style.background   = '';
    el.style.borderColor  = '';
    el.style.color        = '';
  });
  const active = {invoice:'btn-inv', quote:'btn-quo', receipt:'btn-rec'}[type];
  const btn = document.getElementById(active);
  btn.style.background  = 'var(--brand-dim)';
  btn.style.borderColor = 'var(--brand-h)';
  btn.style.color       = 'var(--brand-h)';
}

// Init
updatePreview();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
