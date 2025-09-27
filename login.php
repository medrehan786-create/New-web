<?php
require __DIR__ . '/config.php';
if(is_logged_in()){ header('Location: /index.php'); exit; }

$oauth_link = "https://oauth.telegram.org/auth?bot_id=".BOT_ID.
"&origin=".urlencode(SITE_ORIGIN).
"&embed=1&request_access=write".
"&return_to=".urlencode(RETURN_TO);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Lykan Telegram OAuth Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{ background: radial-gradient(circle at 20% 20%, #0f0f0f,#050505); font-family:'Inter',sans-serif; display:flex; justify-content:center; align-items:center; height:100vh;}
.card{ padding:40px; border-radius:24px; background: rgba(255,255,255,0.03); backdrop-filter: blur(20px); text-align:center; box-shadow:0 25px 50px rgba(255,31,31,0.5); transform: rotateX(8deg) rotateY(-8deg); transition: transform 0.4s;}
.card:hover{ transform: rotateX(0deg) rotateY(0deg);}
.btn{ padding:16px; width:100%; border-radius:16px; background: linear-gradient(90deg,#ff1f1f,#ff6b6b); color:#fff; font-weight:700; font-size:1.2rem; cursor:pointer; transition: all 0.3s;}
.btn:hover{ transform:scale(1.05); filter:brightness(1.1);}
</style>
</head>
<body>
<div class="card">
  <h1 class="text-5xl font-extrabold mb-4">Lykan</h1>
  <p class="text-gray-300 mb-6">Secure login via Telegram OAuth</p>
  <a href="<?= $oauth_link ?>" class="btn">🔐 Login with Telegram</a>
</div>
</body>
</html>
