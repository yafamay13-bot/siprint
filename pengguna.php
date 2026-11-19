<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
require_admin($user);

// Sesuaikan dengan nilai ENUM kolom role di tabel users
$roles = ['admin', 'pegawai'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';

    if ($do === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nama dan email yang valid wajib diisi.');
        } elseif (strlen($pass) < 8) {
            flash('error', 'Password minimal 8 karakter.');
        } elseif (!in_array($role, $roles, true)) {
            flash('error', 'Role tidak valid.');
        } else {
            try {
                db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)')
                    ->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $role]);
                audit((int)$user['id'], 'pengguna_tambah', "Menambah pengguna $email ($role)", 'user', (int)db()->lastInsertId());
                flash('success', 'Pengguna berhasil ditambahkan.');
            } catch (PDOException $ex) {
                flash('error', $ex->getCode() === '23000' ? 'Email sudah terdaftar.' : 'Gagal menyimpan pengguna.');
            }
        }
        redirect('pengguna.php');
    }

    if ($do === 'update') {
        $id   = (int)($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? '';
        $pass = $_POST['password'] ?? '';
        if (!in_array($role, $roles, true)) {
            flash('error', 'Role tidak valid.');
        } elseif ($pass !== '' && strlen($pass) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
        } else {
            if ($id === (int)$user['id']) $role = $user['role'];
            if ($pass !== '') {
                db()->prepare('UPDATE users SET role = ?, password_hash = ? WHERE id = ?')
                    ->execute([$role, password_hash($pass, PASSWORD_BCRYPT), $id]);
            } else {
                db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
            }
            audit((int)$user['id'], 'pengguna_ubah', "Mengubah pengguna ID $id", 'user', $id);
            flash('success', 'Data pengguna diperbarui.');
        }
        redirect('pengguna.php');
    }

    if ($do === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$user['id']) {
            flash('error', 'Anda tidak bisa menghapus akun sendiri.');
        } else {
            $st = db()->prepare('SELECT file_path FROM documents WHERE user_id = ?');
            $st->execute([$id]);
            foreach ($st->fetchAll() as $f) @unlink(__DIR__ . '/uploads/' . basename($f['file_path']));
            db()->prepare('DELETE FROM documents WHERE user_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            audit((int)$user['id'], 'pengguna_hapus', "Menghapus pengguna ID $id", 'user', $id);
            flash('success', 'Pengguna dihapus.');
        }
        redirect('pengguna.php');
    }
}

$users = db()->query('SELECT id, name, email, role FROM users ORDER BY id ASC')->fetchAll();

$pageTitle = 'Data Pengguna';
$active = 'pengguna';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <h3>Tambah Pengguna</h3>
  <div class="muted">Akun dibuat oleh administrator. Password disimpan dalam bentuk hash.</div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="do" value="add">
    <div class="form-grid">
      <div><label>Nama</label><input type="text" name="name" required></div>
      <div><label>Email</label><input type="email" name="email" required></div>
      <div><label>Password (min. 8)</label><input type="password" name="password" required minlength="8"></div>
      <div><label>Role</label>
        <select name="role"><?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"><?= e(ucfirst($r)) ?></option><?php endforeach; ?></select></div>
    </div>
    <button class="btn" type="submit">Tambah Pengguna</button>
  </form>
</div>

<div class="card">
  <h3>Daftar Pengguna (<?= count($users) ?>)</h3>
  <table class="tbl" style="margin-top:10px">
    <thead><tr><th>Nama</th><th>Email</th><th>Role &amp; Reset Password</th><th>Hapus</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): $self = ((int)$u['id'] === (int)$user['id']); ?>
      <tr>
        <td><b><?= e($u['name']) ?></b><?= $self ? ' <span class="small">(Anda)</span>' : '' ?></td>
        <td><?= e($u['email']) ?></td>
        <td>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <select name="role" <?= $self ? 'disabled' : '' ?>>
              <?php foreach ($roles as $r): ?><option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
            </select>
            <?php if ($self): ?><input type="hidden" name="role" value="<?= e($u['role']) ?>"><?php endif; ?>
            <input type="password" name="password" placeholder="Password baru (opsional)" style="width:170px">
            <button class="btn sec sm" type="submit">Simpan</button>
          </form>
        </td>
        <td>
          <?php if (!$self): ?>
          <form method="post" onsubmit="return confirm('Hapus pengguna ini beserta seluruh dokumennya?')">
            <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button class="btn danger sm" type="submit">Hapus</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>