<?php
require __DIR__ . '/config.php';
if (is_logged_in()) { header('Location:/index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Onyx — Login</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{background:radial-gradient(circle at 30% 30%,#1f1f1f,#0b0b0b);font-family:'Inter',sans-serif;min-height:100vh;}
.card{background:rgba(255,255,255,0.05);backdrop-filter:blur(15px);border-radius:2rem;padding:2rem;box-shadow:0 20px 40px rgba(0,0,0,.6);}
h1{font-size:3rem;font-weight:800;color:#fff;text-shadow:0 0 15px #ff1f1f;}
.btn{display:inline-block;width:100%;padding:1rem 2rem;border-radius:1rem;font-size:1.2rem;
background:linear-gradient(135deg,#ff1f1f,#ff6b6b);color:#fff;font-weight:700;transition:all .3s;}
.btn:hover{transform:scale(1.05);}
</style>
</head>
<body class="flex items-center justify-center">
  <div class="card w-full max-w-md text-center">
    <h1>⚡ Onyx</h1>
    <p class="text-gray-300 mb-4">Secure login via Telegram Web App</p>
    <div id="alert" class="text-red-400 font-semibold mb-2"></div>
    <button id="tgLoginBtn" class="btn">🔐 Login with Telegram</button>
  </div>
<script>
const tg = window.Telegram?.WebApp;
if (!tg) {
  document.getElementById('alert').innerText = "❌ Open inside Telegram Web App!";
} else {
  tg.ready(); tg.expand();
  document.getElementById('tgLoginBtn').addEventListener('click', () => {
    const u = tg.initDataUnsafe?.user;
    if (!u) {
      document.getElementById('alert').innerText = "❌ Telegram user data missing!";
      return;
    }
    const payload = {
      id: u.id,
      first_name: u.first_name,
      last_name: u.last_name,
      username: u.username,
      photo_url: u.photo_url,
      auth_date: tg.initDataUnsafe.auth_date,
      hash: tg.initDataUnsafe.hash
    };
    fetch('/tg_auth.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(payload)
    }).then(r=>r.json()).then(res=>{
      if(res.ok){ window.location.href='/index.php'; }
      else{ document.getElementById('alert').innerText="❌ "+res.error; }
    }).catch(e=>{document.getElementById('alert').innerText='Error: '+e;});
  });
}
</script>
</body>
</html>
