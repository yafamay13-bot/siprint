<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$isAdmin = ($user['role'] === 'admin');

$pageTitle = 'Dashboard';
$active = 'dashboard';

/* ====== ASUMSI NAMA KOLOM (ubah di sini kalau beda) ====== */
$T_DOK   = 'documents';
$C_TITLE = 'title';       // judul/nama dokumen
$C_STAT  = 'status';      // menunggu | diproses | selesai | ditolak
$C_USER  = 'user_id';     // pemilik dokumen
$C_DATE  = 'created_at';
/* ========================================================= */

$scopeSql = $isAdmin ? '' : " WHERE $C_USER = :uid";
$scopeAnd = $isAdmin ? '' : " AND $C_USER = :uid";
$params   = $isAdmin ? [] : [':uid' => $user['id']];

$count = ['menunggu' => 0, 'diproses' => 0, 'selesai' => 0, 'ditolak' => 0];
$total = 0;
$trend = [];
$recent = [];

for ($i = 6; $i >= 0; $i--) {
    $trend[date('Y-m-d', strtotime("-$i day"))] = 0;
}

try {
    $st = db()->prepare("SELECT $C_STAT AS s, COUNT(*) AS c FROM $T_DOK $scopeSql GROUP BY $C_STAT");
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $k = strtolower($r['s']);
        if (isset($count[$k])) $count[$k] = (int)$r['c'];
        $total += (int)$r['c'];
    }
} catch (Throwable $e) {}

try {
    $st = db()->prepare("SELECT DATE($C_DATE) AS d, COUNT(*) AS c FROM $T_DOK
                         WHERE $C_DATE >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) $scopeAnd GROUP BY DATE($C_DATE)");
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        if (isset($trend[$r['d']])) $trend[$r['d']] = (int)$r['c'];
    }
} catch (Throwable $e) {}

try {
    $st = db()->prepare("SELECT $C_TITLE AS t, $C_STAT AS s, $C_DATE AS d FROM $T_DOK $scopeSql ORDER BY $C_DATE DESC LIMIT 5");
    $st->execute($params);
    $recent = $st->fetchAll();
} catch (Throwable $e) {}

$trendTotal = array_sum($trend);
$maxTrend = max(1, max($trend));
$pct = fn($n) => $total > 0 ? round($n / $total * 100) : 0;

$bln = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$nama = explode(' ', trim($user['name']))[0];
$jam = (int)date('G');
$salam = $jam < 11 ? 'Selamat Pagi' : ($jam < 15 ? 'Selamat Siang' : ($jam < 18 ? 'Selamat Sore' : 'Selamat Malam'));

require __DIR__ . '/includes/layout_top.php';
?>

<section class="hero">
  <small>DINAS PERPUSTAKAAN DAN KEARSIPAN KABUPATEN CIREBON</small>
  <h1><?= $salam ?>,<br><em><?= e($user['name']) ?>.</em></h1>
  <p>
    <?= $isAdmin
        ? 'Semoga aktivitas Anda hari ini berjalan lancar. Pantau proses pengiriman dan pencetakan dokumen di SIPRINT.'
        : 'Ajukan dan pantau status pencetakan dokumen Anda di SIPRINT dengan mudah.' ?>
  </p>
  <a class="cta" href="<?= $isAdmin ? 'antrean.php' : 'dokumen.php' ?>">
    <?= $isAdmin ? 'Kelola Antrean Cetak' : 'Lihat Dokumen Saya' ?> &rarr;
  </a>
</section>

