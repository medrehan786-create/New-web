<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json');

// read JSON from frontend
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['init_data'])) {
    echo json_encode(['ok'=>false,'error'=>'No init_data received']);
    exit;
}

$initData = $input['init_data'];

// parse query string
parse_str($initData, $data);

// extract hash
if (!isset($data['hash'])) {
    echo json_encode(['ok'=>false,'error'=>'No hash in init_data']);
    exit;
}
$hash = $data['hash'];
unset($data['hash']);

// build data check string exactly like docs
ksort($data, SORT_STRING);
$pairs = [];
foreach ($data as $k => $v) {
    $pairs[] = $k.'='.$v;
}
$data_check_string = implode("\n", $pairs);

// compute secret key and HMAC
$secret_key = hash('sha256', TELEGRAM_BOT_TOKEN, true);
$hmac = hash_hmac('sha256', $data_check_string, $secret_key);

// optional freshness check (24h)
if (isset($data['auth_date']) && time() - (int)$data['auth_date'] > 86400) {
    echo json_encode(['ok'=>false,'error'=>'Auth data too old']);
    exit;
}

if (!hash_equals($hmac, $hash)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}

// decode user JSON inside query
if (!isset($data['user'])) {
    echo json_encode(['ok'=>false,'error'=>'No user info in init_data']);
    exit;
}
$userObj = json_decode($data['user'], true);
if (!$userObj || !isset($userObj['id'])) {
    echo json_encode(['ok'=>false,'error'=>'Invalid user JSON']);
    exit;
}

// upsert user in DB
$user = upsert_user_from_tg([
    'id'         => (string)$userObj['id'],
    'username'   => $userObj['username'] ?? null,
    'first_name' => $userObj['first_name'] ?? null,
    'last_name'  => $userObj['last_name'] ?? null,
    'photo_url'  => $userObj['photo_url'] ?? null,
    'auth_date'  => $data['auth_date'] ?? time()
]);

// set session
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['uid']  = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'];

// return success
echo json_encode(['ok'=>true,'user'=>$user]);
