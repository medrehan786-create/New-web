<?php
require __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Telegram sends GET parameters like ?id=123&first_name=...&hash=...
$data = $_GET;

if (!$data || !isset($data['id'])) {
    die("❌ No Telegram data received");
}

// Optional: you can verify hash if needed
$user = upsert_user_from_telegram_oauth($data);

$_SESSION['uid']  = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'];

header('Location: /index.php');
exit;
