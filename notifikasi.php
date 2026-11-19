<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($do === 'read') {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    } elseif ($do === 'readall') {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
        flash('success', 'Semua notifikasi ditandai sudah dibaca.');
    } elseif ($do === 'delete') {
        db()->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    } elseif ($do === 'deleteall') {
        db()->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$uid]);
        flash('success', 'Semua notifikasi dihapus.');
    }
    redirect('notifikasi.php');
}

$st = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$st->execute([$uid]);
$list = $st->fetchAll();

$pageTitle = 'Notifikasi';
$active = 'notifikasi';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <div class="page-head">
    <h3>Notifikasi Anda</h3>
    <?php if ($list): ?>
    <div class="acts" style="display:flex;gap:8px">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="readall"><button class="btn sec sm" type="submit">Tandai semua dibaca</button></form>
      <form method="post" onsubmit="return confirm('Hapus semua notifikasi?')"><?= csrf_field() ?><input type="hidden" name="do" value="deleteall"><button class="btn danger sm" type="submit">Hapus semua</button></form>
    </div>
    <?php endif; ?>
  </div>

  <?php if (!$list): ?>
    <div class="empty"><b>Tidak ada notifikasi</b>Pemberitahuan terbaru akan muncul di sini.</div>
  <?php else: foreach ($list as $n): ?>
    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
      <div>
        <b><?= e($n['title']) ?></b><br>
        <span><?= e($n['message']) ?></span><br>
        <span class="small"><?= e(date('d/m/Y H:i', strtotime($n['created_at']))) ?></span>
      </div>
      <div style="display:flex;gap:6px;align-items:flex-start">
        <?php if (!$n['is_read']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="read"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn sec sm" type="submit">Dibaca</button></form>
        <?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn danger sm" type="submit">Hapus</button></form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>