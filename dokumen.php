<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$isAdmin = ($user['role'] === 'admin');

$allowedExt = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png'];
$maxSize = 10 * 1024 * 1024;
$uploadDir = __DIR__ . '/uploads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'upload' && !$isAdmin) {
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $copies = max(1, min(500, (int)($_POST['copies'] ?? 1)));
        $paper  = in_array($_POST['paper_size'] ?? '', ['A4','F4','A3'], true) ? $_POST['paper_size'] : 'A4';
        $color  = (($_POST['color'] ?? '') === 'warna') ? 'warna' : 'hitam_putih';
        $f = $_FILES['file'] ?? null;
        $err = null;
        $ext = '';

        if ($title === '') {
            $err = 'Judul dokumen wajib diisi.';
        } elseif (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $err = 'File gagal diunggah. Pilih file dan pastikan ukurannya tidak terlalu besar.';
        } else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) $err = 'Format file tidak didukung.';
            elseif ($f['size'] > $maxSize) $err = 'Ukuran file maksimal 10 MB.';
        }

        if ($err) {
            flash('error', $err);
        } else {
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            $stored = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $uploadDir . '/' . $stored)) {
                $st = db()->prepare('INSERT INTO documents (user_id, title, description, file_name, file_path, copies, paper_size, color)
                                     VALUES (?,?,?,?,?,?,?,?)');
                $st->execute([$user['id'], $title, $desc, $f['name'], $stored, $copies, $paper, $color]);
                $newId = (int)db()->lastInsertId();
                audit((int)$user['id'], 'dokumen_upload', "Mengajukan dokumen: $title", 'document', $newId);
                foreach (db()->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll() as $a) {
                    notify((int)$a['id'], 'Dokumen baru masuk', $user['name'] . ' mengajukan: ' . $title);
                }
                flash('success', 'Dokumen berhasil diajukan.');
            } else {
                flash('error', 'File gagal disimpan di server.');
            }
        }
        redirect('dokumen.php');
    }

    if ($do === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->prepare('SELECT * FROM documents WHERE id = ?');
        $st->execute([$id]);
        $d = $st->fetch();
        $owner = $d && (int)$d['user_id'] === (int)$user['id'];
        if ($d && ($isAdmin || ($owner && in_array($d['status'], ['menunggu','ditolak'], true)))) {
            @unlink($uploadDir . '/' . basename($d['file_path']));
            db()->prepare('DELETE FROM documents WHERE id = ?')->execute([$id]);
            audit((int)$user['id'], 'dokumen_hapus', 'Menghapus dokumen: ' . $d['title'], 'document', $id);
            flash('success', 'Dokumen dihapus.');
        } else {
            flash('error', 'Dokumen tidak bisa dihapus.');
        }
        redirect('dokumen.php');
    }
}

$status = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$where = []; $p = [];
if (!$isAdmin) { $where[] = 'd.user_id = ?'; $p[] = $user['id']; }
if (in_array($status, ['menunggu','diproses','selesai','ditolak'], true)) { $where[] = 'd.status = ?'; $p[] = $status; }
if ($q !== '') { $where[] = '(d.title LIKE ? OR u.name LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }

$sql = 'SELECT d.*, u.name AS owner FROM documents d JOIN users u ON u.id = d.user_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY d.created_at DESC LIMIT 200';
$st = db()->prepare($sql);
$st->execute($p);
$docs = $st->fetchAll();

$pageTitle = $isAdmin ? 'Data Dokumen' : 'Dokumen Saya';
$active = 'dokumen';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if (!$isAdmin): ?>
<div class="card">
  <h3>Ajukan Dokumen Baru</h3>
  <div class="muted">Format: PDF, Word, Excel, PowerPoint, JPG, PNG. Maksimal 10 MB.</div>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="upload">
    <div class="form-grid">
      <div><label>Judul Dokumen</label><input type="text" name="title" required maxlength="200"></div>
      <div><label>File</label><input type="file" name="file" required></div>
      <div><label>Jumlah Salinan</label><input type="number" name="copies" min="1" max="500" value="1"></div>
      <div><label>Ukuran Kertas</label>
        <select name="paper_size"><option>A4</option><option>F4</option><option>A3</option></select></div>
      <div><label>Warna</label>
        <select name="color"><option value="hitam_putih">Hitam Putih</option><option value="warna">Berwarna</option></select></div>
    </div>
    <div style="margin-bottom:12px"><label>Keterangan (opsional)</label><textarea name="description"></textarea></div>
    <button class="btn" type="submit">Ajukan untuk Dicetak</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h3><?= $isAdmin ? 'Seluruh Dokumen' : 'Riwayat Dokumen Saya' ?></h3>
  <form class="filters" method="get" style="margin-top:12px">
    <input type="text" name="q" placeholder="Cari judul<?= $isAdmin ? ' / pemohon' : '' ?>..." value="<?= e($q) ?>">
    <select name="status">
      <option value="">Semua status</option>
      <?php foreach (['menunggu','diproses','selesai','ditolak'] as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn sec" type="submit">Filter</button>
  </form>

  <?php if (!$docs): ?>
    <div class="empty"><b>Belum ada dokumen</b>Dokumen yang diajukan akan muncul di sini.</div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr>
      <th>Judul</th><?php if ($isAdmin): ?><th>Pemohon</th><?php endif; ?>
      <th>Opsi Cetak</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
    </tr></thead>
    <tbody>
    <?php foreach ($docs as $d): ?>
      <tr>
        <td><b><?= e($d['title']) ?></b><br><span class="small"><?= e($d['file_name']) ?></span>
          <?php if ($d['status'] === 'ditolak' && $d['reject_reason']): ?>
            <br><span class="small" style="color:#b91c1c">Alasan: <?= e($d['reject_reason']) ?></span>
          <?php endif; ?></td>
        <?php if ($isAdmin): ?><td><?= e($d['owner']) ?></td><?php endif; ?>
        <td><?= (int)$d['copies'] ?> salinan, <?= e($d['paper_size']) ?>, <?= $d['color'] === 'warna' ? 'Warna' : 'Hitam Putih' ?></td>
        <td><?= status_badge($d['status']) ?></td>
        <td class="small"><?= e(date('d/m/Y H:i', strtotime($d['created_at']))) ?></td>
        <td><div class="acts">
          <a class="btn sec sm" href="download.php?id=<?= (int)$d['id'] ?>">Unduh</a>
          <?php if ($isAdmin || ((int)$d['user_id'] === (int)$user['id'] && in_array($d['status'], ['menunggu','ditolak'], true))): ?>
            <form method="post" onsubmit="return confirm('Hapus dokumen ini?')">
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="delete">
              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
              <button class="btn danger sm" type="submit">Hapus</button>
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