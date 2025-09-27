<?php
require __DIR__ . '/config.php';

// Telegram WebApp sends JSON user data
$data = json_decode(file_get_contents("php://input"), true);

header('Content-Type: application/json');

// Check if data exists
if (!$data || !isset($data['id'])) {
    echo json_encode(['ok'=>false,'error'=>'No Telegram data received']);
    exit;
}

// Optional: verify Telegram auth signature
function verifyTelegram($data){
    if (!isset($data['hash'])) return false;

    $hash = $data['hash'];
    unset($data['hash']);

    ksort($data, SORT_STRING);
    $check_str = implode("\n", array_map(fn($k,$v)=>"$k=$v", array_keys($data), $data));

    $secret_key = hash('sha256', TELEGRAM_BOT_TOKEN, true);
    $hmac = hash_hmac('sha256', $check_str, $secret_key);

    // freshness check (24h)
    if (isset($data['auth_date']) && time() - (int)$data['auth_date'] > 86400) return false;

    return hash_equals($hmac, $hash);
}

// Optional: uncomment if you want signature verification
/*
if (!verifyTelegram($data)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}
*/

// Insert or update user in DB
$user = upsert_user_from_tg([
    'id'         => (string)$data['id'],
    'username'   => $data['username'] ?? null,
    'first_name' => $data['first_name'] ?? null,
    'last_name'  => $data['last_name'] ?? null,
    'photo_url'  => $data['photo_url'] ?? null,
    'auth_date'  => $data['auth_date'] ?? time()
]);

// Set session
$_SESSION['uid']  = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'];

// Return success
echo json_encode(['ok'=>true]);
