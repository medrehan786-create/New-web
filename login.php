<?php
require __DIR__ . '/config.php';
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login with Telegram</title>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap">
  <style>
    *{box-sizing:border-box;}
    body{
      margin:0;
      font-family:'Poppins',sans-serif;
      background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
      display:flex;
      align-items:center;
      justify-content:center;
      height:100vh;
      overflow:hidden;
    }
    .card{
      background:rgba(255,255,255,0.1);
      border:1px solid rgba(255,255,255,0.2);
      backdrop-filter:blur(15px);
      border-radius:20px;
      padding:40px;
      width:320px;
      text-align:center;
      color:#fff;
      box-shadow:0 20px 40px rgba(0,0,0,0.4);
      transform-style:preserve-3d;
      transition:transform .3s ease;
    }
    .card:hover{
      transform:rotateY(5deg) rotateX(5deg) scale(1.03);
    }
    .btn{
      display:inline-block;
      padding:12px 24px;
      background:#00b4d8;
      color:#fff;
      text-decoration:none;
      border-radius:30px;
      font-weight:600;
      box-shadow:0 8px 15px rgba(0,180,216,.3);
      transition:all .2s ease;
      cursor:pointer;
    }
    .btn:hover{
      background:#0096c7;
      box-shadow:0 12px 20px rgba(0,180,216,.4);
    }
    .logo{
      font-size:2rem;
      font-weight:600;
      margin-bottom:1rem;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">🚀 Lykan</div>
    <p>Secure login via Telegram</p>
    <button class="btn" id="loginBtn">Login with Telegram</button>
    <div id="status" style="margin-top:1rem;font-size:.9rem;"></div>
  </div>

<script>
const tg = window.Telegram.WebApp;

document.getElementById('loginBtn').addEventListener('click', async ()=>{
  document.getElementById('status').innerText='Authenticating…';

  try {
    // send full initData string to backend
    const resp = await fetch('tg_auth.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({init_data: tg.initData})
    });
    const data = await resp.json();
    if (data.ok) {
      document.getElementById('status').innerText='Success! Redirecting…';
      setTimeout(()=>window.location='index.php',500);
    } else {
      document.getElementById('status').innerText='Error: '+data.error;
    }
  } catch(e){
    document.getElementById('status').innerText='Network error.';
  }
});
</script>
</body>
</html>
