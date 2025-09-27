<?php
require __DIR__.'/config.php';

if($_SERVER['REQUEST_METHOD']!=='POST' || empty($_POST['tg_init_data'])){
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'error'=>'No Telegram data received']);
    exit;
}

$initData = $_POST['tg_init_data'];
parse_str($initData,$data);

if(!verifyTelegramAuth($data)){
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'error'=>'Invalid Telegram signature']);
    exit;
}

// Upsert user
$user = upsert_user_from_tg([
    'id'=>$data['id'],
    'username'=>$data['username']??null,
    'first_name'=>$data['first_name']??null,
    'last_name'=>$data['last_name']??null,
    'photo_url'=>$data['photo_url']??null,
    'auth_date'=>$data['auth_date']??time()
]);

// Set session
$_SESSION['uid']=$user['id'];
$_SESSION['tgid']=$user['telegram_id'];
$_SESSION['role']=$user['role'];

// Redirect to index/dashboard
header('Location:/index.php');
exit;
