<?php
/**
 * tg_auth.php – Handles Telegram WebApp login
 * - Reads JSON payload from php://input
 * - Verifies Telegram signature (optional)
 * - Inserts/updates user and sets session
 * - Returns JSON
 */

require __DIR__ . '/config.php'; // starts session safely

// Send JSON header immediately, before any output
header('Content-Type: application/json');

// Read raw JSON body (Telegram WebApp sends JSON)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// No data?
if (empty($data) || !isset($data['id'])) {
    echo json_encode(['ok' => false, 'error' => 'No Telegram data received']);
    exit;
}

// Optional: signature verification
if (!verifyTelegramAuth($data)) { // uses the helper from config.php
    echo json_encode(['ok' => false, 'error' => 'Invalid Telegram signature']);
    exit;
}

// Upsert user in DB
$user = upsert_user_from_tg([
    'id'         => (string)$data['id'],
    'username'   => $data['username'] ?? null,
    'first_name' => $data['first_name'] ?? null,
    'last_name'  => $data['last_name'] ?? null,
    'photo_url'  => $data['photo_url'] ?? null,
    'auth_date'  => $data['auth_date'] ?? time()
]);

// Set session variables
$_SESSION['uid']  = $user['id'];
$_SESSION['tgid'] = $user['telegram_id'];
$_SESSION['role'] = $user['role'];

// Return success JSON
echo json_encode(['ok' => true]);
exit;
