<?php
// tg_return.php
require __DIR__ . '/config.php';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Telegram Auth Redirect</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { background:#07121a; color:#fff; font-family:Inter, sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; }
    .card { background: rgba(255,255,255,0.03); padding: 28px; border-radius: 12px; border:1px solid rgba(255,255,255,0.04); text-align:center; }
  </style>
</head>
<body>
  <div class="card">
    <div style="font-weight:800;font-size:18px;margin-bottom:8px">Signing you in…</div>
    <div style="font-size:13px;color:#aac0d6">If nothing happens, please make sure you opened this in Telegram Web App.</div>
  </div>

<script>
(function(){
  function parseHash(h) {
    if (!h) return {};
    if (h[0] === '#') h = h.slice(1);
    const obj = {};
    h.split('&').forEach(pair => {
      const idx = pair.indexOf('=');
      if (idx === -1) return;
      const k = decodeURIComponent(pair.slice(0, idx));
      const v = decodeURIComponent(pair.slice(idx+1));
      obj[k] = v;
    });
    return obj;
  }

  const data = parseHash(location.hash);
  // If no id found, redirect to login with error
  if (!data.id) {
    window.location = '/login.php?err=auth_failed';
    return;
  }

  // Build a form and POST to tg_auth.php (fallback)
  const f = document.createElement('form');
  f.method = 'POST';
  f.action = '/tg_auth.php';
  for (const k in data) {
    const inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = k;
    inp.value = data[k];
    f.appendChild(inp);
  }
  document.body.appendChild(f);
  f.submit();
})();
</script>
</body>
</html>
