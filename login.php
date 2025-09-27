<?php require __DIR__.'/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Onyx — GOD LEVEL</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
body{margin:0; font-family:Inter,sans-serif; background:#0b0b0b; display:flex; justify-content:center; align-items:center; min-height:100vh;}
.glass{background: rgba(0,0,0,0.25); border-radius:2rem; padding:3rem; text-align:center; backdrop-filter:blur(15px); box-shadow: 0 0 25px rgba(255,31,31,0.6);}
.btn-primary{background:linear-gradient(90deg,#ff1f1f,#ff6b6b); color:#fff; font-weight:700; padding:1rem 2rem; border-radius:2rem; transition:0.3s; cursor:pointer;}
.btn-primary:hover{transform:scale(1.05); filter:brightness(1.1);}
.neon{color:#ff1f1f; text-shadow:0 0 8px rgba(255,31,31,0.9),0 0 20px rgba(255,31,31,0.7),0 0 30px rgba(255,31,31,0.5);}
</style>
</head>
<body>

<div class="glass">
    <h1 class="text-6xl font-extrabold neon">Onyx</h1>
    <p class="text-white/70 text-lg mt-2 mb-6">Secure login via Telegram Web App</p>
    <button id="tgLoginBtn" class="btn-primary">🔐 Login with Telegram</button>
</div>

<script>
const tg = window.Telegram?.WebApp;
if(!tg){
    alert("Open this page inside Telegram Web App!");
    throw new Error("Telegram Web App not found");
}

tg.ready();
tg.expand();

document.getElementById('tgLoginBtn').addEventListener('click',()=>{
    const initData = tg.initData;
    if(!initData){
        alert("❌ Telegram initData not found!");
        return;
    }
    fetch('tg_auth.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({initData})
    }).then(r=>r.json()).then(res=>{
        if(res.ok) window.location.href='/index.php';
        else alert("❌ "+res.error);
    });
});
</script>

</body>
</html>
