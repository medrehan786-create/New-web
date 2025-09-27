<?php
require __DIR__ . '/../config.php';
require_login(); require_admin();

$pdo = pdo();
$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$tot_users   = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$global_live = (int)$pdo->query('SELECT COALESCE(SUM(lives_total),0) FROM users')->fetchColumn();

$sql = "SELECT id,telegram_id,username,first_name,last_name,photo_url,credits,checks_total,lives_total,
               DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i') AS premium_until,role
        FROM users ORDER BY id DESC LIMIT 300";
$users = $pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Lykan — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  :root { color-scheme: dark; }
  body{
    min-height:100vh;
    background:
      radial-gradient(1100px 450px at -10% -10%, rgba(99,102,241,.25), transparent 60%),
      radial-gradient(1100px 450px at 120% 10%, rgba(236,72,153,.18), transparent 60%),
      linear-gradient(135deg,#0b1220,#0f172a 40%,#0b1220);
    font-family: Inter, ui-sans-serif, system-ui;
  }
  .glass{ background: rgba(255,255,255,.06); backdrop-filter: blur(16px); border:1px solid rgba(255,255,255,.12); }
  .card:hover{ transform: translateY(-3px); box-shadow: 0 16px 40px rgba(0,0,0,.35); }
  th,td{ padding:.6rem .5rem; text-align:left; }
  .toast{ position: fixed; top: 16px; right: 16px; z-index: 50; }
</style>
</head>
<body class="text-white">
  <?php if($flash): ?>
    <div class="toast glass px-4 py-3 rounded-2xl text-sm border border-white/15"><?= htmlspecialchars($flash,ENT_QUOTES) ?></div>
    <script>setTimeout(()=>{document.querySelector('.toast')?.remove()},3500);</script>
  <?php endif; ?>

  <!-- Header -->
  <header class="px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-500 to-fuchsia-500 grid place-items-center">🛠️</div>
      <div>
        <div class="font-bold text-lg">Lykan Admin</div>
        <div class="text-xs text-white/60">Manage users, credits & premium</div>
      </div>
    </div>
    <div class="flex gap-2">
      <a href="/index.php" class="px-4 py-2 rounded-xl glass hover:bg-white/10">User View</a>
      <a href="/logout.php" class="px-4 py-2 rounded-xl glass hover:bg-white/10">Logout</a>
    </div>
  </header>

  <main class="px-6 pb-12 max-w-7xl mx-auto space-y-8">
    <!-- Stats -->
    <div class="grid md:grid-cols-3 gap-6">
      <div class="glass card p-6 rounded-2xl">
        <div class="text-sm text-white/60">Total Users</div>
        <div class="text-4xl font-extrabold mt-2"><?= $tot_users ?></div>
      </div>
      <div class="glass card p-6 rounded-2xl">
        <div class="text-sm text-white/60">Global Live</div>
        <div class="text-4xl font-extrabold mt-2"><?= $global_live ?></div>
      </div>
      <div class="glass card p-6 rounded-2xl">
        <div class="text-sm text-white/60">Actions</div>
        <div class="text-white/70 text-sm mt-2">Grant premium & credits, search users.</div>
      </div>
    </div>

    <!-- Top: Give Premium + Credits by ID -->
    <div class="glass p-6 rounded-2xl">
      <h2 class="text-lg font-semibold mb-3">🎯 Give Premium (by User ID)</h2>
      <form method="post" action="/admin/actions.php" class="grid md:grid-cols-5 gap-3">
        <input name="uid" type="number" placeholder="User ID" class="px-4 py-3 rounded-xl bg-white/5 border border-white/15" required>
        <input name="days" type="number" placeholder="Days" class="px-4 py-3 rounded-xl bg-white/5 border border-white/15" required>
        <input name="credits" type="number" placeholder="Credits" class="px-4 py-3 rounded-xl bg-white/5 border border-white/15" required>
        <input type="hidden" name="act" value="grant_both">
        <button class="px-4 py-3 rounded-xl bg-emerald-500/20 border border-emerald-500/40 hover:bg-emerald-500/30 font-semibold">Give</button>
        <a href="#keys" class="px-4 py-3 rounded-xl glass hover:bg-white/10 text-center">Generate Keys</a>
      </form>
    </div>

    <!-- Keys Generator -->
    <div id="keys" class="glass p-6 rounded-2xl">
      <h2 class="text-lg font-semibold mb-3">🔑 Generate Keys</h2>
      <form method="post" action="/admin/actions.php" class="grid md:grid-cols-5 gap-3">
        <input name="credits" type="number" placeholder="Credits" class="px-4 py-3 rounded-xl bg-white/5 border border-white/15" required>
        <input name="days" type="number" placeholder="Days" class="px-4 py-3 rounded-xl bg-white/5 border border-white/15" required>
        <input name="count" type="number" value="1" placeholder="Count" class="px-4 py-3 rounded-xl bg-white/5 border border-white/15">
        <input type="hidden" name="act" value="gen_keys">
        <button class="px-4 py-3 rounded-xl bg-fuchsia-500/20 border border-fuchsia-500/40 hover:bg-fuchsia-500/30 font-semibold">Generate</button>
      </form>
      <?php if(!empty($_SESSION['gen_keys'])): $list=$_SESSION['gen_keys']; unset($_SESSION['gen_keys']); ?>
        <div class="mt-4 grid sm:grid-cols-2 md:grid-cols-3 gap-2">
          <?php foreach($list as $k): ?>
            <div class="group flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/15 font-mono text-xs">
              <span class="select-all"><?= htmlspecialchars($k,ENT_QUOTES) ?></span>
              <button class="opacity-60 group-hover:opacity-100 text-[11px]" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($k,ENT_QUOTES) ?>')">Copy</button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Users Table -->
    <div class="glass p-6 rounded-2xl overflow-auto">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-semibold">👥 Users</h2>
        <input id="userFilter" class="px-3 py-2 rounded-xl bg-white/5 border border-white/15 text-sm" placeholder="Search name / @user / ID">
      </div>
      <table id="usersTable" class="w-full text-sm border-collapse">
        <thead class="text-white/70 border-b border-white/20">
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Username</th>
            <th>Credits</th>
            <th>Checks</th>
            <th>Lives</th>
            <th>Premium</th>
            <th>Role</th>
            <th style="min-width:300px">Give (Days + Credits)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $r): ?>
          <tr class="border-t border-white/10 hover:bg-white/5 align-top">
            <td><?= $r['id'] ?></td>
            <td>
              <div class="font-medium"><?= htmlspecialchars(trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')), ENT_QUOTES) ?></div>
              <div class="text-[11px] text-white/50">#<?= (int)$r['telegram_id'] ?></div>
            </td>
            <td>@<?= htmlspecialchars($r['username'] ?? '—', ENT_QUOTES) ?></td>
            <td><?= $r['credits'] ?></td>
            <td><?= $r['checks_total'] ?></td>
            <td><?= $r['lives_total'] ?></td>
            <td><?= htmlspecialchars($r['premium_until'] ?? '—', ENT_QUOTES) ?></td>
            <td><?= $r['role'] ?></td>
            <td>
              <form method="post" action="/admin/actions.php" class="flex flex-col sm:flex-row gap-2">
                <input type="hidden" name="uid" value="<?= (int)$r['id'] ?>">
                <input name="days" type="number" placeholder="Days" class="px-3 py-2 rounded-lg bg-white/5 border border-white/15" required>
                <input name="credits" type="number" placeholder="Credits" class="px-3 py-2 rounded-lg bg-white/5 border border-white/15" required>
                <input type="hidden" name="act" value="grant_both">
                <button class="px-3 py-2 rounded-lg bg-gradient-to-r from-indigo-500/20 to-fuchsia-500/20 border border-white/15 hover:from-indigo-500/30 hover:to-fuchsia-500/30">Give</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <script>
    // Client-side filter for Users table
    const filter = document.getElementById('userFilter');
    const table  = document.getElementById('usersTable').querySelector('tbody');
    filter?.addEventListener('input', () => {
      const q = filter.value.toLowerCase();
      for (const tr of table.rows) {
        const txt = tr.innerText.toLowerCase();
        tr.style.display = txt.includes(q) ? '' : 'none';
      }
    });
  </script>
</body>
</html>
