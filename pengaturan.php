<?php
require 'config.php'; require_login();
$db = db();
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['periode', 'keamanan']) ? $_GET['tab'] : 'perusahaan';

/* ---------- Backup database (unduh .sql) ---------- */
if (isset($_GET['backup'])) {
    log_aktivitas('Backup database', 'Unduh file SQL');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql"');
    echo "-- Backup " . DB_NAME . " · " . date('Y-m-d H:i:s') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $c = array_values($db->query('SHOW CREATE TABLE `' . $t . '`')->fetch());
        echo 'DROP TABLE IF EXISTS `' . $t . "`;\n" . $c[1] . ";\n\n";
        $st = $db->query('SELECT * FROM `' . $t . '`');
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            $vals = [];
            foreach ($r as $v) $vals[] = $v === null ? 'NULL' : $db->quote($v);
            echo 'INSERT INTO `' . $t . '` VALUES (' . implode(',', $vals) . ");\n";
        }
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    $back = 'pengaturan.php?tab=' . $tab;
    try {
        if ($do === 'perusahaan') {
            set_setting('nama_perusahaan', trim($_POST['nama_perusahaan']) ?: 'Perusahaan Anda');
            set_setting('alamat', trim($_POST['alamat'])); set_setting('telepon', trim($_POST['telepon']));
            if (!empty($_FILES['logo']['name'])) {
                $f = $_FILES['logo'];
                if ($f['error'] !== UPLOAD_ERR_OK) throw new Exception('Logo gagal diunggah. Coba lagi.');
                if ($f['size'] > 1048576) throw new Exception('Ukuran logo maksimal 1 MB.');
                $info = @getimagesize($f['tmp_name']);
                $ext = $info ? [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'] : [];
                if (!$info || !isset($ext[$info[2]])) throw new Exception('Logo harus berupa gambar PNG, JPG, atau WEBP.');
                $nama = 'logo_' . time() . '.' . $ext[$info[2]];
                if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
                if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $nama)) throw new Exception('Folder uploads tidak dapat ditulis.');
                $lama = setting('logo', ''); if ($lama && file_exists(__DIR__ . '/uploads/' . $lama)) @unlink(__DIR__ . '/uploads/' . $lama);
                set_setting('logo', $nama);
            }
            log_aktivitas('Ubah profil perusahaan', trim($_POST['nama_perusahaan']));
            flash('success', 'Profil perusahaan disimpan.');
        } elseif ($do === 'periode_simpan') {
            need_admin();
            $id = (int)$_POST['id']; $nama = trim($_POST['nama_periode']); $a = valid_date($_POST['tanggal_mulai']); $b = valid_date($_POST['tanggal_selesai']);
            if ($nama === '' || !$a || !$b || $a > $b) throw new Exception('Isi nama periode dan pastikan tanggal mulai tidak melebihi tanggal selesai.');
            if ($id) {
                $st = $db->prepare('SELECT status FROM periode_laporan WHERE id_periode=?'); $st->execute([$id]);
                if ($st->fetchColumn() === 'Ditutup') throw new Exception('Periode yang sudah ditutup tidak dapat diubah.');
                $db->prepare('UPDATE periode_laporan SET nama_periode=?,tanggal_mulai=?,tanggal_selesai=? WHERE id_periode=?')->execute([$nama, $a, $b, $id]);
            } else $db->prepare("INSERT INTO periode_laporan(nama_periode,tanggal_mulai,tanggal_selesai,status) VALUES(?,?,?,'Terbuka')")->execute([$nama, $a, $b]);
            log_aktivitas($id ? 'Ubah periode' : 'Tambah periode', $nama); flash('success', 'Periode "' . $nama . '" disimpan.'); $back = 'pengaturan.php?tab=periode';
        } elseif ($do === 'periode_tutup' || $do === 'periode_buka') {
            need_owner(); $id = (int)$_POST['id']; $back = 'pengaturan.php?tab=periode';
            $st = $db->prepare('SELECT * FROM periode_laporan WHERE id_periode=?'); $st->execute([$id]); $pr = $st->fetch();
            if (!$pr) throw new Exception('Periode tidak ditemukan.');
            if ($do === 'periode_tutup') {
                $st = $db->prepare("SELECT status FROM persetujuan_laporan WHERE id_periode=?"); $st->execute([$id]);
                if ($st->fetchColumn() !== 'Disetujui') throw new Exception('Setujui laporan periode ini terlebih dahulu (menu Laporan Keuangan) sebelum menutup buku.');
                $db->prepare("UPDATE periode_laporan SET status='Ditutup' WHERE id_periode=?")->execute([$id]);
                log_aktivitas('Tutup buku', $pr['nama_periode']); flash('success', 'Buku periode ' . $pr['nama_periode'] . ' ditutup. Transaksi pada rentang ini kini terkunci.');
            } else {
                $db->prepare("UPDATE periode_laporan SET status='Terbuka' WHERE id_periode=?")->execute([$id]);
                $db->prepare("UPDATE persetujuan_laporan SET status='Perlu Koreksi',catatan='Periode dibuka kembali oleh owner.',disetujui_oleh=NULL,tgl_disetujui=NULL WHERE id_periode=?")->execute([$id]);
                log_aktivitas('Buka kembali periode', $pr['nama_periode']); flash('warn', 'Periode ' . $pr['nama_periode'] . ' dibuka kembali. Persetujuan laporan direset.');
            }
        } elseif ($do === 'periode_hapus') {
            need_admin(); $id = (int)$_POST['id']; $back = 'pengaturan.php?tab=periode';
            $st = $db->prepare('SELECT status FROM periode_laporan WHERE id_periode=?'); $st->execute([$id]);
            if ($st->fetchColumn() === 'Ditutup') throw new Exception('Periode yang sudah ditutup tidak dapat dihapus.');
            $db->prepare('DELETE FROM periode_laporan WHERE id_periode=?')->execute([$id]); log_aktivitas('Hapus periode', '#' . $id); flash('success', 'Periode dihapus.');
        } elseif ($do === 'password') {
            $lama = $_POST['lama']; $baru = $_POST['baru']; $ulang = $_POST['ulang']; $back = 'pengaturan.php?tab=keamanan';
            $st = $db->prepare('SELECT password FROM users WHERE id_user=?'); $st->execute([user()['id_user']]);
            if (!password_verify($lama, $st->fetchColumn())) throw new Exception('Password lama tidak cocok.');
            if (strlen($baru) < 6) throw new Exception('Password baru minimal 6 karakter.');
            if ($baru !== $ulang) throw new Exception('Konfirmasi password baru tidak sama.');
            $db->prepare('UPDATE users SET password=? WHERE id_user=?')->execute([password_hash($baru, PASSWORD_DEFAULT), user()['id_user']]);
            log_aktivitas('Ganti password', 'Mengubah password sendiri'); flash('success', 'Password berhasil diganti.');
        }
    } catch (Exception $ex) { flash('danger', $ex->getMessage()); }
    redirect($back);
}

