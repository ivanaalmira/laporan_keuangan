<?php
require 'config.php'; require_login(); need_owner();
$db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    try {
        if ($do === 'simpan') {
            $id = (int)$_POST['id']; $nama = trim($_POST['nama']); $un = trim($_POST['username']); $pw = $_POST['password'];
            $role = $_POST['role'] === 'owner' ? 'owner' : 'admin';
            if ($nama === '' || $un === '') throw new Exception('Nama dan username wajib diisi.');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $un)) throw new Exception('Username 3–50 karakter: huruf, angka, titik, garis bawah, atau strip.');
            $st = $db->prepare('SELECT COUNT(*) FROM users WHERE username=? AND id_user<>?'); $st->execute([$un, $id]);
            if ($st->fetchColumn()) throw new Exception('Username sudah dipakai.');
            if (!$id && strlen($pw) < 6) throw new Exception('Password pengguna baru minimal 6 karakter.');
            if ($pw !== '' && strlen($pw) < 6) throw new Exception('Password minimal 6 karakter.');
            if ($id) {
                if ($id === user()['id_user'] && $role !== 'owner') throw new Exception('Anda tidak dapat menurunkan peran akun Anda sendiri.');
                $db->prepare('UPDATE users SET nama=?,username=?,role=? WHERE id_user=?')->execute([$nama, $un, $role, $id]);
                if ($pw !== '') $db->prepare('UPDATE users SET password=? WHERE id_user=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
                log_aktivitas('Ubah pengguna', $un);
            } else {
                $db->prepare('INSERT INTO users(nama,username,password,role) VALUES(?,?,?,?)')->execute([$nama, $un, password_hash($pw, PASSWORD_DEFAULT), $role]);
                log_aktivitas('Tambah pengguna', $un . ' (' . $role . ')');
            }
            flash('success', 'Pengguna "' . $un . '" disimpan.');
        } elseif ($do === 'hapus') {
            $id = (int)$_POST['id'];
            if ($id === user()['id_user']) throw new Exception('Anda tidak dapat menghapus akun yang sedang dipakai.');
            try { $db->prepare('DELETE FROM users WHERE id_user=?')->execute([$id]); log_aktivitas('Hapus pengguna', '#' . $id); flash('success', 'Pengguna dihapus.'); }
            catch (PDOException $ex) { throw new Exception('Pengguna sudah mencatat transaksi sehingga tidak dapat dihapus.'); }
        }
    } catch (Exception $ex) { flash('danger', $ex->getMessage()); }
    redirect('pengguna.php');
}
$rows = $db->query('SELECT u.*,(SELECT COUNT(*) FROM transaksi t WHERE t.id_user=u.id_user) AS trx FROM users u ORDER BY role,nama')->fetchAll();
layout_start('Manajemen Pengguna', 'pengguna');
page_head('Manajemen pengguna', 'Owner melihat dan menyetujui laporan; admin keuangan mencatat transaksi.', '<button class="btn btn-amber" data-open="dlg" data-title="Tambah pengguna">' . icon('plus') . ' Tambah pengguna</button>');
?>
<div class="card flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th class="num">Transaksi dicatat</th><th>Dibuat</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['nama']) ?></b></td><td><?= e($r['username']) ?></td><td><?= pill($r['role'] === 'owner' ? 'Owner' : 'Admin Keuangan', $r['role'] === 'owner' ? 'amber' : 'blue') ?></td><td class="num"><?= (int)$r['trx'] ?></td><td><?= e(tgl($r['created_at'])) ?></td>
<td><div class="cell-actions"><button class="btn-icon" title="Ubah" data-open="dlg" data-title="Ubah pengguna" data-json='<?= e(json_encode(['id' => $r['id_user'], 'nama' => $r['nama'], 'username' => $r['username'], 'role' => $r['role']])) ?>'><?= icon('edit') ?></button>
<?php if ((int)$r['id_user'] !== user()['id_user']): ?><form method="post" data-confirm="Hapus pengguna ini?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_user'] ?>"><button class="btn-icon del" title="Hapus"><?= icon('trash') ?></button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<dialog class="modal" id="dlg"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="simpan"><input type="hidden" name="id" value="0">
  <div class="modal-h"><h2 data-title>Pengguna</h2></div><div class="modal-b"><div class="form-grid">
    <div class="field"><label for="u_nama">Nama lengkap</label><input type="text" id="u_nama" name="nama" required></div>
    <div class="field"><label for="u_un">Username</label><input type="text" id="u_un" name="username" required autocomplete="off"></div>
    <div class="field"><label for="u_role">Peran</label><select id="u_role" name="role"><option value="admin">Admin Keuangan</option><option value="owner">Owner</option></select></div>
    <div class="field"><label for="u_pw">Password</label><input type="password" id="u_pw" name="password" autocomplete="new-password" placeholder="Kosongkan bila tidak diganti"></div>
  </div></div><div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan pengguna</button></div></form></dialog>
<?php layout_end(); ?>
