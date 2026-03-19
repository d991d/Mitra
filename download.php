<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

// download.php — serves uploaded attachments securely
require_once __DIR__ . '/includes/functions.php';
$currentUser = requireLogin();

$file = basename($_GET['file'] ?? '');
if (!$file) { http_response_code(400); die('Bad request'); }

// Verify the user has access to this attachment
$att = DB::fetch(
    "SELECT a.*, t.user_id FROM ticket_attachments a
     JOIN tickets t ON a.ticket_id = t.id
     WHERE a.filename = ?",
    [$file]
);

if (!$att) { http_response_code(404); die('Not found'); }

// Clients can only download their own ticket attachments
if ($currentUser['role'] === 'client' && $att['user_id'] !== $currentUser['id']) {
    http_response_code(403); die('Forbidden');
}

$path = UPLOAD_DIR . $file;
if (!file_exists($path)) { http_response_code(404); die('File not found'); }

header('Content-Type: ' . ($att['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . addslashes($att['original_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
