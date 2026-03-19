<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

// includes/header.php
if (!isset($pageTitle)) $pageTitle = 'Mitra';
if (!isset($activeNav)) $activeNav = '';
if (!isset($activeSection)) $activeSection = ''; // 'tickets' or 'pos'

$currentUser = getCurrentUser();
$isAdmin  = $currentUser && in_array($currentUser['role'], ['admin','agent']);
$initials = strtoupper(substr($currentUser['name'] ?? 'U', 0, 1));

// Badge counts
$openCount = 0;
$overdueInvoices = 0;
if ($isAdmin) {
    $r = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE status='open'");
    $openCount = $r['c'];
    // Check if POS tables exist before querying
    try {
        $r2 = DB::fetch("SELECT COUNT(*) as c FROM pos_invoices WHERE status IN ('overdue','sent','partial')");
        $overdueInvoices = $r2['c'];
    } catch (Exception $e) { $overdueInvoices = 0; }
} else if ($currentUser) {
    $r = DB::fetch("SELECT COUNT(*) as c FROM tickets WHERE user_id=? AND status='open'", [$currentUser['id']]);
    $openCount = $r['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — Mitra</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body>
<div class="app-layout">

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">
      <div class="logo-icon">Y</div>
      <div>
        <div>Mitra</div>
        <div class="logo-sub">Business Suite</div>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php if ($isAdmin): ?>

    <!-- ── SUPPORT TICKETS ── -->
    <div class="nav-label">🎫 Support</div>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">📊</span> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/admin/tickets.php" class="nav-item <?= $activeNav === 'tickets' ? 'active' : '' ?>">
      <span class="nav-icon">🎫</span> All Tickets
      <?php if ($openCount > 0): ?><span class="nav-badge"><?= $openCount ?></span><?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin/new-ticket.php" class="nav-item <?= $activeNav === 'new-ticket' ? 'active' : '' ?>">
      <span class="nav-icon">➕</span> New Ticket
    </a>

    <!-- ── POS & BILLING ── -->
    <div class="nav-label">💳 POS & Billing</div>
    <a href="<?= BASE_URL ?>/pos/dashboard.php" class="nav-item <?= $activeNav === 'pos-dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">🏪</span> POS Overview
    </a>
    <a href="<?= BASE_URL ?>/pos/sale.php" class="nav-item <?= $activeNav === 'pos-sale' ? 'active' : '' ?>">
      <span class="nav-icon">🛒</span> Quick Sale
    </a>
    <a href="<?= BASE_URL ?>/pos/invoices.php" class="nav-item <?= $activeNav === 'pos-invoices' ? 'active' : '' ?>">
      <span class="nav-icon">🧾</span> Invoices
      <?php if ($overdueInvoices > 0): ?><span class="nav-badge"><?= $overdueInvoices ?></span><?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/pos/invoice-new.php" class="nav-item <?= $activeNav === 'pos-invoice-new' ? 'active' : '' ?>">
      <span class="nav-icon">📄</span> New Invoice
    </a>
    <a href="<?= BASE_URL ?>/pos/products.php" class="nav-item <?= $activeNav === 'pos-products' ? 'active' : '' ?>">
      <span class="nav-icon">📦</span> Products
    </a>

    <!-- ── MANAGE ── -->
    <div class="nav-label">Manage</div>
    <a href="<?= BASE_URL ?>/admin/users.php" class="nav-item <?= $activeNav === 'users' ? 'active' : '' ?>">
      <span class="nav-icon">👥</span> Users / Clients
    </a>
    <a href="<?= BASE_URL ?>/admin/departments.php" class="nav-item <?= $activeNav === 'departments' ? 'active' : '' ?>">
      <span class="nav-icon">🏢</span> Departments
    </a>

    <?php if ($currentUser['role'] === 'admin'): ?>
    <div class="nav-label">System</div>
    <a href="<?= BASE_URL ?>/admin/reports.php" class="nav-item <?= $activeNav === 'reports' ? 'active' : '' ?>">
      <span class="nav-icon">📈</span> Reports
    </a>
    <a href="<?= BASE_URL ?>/pos/reports.php" class="nav-item <?= $activeNav === 'pos-reports' ? 'active' : '' ?>">
      <span class="nav-icon">💰</span> Financial Reports
    </a>
    <a href="<?= BASE_URL ?>/admin/branding.php" class="nav-item <?= $activeNav === 'branding' ? 'active' : '' ?>">
      <span class="nav-icon">🎨</span> Branding
    </a>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="nav-item <?= $activeNav === 'settings' ? 'active' : '' ?>">
      <span class="nav-icon">⚙️</span> Settings
    </a>
    <?php endif; ?>

    <?php else: ?>
    <!-- CLIENT SIDE -->
    <div class="nav-label">Support</div>
    <a href="<?= BASE_URL ?>/client/dashboard.php" class="nav-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon">🏠</span> My Dashboard
    </a>
    <a href="<?= BASE_URL ?>/client/tickets.php" class="nav-item <?= $activeNav === 'tickets' ? 'active' : '' ?>">
      <span class="nav-icon">🎫</span> My Tickets
      <?php if ($openCount > 0): ?><span class="nav-badge"><?= $openCount ?></span><?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/client/new-ticket.php" class="nav-item <?= $activeNav === 'new-ticket' ? 'active' : '' ?>">
      <span class="nav-icon">➕</span> Open Ticket
    </a>

    <div class="nav-label">Billing</div>
    <a href="<?= BASE_URL ?>/client/invoices.php" class="nav-item <?= $activeNav === 'client-invoices' ? 'active' : '' ?>">
      <span class="nav-icon">🧾</span> My Invoices
    </a>
    <?php endif; ?>

    <div class="nav-label">Account</div>
    <a href="<?= BASE_URL ?>/profile.php" class="nav-item <?= $activeNav === 'profile' ? 'active' : '' ?>">
      <span class="nav-icon">👤</span> My Profile
    </a>
  </nav>

  <div class="sidebar-user">
    <div class="user-info">
      <div class="user-avatar"><?= $initials ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($currentUser['name'] ?? '') ?></div>
        <div class="user-role"><?= ucfirst($currentUser['role'] ?? '') ?></div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="logout-btn" title="Logout">🚪</a>
    </div>
  </div>
</aside>

<!-- Main content -->
<div class="main-content">
<div class="topbar">
  <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
  <div class="topbar-right">
    <div class="search-wrap">
      <span class="search-icon"></span>
      <form action="<?= $isAdmin ? BASE_URL.'/admin/tickets.php' : BASE_URL.'/client/tickets.php' ?>" method="get">
        <input type="text" name="search" class="topbar-search" placeholder="Search…  (/)" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
      </form>
    </div>
  </div>
</div>
<div class="page-body">

<?php
// Flash messages
foreach (['success','error','warning','info'] as $type) {
    $msg = flash($type);
    if ($msg) echo "<div class=\"alert alert-$type\">$msg</div>";
}
?>
