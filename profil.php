<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'info') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nama dan email yang valid wajib diisi.');
        } else {
            try {
                db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')->execute([$name, $email, $uid]);
                audit($uid, 'profil_ubah', 'Memperbarui data profil', 'user', $uid);
                flash('success', 'Profil berhasil diperbarui.');
            } catch (PDOException $ex) {
                flash('error', $ex->getCode() === '23000' ? 'Email sudah dipakai pengguna lain.' : 'Gagal menyimpan profil.');
            }
        }
    }

    if ($do === 'password') {
        $old = $_POST['old'] ?? '';
        $new = $_POST['new'] ?? '';
        $cfm = $_POST['confirm'] ?? '';
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$uid]);
        $hash = (string)$st->fetchColumn();

        if (!password_verify($old, $hash)) {
            flash('error', 'Password lama salah.');
        } elseif (strlen($new) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
        } elseif ($new !== $cfm) {
            flash('error', 'Konfirmasi password tidak cocok.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
            audit($uid, 'password_ubah', 'Mengganti password', 'user', $uid);
            flash('success', 'Password berhasil diganti.');
        }
    }
    redirect('profil.php');
}

$pageTitle = 'Edit Profil';
$active = 'profil';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card narrow">
  <h3>Data Profil</h3>
  <div class="muted">Role Anda: <b><?= e($user['role']) ?></b></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="info">
    <div style="margin-bottom:12px"><label>Nama</label><input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
    <div style="margin-bottom:12px"><label>Email</label><input type="email" name="email" value="<?= e($user['email']) ?>" required></div>
    <button class="btn" type="submit">Simpan Profil</button>
  </form>
</div>

<div class="card narrow">
  <h3>Ganti Password</h3>
  <div class="muted">Gunakan minimal 8 karakter.</div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="password">
    <div style="margin-bottom:12px"><label>Password Lama</label><input type="password" name="old" required></div>
    <div style="margin-bottom:12px"><label>Password Baru</label><input type="password" name="new" required minlength="8"></div>
    <div style="margin-bottom:12px"><label>Konfirmasi Password Baru</label><input type="password" name="confirm" required minlength="8"></div>
    <button class="btn" type="submit">Ganti Password</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>