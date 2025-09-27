<?php
require __DIR__ . '/config.php';
require_login();

$st = pdo()->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$now = new DateTimeImmutable('now');
$premium_until = $user['premium_until'] ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $user['premium_until']) : null;
$premium_remaining = 'No Premium';
if ($premium_until && $premium_until > $now) {
    $diff = $now->diff($premium_until);
    $premium_remaining = $diff->format('%ad %hh %im left');
}

$display_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'User');
$avatar = $user['photo_url'] ?: 'https://placehold.co/100x100/111827/ff0000?text=No+Img';
$telegram_handle = $user['username'] ? ('@' . $user['username']) : '—';

$tools = [
    'Dashboard' => 'index.php',
    'Tools' => 'tools.php',
    'Checker' => 'checker.php',
    'Dumb Shop' => 'shop.php',
    'Number Lookup' => 'number.php',
    'OTPs' => 'otp.php',
    'API Sell' => 'api.php',
    'Killer' => 'killer.php',
    'Buy Credits' => 'buy.php',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>God Panel Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/@tailwindcss/forms"></script>
<style>
  body {
    background: radial-gradient(circle at top left, #1b0000 0%, #050505 80%);
    font-family: 'Inter', ui-sans-serif;
    color: #f5f5f5;
  }
  .sidebar {
    transition: transform .3s ease;
  }
  .sidebar.hidden {
    transform: translateX(-100%);
  }
  .neon {
    text-shadow: 0 0 8px rgba(255,0,0,.9);
  }
  .card {
    background: rgba(20,20,20,0.8);
    border: 1px solid rgba(255,31,31,0.1);
    box-shadow: 0 0 25px rgba(255,0,0,0.1);
  }
  .btn-danger {
    background: linear-gradient(90deg,#ff1f1f,#ff6b6b);
  }
  .glass {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    backdrop-filter: blur(10px);
  }
  input[type=range]::-webkit-slider-thumb {
    background: #ff1f1f;
  }
</style>
</head>
<body class="flex">

<!-- Sidebar -->
<div id="sidebar" class="sidebar hidden fixed z-50 top-0 left-0 w-72 h-full bg-black/90 p-6 space-y-6">
  <div class="text-2xl font-bold neon mb-4 tracking-wide">⚡ GOD MENU</div>
  <ul class="space-y-3">
    <?php foreach ($tools as $label=>$link): ?>
      <li><a href="<?=htmlspecialchars($link)?>" class="block px-4 py-2 rounded-lg hover:bg-red-900/30 transition"><?=htmlspecialchars($label)?></a></li>
    <?php endforeach;?>
    <li><a href="?action=logout" class="block px-4 py-2 rounded-lg hover:bg-red-900/30">Logout</a></li>
  </ul>
  <!-- Killer slider section -->
  <div class="mt-6">
    <div class="text-sm uppercase text-gray-400 mb-1">Killer Power</div>
    <input id="killerSlider" type="range" min="1" max="10" value="5" class="w-full">
    <div id="killerValue" class="text-xs text-gray-500 mt-1">Power: 5</div>
  </div>
</div>

<!-- Main content -->
<div class="flex-1 min-h-screen">
  <!-- Header -->
  <header class="flex items-center justify-between px-6 py-4 border-b border-red-900/30 bg-black/40 backdrop-blur-md sticky top-0 z-40">
    <button onclick="toggleSidebar()" class="p-2 rounded hover:bg-red-900/20 transition">
      <!-- Hamburger -->
      <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    <div class="text-xl font-extrabold neon tracking-wider">RaniJhansi — GOD PANEL</div>
    <div class="flex items-center gap-3">
      <button onclick="window.location='buy.php'" class="px-4 py-2 rounded-lg btn-danger text-black font-semibold shadow hover:brightness-95 transition">Buy Credits</button>
    </div>
  </header>

  <!-- Dashboard content -->
  <main class="p-8 space-y-8">
    <!-- User info -->
    <div class="card rounded-3xl p-8 flex flex-col md:flex-row items-center gap-8 animate-fadeIn">
      <img src="<?=htmlspecialchars($avatar)?>" class="w-32 h-32 rounded-full ring-4 ring-red-700/50 object-cover shadow-xl">
      <div class="flex-1">
        <h1 class="text-3xl font-extrabold neon"><?=htmlspecialchars($display_name)?></h1>
        <div class="text-sm text-gray-400 mt-1"><?=htmlspecialchars($telegram_handle)?></div>
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="glass rounded-xl p-4 text-center">
            <div class="text-sm text-gray-400">Credits</div>
            <div class="text-2xl font-bold text-red-400"><?=$user['credits']?></div>
          </div>
          <div class="glass rounded-xl p-4 text-center">
            <div class="text-sm text-gray-400">Premium</div>
            <div class="text-2xl font-bold text-green-400"><?=htmlspecialchars($premium_remaining)?></div>
          </div>
          <div class="glass rounded-xl p-4 text-center">
            <div class="text-sm text-gray-400">Total Checks</div>
            <div class="text-2xl font-bold text-red-300"><?=$user['checks_total']?></div>
          </div>
          <div class="glass rounded-xl p-4 text-center">
            <div class="text-sm text-gray-400">Total Lives</div>
            <div class="text-2xl font-bold text-green-300"><?=$user['lives_total']?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Info text -->
    <div class="text-center text-sm text-gray-500">Use the ☰ menu on top-left to open all tools & control Killer Power</div>
  </main>
</div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('hidden');
}
const killerSlider = document.getElementById('killerSlider');
const killerValue = document.getElementById('killerValue');
killerSlider.addEventListener('input', () => {
  killerValue.textContent = `Power: ${killerSlider.value}`;
  // here you can send AJAX to killer.php to set power dynamically
});
</script>
</body>
</html>
