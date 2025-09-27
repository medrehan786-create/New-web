<?php
// tg_auth.php
require __DIR__ . '/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

// Utility: read input (JSON or form POST)
$rawPost = file_get_contents('php://input');
$data = null;

// If JSON body like { "init_data": "id=...&hash=..." }
if ($rawPost) {
    $maybe = json_decode($rawPost, true);
    if (is_array($maybe) && isset($maybe['init_data'])) {
        $rawInit = $maybe['init_data'];
    } else {
        // if TG WebApp sent full fields in JSON (fragment-post fallback)
        $maybeKeys = is_array($maybe) ? $maybe : [];
        if (!empty($maybeKeys['id'])) {
            // treat $maybe as the direct user data
            $userData = $maybe;
            $rawInit = null;
        } else {
            $rawInit = null;
        }
    }
} else {
    // Form POST fallback: 'tg_init_data' field contains the url-encoded init string
    $rawInit = $_POST['tg_init_data'] ?? null;
}

// Helper: verify the Telegram `initData` signed string.
// Accepts a urlencoded querystring (key=val&key2=val2&hash=...) and returns parsed array on success or false
function verify_init_data(string $initData) {
    // parse_str will urldecode automatically
    parse_str($initData, $data);
    if (!is_array($data) || !isset($data['hash'])) return false;
    $check_hash = $data['hash'];
    unset($data['hash']);

    // Sort by keys (bytewise)
    ksort($data, SORT_STRING);

    // Build data_check_string: "key=value\nkey2=value2\n..."
    $pairs = [];
    foreach ($data as $k => $v) {
        // Per Telegram: use the raw values (already decoded by parse_str)
        $pairs[] = $k . '=' . $v;
    }
    $data_check_string = implode("\n", $pairs);

    // Secret key: SHA256(bot_token) as binary
    if (!defined('TELEGRAM_BOT_TOKEN') || empty(TELEGRAM_BOT_TOKEN)) return false;
    $secret_key = hash('sha256', TELEGRAM_BOT_TOKEN, true);

    // HMAC-SHA256 over data_check_string using the secret_key
    $hmac = hash_hmac('sha256', $data_check_string, $secret_key);

    // Compare provided hash and HMAC in constant-time
    if (!hash_equals($hmac, $check_hash)) return false;

    // freshness check (auth_date within 1 day)
    if (isset($data['auth_date']) && (time() - (int)$data['auth_date']) > 86400) {
        return false;
    }

    return $data;
}

// If rawInit provided, verify it and extract user data
$userData = null;
if (!empty($rawInit)) {
    $verified = verify_init_data($rawInit);
    if ($verified === false) {
        echo json_encode(['ok'=>false, 'error'=>'Invalid Telegram signature or expired init_data']);
        exit;
    }
    $userData = $verified;
} else {
    // maybe we already parsed the JSON directly as fields in $maybe (see above)
    // try to read fallback POST fields directly
    if (!empty($_POST['id']) || !empty($_POST['user_id'])) {
        // collect fields from POST
        $userData = [];
        foreach ($_POST as $k=>$v) {
            $userData[$k] = $v;
        }
    } else {
        echo json_encode(['ok'=>false, 'error'=>'No Telegram data received']);
        exit;
    }
}

// At this point $userData contains Telegram fields (id, username, first_name, last_name, photo_url, auth_date, ...)
// Upsert user into DB (you should implement upsert_user_from_tg in config.php or here)
try {
    $pdo = pdo(); // your database connection function from config.php

    // Basic upsert example if you don't have helper:
    $tgid = $userData['id'];
    $st = $pdo->prepare("SELECT * FROM users WHERE telegram_user_id = ?");
    $st->execute([$tgid]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $upd = $pdo->prepare("UPDATE users SET username = :username, first_name = :first_name, last_name = :last_name, photo_url = :photo, last_login_at = NOW() WHERE id = :id");
        $upd->execute([
            ':username' => $userData['username'] ?? null,
            ':first_name' => $userData['first_name'] ?? null,
            ':last_name' => $userData['last_name'] ?? null,
            ':photo' => $userData['photo_url'] ?? null,
            ':id' => $user['id']
        ]);
        $finalUser = $user;
        $finalUser['username'] = $userData['username'] ?? $user['username'];
        $finalUser['first_name'] = $userData['first_name'] ?? $user['first_name'];
        $finalUser['last_name'] = $userData['last_name'] ?? $user['last_name'];
        $finalUser['photo_url'] = $userData['photo_url'] ?? $user['photo_url'];
    } else {
        $ins = $pdo->prepare("INSERT INTO users (telegram_user_id, username, first_name, last_name, photo_url, auth_date, last_login_at) VALUES (:tgid, :username, :first_name, :last_name, :photo, :auth_date, NOW())");
        $ins->execute([
            ':tgid' => $tgid,
            ':username' => $userData['username'] ?? null,
            ':first_name' => $userData['first_name'] ?? null,
            ':last_name' => $userData['last_name'] ?? null,
            ':photo' => $userData['photo_url'] ?? null,
            ':auth_date' => $userData['auth_date'] ?? time()
        ]);
        $newId = $pdo->lastInsertId();
        // Load the inserted row
        $st2 = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $st2->execute([$newId]);
        $finalUser = $st2->fetch(PDO::FETCH_ASSOC);
    }

    // Set session
    $_SESSION['uid'] = $finalUser['id'];
    $_SESSION['tgid'] = $finalUser['telegram_user_id'];
    $_SESSION['role'] = $finalUser['role'] ?? 'user';

    // Return success JSON
    echo json_encode(['ok'=>true, 'redirect'=>'/index.php']);
    exit;

} catch (Exception $e) {
    // In production, log the exception instead of echoing
    echo json_encode(['ok'=>false, 'error'=>'Server DB error: '.$e->getMessage()]);
    exit;
}
