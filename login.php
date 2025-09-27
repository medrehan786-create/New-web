<?php
require __DIR__ . '/config.php';
if (is_logged_in()) { header('Location:/index.php'); exit; }

$error = '';

function verify_init_data($initData) {
    parse_str($initData, $data);
    if (!isset($data['hash'])) return false;

    $check_hash = $data['hash'];
    unset($data['hash']);

    ksort($data);
    $data_check_arr = [];
    foreach ($data as $k => $v) { $data_check_arr[] = $k.'='.$v; }
    $data_check_string = implode("\n", $data_check_arr);

    $secret_key = hash('sha256', TELEGRAM_BOT_TOKEN, true);
    $hmac = hash_hmac('sha256', $data_check_string, $secret_key);

    return hash_equals($hmac, $check_hash) ? $data : false;
}

// Only allow POST from Telegram WebApp with valid initData
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['tg_init_data'])) {
    $userData = verify_init_data($_POST['tg_init_data']);
    if (!$userData) {
        die("❌ Invalid Telegram authentication!");
    }

    $pdo = pdo();
    $tgid = $userData['id'];

    // Check if user exists
    $st = $pdo->prepare("SELECT * FROM users WHERE telegram_user_id=?");
    $st->execute([$tgid]);
    $user = $st->fetch();

    if ($user) {
        $upd = $pdo->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?");
        $upd->execute([$user['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO users
            (telegram_user_id, username, first_name, last_name, photo_url, auth_date, last_login_at)
            VALUES (:id, :username, :first_name, :last_name, :photo, :auth_date, NOW())");
        $ins->execute([
            ':id' => $userData['id'],
            ':username' => $userData['username'] ?? null,
            ':first_name' => $userData['first_name'] ?? null,
            ':last_name' => $userData['last_name'] ?? null,
            ':photo' => $userData['photo_url'] ?? null,
            ':auth_date' => $userData['auth_date'] ?? null
        ]);
        $user = load_user_by_id($pdo->lastInsertId());
    }

    // Login
    $_SESSION['uid'] = $user['id'];
    $_SESSION['tgid'] = $user['telegram_user_id'];
    $_SESSION['role'] = $user['role'];
    header('Location:/index.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Onyx Telegram Login</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{ background: linear-gradient(135deg,#0f0f0f,#1a1a1a); font-family:'Inter',sans-serif; }
.glass{ background: rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.1); backdrop-filter: blur(20px); border-radius:2rem; box-shadow: 0 0 25px rgba(255,31,31,0.3);}
.btn-primary{ background: linear-gradient(90deg,#ff1f1f,#ff6b6b); font-weight:700;color:#fff; transition: all 0.3s ease; }
.btn-primary:hover{ transform: scale(1.05); filter: brightness(1.1);}
.neon{ text-shadow:0 0 8px rgba(255,31,31,0.9),0 0 20px rgba(255,31,31,0.7),0 0 30px rgba(255,31,31,0.5);}
</style>
</head>
<body class="flex items-center justify-center min-h-screen px-4">

<div id="loginBox" class="glass p-10 max-w-md w-full space-y-8 text-center">
    <h1 class="text-6xl font-extrabold neon">Onyx</h1>
    <p class="text-white/70 text-lg">Login only via Telegram Web App</p>
    <div id="alert" class="text-red-400 font-semibold"></div>
    <button id="tgLoginBtn" class="w-full px-5 py-4 btn-primary rounded-2xl shadow-lg hover:brightness-105">
        🔐 Login with Telegram
    </button>
</div>

<form id="tgForm" method="post" style="display:none;">
    <input type="hidden" name="tg_init_data" id="tg_init_data"/>
</form>

<script>
const tg = window.Telegram?.WebApp;
if(!tg){
    document.getElementById('loginBox').innerHTML = "<p class='text-red-400 font-bold text-xl'>❌ Open this page inside Telegram Web App only!</p>";
} else {
    tg.ready(); tg.expand();
    document.getElementById('tgLoginBtn').addEventListener('click', ()=>{
        const initData = tg.initData;
        if(!initData){
            document.getElementById('alert').innerText = "❌ Telegram initData missing!";
            return;
        }
        document.getElementById('tg_init_data').value = initData;
        document.getElementById('tgForm').submit();
    });
}
</script>
</body>
</html>
