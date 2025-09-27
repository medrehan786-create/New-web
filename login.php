<?php
require __DIR__ . '/config.php';
if (is_logged_in()) { header('Location: /index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Onyx — GOD LEVEL Login</title>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:radial-gradient(circle at 20% 20%,#0b0b0b,#050505);font-family:Inter,ui-sans-serif;color:#eee}
.card{width:96%;max-width:520px;padding:36px;border-radius:20px;background:linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.01));backdrop-filter:blur(12px);box-shadow:0 20px 60px rgba(0,0,0,0.6);border:1px solid rgba(255,31,31,0.06);text-align:center}
.logo{font-size:48px;font-weight:800;color:#ff6b6b;text-shadow:0 0 12px rgba(255,31,31,0.8)}
.lead{color:#cbd5e1;margin-top:6px;margin-bottom:22px}
.btn{display:inline-block;width:100%;padding:14px;border-radius:12px;background:linear-gradient(90deg,#ff1f1f,#ff6b6b);color:#08080a;font-weight:800;box-shadow:0 8px 30px rgba(255,31,31,0.18);cursor:pointer}
.small{font-size:13px;color:#9ca3af;margin-top:12px}
#error{color:#ffb4b4;font-weight:700;margin-top:10px}
</style>
</head>
<body>
  <div class="card">
    <div class="logo">⚡ Onyx</div>
    <div class="lead">Secure Telegram WebApp login — official verification</div>

    <button id="loginBtn" class="btn">🔐 Login with Telegram</button>
    <div id="error" role="status" aria-live="polite"></div>
    <div class="small">Open via your bot's WebApp button (do not open directly in browser)</div>
  </div>

<script>
const btn = document.getElementById('loginBtn');
const err = document.getElementById('error');

function showErr(text){ err.textContent = text; }

if (!window.Telegram || !Telegram.WebApp) {
  showErr('Open this page inside Telegram Web App.');
  btn.disabled = true;
} else {
  Telegram.WebApp.ready();
  Telegram.WebApp.expand();
  btn.addEventListener('click', async () => {
    showErr('');
    const initData = Telegram.WebApp.initData; // RAW signed string
    if (!initData) { showErr('Telegram initData not available'); return; }

    try {
      const res = await fetch('tg_auth.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ init_data: initData })
      });
      const j = await res.json();
      if (j.ok) {
        location.href = '/index.php';
      } else {
        showErr('❌ ' + (j.error || 'Authentication failed'));
      }
    } catch (e) {
      console.error(e);
      showErr('Network error');
    }
  });
}
</script>
</body>
</html>
