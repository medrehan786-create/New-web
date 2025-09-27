<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

// read incoming JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Telegram WebApp sends initDataUnsafe.user + other fields,
// but we also need auth_date + hash to verify signature.
// So better send the whole initDataUnsafe instead of just user.
// (Adjust JS above accordingly if needed.)

if (!$data || !isset($data['id'])) {
    echo json_encode(['ok'=>false,'error'=>'No Telegram data received']);
    exit;
}

// verify signature
if (!verifyTelegramAuth($data)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}

// insert or update user
$user = upsert_user_from_tg($data);

// set session
$_SESSION['uid']  = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'];

echo json_encode(['ok'=>true]);
