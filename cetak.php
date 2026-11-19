<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
require_admin($user);

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT d.*, u.name AS owner FROM documents d JOIN users u ON u.id = d.user_id WHERE d.id = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { http_response_code(404); exit('Dokumen tidak ditemukan.'); }

$path = __DIR__ . '/uploads/' . basename($d['file_path']);
if (!is_file($path)) { http_response_code(404); exit('File tidak ada di server.'); }

$ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
$mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
$previewable = isset($mimes[$ext]);

// Mode raw: tampilkan file langsung (dipakai oleh iframe di bawah)
if (isset($_GET['raw']) && $previewable) {
    header('Content-Type: ' . $mimes[$ext]);
    header('Content-Disposition: inline; filename="cetak.' . $ext . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

audit((int)$user['id'], 'dokumen_cetak', 'Membuka halaman cetak: ' . $d['title'], 'document', (int)$d['id']);
$warna = $d['color'] === 'warna' ? 'Berwarna' : 'Hitam Putih';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cetak: <?= e($d['title']) ?> | SIPRINT</title>
<style>
  body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:#eef3fc;color:#14274e}
  .bar{background:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;border-bottom:1px solid #d5e0f5}
  .info b{font-size:16px}
  .info div{font-size:12px;color:#5b6f99;margin-top:3px}
  .chip{display:inline-block;background:#eaf1ff;color:#2563eb;border-radius:99px;padding:2px 10px;font-weight:700;margin-right:6px}
  .btn{border:0;cursor:pointer;background:#2563eb;color:#fff;padding:10px 18px;border-radius:9px;font-weight:700;font-size:13px;text-decoration:none;display:inline-block}
  .btn.sec{background:#eaf1ff;color:#2563eb}
  iframe{display:block;width:100%;height:calc(100vh - 80px);border:0;background:#fff}
  .nopreview{max-width:520px;margin:60px auto;background:#fff;padding:28px;border-radius:14px;text-align:center}
</style>
</head>
<body>
<div class="bar">
  <div class="info">
    <b><?= e($d['title']) ?></b>
    <div>
      <span class="chip"><?= (int)$d['copies'] ?> salinan</span>
      <span class="chip"><?= e($d['paper_size']) ?></span>
      <span class="chip"><?= e($warna) ?></span>
      Pemohon: <?= e($d['owner']) ?>
    </div>
  </div>
  <div>
    <a class="btn sec" href="download.php?id=<?= (int)$d['id'] ?>">Unduh</a>
    <?php if ($previewable): ?>
      <button class="btn" type="button" onclick="cetak()">Cetak Sekarang</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($previewable): ?>
  <iframe id="pv" src="cetak.php?id=<?= (int)$d['id'] ?>&raw=1"></iframe>
  <script>
    function cetak() {
      var w = document.getElementById('pv').contentWindow;
      w.focus();
      w.print();
    }
  </script>
<?php else: ?>
  <div class="nopreview">
    <h3>Pratinjau tidak tersedia</h3>
    <p>File berformat <b><?= e(strtoupper($ext)) ?></b> tidak bisa dicetak langsung dari browser.
       Unduh file, buka dengan Word/Excel/PowerPoint, lalu cetak dari sana sesuai opsi di atas.</p>
    <a class="btn" href="download.php?id=<?= (int)$d['id'] ?>">Unduh File</a>
  </div>
<?php endif; ?>
</body>
</html>