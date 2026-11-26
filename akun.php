<?php
require 'config.php'; require_login();
$db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin();
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    try {
        if ($do === 'simpan') {
            $id = (int)$_POST['id']; $kode = trim($_POST['kode_akun']); $nama = trim($_POST['nama_akun']);
            $kel = in_array($_POST['kelompok'], ['Aset', 'Kewajiban', 'Modal', 'Pendapatan', 'Beban']) ? $_POST['kelompok'] : 'Aset';
            $sn = $_POST['saldo_normal'] === 'Kredit' ? 'Kredit' : 'Debit';
            if ($kode === '' || $nama === '') throw new Exception('Kode dan nama akun wajib diisi.');
            $st = $db->prepare('SELECT COUNT(*) FROM akun WHERE kode_akun=? AND id_akun<>?'); $st->execute([$kode, $id]);
            if ($st->fetchColumn()) throw new Exception('Kode akun ' . $kode . ' sudah dipakai.');
            if ($id) { $db->prepare('UPDATE akun SET kode_akun=?,nama_akun=?,kelompok=?,saldo_normal=? WHERE id_akun=?')->execute([$kode, $nama, $kel, $sn, $id]); log_aktivitas('Ubah akun', "$kode $nama"); }
            else { $db->prepare('INSERT INTO akun(kode_akun,nama_akun,kelompok,saldo_normal) VALUES(?,?,?,?)')->execute([$kode, $nama, $kel, $sn]); log_aktivitas('Tambah akun', "$kode $nama"); }
            flash('success', 'Akun ' . $kode . ' · ' . $nama . ' disimpan.');
        } elseif ($do === 'hapus') {
            $id = (int)$_POST['id'];
            try { $db->prepare('DELETE FROM akun WHERE id_akun=?')->execute([$id]); log_aktivitas('Hapus akun', '#' . $id); flash('success', 'Akun dihapus.'); }
            catch (PDOException $ex) { flash('danger', 'Akun tidak dapat dihapus karena sudah dipakai di jurnal.'); }
        }
    } catch (Exception $ex) { flash('danger', $ex->getMessage()); }
    redirect('akun.php');
}
$rows = saldo_rows();
$cls = ['Aset' => 'blue', 'Kewajiban' => 'amber', 'Modal' => 'gray', 'Pendapatan' => 'green', 'Beban' => 'coral'];
layout_start('Daftar Akun', 'akun');
page_head('Daftar akun', 'Chart of accounts: dasar pengelompokan seluruh jurnal.', is_owner() ? '' : '<button class="btn btn-amber" data-open="dlg" data-title="Tambah akun">' . icon('plus') . ' Tambah akun</button>');
?>
<div class="card flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Kode</th><th>Nama akun</th><th>Kelompok</th><th>Saldo normal</th><th class="num">Saldo saat ini</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><b><?= e($r['kode_akun']) ?></b></td><td><?= e($r['nama_akun']) ?></td><td><?= pill($r['kelompok'], $cls[$r['kelompok']]) ?></td><td><?= e($r['saldo_normal']) ?></td>
  <td class="num"><?= e(rp($r['saldo_normal'] === 'Debit' ? $r['debit'] - $r['kredit'] : $r['kredit'] - $r['debit'])) ?></td>
  <td><?php if (!is_owner()): ?><div class="cell-actions"><button class="btn-icon" title="Ubah" data-open="dlg" data-title="Ubah akun" data-json='<?= e(json_encode(['id' => $r['id_akun'], 'kode_akun' => $r['kode_akun'], 'nama_akun' => $r['nama_akun'], 'kelompok' => $r['kelompok'], 'saldo_normal' => $r['saldo_normal']])) ?>'><?= icon('edit') ?></button>
    <form method="post" data-confirm="Hapus akun ini?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_akun'] ?>"><button class="btn-icon del" title="Hapus"><?= icon('trash') ?></button></form></div><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<dialog class="modal" id="dlg"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="simpan"><input type="hidden" name="id" value="0">
  <div class="modal-h"><h2 data-title>Akun</h2></div>
  <div class="modal-b"><div class="form-grid">
    <div class="field"><label for="a_kode">Kode akun</label><input type="text" id="a_kode" name="kode_akun" required maxlength="20"></div>
    <div class="field"><label for="a_nama">Nama akun</label><input type="text" id="a_nama" name="nama_akun" required maxlength="100"></div>
    <div class="field"><label for="akun_kelompok">Kelompok</label><select id="akun_kelompok" name="kelompok"><option>Aset</option><option>Kewajiban</option><option>Modal</option><option>Pendapatan</option><option>Beban</option></select></div>
    <div class="field"><label for="akun_saldo">Saldo normal</label><select id="akun_saldo" name="saldo_normal"><option>Debit</option><option>Kredit</option></select></div>
    <p class="hint full">Aset dan Beban biasanya bersaldo normal Debit; Kewajiban, Modal, dan Pendapatan bersaldo normal Kredit.</p></div></div>
  <div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan akun</button></div></form></dialog>
<?php layout_end(); ?>
