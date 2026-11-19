<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM documents WHERE id = ?');
$st->execute([$id]);
$d = $st->fetch();

if (!$d || ($user['role'] !== 'admin' && (int)$d['user_id'] !== (int)$user['id'])) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}

$path = __DIR__ . '/uploads/' . basename($d['file_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit('File tidak ada di server.');
}

audit((int)$user['id'], 'dokumen_unduh', 'Mengunduh dokumen: ' . $d['title'], 'document', (int)$d['id']);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $d['file_name']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);