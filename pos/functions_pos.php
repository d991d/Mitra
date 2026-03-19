<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

// pos/functions_pos.php — POS & Invoicing helper functions
// Included by all POS pages alongside the main functions.php

// ─── Number generators ───────────────────────────────────────
function generateInvoiceNumber() {
    $prefix = DB::setting('pos_invoice_prefix') ?: 'INV';
    $next   = (int)(DB::setting('pos_next_invoice') ?: 1001);
    DB::query("UPDATE settings SET setting_value=? WHERE setting_key='pos_next_invoice'", [$next + 1]);
    return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function generateSaleNumber() {
    $prefix = DB::setting('pos_sale_prefix') ?: 'SALE';
    $next   = (int)(DB::setting('pos_next_sale') ?: 1001);
    DB::query("UPDATE settings SET setting_value=? WHERE setting_key='pos_next_sale'", [$next + 1]);
    return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

// ─── Money formatting ────────────────────────────────────────
function money($amount) {
    $cur = DB::setting('pos_currency') ?: 'CAD';
    return '$' . number_format((float)$amount, 2) . ' ' . $cur;
}

function moneyRaw($amount) {
    return '$' . number_format((float)$amount, 2);
}

// ─── Invoice totals recalculation ────────────────────────────
function recalcInvoice($invoiceId) {
    $items = DB::fetchAll("SELECT * FROM pos_invoice_items WHERE invoice_id=?", [$invoiceId]);
    $inv   = DB::fetch("SELECT * FROM pos_invoices WHERE id=?", [$invoiceId]);

    $subtotal = 0;
    $taxTotal = 0;
    foreach ($items as $item) {
        $sub       = round($item['quantity'] * $item['unit_price'], 2);
        $tax       = round($sub * $item['tax_rate'] / 100, 2);
        $subtotal += $sub;
        $taxTotal += $tax;
        DB::query("UPDATE pos_invoice_items SET subtotal=?, tax_amount=? WHERE id=?",
                  [$sub, $tax, $item['id']]);
    }

    $discTotal = 0;
    if ($inv['discount_value'] > 0) {
        $discTotal = $inv['discount_type'] === 'percent'
            ? round($subtotal * $inv['discount_value'] / 100, 2)
            : (float)$inv['discount_value'];
    }
    $total   = max(0, $subtotal + $taxTotal - $discTotal);
    $paid    = DB::fetch("SELECT COALESCE(SUM(amount),0) as s FROM pos_payments WHERE invoice_id=?", [$invoiceId])['s'];
    $balance = max(0, $total - $paid);

    DB::query("UPDATE pos_invoices SET subtotal=?,tax_total=?,discount_total=?,total=?,amount_paid=?,balance=?,updated_at=NOW() WHERE id=?",
              [$subtotal, $taxTotal, $discTotal, $total, $paid, $balance, $invoiceId]);

    // Auto-status
    if ($balance <= 0 && $total > 0) {
        DB::query("UPDATE pos_invoices SET status='paid' WHERE id=? AND status NOT IN ('void')", [$invoiceId]);
    } elseif ($paid > 0 && $balance > 0) {
        DB::query("UPDATE pos_invoices SET status='partial' WHERE id=? AND status NOT IN ('void')", [$invoiceId]);
    } elseif (DB::fetch("SELECT due_date FROM pos_invoices WHERE id=?",[$invoiceId])['due_date'] < date('Y-m-d') && $balance > 0) {
        DB::query("UPDATE pos_invoices SET status='overdue' WHERE id=? AND status NOT IN ('void','paid')", [$invoiceId]);
    }
}

// ─── Invoice status badge ────────────────────────────────────
function getInvoiceStatusBadge($s) {
    $map = [
        'draft'   => 'status-closed',
        'sent'    => 'status-open',
        'paid'    => 'status-resolved',
        'partial' => 'status-pending',
        'overdue' => 'badge-critical',
        'void'    => 'status-closed',
    ];
    return "<span class=\"status-badge " . ($map[$s] ?? 'status-open') . "\">" . ucfirst($s) . "</span>";
}

// ─── POS dashboard stats ─────────────────────────────────────
function getPosStats() {
    $today = date('Y-m-d');
    $month = date('Y-m');
    return [
        'invoices_total'   => DB::fetch("SELECT COUNT(*) c FROM pos_invoices")['c'],
        'invoices_unpaid'  => DB::fetch("SELECT COUNT(*) c FROM pos_invoices WHERE status IN ('sent','overdue','partial')")['c'],
        'revenue_month'    => DB::fetch("SELECT COALESCE(SUM(amount),0) s FROM pos_payments WHERE DATE_FORMAT(payment_date,'%Y-%m')=?", [$month])['s'],
        'revenue_today'    => DB::fetch("SELECT COALESCE(SUM(total),0) s FROM pos_sales WHERE DATE(created_at)=?", [$today])['s'],
        'overdue'          => DB::fetch("SELECT COUNT(*) c FROM pos_invoices WHERE status='overdue'")['c'],
        'outstanding'      => DB::fetch("SELECT COALESCE(SUM(balance),0) s FROM pos_invoices WHERE status IN ('sent','partial','overdue')")['s'],
        'sales_today'      => DB::fetch("SELECT COUNT(*) c FROM pos_sales WHERE DATE(created_at)=?", [$today])['c'],
        'products_low'     => DB::fetch("SELECT COUNT(*) c FROM pos_products WHERE stock_qty IS NOT NULL AND stock_qty<=3 AND is_active=1")['c'],
    ];
}

// ─── Stock update helper ─────────────────────────────────────
function deductStock($productId, $qty) {
    if (!$productId) return;
    $p = DB::fetch("SELECT stock_qty FROM pos_products WHERE id=?", [$productId]);
    if ($p && $p['stock_qty'] !== null) {
        DB::query("UPDATE pos_products SET stock_qty=GREATEST(0, stock_qty-?) WHERE id=?", [$qty, $productId]);
    }
}

function restoreStock($productId, $qty) {
    if (!$productId) return;
    $p = DB::fetch("SELECT stock_qty FROM pos_products WHERE id=?", [$productId]);
    if ($p && $p['stock_qty'] !== null) {
        DB::query("UPDATE pos_products SET stock_qty=stock_qty+? WHERE id=?", [$qty, $productId]);
    }
}
