<?php
require __DIR__ . '/config.php';
if (is_logged_in()) { header('Location: /index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Onyx — GOD LEVEL Login</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
/* ------------------ GLOBAL ------------------ */
*{margin:0;padding:0;box-sizing:border-box;}
body{
    height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background: linear-gradient(135deg,#0f0f0f,#1a1a1a);
    font-family:'Inter',sans-serif;
    overflow:hidden;
}
.glass{
    background: rgba(0,0,0,0.25);
    border:1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(20px);
    border-radius:2rem;
    box-shadow: 0 0 50px rgba(255,31,31,0.4);
    padding:50px;
    text-align:center;
    max-width:400px;
    width:90%;
    transform: translateZ(0);
}
h1{
    font-family:'Orbitron',sans-serif;
    font-size:4rem;
    color:#ff1f1f;
    text-shadow: 0 0 8px #ff1f1f, 0 0 20px #ff6b6b, 0 0 30px #ff1f1f80;
    margin-bottom:20px;
}
p{
    color: rgba(255,255,255,0.7);
    margin-bottom:30px;
    font-size:1rem;
}
#alert{
    color:#ff4d4d;
    font-weight:600;
    margin-bottom:15px;
}

/* ------------------ BUTTON ------------------ */
.btn-neon{
    display:inline-block;
    padding:15px 30px;
    font-size:1.1rem;
    font-weight:700;
    color:#fff;
    text-transform:uppercase;
    border:none;
    border-radius:50px;
    cursor:pointer;
    background: linear-gradient(90deg,#ff1f1f,#ff6b6b);
    box-shadow: 0 0 20px #ff1f1f80, 0 0 40px #ff6b6b50;
    transition: all 0.3s ease;
}
.btn-neon:hover{
    transform: scale(1.05) rotate(-1deg);
    box-shadow: 0 0 30px #ff1f1f, 0 0 50px #ff6b6b;
}

/* ------------------ 3D FLOAT ------------------ */
.glass::before{
    content:'';
    position:absolute;
    top:-50%; left:-50%;
    width:200%; height:200%;
    background: radial-gradient(circle at center, rgba(255,31,31,0.1), transparent 70%);
    pointer-events:none;
    animation: float 6s ease-in-out infinite;
}
@keyframes float{
    0%,100%{transform:translateY(0);}
    50%{transform:translateY(20px);}
}
</style>
</head>
<body>

<div class="glass">
    <h1>Onyx</h1>
    <p>Secure login via Telegram Web App — built for speed & style</p>
    <div id="alert"></div>
    <button id="tgLoginBtn" class="btn-neon">🔐 Login with Telegram</button>
</div>

<form id="tgForm" method="post" action="/tg_auth.php" style="display:none;">
    <input type="hidden" name="tg_init_data" id="tg_init_data"/>
</form>

<script>
const tg = window.Telegram?.WebApp;
if(!tg){
    document.getElementById('alert').innerText = "❌ Open this page inside Telegram Web App only!";
} else {
    tg.ready(); tg.expand();
    document.getElementById('tgLoginBtn').addEventListener('click', ()=>{
        const initData = tg.initData || tg.initDataUnsafe;
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
