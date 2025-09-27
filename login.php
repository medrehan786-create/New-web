<?php
require __DIR__ . '/config.php';
if (is_logged_in()) { header('Location:/index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>⚡ Lykan Login</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body {
  background: radial-gradient(circle at 50% 50%, #0f0f0f, #1a1a1a);
  font-family: 'Inter', sans-serif;
}
.glass {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255,255,255,0.1);
  backdrop-filter: blur(25px);
  border-radius: 2rem;
  box-shadow:
    0 0 50px rgba(255, 50, 50, 0.4),
    inset 0 0 20px rgba(255,255,255,0.05);
  transform: perspective(800px) rotateX(3deg);
}
.btn-primary {
  background: linear-gradient(90deg, #ff1f1f, #ff6b6b);
  font-weight: 700;
  color: #fff;
  border-radius: 1rem;
  transition: transform .3s ease, box-shadow .3s ease;
}
.btn-primary:hover {
  transform: scale(1.05) translateZ(5px);
  box-shadow: 0 0 20px rgba(255,31,31,0.6);
}
.neon {
  text-shadow:
    0 0 8px rgba(255,31,31,0.9),
    0 0 20px rgba(255,31,31,0.7),
    0 0 30px rgba(255,31,31,0.5);
}
</style>
</head>
<body class="flex items-center justify-center min-h-screen px-4">
<div id="loginBox" class="glass p-10 max-w-md w-full space-y-8 text-center animate-fade-in">
  <h1 class="text-5xl md:text-6xl font-extrabold neon">⚡ Lykan</h1>
  <p class="text-white/70 text-lg">Secure login via Telegram WebApp — speed &amp; style</p>
  <div id="alert" class="text-red-400 font-semibold"></div>
  <button id="tgLoginBtn" class="w-full px-5 py-4 btn-primary shadow-lg">
    🔐 Login with Telegram
  </button>
</div>
<form id="tgForm" method="post" action="/tg_auth.php" style="display:none;">
  <input type="hidden" name="tg_init_data" id="tg_init_data"/>
</form>

<script>
const tg = window.Telegram?.WebApp;
if(!tg || !tg.initData){
  document.getElementById('alert').innerText =
    "❌ Telegram initData not found. Please open this page from the Telegram WebApp button.";
} else {
  tg.ready(); tg.expand();
  document.getElementById('tgLoginBtn').addEventListener('click', ()=>{
    document.getElementById('tg_init_data').value = tg.initData;
    document.getElementById('tgForm').submit();
  });
}
</script>
</body>
</html>
