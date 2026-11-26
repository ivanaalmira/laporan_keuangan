<?php
require 'config.php'; require_login();
$db = db();
$kasAkun = $db->query("SELECT id_akun,nama_akun FROM akun WHERE kode_akun IN ('1001','1002') ORDER BY kode_akun")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin();
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    try {
        $db->beginTransaction();
        if ($do === 'simpan') {
            $id = (int)$_POST['id']; $tgl = valid_date($_POST['tanggal']); $jml = (float)$_POST['jumlah']; $ket = trim($_POST['keterangan']);
            $jenis = in_array($_POST['jenis'], ['Setoran', 'Penarikan', 'Dividen']) ? $_POST['jenis'] : 'Setoran';
            if (!$tgl || $jml <= 0) throw new Exception('Tanggal dan nominal wajib diisi dengan benar.');
            if ($id) {
                $db->prepare('UPDATE modal SET tanggal=?,jenis=?,keterangan=?,jumlah=? WHERE id_modal=?')->execute([$tgl, $jenis, $ket, $jml, $id]);
                log_aktivitas('Ubah modal', $jenis . ' · ' . rp($jml));
            } else {
                $db->prepare('INSERT INTO modal(tanggal,jenis,keterangan,jumlah) VALUES(?,?,?,?)')->execute([$tgl, $jenis, $ket, $jml]);
                log_aktivitas('Tambah modal', $jenis . ' · ' . rp($jml));
                if (!empty($_POST['jurnal'])) {
                    $kas = (int)$_POST['akun_kas']; if (!$kas) throw new Exception('Pilih akun kas/bank.');
                    if ($jenis === 'Setoran') simpan_jurnal($tgl, nomor_baru('MDL'), 'Setoran modal. ' . $ket, 'Pemasukan', null, [[$kas, $jml, 0], [akun_id('3001'), 0, $jml]]);
                    else simpan_jurnal($tgl, nomor_baru('MDL'), $jenis . ' pemilik. ' . $ket, 'Pengeluaran', null, [[akun_id('3002'), $jml, 0], [$kas, 0, $jml]]);
                }
            }
            flash('success', 'Catatan modal disimpan.');
        } elseif ($do === 'hapus') {
            $db->prepare('DELETE FROM modal WHERE id_modal=?')->execute([(int)$_POST['id']]);
            log_aktivitas('Hapus modal', '#' . (int)$_POST['id']);
            flash('success', 'Catatan modal dihapus. Jurnal terkait (jika ada) tetap ada; batalkan lewat menu Transaksi.');
        }
        $db->commit();
    } catch (Exception $ex) { if ($db->inTransaction()) $db->rollBack(); flash('danger', $ex->getMessage()); }
    redirect('modal.php');
}
$rows = $db->query('SELECT * FROM modal ORDER BY tanggal DESC,id_modal DESC')->fetchAll();
$s = ['Setoran' => 0, 'Penarikan' => 0, 'Dividen' => 0]; foreach ($rows as $r) $s[$r['jenis']] += $r['jumlah'];
$ner = neraca(date('Y-m-d'));
layout_start('Modal', 'modal');
page_head('Modal pemilik', 'Setoran modal, penarikan pribadi (prive), dan dividen.', is_owner() ? '' : '<button class="btn btn-amber" data-open="dlg" data-title="Catat modal">' . icon('plus') . ' Catat modal</button>');
?>
<div class="stats">
  <div class="stat t-green"><span>Total setoran</span><strong><?= e(rp($s['Setoran'])) ?></strong><small>Tambahan modal</small></div>
  <div class="stat t-coral"><span>Penarikan dan dividen</span><strong><?= e(rp($s['Penarikan'] + $s['Dividen'])) ?></strong><small>Prive <?= e(rp($s['Penarikan'])) ?> · Dividen <?= e(rp($s['Dividen'])) ?></small></div>
  <div class="stat t-pine"><span>Modal bersih tercatat</span><strong><?= e(rp($s['Setoran'] - $s['Penarikan'] - $s['Dividen'])) ?></strong><small>Dari daftar di bawah</small></div>
  <div class="stat t-amber"><span>Ekuitas menurut neraca</span><strong><?= e(rp($ner['tot_ekuitas'])) ?></strong><small>Modal ditambah laba berjalan (jurnal)</small></div>
</div>
<div class="card flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="num">Jumlah</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><?= e(tgl($r['tanggal'])) ?></td><td><?= pill($r['jenis'], $r['jenis'] === 'Setoran' ? 'green' : 'coral') ?></td><td><?= e($r['keterangan']) ?></td><td class="num"><b><?= e(rp($r['jumlah'])) ?></b></td>
  <td><?php if (!is_owner()): ?><div class="cell-actions"><button class="btn-icon" title="Ubah" data-open="dlg" data-title="Ubah catatan modal" data-json='<?= e(json_encode(['id' => $r['id_modal'], 'tanggal' => $r['tanggal'], 'jenis' => $r['jenis'], 'keterangan' => $r['keterangan'], 'jumlah' => $r['jumlah']])) ?>'><?= icon('edit') ?></button>
    <form method="post" data-confirm="Hapus catatan modal ini?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="hapus"><input type="hidden" name="id" value="<?= (int)$r['id_modal'] ?>"><button class="btn-icon del" title="Hapus"><?= icon('trash') ?></button></form></div><?php endif; ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="5" class="empty">Belum ada catatan modal.</td></tr>'; ?></tbody></table></div></div>
<dialog class="modal" id="dlg"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="simpan"><input type="hidden" name="id" value="0">
  <div class="modal-h"><h2 data-title>Catat modal</h2></div><div class="modal-b"><div class="form-grid">
    <div class="field"><label for="m_tgl">Tanggal</label><input type="date" id="m_tgl" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
    <div class="field"><label for="m_jenis">Jenis</label><select id="m_jenis" name="jenis"><option>Setoran</option><option>Penarikan</option><option>Dividen</option></select></div>
    <div class="field full"><label for="m_jml">Jumlah (Rp)</label><input type="number" id="m_jml" name="jumlah" min="0" step="any" required data-rp></div>
    <div class="field full"><label for="m_ket">Keterangan</label><textarea id="m_ket" name="keterangan"></textarea></div>
    <div class="field full" data-new-only><label class="check"><input type="checkbox" name="jurnal" value="1" checked><span>Catat ke jurnal. Setoran: Debit Kas/Bank, Kredit Modal Pemilik. Penarikan/dividen: Debit Prive, Kredit Kas/Bank. Hilangkan centang bila sudah tercatat.</span></label></div>
    <div class="field full" data-new-only><label for="m_kas">Akun kas/bank</label><select id="m_kas" name="akun_kas"><?php foreach ($kasAkun as $k) echo '<option value="' . $k['id_akun'] . '">' . e($k['nama_akun']) . '</option>'; ?></select></div>
  </div></div><div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan</button></div></form></dialog>
<?php layout_end(); ?>
