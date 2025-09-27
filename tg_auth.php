<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$post = json_decode($raw,true);

if (empty($post['init_data'])) {
    echo json_encode(['ok'=>false,'error'=>'No Telegram data']);
    exit;
}

// parse & verify exactly as Telegram docs:
parse_str($post['init_data'],$data);

if (!verifyTelegramAuth($data)) {
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}

$user = upsert_user_from_tg([
    'id'=>(string)$data['id'],
    'username'=>$data['username']??null,
    'first_name'=>$data['first_name']??null,
    'last_name'=>$data['last_name']??null,
    'photo_url'=>$data['photo_url']??null,
    'auth_date'=>$data['auth_date']??time()
]);

$_SESSION['uid']=$user['id'];
$_SESSION['tgid']=$user['telegram_id'];
$_SESSION['role']=$user['role'];

echo json_encode(['ok'=>true]);