<section class="grid4">
  <div class="card stat">
    <span class="ico b">D</span>
    <div class="label"><?= $isAdmin ? 'Semua Dokumen' : 'Dokumen Saya' ?></div>
    <div class="num"><?= $total ?></div>
    <div class="sub">Total dokumen yang masuk</div>
  </div>
  <div class="card stat">
    <span class="ico p">M</span>
    <div class="label">Menunggu Diproses</div>
    <div class="num"><?= $count['menunggu'] ?></div>
    <div class="sub">Menunggu verifikasi antrean</div>
  </div>
  <div class="card stat">
    <span class="ico g">P</span>
    <div class="label">Sedang Diproses</div>
    <div class="num"><?= $count['diproses'] ?></div>
    <div class="sub">Dalam proses pencetakan</div>
  </div>
  <div class="card stat">
    <span class="ico c">S</span>
    <div class="label">Selesai Dicetak</div>
    <div class="num"><?= $count['selesai'] ?></div>
    <div class="sub">Dokumen berhasil dicetak</div>
  </div>
</section>

<section class="grid2">
  <div class="card">
    <span class="pill">Total: <?= $trendTotal ?> Dokumen</span>
    <h3>Tren Dokumen Masuk (7 Hari Terakhir)</h3>
    <div class="muted"><?= $isAdmin ? 'Volume pengajuan dokumen seluruh pegawai' : 'Pengajuan dokumen Anda' ?></div>
    <div class="bars">
      <?php foreach ($trend as $v): ?>
        <div class="bar">
          <span class="v"><?= $v ?></span>
          <i style="height:<?= round($v / $maxTrend * 100) ?>%"></i>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="bar-x">
      <?php foreach ($trend as $d => $v): ?>
        <span><?= (int)date('j', strtotime($d)) . ' ' . $bln[(int)date('n', strtotime($d))] ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h3>Distribusi Status</h3>
    <div class="muted">Persentase status proses dokumen</div>
    <div class="track">
      <i class="c-green" style="width:<?= $pct($count['selesai']) ?>%"></i>
      <i class="c-blue"  style="width:<?= $pct($count['diproses']) ?>%"></i>
      <i class="c-amber" style="width:<?= $pct($count['menunggu']) ?>%"></i>
      <i class="c-red"   style="width:<?= $pct($count['ditolak']) ?>%"></i>
    </div>
    <div class="legend">
      <div><span><i class="d c-green"></i>Selesai Dicetak</span><b><?= $count['selesai'] ?> (<?= $pct($count['selesai']) ?>%)</b></div>
      <div><span><i class="d c-blue"></i>Sedang Diproses</span><b><?= $count['diproses'] ?> (<?= $pct($count['diproses']) ?>%)</b></div>
      <div><span><i class="d c-amber"></i>Menunggu Verifikasi</span><b><?= $count['menunggu'] ?> (<?= $pct($count['menunggu']) ?>%)</b></div>
      <div><span><i class="d c-red"></i>Ditolak / Dibatalkan</span><b><?= $count['ditolak'] ?> (<?= $pct($count['ditolak']) ?>%)</b></div>
    </div>
  </div>
</section>

<section class="grid2">
  <div class="card">
    <div class="act-head">
      <h3>Aktivitas Dokumen Terbaru</h3>
      <a href="dokumen.php">Lihat semua &rarr;</a>
    </div>
    <?php if (!$recent): ?>
      <div class="empty"><b>Belum ada aktivitas dokumen</b>Dokumen yang diajukan atau diproses akan muncul di sini secara otomatis.</div>
    <?php else: foreach ($recent as $r): $k = strtolower($r['s']); ?>
      <div class="act">
        <div><?= e($r['t']) ?><br><small><?= e(date('d/m/Y H:i', strtotime($r['d']))) ?></small></div>
        <span class="badge bd-<?= e($k) ?>"><?= e(ucfirst($k)) ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card quick">
    <h3 style="margin-bottom:12px">Akses Cepat</h3>
    <?php if ($isAdmin): ?>
      <a href="antrean.php">Kelola Antrean Cetak</a>
      <a href="dokumen.php">Lihat Seluruh Dokumen</a>
      <a href="pengguna.php">Kelola Data Pengguna</a>
    <?php else: ?>
      <a href="dokumen.php">Dokumen Saya</a>
      <a href="notifikasi.php">Notifikasi</a>
      <a href="profil.php">Edit Profil</a>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>