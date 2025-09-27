<?php
// ==============================
// L Y K A N CONFIG (SAFE + FULL) — with ADMIN & KEY MGMT
// ==============================

// ---- Database credentials ----
define('DB_HOST', 'sgp.domcloud.co');
define('DB_NAME', 'ranijhansischool_db');
define('DB_USER', 'ranijhansischool');
define('DB_PASS', '5V(_9EsBo89mu4U(Yx');
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

// ---- Telegram Bot details ----
define('BOT_ID', 8437832185);
define('BOT_USERNAME', 'LOGINTOWEBBOT');
define('TELEGRAM_BOT_TOKEN', '8437832185:AAGql93tVpSyJtXQJwmLPvqcuVZpD6YtYy8');

// ---- Detect SITE_ORIGIN dynamically ----
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_ORIGIN', $protocol . $host);
define('RETURN_TO', SITE_ORIGIN . '/tg_return.php');

// ---- Tables ----
define('TBL_KEYS', 'redeem_keys'); // ensure this table exists
define('TBL_USERS', 'users');      // ensure your users table name matches

// ---- Sessions (safe start) ----
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_strict_mode', '1');
    session_name('lykan');
    session_start();
}

// ==============================
// PDO CONNECTOR
// ==============================
function pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            DB_DSN,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

// ==============================
// SESSION + AUTH HELPERS
// ==============================
function is_logged_in() {
    return !empty($_SESSION['uid']) && !empty($_SESSION['tgid']);
}

function require_login() {
    if (!is_logged_in()) { header('Location: /login.php'); exit; }
}

function is_admin() {
    // Admin flag stored in session if available; fall back to DB check
    if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin') return true;
    if (!empty($_SESSION['uid'])) {
        $st = pdo()->prepare('SELECT role FROM ' . TBL_USERS . ' WHERE id = ?');
        $st->execute([(int)$_SESSION['uid']]);
        $r = $st->fetch();
        if ($r && !empty($r['role']) && $r['role'] === 'admin') {
            $_SESSION['role'] = 'admin'; // cache
            return true;
        }
    }
    return false;
}

function require_admin() {
    if (!is_admin()) { header('Location: /index.php'); exit; }
}

