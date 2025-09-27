<?php
require __DIR__ . '/config.php';

// Return JSON always
header('Content-Type: application/json');

// Read POST JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['init_data'])) {
    echo json_encode(['ok'=>false,'error'=>'No init_data received']);
    exit;
}

$initData = $input['init_data'];

// parse initData (query-string style) into array
parse_str($initData, $data);

// Basic checks
if (empty($data) || !isset($data['hash'])) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram data']);
    exit;
}

// Verify signature & freshness
if (!verifyTelegramAuth($data)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}

// Telegram returns user field as JSON string inside data['user'] sometimes; prefer direct fields if present
// Common fields: id, first_name, last_name, username, photo_url, auth_date
$userPayload = [];

// if there's a `user` JSON inside initData (some clients include), decode it
if (!empty($data['user'])) {
    $u = json_decode($data['user'], true);
    if (is_array($u)) {
        $userPayload = $u;
    }
}

// fallback to direct fields (some initData contains them directly)
foreach (['id','first_name','last_name','username','photo_url','auth_date'] as $k) {
    if (!isset($userPayload[$k]) && isset($data[$k])) $userPayload[$k] = $data[$k];
}

// final validation
if (empty($userPayload['id'])) {
    echo json_encode(['ok'=>false,'error'=>'No user id in Telegram data']);
    exit;
}

// Upsert user under your DB
$user = upsert_user_from_tg([
    'id'         => (string)$userPayload['id'],
    'username'   => $userPayload['username'] ?? null,
    'first_name' => $userPayload['first_name'] ?? null,
    'last_name'  => $userPayload['last_name'] ?? null,
    'photo_url'  => $userPayload['photo_url'] ?? null,
    'auth_date'  => $userPayload['auth_date'] ?? ($data['auth_date'] ?? time())
]);

// Safe session set (config.php already started session)
$_SESSION['uid']  = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'] ?? 'user';

// Success
echo json_encode(['ok'=>true]);
exit;
