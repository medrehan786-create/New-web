<?php
// ==============================
// LYKAN CONFIG (FULL + SAFE)
// ==============================

// ---- Database credentials ----
define('DB_HOST', 'sgp.domcloud.co');
define('DB_NAME', 'ranijhansischool_db');
define('DB_USER', 'ranijhansischool');
define('DB_PASS', '5V(_9EsBo89mu4U(Yx');
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

// ---- Telegram Bot details (OAuth) ----
define('BOT_ID', 8437832185); 
define('BOT_USERNAME', '@LOGINTOWEBBOT'); 
define('TELEGRAM_BOT_TOKEN', '8437832185:AAGql93tVpSyJtXQJwmLPvqcuVZpD6YtYy8'); 

// ---- Detect SITE_ORIGIN dynamically ----
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_ORIGIN', $protocol . $host);
define('RETURN_TO', SITE_ORIGIN . '/tg_callback.php');

// ---- Tables ----
define('TBL_KEYS', 'redeem_keys');
define('TBL_USERS', 'users');

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
// AUTH HELPERS
// ==============================
function is_logged_in() {
    return !empty($_SESSION['uid']);
}
function require_login() {
    if (!is_logged_in()) { header('Location: /login.php'); exit; }
}
function is_admin() {
    return !empty($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function require_admin() {
    if (!is_admin()) { header('Location: /index.php'); exit; }
}

// ==============================
// USER HELPERS
// ==============================
function load_user_by_id($id) {
    $st = pdo()->prepare('SELECT * FROM users WHERE id=?');
    $st->execute([(int)$id]);
    return $st->fetch();
}

function upsert_user_from_telegram_oauth($u) {
    $pdo  = pdo();
    $tgid = isset($u['id']) ? (string)$u['id'] : '';

    $st = $pdo->prepare('SELECT id, role FROM users WHERE telegram_id = ?');
    $st->execute([$tgid]);
    $row = $st->fetch();

    if ($row) {
        $st = $pdo->prepare('UPDATE users
            SET username=:username, first_name=:first_name, last_name=:last_name,
                photo_url=:photo_url, last_login_at=NOW()
            WHERE telegram_id=:tg');
    } else {
        $st = $pdo->prepare('INSERT INTO users
            (telegram_id, username, first_name, last_name, photo_url, last_login_at)
            VALUES (:tg, :username, :first_name, :last_name, :photo_url, NOW())');
    }

    $st->execute([
        ':tg'         => $tgid,
        ':username'   => $u['username'] ?? null,
        ':first_name' => $u['first_name'] ?? null,
        ':last_name'  => $u['last_name'] ?? null,
        ':photo_url'  => $u['photo_url'] ?? null,
    ]);

    $id   = $row ? (int)$row['id'] : (int)$pdo->lastInsertId();
    $role = $row ? $row['role']    : 'user';

    return ['id'=>$id,'telegram_id'=>$tgid,'role'=>$role];
}

// ==============================
// BUSINESS HELPERS
// ==============================
function change_credits($uid, $delta) {
    $pdo = pdo();
    $stmt = $pdo->prepare('UPDATE users SET credits = IFNULL(credits,0) + :d WHERE id=:u');
    $stmt->execute([':d'=>(int)$delta, ':u'=>(int)$uid]);
    return (int)$pdo->query('SELECT credits FROM users WHERE id='.(int)$uid)->fetchColumn();
}

function set_premium_days($uid, $days) {
    $pdo = pdo();
    if ((int)$days <= 0) {
        return (string)$pdo->query("SELECT IFNULL(DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i'),'—') FROM users WHERE id=".(int)$uid)->fetchColumn();
    }
    $stmt = $pdo->prepare('UPDATE users
        SET premium_until = CASE
            WHEN premium_until IS NULL OR premium_until < NOW()
            THEN DATE_ADD(NOW(), INTERVAL :days DAY)
            ELSE DATE_ADD(premium_until, INTERVAL :days DAY)
        END
        WHERE id=:uid');
    $stmt->execute([':days'=>(int)$days, ':uid'=>(int)$uid]);
    return (string)$pdo->query("SELECT IFNULL(DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i'),'—') FROM users WHERE id=".(int)$uid)->fetchColumn();
}

function increase_stats($uid, $checks, $lives) {
    $pdo = pdo();
    $stmt = $pdo->prepare('UPDATE users SET checks_total=checks_total+:c, lives_total=lives_total+:l WHERE id=:u');
    $stmt->execute([':c'=>(int)$checks, ':l'=>(int)$lives, ':u'=>(int)$uid]);
}

// ==============================
// KEY MANAGEMENT
// ==============================
function gen_key($credits, $days, $created_by) {
    $k = strtolower(bin2hex(random_bytes(8)));
    $stmt = pdo()->prepare('INSERT INTO ' . TBL_KEYS . ' (kcode, credits, days, created_by) VALUES (?,?,?,?)');
    $stmt->execute([$k,(int)$credits,(int)$days,(int)$created_by]);
    return $k;
}

function gen_keys_bulk($n,$credits,$days,$created_by){
    $out=[];
    for($i=0;$i<$n;$i++) $out[]=gen_key($credits,$days,$created_by);
    return $out;
}

function claim_key($kcode,$user_id){
    $pdo=pdo();
    $pdo->beginTransaction();
    try {
        $code=strtolower(trim($kcode));
        if($code===''){$pdo->rollBack();return false;}

        $st=$pdo->prepare('SELECT * FROM ' . TBL_KEYS . ' WHERE kcode=? FOR UPDATE');
        $st->execute([$code]);
        $k=$st->fetch();
        if(!$k || !empty($k['claimed_by'])){$pdo->rollBack();return false;}

        if((int)$k['credits']>0) change_credits((int)$user_id,(int)$k['credits']);
        if((int)$k['days']>0) set_premium_days((int)$user_id,(int)$k['days']);

        $upd=$pdo->prepare('UPDATE ' . TBL_KEYS . ' SET claimed_by=?, claimed_at=NOW() WHERE id=?');
        $upd->execute([(int)$user_id,(int)$k['id']]);

        $pdo->commit();
        $credits=(int)$pdo->query('SELECT credits FROM users WHERE id='.(int)$user_id)->fetchColumn();
        $exp=(string)$pdo->query("SELECT IFNULL(DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i'),'—') FROM users WHERE id=".(int)$user_id)->fetchColumn();
        return [$exp,$credits];

    } catch(Exception $e){
        $pdo->rollBack();
        return false;
    }
}
?>
