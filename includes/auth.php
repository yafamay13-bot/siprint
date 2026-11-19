<?php
require_once __DIR__ . '/bootstrap.php';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function require_admin(array $user): void {
    if ($user['role'] !== 'admin') redirect('dashboard.php');
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa atau file terlalu besar. Kembali dan coba lagi.');
    }
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = [$type, $msg];
}

function audit(?int $uid, string $action, string $desc = '', ?string $entityType = null, ?int $entityId = null): void {
    try {
        if ($entityType === null) {
            $prefix = explode('_', $action)[0];
            $map = [
                'dokumen'  => 'document',
                'antrean'  => 'document',
                'pengguna' => 'user',
                'profil'   => 'user',
                'password' => 'user',
                'logout'   => 'auth',
                'login'    => 'auth',
            ];
            $entityType = $map[$prefix] ?? 'system';
        }
        $details = $desc !== '' ? json_encode(['keterangan' => $desc], JSON_UNESCAPED_UNICODE) : null;
        $st = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
                             VALUES (?,?,?,?,?,?)');
        $st->execute([$uid, $action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {}
}

function notify(int $uid, string $title, string $msg = ''): void {
    try {
        $st = db()->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
        $st->execute([$uid, $title, $msg]);
    } catch (Throwable $e) {}
}

function unread_count(int $uid): int {
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $st->execute([$uid]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function status_badge(string $s): string {
    $s = strtolower($s);
    $label = ['menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak'][$s] ?? ucfirst($s);
    return '<span class="badge bd-' . e($s) . '">' . e($label) . '</span>';
}