layout_start('Pengaturan', 'pengaturan');
page_head('Pengaturan', 'Profil perusahaan, periode laporan, dan keamanan akun.');
echo '<div class="tabs"><a href="?tab=perusahaan" class="' . ($tab === 'perusahaan' ? 'active' : '') . '">Perusahaan</a><a href="?tab=periode" class="' . ($tab === 'periode' ? 'active' : '') . '">Periode laporan</a><a href="?tab=keamanan" class="' . ($tab === 'keamanan' ? 'active' : '') . '">Keamanan dan backup</a></div>';

if ($tab === 'perusahaan') { ?>
  <div class="card" style="max-width:720px"><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="do" value="perusahaan"><div class="form-grid">
    <div class="field full"><label for="np">Nama perusahaan</label><input type="text" id="np" name="nama_perusahaan" value="<?= e(setting('nama_perusahaan', 'Perusahaan Anda')) ?>" required></div>
    <div class="field full"><label for="al">Alamat</label><textarea id="al" name="alamat"><?= e(setting('alamat')) ?></textarea></div>
    <div class="field"><label for="tl">Telepon</label><input type="text" id="tl" name="telepon" value="<?= e(setting('telepon')) ?>"></div>
    <div class="field"><label for="lg">Logo (PNG/JPG/WEBP, maks 1 MB)</label><input type="file" id="lg" name="logo" accept="image/png,image/jpeg,image/webp"></div>
  </div><div class="actions" style="margin-top:18px"><button class="btn btn-primary">Simpan profil</button></div></form></div>
<?php } elseif ($tab === 'periode') {
    $rows = $db->query('SELECT p.*,(SELECT status FROM persetujuan_laporan a WHERE a.id_periode=p.id_periode) AS persetujuan FROM periode_laporan p ORDER BY tanggal_mulai DESC')->fetchAll(); ?>
  <div class="card flush"><div class="card-h"><h2>Periode laporan</h2><?= is_owner() ? '' : '<button class="btn btn-amber btn-sm" data-open="dlg" data-title="Tambah periode">' . icon('plus') . ' Tambah periode</button>' ?></div>
  <p class="hint" style="padding:6px 22px 0">Alur: admin mengajukan laporan → owner menyetujui → owner menutup buku. Periode yang ditutup mengunci semua transaksi di rentangnya.</p>
  <div class="tbl-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>Periode</th><th>Rentang</th><th>Persetujuan</th><th>Status buku</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $ts = $r['status'] === 'Ditutup'; ?>
    <tr><td><b><?= e($r['nama_periode']) ?></b></td><td><?= e(tgl($r['tanggal_mulai'])) ?> – <?= e(tgl($r['tanggal_selesai'])) ?></td>
    <td><a href="laporan.php?periode=<?= (int)$r['id_periode'] ?>"><?= e($r['persetujuan'] ?: 'Draft') ?></a></td><td><?= pill($r['status'], $ts ? 'amber' : 'green') ?></td>
    <td><div class="cell-actions">
      <?php if (is_owner()): ?><form method="post" data-confirm="<?= $ts ? 'Buka kembali periode ini? Persetujuan akan direset.' : 'Tutup buku periode ini? Transaksi di rentangnya akan terkunci.' ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="<?= $ts ? 'periode_buka' : 'periode_tutup' ?>"><input type="hidden" name="id" value="<?= (int)$r['id_periode'] ?>"><button class="btn btn-ghost btn-sm"><?= $ts ? 'Buka kembali' : 'Tutup buku' ?></button></form>
      <?php elseif (!$ts): ?><button class="btn-icon" title="Ubah" data-open="dlg" data-title="Ubah periode" data-json='<?= e(json_encode(['id' => $r['id_periode'], 'nama_periode' => $r['nama_periode'], 'tanggal_mulai' => $r['tanggal_mulai'], 'tanggal_selesai' => $r['tanggal_selesai']])) ?>'><?= icon('edit') ?></button>
        <form method="post" data-confirm="Hapus periode ini?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="periode_hapus"><input type="hidden" name="id" value="<?= (int)$r['id_periode'] ?>"><button class="btn-icon del" title="Hapus"><?= icon('trash') ?></button></form><?php endif; ?></div></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="5" class="empty">Belum ada periode.</td></tr>'; ?></tbody></table></div></div>
  <dialog class="modal" id="dlg"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="periode_simpan"><input type="hidden" name="id" value="0">
    <div class="modal-h"><h2 data-title>Periode</h2></div><div class="modal-b"><div class="form-grid">
      <div class="field full"><label for="pn">Nama periode</label><input type="text" id="pn" name="nama_periode" placeholder="Desember 2026" required></div>
      <div class="field"><label for="pa">Tanggal mulai</label><input type="date" id="pa" name="tanggal_mulai" required></div>
      <div class="field"><label for="pb">Tanggal selesai</label><input type="date" id="pb" name="tanggal_selesai" required></div>
    </div></div><div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan periode</button></div></form></dialog>
<?php } else { ?>
  <div class="grid grid-2">
    <div class="card"><div class="card-h"><h2>Ganti password</h2></div><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="password"><div class="form-grid">
      <div class="field full"><label for="pl">Password lama</label><input type="password" id="pl" name="lama" required autocomplete="current-password"></div>
      <div class="field"><label for="pb2">Password baru</label><input type="password" id="pb2" name="baru" required minlength="6" autocomplete="new-password"></div>
      <div class="field"><label for="pu">Ulangi password baru</label><input type="password" id="pu" name="ulang" required autocomplete="new-password"></div>
    </div><div class="actions" style="margin-top:18px"><button class="btn btn-primary">Ganti password</button></div></form></div>
    <div class="card"><div class="card-h"><h2>Backup database</h2></div>
      <p class="muted" style="margin-top:0">Unduh salinan seluruh tabel dalam satu file .sql. Simpan di tempat aman dan lakukan secara rutin. Untuk memulihkan, impor file ini lewat phpMyAdmin.</p>
      <a class="btn btn-amber" href="pengaturan.php?backup=1"><?= icon('download') ?> Unduh backup (.sql)</a></div>
  </div>
<?php }
layout_end();
