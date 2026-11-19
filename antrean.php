<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
require_admin($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';

    $st = db()->prepare('SELECT * FROM documents WHERE id = ?');
    $st->execute([$id]);
    $d = $st->fetch();

    if ($d) {
        $new = null; $msg = '';
        if ($do === 'proses' && $d['status'] === 'menunggu') {
            $new = 'diproses'; $msg = 'Dokumen Anda sedang diproses untuk dicetak.';
        } elseif ($do === 'selesai' && $d['status'] === 'diproses') {
            $new = 'selesai'; $msg = 'Dokumen Anda sudah selesai dicetak dan siap diambil.';
        } elseif ($do === 'tolak' && in_array($d['status'], ['menunggu','diproses'], true)) {
            $reason = trim($_POST['reason'] ?? '');
            if ($reason === '') {
                flash('error', 'Alasan penolakan wajib diisi.');
                redirect('antrean.php');
            }
            $new = 'ditolak'; $msg = 'Dokumen Anda ditolak. Alasan: ' . $reason;
            db()->prepare('UPDATE documents SET reject_reason = ? WHERE id = ?')->execute([$reason, $id]);
        }

        if ($new) {
            db()->prepare('UPDATE documents SET status = ?, processed_by = ? WHERE id = ?')
                ->execute([$new, $user['id'], $id]);
            notify((int)$d['user_id'], 'Status dokumen: ' . ucfirst($new), $d['title'] . ' - ' . $msg);
            audit((int)$user['id'], 'antrean_' . $new, 'Dokumen "' . $d['title'] . '" diubah ke ' . $new, 'document', $id);
            flash('success', 'Status dokumen diperbarui menjadi ' . $new . '.');
        } else {
            flash('error', 'Perubahan status tidak valid.');
        }
    }
    redirect('antrean.php' . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : ''));
}

$tab = ($_GET['tab'] ?? 'aktif') === 'riwayat' ? 'riwayat' : 'aktif';
if ($tab === 'aktif') {
    $sql = "SELECT d.*, u.name AS owner FROM documents d JOIN users u ON u.id = d.user_id
            WHERE d.status IN ('menunggu','diproses') ORDER BY d.created_at ASC";
} else {
    $sql = "SELECT d.*, u.name AS owner FROM documents d JOIN users u ON u.id = d.user_id
            WHERE d.status IN ('selesai','ditolak') ORDER BY d.updated_at DESC LIMIT 100";
}
$rows = db()->query($sql)->fetchAll();

$pageTitle = 'Antrean Cetak';
$active = 'antrean';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="tabs">
  <a href="antrean.php" class="<?= $tab === 'aktif' ? 'on' : '' ?>">Antrean Aktif</a>
  <a href="antrean.php?tab=riwayat" class="<?= $tab === 'riwayat' ? 'on' : '' ?>">Riwayat</a>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty"><b>Tidak ada dokumen</b><?= $tab === 'aktif' ? 'Antrean cetak kosong.' : 'Belum ada riwayat.' ?></div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>#</th><th>Dokumen</th><th>Pemohon</th><th>Opsi Cetak</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $d): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><b><?= e($d['title']) ?></b><br>
          <span class="small"><?= e(date('d/m/Y H:i', strtotime($d['created_at']))) ?></span>
          <?php if ($d['description']): ?><br><span class="small"><?= e($d['description']) ?></span><?php endif; ?>
          <?php if ($d['status'] === 'ditolak' && $d['reject_reason']): ?>
            <br><span class="small" style="color:#b91c1c">Alasan: <?= e($d['reject_reason']) ?></span>
          <?php endif; ?></td>
        <td><?= e($d['owner']) ?></td>
        <td><?= (int)$d['copies'] ?> salinan, <?= e($d['paper_size']) ?>, <?= $d['color'] === 'warna' ? 'Warna' : 'Hitam Putih' ?></td>
        <td><?= status_badge($d['status']) ?></td>
        <td><div class="acts">
          <a class="btn sec sm" href="download.php?id=<?= (int)$d['id'] ?>">Unduh</a>
          <a class="btn sm" target="_blank" href="cetak.php?id=<?= (int)$d['id'] ?>">Cetak</a>
          <?php if ($d['status'] === 'menunggu'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="proses"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn sm" type="submit">Proses</button></form>
          <?php elseif ($d['status'] === 'diproses'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="selesai"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn ok sm" type="submit">Selesai</button></form>
          <?php endif; ?>
          <?php if (in_array($d['status'], ['menunggu','diproses'], true)): ?>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="do" value="tolak"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <input type="text" name="reason" placeholder="Alasan tolak" required style="width:130px">
              <button class="btn danger sm" type="submit">Tolak</button>
            </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>