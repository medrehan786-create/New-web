<?php require __DIR__ . '/config.php'; ?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Telegram Auth</title></head>
<body style="background:#0b1220;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh">
  Redirecting…
<script>
(function(){
  function parseHash(hash){ if(!hash) return {}; if(hash[0]==='#') hash=hash.slice(1);
    const o={}; hash.split('&').forEach(s=>{const i=s.indexOf('='); if(i>-1){o[decodeURIComponent(s.slice(0,i))]=decodeURIComponent(s.slice(i+1));}});
    return o;
  }
  const data = parseHash(location.hash);
  if(!data.id){ location.href='/login.php?err=auth_failed'; return; }
  const f=document.createElement('form'); f.method='POST'; f.action='/tg_auth.php';
  Object.keys(data).forEach(k=>{const i=document.createElement('input'); i.type='hidden'; i.name=k; i.value=data[k]; f.appendChild(i);});
  document.body.appendChild(f); f.submit();
})();
</script>
</body>
</html>
