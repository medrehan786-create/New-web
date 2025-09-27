<?php
require __DIR__.'/config.php';
if(is_logged_in()){ header('Location:/index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Onyx Login</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { background: linear-gradient(135deg,#0f0f0f,#1a1a1a); font-family:'Inter',sans-serif; }
.glass { background: rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.1); backdrop-filter: blur(20px); border-radius:2rem; box-shadow:0 0 25px rgba(255,31,31,0.3);}
.btn-primary { background: linear-gradient(90deg,#ff1f1f,#ff6b6b); font-weight:700;color:#fff; transition: all 0.3s ease;}
.btn-primary:hover{ transform: scale(1.05); filter: brightness(1.1);}
.neon{ text-shadow:0 0 8px rgba(255,31,31,0.9),0 0 20px rgba(255,31,31,0.7),0 0 30px rgba(255,31,31,0.5);}
</style>
</head>
<body class="flex items-center justify-center min-h-screen px-4">

<div class="glass p-10 max-w-md w-full space-y-8 text-center">
    <h1 class="text-6xl font-extrabold neon">Onyx</h1>
    <p class="text-white/70 text-lg">Secure login via Telegram Web App</p>
    <div id="alert" class="text-red-400 font-semibold"></div>
    <button id="tgLoginBtn" class="w-full px-5 py-4 btn-primary rounded-2xl shadow-lg">🔐 Login with Telegram</button>
</div>

<form id="tgForm" method="POST" action="tg_auth.php" style="display:none;">
    <input type="hidden" name="tg_data" id="tg_data"/>
</form>

<script>
const tg = window.Telegram?.WebApp;
if(!tg){
    document.getElementById('alert').innerText="❌ Open this page inside Telegram Web App only!";
}else{
    tg.ready(); tg.expand();
    document.getElementById('tgLoginBtn').addEventListener('click',()=>{
        const data = JSON.stringify(tg.initDataUnsafe || {});
        if(!data){
            document.getElementById('alert').innerText="❌ Telegram data missing!";
            return;
        }
        document.getElementById('tg_data').value=data;
        document.getElementById('tgForm').submit();
    });
}
</script>
</body>
</html>