// CSRF helpers for admin forms
function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf($token) {
    return !empty($token) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

// ==============================
// TELEGRAM AUTH VERIFY
// ==============================
function verifyTelegramAuth($data) {
    if (!isset($data['hash'])) return false;

    $check = $data['hash'];
    unset($data['hash']);

    // Sort & build data check string
    ksort($data, SORT_STRING);
    $pairs = [];
    foreach ($data as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    $data_check_string = implode("\n", $pairs);

    $secret_key = hash('sha256', TELEGRAM_BOT_TOKEN, true);
    $hmac = hash_hmac('sha256', $data_check_string, $secret_key);

    // freshness (24h)
    if (isset($data['auth_date']) && time() - (int)$data['auth_date'] > 86400) return false;

    return hash_equals($hmac, $check);
}

// ==============================
// USER HELPERS
// ==============================
function load_user_by_id($id) {
    $st = pdo()->prepare('SELECT * FROM ' . TBL_USERS . ' WHERE id = ? LIMIT 1');
    $st->execute([(int)$id]);
    return $st->fetch();
}

function get_user_by_telegram_id($tgid) {
    $st = pdo()->prepare('SELECT * FROM ' . TBL_USERS . ' WHERE telegram_id = ? LIMIT 1');
    $st->execute([(string)$tgid]);
    return $st->fetch();
}

function upsert_user_from_tg($u) {
    $pdo  = pdo();
    $tgid = isset($u['id']) ? (string)$u['id'] : '';

    // try existing by telegram_id
    $st = $pdo->prepare('SELECT id, role FROM ' . TBL_USERS . ' WHERE telegram_id = ? LIMIT 1');
    $st->execute([$tgid]);
    $row = $st->fetch();

    if ($row) {
        $st = $pdo->prepare('UPDATE ' . TBL_USERS . '
            SET username = :username, first_name = :first_name, last_name = :last_name,
                photo_url = :photo_url, auth_date = :auth_date, last_login_at = NOW()
            WHERE telegram_id = :tg');
    } else {
        $st = $pdo->prepare('INSERT INTO ' . TBL_USERS . '
            (telegram_id, username, first_name, last_name, photo_url, auth_date, last_login_at)
            VALUES (:tg, :username, :first_name, :last_name, :photo_url, :auth_date, NOW())');
    }

    $st->execute([
        ':tg'         => $tgid,
        ':username'   => $u['username'] ?? null,
        ':first_name' => $u['first_name'] ?? null,
        ':last_name'  => $u['last_name'] ?? null,
        ':photo_url'  => $u['photo_url'] ?? null,
        ':auth_date'  => $u['auth_date'] ?? null,
    ]);

    $id   = $row ? (int)$row['id'] : (int)$pdo->lastInsertId();
    $role = $row ? $row['role'] : 'user';

    return ['id' => $id, 'telegram_id' => $tgid, 'role' => $role];
}

// Promote/demote admin (admin-only functions)
function promote_user_to_admin($user_id) {
    $pdo = pdo();
    $st = $pdo->prepare('UPDATE ' . TBL_USERS . ' SET role = :role WHERE id = :id');
    $st->execute([':role' => 'admin', ':id' => (int)$user_id]);
    return $st->rowCount() > 0;
}
function revoke_admin($user_id) {
    $pdo = pdo();
    $st = $pdo->prepare('UPDATE ' . TBL_USERS . ' SET role = :role WHERE id = :id');
    $st->execute([':role' => 'user', ':id' => (int)$user_id]);
    return $st->rowCount() > 0;
}

// ==============================
// BUSINESS HELPERS
// ==============================
function change_credits($uid, $delta) {
    $pdo = pdo();
    $stmt = $pdo->prepare('UPDATE ' . TBL_USERS . ' SET credits = IFNULL(credits,0) + :d WHERE id = :u');
    $stmt->execute([':d' => (int)$delta, ':u' => (int)$uid]);
    return (int)$pdo->query('SELECT credits FROM ' . TBL_USERS . ' WHERE id=' . (int)$uid)->fetchColumn();
}

function set_premium_days($uid, $days) {
    $pdo = pdo();
    if ((int)$days <= 0) {
        return (string)$pdo->query("SELECT IFNULL(DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i'),'—') FROM " . TBL_USERS . " WHERE id=" . (int)$uid)->fetchColumn();
    }
    $stmt = $pdo->prepare('UPDATE ' . TBL_USERS . '
        SET premium_until = CASE
            WHEN premium_until IS NULL OR premium_until < NOW()
            THEN DATE_ADD(NOW(), INTERVAL :days DAY)
            ELSE DATE_ADD(premium_until, INTERVAL :days DAY)
        END
        WHERE id=:uid');
    $stmt->execute([':days' => (int)$days, ':uid' => (int)$uid]);
    return (string)$pdo->query("SELECT IFNULL(DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i'),'—') FROM " . TBL_USERS . " WHERE id=" . (int)$uid)->fetchColumn();
}

function increase_stats($uid, $checks, $lives) {
    $pdo = pdo();
    $stmt = $pdo->prepare('UPDATE ' . TBL_USERS . ' SET checks_total = checks_total + :c, lives_total = lives_total + :l WHERE id = :u');
    $stmt->execute([':c' => (int)$checks, ':l' => (int)$lives, ':u' => (int)$uid]);
}

// ==============================
// KEY MANAGEMENT (admin + public helpers)
// ==============================
// Ensure you have a table `redeem_keys` with columns at least:
// id, kcode, credits, days, created_by, created_at, claimed_by, claimed_at, revoked (tinyint)

function gen_key_code($lenBytes = 8) {
    return strtolower(bin2hex(random_bytes($lenBytes))); // e.g. 16 hex chars for 8 bytes
}

// Create single key (admin)
function create_key($credits, $days, $created_by) {
    $k = gen_key_code(8);
    $stmt = pdo()->prepare('INSERT INTO ' . TBL_KEYS . ' (kcode, credits, days, created_by, created_at, revoked) VALUES (?, ?, ?, ?, NOW(), 0)');
    $stmt->execute([$k, (int)$credits, (int)$days, (int)$created_by]);
    return $k;
}

// Create many keys
function create_keys_bulk($n, $credits, $days, $created_by) {
    $out = [];
    for ($i = 0; $i < (int)$n; $i++) {
        $out[] = create_key($credits, $days, $created_by);
    }
    return $out;
}

// List keys (admin) with optional filters
function list_keys($limit = 100, $only_unclaimed = false, $only_revoked = null) {
    $sql = 'SELECT * FROM ' . TBL_KEYS;
    $conds = [];
    $params = [];
    if ($only_unclaimed) { $conds[] = 'claimed_by IS NULL'; }
    if ($only_revoked === true) { $conds[] = 'revoked = 1'; }
    if ($only_revoked === false) { $conds[] = 'revoked = 0'; }
    if (!empty($conds)) $sql .= ' WHERE ' . implode(' AND ', $conds);
    $sql .= ' ORDER BY id DESC LIMIT :lim';
    $st = pdo()->prepare($sql);
    $st->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $st->execute($params);
    return $st->fetchAll();
}

// Get key details
function get_key_by_code($kcode) {
    $st = pdo()->prepare('SELECT * FROM ' . TBL_KEYS . ' WHERE kcode = ? LIMIT 1');
    $st->execute([strtolower(trim($kcode))]);
    return $st->fetch();
}

// Revoke a key (admin)
function revoke_key($kcode, $admin_id = null) {
    $st = pdo()->prepare('UPDATE ' . TBL_KEYS . ' SET revoked = 1 WHERE kcode = ?');
    $st->execute([strtolower(trim($kcode))]);
    return $st->rowCount() > 0;
}

// Claim a key (public)
function claim_key($kcode, $user_id) {
    $pdo = pdo();
    $pdo->beginTransaction();
    try {
        $code = strtolower(trim($kcode));
        if ($code === '') { $pdo->rollBack(); return false; }

        $st = $pdo->prepare('SELECT * FROM ' . TBL_KEYS . ' WHERE kcode = ? FOR UPDATE');
        $st->execute([$code]);
        $k = $st->fetch();
        if (!$k) { $pdo->rollBack(); return false; }
        if (!empty($k['claimed_by'])) { $pdo->rollBack(); return false; }
        if (!empty($k['revoked'])) { $pdo->rollBack(); return false; }

        if ((int)$k['credits'] > 0) change_credits((int)$user_id, (int)$k['credits']);
        if ((int)$k['days'] > 0) set_premium_days((int)$user_id, (int)$k['days']);

        $upd = $pdo->prepare('UPDATE ' . TBL_KEYS . ' SET claimed_by = ?, claimed_at = NOW() WHERE id = ?');
        $upd->execute([(int)$user_id, (int)$k['id']]);

        $pdo->commit();
        $credits = (int)$pdo->query('SELECT credits FROM ' . TBL_USERS . ' WHERE id=' . (int)$user_id)->fetchColumn();
        $exp = (string)$pdo->query("SELECT IFNULL(DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i'),'—') FROM " . TBL_USERS . " WHERE id=" . (int)$user_id)->fetchColumn();
        return [$exp, $credits];

    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// ==============================
// UTILS
// ==============================
function esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
