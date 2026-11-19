<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
require_admin($user);

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;

$where = ''; $p = [];
if ($q !== '') {
    $where = 'WHERE (a.action LIKE ? OR a.entity_type LIKE ? OR CAST(a.details AS CHAR) LIKE ? OR u.name LIKE ?)';
    $p = ["%$q%", "%$q%", "%$q%", "%$q%"];
}

$st = db()->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id $where");
$st->execute($p);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;

$st = db()->prepare("SELECT a.*, u.name AS uname FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                     $where ORDER BY a.id DESC LIMIT $per OFFSET $off");
$st->execute($p);
$logs = $st->fetchAll();

function fmt_details($raw): string {
    if ($raw === null || $raw === '') return '-';
    $j = json_decode($raw, true);
    if (!is_array($j)) return (string)$raw;
    if (isset($j['keterangan'])) return (string)$j['keterangan'];
    $out = [];
    foreach ($j as $k => $v) {
        $out[] = $k . ': ' . (is_scalar($v) || $v === null ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
    }
    return implode(', ', $out);
}

$pageTitle = 'Audit Trail / Log';
$active = 'audit';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="card">
  <form class="filters" method="get">
    <input type="text" name="q" placeholder="Cari aksi, entitas, detail, atau nama..." value="<?= e($q) ?>">
    <button class="btn sec" type="submit">Cari</button>
  </form>

  <?php if (!$logs): ?>
    <div class="empty"><b>Belum ada log</b>Aktivitas pengguna akan tercatat di sini.</div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="small"><?= e(date('d/m/Y H:i:s', strtotime($l['created_at']))) ?></td>
        <td><?= e($l['uname'] ?? 'Sistem') ?></td>
        <td><span class="badge bd-diproses"><?= e($l['action']) ?></span></td>
        <td class="small"><?= e($l['entity_type']) ?><?= $l['entity_id'] ? ' #' . (int)$l['entity_id'] : '' ?></td>
        <td><?= e(fmt_details($l['details'])) ?></td>
        <td class="small"><?= e($l['ip_address']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a class="<?= $i === $page ? 'on' : '' ?>" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>