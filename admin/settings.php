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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        flash('error', 'Invalid request.');
    } else {
        $keys = ['company_name','company_email','company_phone','company_address',
                 'tickets_per_page','allow_registration','email_notifications','ticket_prefix',
                 'pos_tax_name','pos_tax_rate','pos_currency','pos_invoice_prefix','pos_sale_prefix',
                 'pos_invoice_terms','pos_invoice_footer','pos_enable_stock'];
        foreach ($keys as $k) {
            $val = trim($_POST[$k] ?? '');
            $existing = DB::fetch("SELECT id FROM settings WHERE setting_key=?", [$k]);
            if ($existing) {
                DB::query("UPDATE settings SET setting_value=? WHERE setting_key=?", [$val, $k]);
            } else {
                DB::query("INSERT INTO settings (setting_key,setting_value) VALUES (?,?)", [$k, $val]);
            }
        }
        flash('success', 'Settings saved successfully.');
    }
    header('Location: settings.php');
    exit;
}

// Load all settings
$settings = [];
$rows = DB::fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

$pageTitle = 'Settings';
$activeNav = 'settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-title" style="margin-bottom:20px">System Settings</div>

<div style="max-width:640px">
<div class="card">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">

    <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--t3);margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--b1)">
      Company Information
    </div>

    <div class="form-group">
      <label class="form-label">Company Name</label>
      <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? 'Mitra') ?>">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Support Email</label>
        <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Phone</label>
        <input type="tel" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Address</label>
      <input type="text" name="company_address" class="form-control" value="<?= htmlspecialchars($settings['company_address'] ?? '') ?>">
    </div>

    <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--t3);margin:20px 0 14px;padding-bottom:10px;border-bottom:1px solid var(--b1)">
      Ticket Settings
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Ticket Number Prefix</label>
        <input type="text" name="ticket_prefix" class="form-control" maxlength="8"
               value="<?= htmlspecialchars($settings['ticket_prefix'] ?? 'MIT') ?>">
        <div class="form-hint">e.g. MIT → MIT-01000</div>
      </div>
      <div class="form-group">
        <label class="form-label">Tickets Per Page</label>
        <select name="tickets_per_page" class="form-control">
          <?php foreach ([10,15,20,25,50] as $n): ?>
          <option value="<?= $n ?>" <?= ($settings['tickets_per_page'] ?? 15) == $n ? 'selected' : '' ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--t3);margin:20px 0 14px;padding-bottom:10px;border-bottom:1px solid var(--b1)">
      Portal Settings
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
        <input type="hidden" name="allow_registration" value="0">
        <input type="checkbox" name="allow_registration" value="1" <?= ($settings['allow_registration'] ?? '1') === '1' ? 'checked' : '' ?>>
        <span class="form-label" style="margin:0">Allow Public Registration</span>
      </label>
      <div class="form-hint">Allow clients to self-register on the portal</div>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
        <input type="hidden" name="email_notifications" value="0">
        <input type="checkbox" name="email_notifications" value="1" <?= ($settings['email_notifications'] ?? '0') === '1' ? 'checked' : '' ?>>
        <span class="form-label" style="margin:0">Email Notifications</span>
      </label>
      <div class="form-hint">Send email alerts on ticket updates (requires PHP mail to be configured)</div>
    </div>

    <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--t3);margin:20px 0 14px;padding-bottom:10px;border-bottom:1px solid var(--b1)">
      💳 POS &amp; Billing
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Tax Name</label>
        <input type="text" name="pos_tax_name" class="form-control" value="<?= htmlspecialchars($settings['pos_tax_name'] ?? 'GST') ?>" placeholder="GST, HST, VAT…">
      </div>
      <div class="form-group">
        <label class="form-label">Default Tax Rate (%)</label>
        <input type="number" name="pos_tax_rate" class="form-control" min="0" max="100" step="0.01" value="<?= htmlspecialchars($settings['pos_tax_rate'] ?? '5.00') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Currency</label>
        <select name="pos_currency" class="form-control">
          <?php foreach (['CAD','USD','EUR','GBP','AUD'] as $cur): ?>
          <option value="<?= $cur ?>" <?= ($settings['pos_currency']??'CAD')===$cur?'selected':'' ?>><?= $cur ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Invoice Number Prefix</label>
        <input type="text" name="pos_invoice_prefix" class="form-control" maxlength="8" value="<?= htmlspecialchars($settings['pos_invoice_prefix'] ?? 'INV') ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Sale Number Prefix</label>
        <input type="text" name="pos_sale_prefix" class="form-control" maxlength="8" value="<?= htmlspecialchars($settings['pos_sale_prefix'] ?? 'SALE') ?>">
      </div>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding-top:22px">
          <input type="hidden" name="pos_enable_stock" value="0">
          <input type="checkbox" name="pos_enable_stock" value="1" <?= ($settings['pos_enable_stock']??'1')==='1'?'checked':'' ?>>
          <span class="form-label" style="margin:0">Track Stock Levels</span>
        </label>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Default Invoice Terms</label>
      <textarea name="pos_invoice_terms" class="form-control" rows="2"><?= htmlspecialchars($settings['pos_invoice_terms'] ?? 'Payment due within 30 days. Thank you for your business.') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label">Invoice Footer Text</label>
      <input type="text" name="pos_invoice_footer" class="form-control" value="<?= htmlspecialchars($settings['pos_invoice_footer'] ?? '') ?>" placeholder="Company tagline, address, etc.">
    </div>

    <div style="margin-top:20px">
      <button type="submit" class="btn btn-primary">Save All Settings</button>
    </div>
  </form>
</div>

<!-- DB info -->
<div class="card" style="margin-top:16px">
  <div class="card-title" style="margin-bottom:14px">System Information</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.85rem">
    <div style="color:var(--t3)">PHP Version</div>
    <div><?= phpversion() ?></div>
    <div style="color:var(--t3)">Database</div>
    <div>MySQL / MariaDB</div>
    <div style="color:var(--t3)">Upload Max Size</div>
    <div><?= UPLOAD_MAX_MB ?>MB</div>
    <div style="color:var(--t3)">System Version</div>
    <div>Mitra Suite v2.0</div>
    <div style="color:var(--t3)">Modules</div>
    <div>Ticketing · POS · Invoicing</div>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
