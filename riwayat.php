<?php
require 'config.php'; require_login();
$db = db();
$q = trim(isset($_GET['q']) ? $_GET['q'] : ''); $uid = (int)(isset($_GET['user']) ? $_GET['user'] : 0);
$dari = valid_date(isset($_GET['dari']) ? $_GET['dari'] : ''); $sampai = valid_date(isset($_GET['sampai']) ? $_GET['sampai'] : '');
$w = []; $pa = [];
if ($q !== '') { $w[] = '(l.aksi LIKE ? OR l.detail LIKE ?)'; $pa[] = "%$q%"; $pa[] = "%$q%"; }
if ($uid) { $w[] = 'l.id_user=?'; $pa[] = $uid; }
if ($dari) { $w[] = 'DATE(l.created_at)>=?'; $pa[] = $dari; }
if ($sampai) { $w[] = 'DATE(l.created_at)<=?'; $pa[] = $sampai; }
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
$rows = []; $pages = 1; $page = 1; $err = '';
try {
    $st = $db->prepare('SELECT COUNT(*) FROM log_aktivitas l' . $where); $st->execute($pa);
    list($page, $pages, $off) = paginate((int)$st->fetchColumn(), 20, (int)(isset($_GET['hal']) ? $_GET['hal'] : 1));
    $st = $db->prepare('SELECT l.*,u.nama FROM log_aktivitas l LEFT JOIN users u ON u.id_user=l.id_user' . $where . ' ORDER BY l.id_log DESC LIMIT 20 OFFSET ' . (int)$off);
    $st->execute($pa); $rows = $st->fetchAll();
} catch (Exception $ex) { $err = 'Tabel riwayat belum ada. Jalankan setup.php terlebih dahulu.'; }
$users = $db->query('SELECT id_user,nama FROM users ORDER BY nama')->fetchAll();
layout_start('Riwayat Aktivitas', 'riwayat');
page_head('Riwayat aktivitas', 'Jejak audit: siapa mengubah apa dan kapan.');
if ($err) echo '<div class="alert alert-danger">' . e($err) . '</div>';
?>
<div class="card flush"><form class="filters" method="get">
  <div class="field grow"><label for="q">Cari aktivitas</label><input type="search" id="q" name="q" value="<?= e($q) ?>"></div>
  <div class="field"><label for="u">Pengguna</label><select id="u" name="user"><option value="">Semua</option><?php foreach ($users as $u) echo '<option value="' . $u['id_user'] . '"' . ($uid == $u['id_user'] ? ' selected' : '') . '>' . e($u['nama']) . '</option>'; ?></select></div>
  <div class="field"><label for="d1">Dari</label><input type="date" id="d1" name="dari" value="<?= e($dari) ?>"></div>
  <div class="field"><label for="d2">Sampai</label><input type="date" id="d2" name="sampai" value="<?= e($sampai) ?>"></div>
  <button class="btn btn-primary">Terapkan</button><a class="btn btn-ghost" href="riwayat.php">Reset</a></form>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Rincian</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="num" style="text-align:left"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td><td><?= e($r['nama'] ?: 'Sistem') ?></td><td><?= pill($r['aksi'], 'gray') ?></td><td><?= e($r['detail']) ?></td><td class="muted"><?= e($r['ip']) ?></td></tr><?php endforeach;
if (!$rows && !$err) echo '<tr><td colspan="5" class="empty">Belum ada aktivitas.</td></tr>'; ?></tbody></table></div><?= pager($page, $pages) ?></div>
<?php layout_end(); ?>
