<?php
require __DIR__ . '/../config.php';
require_login();
require_admin();

$act = $_POST['act'] ?? '';

try {
    if ($act === 'add_credit') {
        $uid   = (int)($_POST['uid'] ?? 0);
        $delta = (int)($_POST['delta'] ?? 0);
        $new   = change_credits($uid, $delta);
        $_SESSION['flash'] = "✅ Credits updated: User #$uid → $new";
    }

    elseif ($act === 'grant_premium') {
        $uid  = (int)($_POST['uid'] ?? 0);
        $days = (int)($_POST['days'] ?? 0);
        $exp  = set_premium_days($uid, $days);
        $_SESSION['flash'] = "⭐ Premium updated: User #$uid → $exp";
    }

    elseif ($act === 'grant_both') {
        $uid     = (int)($_POST['uid'] ?? 0);
        $days    = (int)($_POST['days'] ?? 0);
        $credits = (int)($_POST['credits'] ?? 0);

        if ($credits !== 0) {
            $after = change_credits($uid, $credits);
        } else {
            $after = (int)pdo()->query("SELECT credits FROM users WHERE id=$uid")->fetchColumn();
        }
        $exp = $days > 0
            ? set_premium_days($uid, $days)
            : (string)pdo()->query("SELECT IFNULL(DATE_FORMAT(premium_until,'%Y-%m-%d %H:%i'),'—') FROM users WHERE id=$uid")->fetchColumn();

        $_SESSION['flash'] = "🎯 Given to #$uid → Credits: $after · Premium: $exp";
    }

    elseif ($act === 'gen_keys') {
        $credits = (int)($_POST['credits'] ?? 0);
        $days    = (int)($_POST['days'] ?? 0);
        $count   = max(1, (int)($_POST['count'] ?? 1));
        $list    = gen_keys_bulk($count, $credits, $days, (int)$_SESSION['uid']);
        $_SESSION['gen_keys'] = $list;
        $_SESSION['flash']    = "🔑 Generated $count key(s).";
    }

    else {
        $_SESSION['flash'] = "⚠️ Unknown action.";
    }
} catch (Exception $e) {
    $_SESSION['flash'] = "❌ Error: " . $e->getMessage();
}

header('Location: /admin/index.php');
exit;
