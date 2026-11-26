<?php
require 'config.php'; require_login();
$db = db();
$tab = (isset($_GET['tab']) && $_GET['tab'] === 'kewajiban') ? 'kewajiban' : 'aset';
$kasAkun = $db->query("SELECT id_akun,nama_akun FROM akun WHERE kode_akun IN ('1001','1002') ORDER BY kode_akun")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin();
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    $back = 'aset_kewajiban.php?tab=' . (in_array($do, ['simpan_kew', 'lunas', 'hapus_kew']) ? 'kewajiban' : 'aset');
    try {
        $db->beginTransaction();
        if ($do === 'simpan_aset') {
            $id = (int)$_POST['id']; $nama = trim($_POST['nama_aset']); $kat = trim($_POST['kategori_aset']);
            $tgl = valid_date($_POST['tanggal_perolehan']); $nilai = (float)$_POST['nilai_perolehan']; $akum = (float)$_POST['akumulasi_penyusutan'];
            if ($nama === '' || $kat === '' || !$tgl) throw new Exception('Nama, kategori, dan tanggal perolehan wajib diisi.');
            if ($nilai < 0 || $akum < 0 || $akum > $nilai) throw new Exception('Akumulasi penyusutan tidak boleh melebihi nilai perolehan.');
            $nb = $nilai - $akum;
            if ($id) {
                $db->prepare('UPDATE aset SET nama_aset=?,kategori_aset=?,tanggal_perolehan=?,nilai_perolehan=?,akumulasi_penyusutan=?,nilai_buku=? WHERE id_aset=?')->execute([$nama, $kat, $tgl, $nilai, $akum, $nb, $id]);
                log_aktivitas('Ubah aset', $nama);
            } else {
                $db->prepare('INSERT INTO aset(nama_aset,kategori_aset,tanggal_perolehan,nilai_perolehan,akumulasi_penyusutan,nilai_buku) VALUES(?,?,?,?,?,?)')->execute([$nama, $kat, $tgl, $nilai, $akum, $nb]);
                log_aktivitas('Tambah aset', $nama . ' · ' . rp($nilai));
                if (!empty($_POST['jurnal']) && $nilai > 0) {
                    $bayar = (int)$_POST['akun_bayar']; if (!$bayar) throw new Exception('Pilih akun kas/bank untuk pembayaran aset.');
                    simpan_jurnal($tgl, nomor_baru('AST'), 'Pembelian aset: ' . $nama, 'Pengeluaran', null, [[akun_id('1005'), $nilai, 0], [$bayar, 0, $nilai]]);
                }
            }
            flash('success', 'Aset "' . $nama . '" disimpan.');
        } elseif ($do === 'susut') {
            $id = (int)$_POST['id']; $tgl = valid_date($_POST['tanggal']); $j = (float)$_POST['jumlah'];
            $st = $db->prepare('SELECT * FROM aset WHERE id_aset=?'); $st->execute([$id]); $a = $st->fetch();
            if (!$a || !$tgl) throw new Exception('Data penyusutan tidak valid.');
            if ($j <= 0 || $j > (float)$a['nilai_buku'] + 0.004) throw new Exception('Jumlah penyusutan harus lebih dari nol dan tidak melebihi nilai buku ' . rp($a['nilai_buku']) . '.');
            $db->prepare('UPDATE aset SET akumulasi_penyusutan=akumulasi_penyusutan+?,nilai_buku=nilai_buku-? WHERE id_aset=?')->execute([$j, $j, $id]);
            simpan_jurnal($tgl, nomor_baru('DEP'), 'Penyusutan ' . $a['nama_aset'], 'Pengeluaran', null, [[akun_id('5005'), $j, 0], [akun_id('1006'), 0, $j]]);
            log_aktivitas('Penyusutan aset', $a['nama_aset'] . ' · ' . rp($j));
            flash('success', 'Penyusutan ' . rp($j) . ' dicatat dan masuk ke jurnal.');
        } elseif ($do === 'hapus_aset') {
            $db->prepare('DELETE FROM aset WHERE id_aset=?')->execute([(int)$_POST['id']]);
            log_aktivitas('Hapus aset', '#' . (int)$_POST['id']);
            flash('success', 'Aset dihapus dari daftar. Jurnal yang sudah dibuat tidak ikut terhapus; batalkan lewat menu Transaksi bila perlu.');
        } elseif ($do === 'simpan_kew') {
            $id = (int)$_POST['id']; $nama = trim($_POST['nama_kewajiban']); $jenis = $_POST['jenis'] === 'Jangka Panjang' ? 'Jangka Panjang' : 'Jangka Pendek';
            $tgl = valid_date($_POST['tanggal']); $jt = valid_date($_POST['jatuh_tempo']) ?: null; $jml = (float)$_POST['jumlah'];
            $status = $_POST['status'] === 'Lunas' ? 'Lunas' : 'Belum Lunas';
            if ($nama === '' || !$tgl || $jml <= 0) throw new Exception('Nama, tanggal, dan jumlah kewajiban wajib diisi dengan benar.');
            if ($id) {
                $db->prepare('UPDATE kewajiban SET nama_kewajiban=?,jenis=?,tanggal=?,jatuh_tempo=?,jumlah=?,status=? WHERE id_kewajiban=?')->execute([$nama, $jenis, $tgl, $jt, $jml, $status, $id]);
                log_aktivitas('Ubah kewajiban', $nama);
            } else {
                $db->prepare('INSERT INTO kewajiban(nama_kewajiban,jenis,tanggal,jatuh_tempo,jumlah,status) VALUES(?,?,?,?,?,?)')->execute([$nama, $jenis, $tgl, $jt, $jml, $status]);
                log_aktivitas('Tambah kewajiban', $nama . ' · ' . rp($jml));
                if (!empty($_POST['jurnal'])) {
                    $kas = (int)$_POST['akun_bayar']; if (!$kas) throw new Exception('Pilih akun kas/bank yang menerima dana.');
                    simpan_jurnal($tgl, nomor_baru('UTG'), 'Penerimaan utang: ' . $nama, 'Pemasukan', null, [[$kas, $jml, 0], [akun_id($jenis === 'Jangka Panjang' ? '2002' : '2001'), 0, $jml]]);
                }
            }
            flash('success', 'Kewajiban "' . $nama . '" disimpan.');
        } elseif ($do === 'lunas') {
            $id = (int)$_POST['id']; $tgl = valid_date($_POST['tanggal']);
            $st = $db->prepare('SELECT * FROM kewajiban WHERE id_kewajiban=?'); $st->execute([$id]); $k = $st->fetch();
            if (!$k || !$tgl) throw new Exception('Data pelunasan tidak valid.');
            if ($k['status'] === 'Lunas') throw new Exception('Kewajiban ini sudah berstatus lunas.');
            $db->prepare("UPDATE kewajiban SET status='Lunas' WHERE id_kewajiban=?")->execute([$id]);
            if (!empty($_POST['jurnal'])) {
                $kas = (int)$_POST['akun_bayar']; if (!$kas) throw new Exception('Pilih akun kas/bank untuk pembayaran.');
                simpan_jurnal($tgl, nomor_baru('PYR'), 'Pelunasan utang: ' . $k['nama_kewajiban'], 'Pengeluaran', null, [[akun_id($k['jenis'] === 'Jangka Panjang' ? '2002' : '2001'), (float)$k['jumlah'], 0], [$kas, 0, (float)$k['jumlah']]]);
            }
            log_aktivitas('Pelunasan kewajiban', $k['nama_kewajiban'] . ' · ' . rp($k['jumlah']));
            flash('success', 'Kewajiban "' . $k['nama_kewajiban'] . '" ditandai lunas.');
        } elseif ($do === 'hapus_kew') {
            $db->prepare('DELETE FROM kewajiban WHERE id_kewajiban=?')->execute([(int)$_POST['id']]);
            log_aktivitas('Hapus kewajiban', '#' . (int)$_POST['id']);
            flash('success', 'Kewajiban dihapus dari daftar.');
        }
        $db->commit();
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        flash('danger', $ex->getMessage());
    }
    redirect($back);
}

