<?php
require 'config.php'; require_login();
$db = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin();
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    try {
        if ($do === 'simpan') {
            $id = (int)$_POST['id']; $nama = trim($_POST['nama_kategori']); $jenis = $_POST['jenis'] === 'pengeluaran' ? 'pengeluaran' : 'pemasukan'; $des = trim($_POST['deskripsi']);
            if ($nama === '') throw new Exception('Nama kategori wajib diisi.');
            if ($id) { $db->prepare('UPDATE kategori SET nama_kategori=?,jenis=?,deskripsi=? WHERE id_kategori=?')->execute([$nama, $jenis, $des, $id]); log_aktivitas('Ubah kategori', $nama); }
            else { $db->prepare('INSERT INTO kategori(nama_kategori,jenis,deskripsi) VALUES(?,?,?)')->execute([$nama, $jenis, $des]); log_aktivitas('Tambah kategori', $nama); }
            flash('success', 'Kategori "' . $nama . '" disimpan.');
        } elseif ($do === 'hapus') {
            $id = (int)$_POST['id'];
            $db->prepare('DELETE FROM kategori WHERE id_kategori=?')->execute([$id]);
            log_aktivitas('Hapus kategori', '#' . $id);
            flash('success', 'Kategori dihapus. Transaksi lama tetap tersimpan tanpa kategori.');
        }
    } catch (Exception $ex) { flash('danger', $ex->getMessage()); }
    redirect('kategori.php');
}
$rows = $db->query('SELECT k.*,(SELECT COUNT(*) FROM transaksi t WHERE t.id_kategori=k.id_kategori) AS pakai FROM kategori k ORDER BY k.jenis,k.nama_kategori')->fetchAll();
layout_start('Kategori', 'kategori');
page_head('Kategori transaksi', 'Mengelompokkan pemasukan dan pengeluaran agar mudah ditelusuri.', is_owner() ? '' : '<button class="btn btn-amber" data-open="dlg" data-title="Tambah kategori">' . icon('plus') . ' Tambah kategori</button>');
?>
<div class="card flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Nama kategori</th><th>Jenis</th><th>Deskripsi</th><th class="num">Dipakai</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><b><?= e($r['nama_kategori']) ?></b></td><td><?= pill(ucfirst($r['jenis']), $r['jenis'] === 'pemasukan' ? 'green' : 'coral') ?></td><td class="muted"><?= e($r['deskripsi']) ?></td><td class="num"><?= (int)$r['pakai'] ?> transaksi</td>
  <td><?php if (!is_owner()): ?><div class="cell-actions"><button class="btn-icon" title="Ubah" data-open="dlg" data-title="Ubah kategori" data-json='<?= e(json_encode(['id' => $r['id_kategori'], 'nama_kategori' => $r['nama_kategori'], 'jenis' => $r['jenis'], 'deskripsi' => $r['deskripsi']])) ?>'><?= icon('edit') ?></button>
    <form method="post" data-confirm="Hapus kategori ini?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_kategori'] ?>"><button class="btn-icon del" title="Hapus"><?= icon('trash') ?></button></form></div><?php endif; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="5" class="empty">Belum ada kategori.</td></tr>'; ?>
</tbody></table></div></div>
<dialog class="modal" id="dlg"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="simpan"><input type="hidden" name="id" value="0">
  <div class="modal-h"><h2 data-title>Kategori</h2></div>
  <div class="modal-b"><div class="form-grid">
    <div class="field"><label for="k_nama">Nama kategori</label><input type="text" id="k_nama" name="nama_kategori" required maxlength="100"></div>
    <div class="field"><label for="k_jenis">Jenis</label><select id="k_jenis" name="jenis"><option value="pemasukan">Pemasukan</option><option value="pengeluaran">Pengeluaran</option></select></div>
    <div class="field full"><label for="k_des">Deskripsi</label><textarea id="k_des" name="deskripsi"></textarea></div></div></div>
  <div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan kategori</button></div></form></dialog>
<?php layout_end(); ?>
