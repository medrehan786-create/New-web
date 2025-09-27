<?php
require __DIR__.'/config.php';
header('Content-Type: application/json');

// Get JSON POST from JS
$data = json_decode(file_get_contents('php://input'), true);
if(empty($data['initData'])){
    echo json_encode(['ok'=>false,'error'=>'No Telegram data received']);
    exit;
}

parse_str($data['initData'], $tgUserData);

if(!verifyTelegramAuth($tgUserData)){
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}

// Upsert user
$user = upsert_user_from_tg([
    'id'=>$tgUserData['id'],
    'username'=>$tgUserData['username']??null,
    'first_name'=>$tgUserData['first_name']??null,
    'last_name'=>$tgUserData['last_name']??null,
    'photo_url'=>$tgUserData['photo_url']??null,
    'auth_date'=>$tgUserData['auth_date']??time()
]);

// Set session
$_SESSION['uid'] = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'];

echo json_encode(['ok'=>true]);
