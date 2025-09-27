<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

// read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['init_data'])) {
    echo json_encode(['ok'=>false,'error'=>'No init_data received']);
    exit;
}

$initData = $input['init_data'];

// parse initData (query-string style) into $data
parse_str($initData, $data);

// basic validation
if (empty($data) || !isset($data['hash'])) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram data']);
    exit;
}

// verify signature & freshness
if (!verifyTelegramAuth($data)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}

// Telegram may include user info either as a JSON string in $data['user'] or as fields directly
$userPayload = [];
if (!empty($data['user'])) {
    $u = json_decode($data['user'], true);
    if (is_array($u)) $userPayload = $u;
}
// fill from direct fields if missing
foreach (['id','first_name','last_name','username','photo_url','auth_date'] as $k) {
    if (!isset($userPayload[$k]) && isset($data[$k])) $userPayload[$k] = $data[$k];
}

if (empty($userPayload['id'])) {
    echo json_encode(['ok'=>false,'error'=>'No user id in Telegram data']);
    exit;
}

// upsert user in DB
$user = upsert_user_from_tg([
    'id' => (string)$userPayload['id'],
    'username' => $userPayload['username'] ?? null,
    'first_name' => $userPayload['first_name'] ?? null,
    'last_name' => $userPayload['last_name'] ?? null,
    'photo_url' => $userPayload['photo_url'] ?? null,
    'auth_date' => $userPayload['auth_date'] ?? ($data['auth_date'] ?? time())
]);

// set session (config already started session)
$_SESSION['uid']  = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'] ?? 'user';

echo json_encode(['ok'=>true]);
exit;
