<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/config.php';
        $dsn = "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['db_user'], $c['db_password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_login(): array
{
    if (empty($_SESSION['user'])) {
        header('Location: index.html');
        exit;
    }

    $s = $_SESSION['user'];
    $fresh = null;
    try {
        if (!empty($s['id'])) {
            $st = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
            $st->execute([$s['id']]);
        } else {
            $st = db()->prepare('SELECT id, name, email, role FROM users WHERE email = ? LIMIT 1');
            $st->execute([$s['email'] ?? '']);
        }
        $fresh = $st->fetch() ?: null;
    } catch (Throwable $e) {
        $fresh = null;
    }

    if (!$fresh) {
        header('Location: logout.php');
        exit;
    }

    $_SESSION['user'] = array_merge($s, $fresh);
    return $_SESSION['user'];
}