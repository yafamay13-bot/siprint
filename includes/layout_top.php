<?php
// Butuh variabel: $user (array), $pageTitle (string), $active (string)
$isAdmin = ($user['role'] === 'admin');
$unread = unread_count((int)$user['id']);

$menuUtama = $isAdmin
    ? [
        'dashboard' => ['Dashboard', 'dashboard.php'],
        'dokumen'   => ['Data Dokumen', 'dokumen.php'],
        'antrean'   => ['Antrean Cetak', 'antrean.php'],
        'pengguna'  => ['Data Pengguna', 'pengguna.php'],
        'audit'     => ['Audit Trail / Log', 'audit.php'],
      ]
    : [
        'dashboard' => ['Dashboard', 'dashboard.php'],
        'dokumen'   => ['Dokumen Saya', 'dokumen.php'],
      ];

$menuPengaturan = [
    'notifikasi' => ['Notifikasi', 'notifikasi.php'],
    'profil'     => ['Edit Profil', 'profil.php'],
];

$hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggalIni = $hari[(int)date('w')] . ', ' . date('j') . ' ' . $bulan[(int)date('n')] . ' ' . date('Y');
$inisial = strtoupper(substr($user['name'], 0, 1));
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> | SIPRINT</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-logo">S</div>
      <div>
        <div class="brand-name">SIPRINT</div>
        <div class="brand-sub">LAYANAN INTERNAL</div>
      </div>
    </div>

    <div class="menu-label">MENU UTAMA</div>
    <nav>
      <?php foreach ($menuUtama as $key => [$label, $href]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="menu-label">PENGATURAN</div>
    <nav>
      <?php foreach ($menuPengaturan as $key => [$label, $href]): ?>
        <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>">
          <?= e($label) ?>
          <?php if ($key === 'notifikasi' && $unread > 0): ?><span class="nbadge"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="help-box">
      <strong>Butuh bantuan?</strong>
      Hubungi administrator SIPRINT Dispusip Kabupaten Cirebon.
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="crumb">SIPRINT <span>/</span> <b><?= e($pageTitle) ?></b></div>
      <div class="topbar-right">
        <span class="date"><i class="dot"></i><?= e($tanggalIni) ?></span>
        <div class="avatar"><?= e($inisial) ?></div>
        <span class="uname"><?= e($user['name']) ?></span>
        <a href="logout.php" class="btn-out">Keluar</a>
      </div>
    </header>
    <main class="content">
      <?php foreach ($flashes as [$ft, $fm]): ?>
        <div class="flash <?= e($ft) ?>"><?= e($fm) ?></div>
      <?php endforeach; ?>