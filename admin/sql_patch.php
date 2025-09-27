<?php
// /admin/sql_patch.php
require __DIR__ . '/../config.php';
require_login(); require_admin();

header('Content-Type: text/plain; charset=utf-8');

$pdo = pdo();

function hasIndex($pdo, $db, $table, $index) {
  $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?");
  $st->execute([$db, $table, $index]);
  return (int)$st->fetchColumn() > 0;
}

try {
  echo "== Lykan SQL Patch ==\n\n";

  // 1) Make telegram_id VARCHAR(32) NOT NULL
  echo "[1] Altering users.telegram_id to VARCHAR(32)... ";
  $pdo->exec("ALTER TABLE users MODIFY COLUMN telegram_id VARCHAR(32) NOT NULL");
  echo "OK\n";

  // 2) Add UNIQUE index if missing
  echo "[2] Ensuring UNIQUE index on users(telegram_id)... ";
  if (!hasIndex($pdo, DB_NAME, 'users', 'uniq_users_telegram_id')) {
    $pdo->exec("CREATE UNIQUE INDEX uniq_users_telegram_id ON users (telegram_id)");
    echo "Created\n";
  } else {
    echo "Already exists\n";
  }

  // 3) Optional cleanup of duplicates like '2147483647'
  echo "[3] Optional cleanup of duplicate telegram_id rows (keeping newest)... ";
  // Find duplicates
  $dupes = $pdo->query("
    SELECT telegram_id
    FROM users
    GROUP BY telegram_id
    HAVING COUNT(*) > 1
  ")->fetchAll(PDO::FETCH_COLUMN);

  if ($dupes) {
    foreach ($dupes as $tid) {
      // keep the highest id (newest), delete others
      $ids = $pdo->prepare("SELECT id FROM users WHERE telegram_id=? ORDER BY id DESC");
      $ids->execute([$tid]);
      $all = $ids->fetchAll(PDO::FETCH_COLUMN);
      array_shift($all); // drop newest, keep as master
      if ($all) {
        $pdo->exec("DELETE FROM users WHERE id IN (" . implode(',', array_map('intval',$all)) . ")");
      }
    }
    echo "Cleaned ".count($dupes)." duplicate group(s)\n";
  } else {
    echo "No duplicates found\n";
  }

  echo "\nAll done ✅\n";
} catch (Throwable $e) {
  http_response_code(500);
  echo "ERROR: " . $e->getMessage() . "\n";
}