$optKas = ''; foreach ($kasAkun as $k) $optKas .= '<option value="' . $k['id_akun'] . '">' . e($k['nama_akun']) . '</option>';
layout_start('Aset dan Kewajiban', 'aset');
page_head('Aset dan kewajiban', 'Data pendukung neraca. Pembelian, penyusutan, dan pelunasan dapat langsung dijurnal.');
echo '<div class="tabs"><a href="?tab=aset" class="' . ($tab === 'aset' ? 'active' : '') . '">Aset</a><a href="?tab=kewajiban" class="' . ($tab === 'kewajiban' ? 'active' : '') . '">Kewajiban</a></div>';

if ($tab === 'aset') {
    $rows = $db->query('SELECT * FROM aset ORDER BY tanggal_perolehan DESC,id_aset DESC')->fetchAll();
    $sum = $db->query('SELECT COALESCE(SUM(nilai_perolehan),0),COALESCE(SUM(akumulasi_penyusutan),0),COALESCE(SUM(nilai_buku),0) FROM aset')->fetch(PDO::FETCH_NUM);
    $nr = saldo_rows(null, date('Y-m-d')); $jur = 0;
    foreach ($nr as $r) if ($r['kode_akun'] === '1005') $jur += $r['nilai']; elseif ($r['kode_akun'] === '1006') $jur -= $r['nilai'];
    ?>
    <div class="stats">
      <div class="stat t-blue"><span>Nilai perolehan</span><strong><?= e(rp($sum[0])) ?></strong><small><?= count($rows) ?> aset terdaftar</small></div>
      <div class="stat t-coral"><span>Akumulasi penyusutan</span><strong><?= e(rp($sum[1])) ?></strong><small>Pengurang nilai aset</small></div>
      <div class="stat t-green"><span>Nilai buku</span><strong><?= e(rp($sum[2])) ?></strong><small>Perolehan dikurangi penyusutan</small></div>
      <div class="stat t-amber"><span>Aset tetap di jurnal</span><strong><?= e(rp($jur)) ?></strong><small>Yang masuk neraca (akun 1005 dan 1006)</small></div>
    </div>
    <?php if (abs($jur - $sum[2]) > 0.5): ?><div class="alert alert-info">Nilai buku daftar aset (<?= e(rp($sum[2])) ?>) berbeda dari aset tetap di jurnal (<?= e(rp($jur)) ?>). Neraca memakai jurnal. Aset lama yang belum dijurnal tidak muncul di neraca; catat melalui menu Modal atau Transaksi manual bila perlu.</div><?php endif; ?>
    <div class="card flush"><div class="card-h"><h2>Daftar aset</h2><?= is_owner() ? '' : '<button class="btn btn-amber btn-sm" data-open="dlgAset" data-title="Tambah aset">' . icon('plus') . ' Tambah aset</button>' ?></div>
    <div class="tbl-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>Aset</th><th>Diperoleh</th><th class="num">Nilai perolehan</th><th class="num">Akumulasi penyusutan</th><th class="num">Nilai buku</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><b><?= e($r['nama_aset']) ?></b><br><small class="muted"><?= e($r['kategori_aset']) ?></small></td><td><?= e(tgl($r['tanggal_perolehan'])) ?></td><td class="num"><?= e(rp($r['nilai_perolehan'])) ?></td><td class="num"><?= e(rp($r['akumulasi_penyusutan'])) ?></td><td class="num"><b><?= e(rp($r['nilai_buku'])) ?></b></td>
      <td><?php if (!is_owner()): ?><div class="cell-actions"><button class="btn btn-ghost btn-sm" data-open="dlgSusut" data-title="Catat penyusutan" data-json='<?= e(json_encode(['id' => $r['id_aset']])) ?>'>Susutkan</button>
        <button class="btn-icon" title="Ubah" data-open="dlgAset" data-title="Ubah aset" data-json='<?= e(json_encode(['id' => $r['id_aset'], 'nama_aset' => $r['nama_aset'], 'kategori_aset' => $r['kategori_aset'], 'tanggal_perolehan' => $r['tanggal_perolehan'], 'nilai_perolehan' => $r['nilai_perolehan'], 'akumulasi_penyusutan' => $r['akumulasi_penyusutan']])) ?>'><?= icon('edit') ?></button>
        <form method="post" data-confirm="Hapus aset dari daftar?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="hapus_aset"><input type="hidden" name="id" value="<?= (int)$r['id_aset'] ?>"><button class="btn-icon del" title="Hapus"><?= icon('trash') ?></button></form></div><?php endif; ?></td></tr>
    <?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="empty">Belum ada aset.</td></tr>'; ?></tbody></table></div></div>

    <dialog class="modal" id="dlgAset"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="simpan_aset"><input type="hidden" name="id" value="0">
      <div class="modal-h"><h2 data-title>Aset</h2></div><div class="modal-b"><div class="form-grid">
        <div class="field"><label for="as_nama">Nama aset</label><input type="text" id="as_nama" name="nama_aset" required></div>
        <div class="field"><label for="as_kat">Kategori aset</label><input type="text" id="as_kat" name="kategori_aset" placeholder="Kendaraan, Peralatan, Bangunan" required></div>
        <div class="field"><label for="as_tgl">Tanggal perolehan</label><input type="date" id="as_tgl" name="tanggal_perolehan" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label for="as_nilai">Nilai perolehan (Rp)</label><input type="number" id="as_nilai" name="nilai_perolehan" min="0" step="any" required data-rp></div>
        <div class="field full"><label for="as_akum">Akumulasi penyusutan awal (Rp)</label><input type="number" id="as_akum" name="akumulasi_penyusutan" min="0" step="any" value="0" data-rp></div>
        <div class="field full" data-new-only><label class="check"><input type="checkbox" name="jurnal" value="1" checked><span>Catat pembelian ke jurnal (Debit Aset Tetap, Kredit Kas/Bank). Hilangkan centang bila aset lama yang sudah tercatat.</span></label></div>
        <div class="field full" data-new-only><label for="as_bayar">Dibayar dari</label><select id="as_bayar" name="akun_bayar"><?= $optKas ?></select></div>
      </div></div><div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan aset</button></div></form></dialog>
    <dialog class="modal" id="dlgSusut"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="susut"><input type="hidden" name="id" value="0">
      <div class="modal-h"><h2 data-title>Catat penyusutan</h2></div><div class="modal-b"><div class="form-grid">
        <div class="field"><label for="su_tgl">Tanggal</label><input type="date" id="su_tgl" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label for="su_jml">Jumlah penyusutan (Rp)</label><input type="number" id="su_jml" name="jumlah" min="0" step="any" required data-rp></div>
        <p class="hint full">Jurnal otomatis: Debit Beban Penyusutan, Kredit Akumulasi Penyusutan. Nilai buku aset berkurang.</p>
      </div></div><div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Catat penyusutan</button></div></form></dialog>
    <?php
} else {
    $rows = $db->query("SELECT * FROM kewajiban ORDER BY status='Lunas',jatuh_tempo IS NULL,jatuh_tempo,id_kewajiban DESC")->fetchAll();
    $bl = 0; $jp = 0; $jl = 0; $segera = 0; $today = date('Y-m-d'); $lim = date('Y-m-d', strtotime('+30 days'));
    foreach ($rows as $r) if ($r['status'] === 'Belum Lunas') { $bl += $r['jumlah']; if ($r['jenis'] === 'Jangka Pendek') $jp += $r['jumlah']; else $jl += $r['jumlah']; if ($r['jatuh_tempo'] && $r['jatuh_tempo'] <= $lim) $segera++; }
    ?>
    <div class="stats">
      <div class="stat t-coral"><span>Belum lunas</span><strong><?= e(rp($bl)) ?></strong><small>Seluruh kewajiban berjalan</small></div>
      <div class="stat t-amber"><span>Jangka pendek</span><strong><?= e(rp($jp)) ?></strong><small>Jatuh tempo kurang dari 1 tahun</small></div>
      <div class="stat t-blue"><span>Jangka panjang</span><strong><?= e(rp($jl)) ?></strong><small>Lebih dari 1 tahun</small></div>
      <div class="stat t-pine"><span>Perlu perhatian</span><strong><?= $segera ?></strong><small>Jatuh tempo ≤ 30 hari atau terlambat</small></div>
    </div>
    <div class="card flush"><div class="card-h"><h2>Daftar kewajiban</h2><?= is_owner() ? '' : '<button class="btn btn-amber btn-sm" data-open="dlgKew" data-title="Tambah kewajiban">' . icon('plus') . ' Tambah kewajiban</button>' ?></div>
    <div class="tbl-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>Kewajiban</th><th>Jenis</th><th>Dicatat</th><th>Jatuh tempo</th><th class="num">Jumlah</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r):
        $late = $r['status'] === 'Belum Lunas' && $r['jatuh_tempo'] && $r['jatuh_tempo'] < $today;
        $soon = $r['status'] === 'Belum Lunas' && $r['jatuh_tempo'] && !$late && $r['jatuh_tempo'] <= $lim; ?>
      <tr><td><b><?= e($r['nama_kewajiban']) ?></b></td><td><?= e($r['jenis']) ?></td><td><?= e(tgl($r['tanggal'])) ?></td>
      <td><?= e(tgl($r['jatuh_tempo'])) ?><?= $late ? ' ' . pill('Terlambat', 'coral') : ($soon ? ' ' . pill('Segera', 'amber') : '') ?></td>
      <td class="num"><b><?= e(rp($r['jumlah'])) ?></b></td><td><?= pill($r['status'], $r['status'] === 'Lunas' ? 'green' : 'gray') ?></td>
      <td><?php if (!is_owner()): ?><div class="cell-actions"><?php if ($r['status'] === 'Belum Lunas'): ?><button class="btn btn-ghost btn-sm" data-open="dlgLunas" data-title="Lunasi: <?= e($r['nama_kewajiban']) ?>" data-json='<?= e(json_encode(['id' => $r['id_kewajiban']])) ?>'>Lunasi</button><?php endif; ?>
        <button class="btn-icon" title="Ubah" data-open="dlgKew" data-title="Ubah kewajiban" data-json='<?= e(json_encode(['id' => $r['id_kewajiban'], 'nama_kewajiban' => $r['nama_kewajiban'], 'jenis' => $r['jenis'], 'tanggal' => $r['tanggal'], 'jatuh_tempo' => $r['jatuh_tempo'], 'jumlah' => $r['jumlah'], 'status' => $r['status']])) ?>'><?= icon('edit') ?></button>
        <form method="post" data-confirm="Hapus kewajiban dari daftar?" style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="hapus_kew"><input type="hidden" name="id" value="<?= (int)$r['id_kewajiban'] ?>"><button class="btn-icon del" title="Hapus"><?= icon('trash') ?></button></form></div><?php endif; ?></td></tr>
    <?php endforeach; if (!$rows) echo '<tr><td colspan="7" class="empty">Belum ada kewajiban.</td></tr>'; ?></tbody></table></div></div>

    <dialog class="modal" id="dlgKew"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="simpan_kew"><input type="hidden" name="id" value="0">
      <div class="modal-h"><h2 data-title>Kewajiban</h2></div><div class="modal-b"><div class="form-grid">
        <div class="field full"><label for="kw_nama">Nama kewajiban</label><input type="text" id="kw_nama" name="nama_kewajiban" required></div>
        <div class="field"><label for="kw_jenis">Jenis</label><select id="kw_jenis" name="jenis"><option>Jangka Pendek</option><option>Jangka Panjang</option></select></div>
        <div class="field"><label for="kw_jml">Jumlah (Rp)</label><input type="number" id="kw_jml" name="jumlah" min="0" step="any" required data-rp></div>
        <div class="field"><label for="kw_tgl">Tanggal dicatat</label><input type="date" id="kw_tgl" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label for="kw_jt">Jatuh tempo</label><input type="date" id="kw_jt" name="jatuh_tempo"></div>
        <div class="field full"><label for="kw_st">Status</label><select id="kw_st" name="status"><option>Belum Lunas</option><option>Lunas</option></select></div>
        <div class="field full" data-new-only><label class="check"><input type="checkbox" name="jurnal" value="1" checked><span>Catat penerimaan dana ke jurnal (Debit Kas/Bank, Kredit Utang). Hilangkan centang bila utang lama yang sudah tercatat.</span></label></div>
        <div class="field full" data-new-only><label for="kw_bayar">Dana diterima di</label><select id="kw_bayar" name="akun_bayar"><?= $optKas ?></select></div>
      </div></div><div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Simpan kewajiban</button></div></form></dialog>
    <dialog class="modal" id="dlgLunas"><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="lunas"><input type="hidden" name="id" value="0">
      <div class="modal-h"><h2 data-title>Lunasi kewajiban</h2></div><div class="modal-b"><div class="form-grid">
        <div class="field"><label for="ln_tgl">Tanggal pembayaran</label><input type="date" id="ln_tgl" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field"><label for="ln_bayar">Dibayar dari</label><select id="ln_bayar" name="akun_bayar"><?= $optKas ?></select></div>
        <div class="field full"><label class="check"><input type="checkbox" name="jurnal" value="1" checked><span>Catat pembayaran ke jurnal (Debit Utang, Kredit Kas/Bank). Hilangkan centang bila pembayarannya sudah tercatat sebagai transaksi.</span></label></div>
      </div></div><div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary">Tandai lunas</button></div></form></dialog>
    <?php
}
layout_end();
