<?php
require __DIR__ . '/../includes/bootstrap.php';

const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 300; // 5 menit

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

// 1. Cek CSRF
$token = $_POST['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
    json_response(['success' => false, 'message' => 'Sesi tidak valid. Muat ulang halaman.'], 403);
}

// 2. Pembatasan percobaan login
$locked = $_SESSION['login_locked_until'] ?? 0;
if ($locked > time()) {
    $minutes = (int)ceil(($locked - time()) / 60);
    json_response(['success' => false, 'message' => "Terlalu banyak percobaan. Coba lagi dalam {$minutes} menit."], 429);
}

// 3. Validasi input
$email    = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190 || $password === '' || strlen($password) > 128) {
    json_response(['success' => false, 'message' => 'Email atau kata sandi tidak valid.'], 422);
}

// 4. Cari user + verifikasi password
try {
    $stmt = db()->prepare('SELECT id, name, email, password_hash, role, auth_version FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'DB error: ' . $e->getMessage()], 500);
}

// hash palsu agar waktu respons tetap sama walau email tidak ditemukan
$dummyHash = '$2y$10$usesomesillystringforsalt.abcdefghijklmnopqrstuvwxyz0123';
$ok = password_verify($password, $user['password_hash'] ?? $dummyHash);

if (!$user || !$ok) {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOCK_SECONDS;
        $_SESSION['login_attempts'] = 0;
    }
    usleep(300000); // jeda 0,3 detik
    json_response(['success' => false, 'message' => 'Email atau kata sandi salah.'], 401);
}

// 5. Berhasil
unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
session_regenerate_id(true);
$_SESSION['user'] = [
    'id'           => (int)$user['id'],
    'name'         => $user['name'],
    'email'        => $user['email'],
    'role'         => $user['role'],
    'auth_version' => (int)$user['auth_version'],
];

// 6. Catat audit (opsional, tidak menggagalkan login)
try {
    db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, ip_address) VALUES (?, ?, ?, ?)')
        ->execute([$user['id'], 'login', 'session', $_SERVER['REMOTE_ADDR'] ?? null]);
} catch (Throwable $e) {
}

json_response(['success' => true, 'redirect' => 'dashboard.php']);