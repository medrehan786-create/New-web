<?php
// login.php
require __DIR__ . '/config.php';
session_start();

if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Onyx — Telegram Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: 'Inter', sans-serif; background: radial-gradient(1000px 600px at 10% 10%, #240000 0%, #070707 35%, #050505 100%); color: #eee; }
  .glass { background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); border:1px solid rgba(255,31,31,0.07); backdrop-filter: blur(10px); }
  .neon { text-shadow: 0 0 12px rgba(255,31,31,0.9), 0 0 28px rgba(255,31,31,0.5) }
  .btn-primary { background: linear-gradient(90deg,#ff1f1f,#ff6b6b); color: #0b0b0b; font-weight: 800; }
  .small-muted { color: rgba(255,255,255,0.65) }
  .accent { color:#ffb0b0 }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">

<!-- Container -->
<div class="max-w-3xl w-full grid md:grid-cols-2 gap-8 items-center">
  <!-- Left: Branding -->
  <div class="glass p-8 rounded-3xl shadow-2xl">
    <div class="flex items-center gap-4">
      <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-red-700 to-red-400 flex items-center justify-center text-3xl font-extrabold neon">⚡</div>
      <div>
        <div class="text-3xl font-extrabold neon">Onyx — <span class="accent">GOD LEVEL</span></div>
        <div class="text-sm small-muted mt-1">Secure login via Telegram Web App — built for speed & style</div>
      </div>
    </div>

    <div class="mt-6">
      <p class="text-sm small-muted">Benefits:</p>
      <ul class="mt-3 space-y-2 text-sm">
        <li>• Passwordless Telegram-auth (secure HMAC verification)</li>
        <li>• Auto account upsert & session setup</li>
        <li>• Works in Telegram Web App, or via redirect fallback</li>
      </ul>
    </div>

    <div class="mt-6 flex gap-3">
      <button id="tgLoginBtn" class="btn-primary px-6 py-3 rounded-2xl shadow-lg w-full">🔐 Login with Telegram</button>
    </div>

    <div id="status" class="mt-4 text-xs small-muted"></div>
  </div>

  <!-- Right: visual + tips -->
  <div class="p-8 rounded-3xl">
    <div class="glass p-6 rounded-2xl">
      <h3 class="text-lg font-bold neon">How to use</h3>
      <ol class="mt-3 text-sm small-muted list-decimal list-inside space-y-2">
        <li>Open this page inside Telegram (Web App or in-app browser).</li>
        <li>Click <strong>Login with Telegram</strong>. The Telegram WebApp SDK provides signed initData.</li>
        <li>If everything verifies, you'll be redirected to the dashboard.</li>
      </ol>
      <div class="mt-4 text-xs small-muted">If you open this page outside Telegram, the page will attempt to guide you to use the Telegram Web App or use the fallback redirect flow.</div>
    </div>
  </div>
</div>

<!-- Hidden form for fallback if needed -->
<form id="tgForm" method="post" action="/tg_auth.php" style="display:none;">
  <input type="hidden" name="tg_init_data" id="tg_init_data">
</form>

<script>
const status = document.getElementById('status');
const tg = window.Telegram?.WebApp;

function showStatus(txt, isError = false){
  status.textContent = txt;
  status.classList.toggle('text-red-400', isError);
  status.classList.toggle('text-green-400', !isError);
}

if (!tg) {
  // Not inside Telegram WebApp — show friendly message but still allow fallback.
  showStatus('⚠️ Please open this page from Telegram Web App for a secure experience. Fallback available.', true);
}

// Click handler — send initData to server via JSON POST
document.getElementById('tgLoginBtn').addEventListener('click', async () => {
  if (!tg) {
    // If no Telegram WebApp, fallback to instructing user to open via tg:// or show fragment handler
    showStatus('Opening fallback flow — please use Telegram to open the Web App.', true);
    // Optional: redirect to bot with deep-link instructions
    // window.location = 'https://t.me/YourBot?start=login';
    return;
  }

  try {
    tg.ready();
    tg.expand();
    const initData = tg.initData || tg.initDataUnsafe?.raw || null;
    if (!initData) {
      showStatus('❌ Telegram initData not found. Make sure you opened this page inside Telegram.', true);
      return;
    }

    showStatus('Sending authentication to server…');

    const res = await fetch('/tg_auth.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({init_data: initData})
    });

    const json = await res.json();
    if (json.ok) {
      showStatus('✅ Auth successful — redirecting…');
      // Use absolute redirect if provided
      window.location = json.redirect || '/index.php';
    } else {
      showStatus('❌ '+(json.error || 'Authentication failed'), true);
      // If server suggests form-post fallback (rare), we can try that:
      if (json.fallback && json.raw_init) {
        document.getElementById('tg_init_data').value = json.raw_init;
        document.getElementById('tgForm').submit();
      }
    }

  } catch (err) {
    console.error(err);
    showStatus('❌ Network error while authenticating', true);
  }
});
</script>
</body>
</html>
