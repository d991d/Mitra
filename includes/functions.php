<?php
/**
 * Mitra Business Suite
 * Ticketing · Point of Sale · Invoicing
 *
 * @author    d991d
 * @copyright 2024 d991d. All rights reserved.
 * @license   Proprietary
 */

require_once __DIR__ . '/config.php';

// ─── Database ────────────────────────────────────────────────
class DB {
    private static $pdo = null;

    public static function connect() {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Show a human-readable error instead of a blank page
                $msg = htmlspecialchars($e->getMessage());
                die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Mitra — Database Error</title>'
                  . '<style>body{font-family:sans-serif;background:#0d1117;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
                  . '.box{background:#161b22;border:1px solid #f85149;border-radius:12px;padding:32px 40px;max-width:520px;width:100%}'
                  . 'h2{color:#f85149;margin-bottom:12px}code{background:#21262d;padding:10px 14px;border-radius:6px;display:block;margin:12px 0;font-size:0.85rem;word-break:break-all}'
                  . 'a{color:#2f81f7}p{color:#8b949e;line-height:1.7}</style></head>'
                  . '<body><div class="box"><h2>&#x26A0; Database Connection Failed</h2>'
                  . '<p>Mitra could not connect to the database. Check your <code>includes/config.php</code> settings.</p>'
                  . '<code>' . $msg . '</code>'
                  . '<p>Common fixes:<br>'
                  . '&bull; Verify DB_HOST, DB_NAME, DB_USER, DB_PASS in config.php<br>'
                  . '&bull; Make sure the database exists in cPanel / phpMyAdmin<br>'
                  . '&bull; Check the database user has full privileges<br>'
                  . '&bull; Try running <a href="../install.php">install.php</a> again</p>'
                  . '</div></body></html>');
            }
        }
        return self::$pdo;
    }

    public static function query($sql, $params = []) {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch($sql, $params = []) {
        return self::query($sql, $params)->fetch();
    }

    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert($sql, $params = []) {
        self::query($sql, $params);
        return self::connect()->lastInsertId();
    }

    public static function setting($key) {
        try {
            $row = self::fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
            return $row ? $row['setting_value'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}

// ─── Session ─────────────────────────────────────────────────
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function getCurrentUser() {
    startSession();
    if (isset($_SESSION['user_id'])) {
        return DB::fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$_SESSION['user_id']]);
    }
    return null;
}

function requireLogin($redirect = '../index.php') {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: ' . BASE_URL . '/' . $redirect);
        exit;
    }
    return $user;
}

function requireAdmin($redirect = '../index.php') {
    $user = requireLogin($redirect);
    if (!in_array($user['role'], ['admin', 'agent'])) {
        header('Location: ' . BASE_URL . '/client/dashboard.php');
        exit;
    }
    return $user;
}

function requireRole($roles, $redirect = '../index.php') {
    $user = requireLogin($redirect);
    if (!in_array($user['role'], (array)$roles)) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    return $user;
}

function login($email, $password) {
    $user = DB::fetch("SELECT * FROM users WHERE email = ? AND is_active = 1", [trim($email)]);
    if ($user && password_verify($password, $user['password'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        DB::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        logActivity($user['id'], 'login', 'user', $user['id']);
        return $user;
    }
    return false;
}

function logout() {
    startSession();
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) logActivity($uid, 'logout', 'user', $uid);
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// ─── Ticket helpers ──────────────────────────────────────────
function generateTicketNumber() {
    $prefix = DB::setting('ticket_prefix') ?: 'MIT';
    $last = DB::fetch("SELECT ticket_number FROM tickets ORDER BY id DESC LIMIT 1");
    if ($last) {
        preg_match('/(\d+)$/', $last['ticket_number'], $m);
        $next = isset($m[1]) ? intval($m[1]) + 1 : 1000;
    } else {
        $next = 1000;
    }
    return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function createTicket($data, $userId) {
    $num = generateTicketNumber();
    $id = DB::insert(
        "INSERT INTO tickets (ticket_number, subject, description, priority, category, user_id, department_id, due_date, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
        [$num, $data['subject'], $data['description'], $data['priority'] ?? 'medium',
         $data['category'] ?? null, $userId, $data['department_id'] ?? null, $data['due_date'] ?? null]
    );
    logActivity($userId, 'created_ticket', 'ticket', $id, "Ticket $num created");
    return ['id' => $id, 'number' => $num];
}

function getTicketStats($agentId = null) {
    $where = $agentId ? "WHERE agent_id = $agentId" : "";
    $stats = [];
    foreach (['open','pending','resolved','closed'] as $s) {
        $w = $agentId ? "WHERE status='$s' AND agent_id=$agentId" : "WHERE status='$s'";
        $r = DB::fetch("SELECT COUNT(*) as c FROM tickets $w");
        $stats[$s] = $r['c'];
    }
    $r = DB::fetch("SELECT COUNT(*) as c FROM tickets $where");
    $stats['total'] = $r['c'];
    return $stats;
}

function getPriorityBadge($p) {
    $map = ['low'=>'badge-low','medium'=>'badge-medium','high'=>'badge-high','critical'=>'badge-critical'];
    $cls = $map[$p] ?? 'badge-medium';
    return "<span class=\"badge $cls\">" . ucfirst($p) . "</span>";
}

function getStatusBadge($s) {
    $map = ['open'=>'status-open','pending'=>'status-pending','resolved'=>'status-resolved','closed'=>'status-closed'];
    $cls = $map[$s] ?? 'status-open';
    return "<span class=\"status-badge $cls\">" . ucfirst($s) . "</span>";
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function logActivity($userId, $action, $entityType = null, $entityId = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    DB::query(
        "INSERT INTO activity_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)",
        [$userId, $action, $entityType, $entityId, $details, $ip]
    );
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function csrf() {
    startSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf($token) {
    startSession();
    return hash_equals($_SESSION['csrf'] ?? '', $token ?? '');
}

function flash($key, $msg = null) {
    startSession();
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
    } else {
        $val = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $val;
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function allowedFileType($mime) {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp',
                 'application/pdf','text/plain','application/msword',
                 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                 'application/vnd.ms-excel',
                 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                 'application/zip','application/x-zip-compressed'];
    return in_array($mime, $allowed);
}

function uploadAttachment($file, $ticketId, $userId, $replyId = null) {
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
    if ($file['size'] > $maxBytes) return ['error' => 'File too large (max ' . UPLOAD_MAX_MB . 'MB)'];
    if (!allowedFileType($file['type'])) return ['error' => 'File type not allowed'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = UPLOAD_DIR . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['error' => 'Upload failed'];
    DB::insert(
        "INSERT INTO ticket_attachments (ticket_id, reply_id, filename, original_name, file_size, mime_type, uploaded_by) VALUES (?,?,?,?,?,?,?)",
        [$ticketId, $replyId, $stored, $file['name'], $file['size'], $file['type'], $userId]
    );
    return ['success' => true, 'filename' => $stored];
}

// Pagination helper
function paginate($total, $perPage, $currentPage, $url) {
    $pages = ceil($total / $perPage);
    if ($pages <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i == $currentPage ? ' active' : '';
        $html .= "<a href=\"{$url}&page={$i}\" class=\"page-btn{$active}\">{$i}</a>";
    }
    $html .= '</div>';
    return $html;
}
