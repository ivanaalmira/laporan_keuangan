<?php
require 'config.php'; require_login();
$db = db();
$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$repost = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    need_admin();
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    if ($do === 'hapus') {
        $id = (int)$_POST['id'];
        $st = $db->prepare('SELECT * FROM transaksi WHERE id_transaksi=?'); $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) { flash('danger', 'Transaksi tidak ditemukan.'); redirect('transaksi.php'); }
        if ($x = periode_tertutup($t['tanggal'])) { flash('danger', 'Periode "' . $x . '" sudah ditutup. Transaksi tidak dapat dihapus.'); redirect('transaksi.php'); }
        $db->prepare('DELETE FROM transaksi WHERE id_transaksi=?')->execute([$id]);
        log_aktivitas('Hapus transaksi', $t['nomor_transaksi'] . ' · ' . rp($t['total']) . ' · ' . $t['keterangan']);
        flash('success', 'Transaksi ' . $t['nomor_transaksi'] . ' dibatalkan dan jurnalnya ikut dihapus.');
        redirect('transaksi.php');
    }
    if ($do === 'simpan') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $tgl = valid_date(isset($_POST['tanggal']) ? $_POST['tanggal'] : '');
        $no = trim(isset($_POST['nomor']) ? $_POST['nomor'] : '');
        $ket = trim(isset($_POST['keterangan']) ? $_POST['keterangan'] : '');
        $jenis = (isset($_POST['jenis']) && $_POST['jenis'] === 'Pengeluaran') ? 'Pengeluaran' : 'Pemasukan';
        $kat = (int)(isset($_POST['id_kategori']) ? $_POST['id_kategori'] : 0) ?: null;
        $mode = (isset($_POST['mode']) && $_POST['mode'] === 'manual') ? 'manual' : 'cepat';
        try {
            if (!$tgl) throw new Exception('Tanggal tidak valid.');
            if ($no === '') throw new Exception('Nomor transaksi wajib diisi.');
            $lines = [];
            if ($mode === 'manual') {
                $ak = isset($_POST['l_akun']) ? $_POST['l_akun'] : []; $de = isset($_POST['l_debit']) ? $_POST['l_debit'] : []; $kr = isset($_POST['l_kredit']) ? $_POST['l_kredit'] : [];
                foreach ($ak as $i => $a) {
                    $d = (float)(isset($de[$i]) ? $de[$i] : 0); $k = (float)(isset($kr[$i]) ? $kr[$i] : 0);
                    if (!$a || ($d == 0 && $k == 0)) continue;
                    if ($d < 0 || $k < 0) throw new Exception('Nilai debit dan kredit tidak boleh negatif.');
                    if ($d > 0 && $k > 0) throw new Exception('Satu baris jurnal hanya boleh berisi debit atau kredit.');
                    $lines[] = [(int)$a, $d, $k];
                }
                if (count($lines) < 2) throw new Exception('Jurnal minimal terdiri dari dua baris akun.');
            } else {
                $nom = (float)(isset($_POST['nominal']) ? $_POST['nominal'] : 0);
                $kasA = (int)(isset($_POST['akun_kas']) ? $_POST['akun_kas'] : 0); $lawan = (int)(isset($_POST['akun_lawan']) ? $_POST['akun_lawan'] : 0);
                if ($nom <= 0) throw new Exception('Nominal harus lebih dari nol.');
                if (!$kasA || !$lawan) throw new Exception('Pilih akun kas/bank dan akun lawannya.');
                if ($kasA === $lawan) throw new Exception('Akun kas/bank dan akun lawan tidak boleh sama.');
                $lines = $jenis === 'Pemasukan' ? [[$kasA, $nom, 0], [$lawan, 0, $nom]] : [[$lawan, $nom, 0], [$kasA, 0, $nom]];
            }
            if ($id) {
                $st = $db->prepare('SELECT tanggal FROM transaksi WHERE id_transaksi=?'); $st->execute([$id]);
                $old = $st->fetchColumn();
                if (!$old) throw new Exception('Transaksi tidak ditemukan.');
                if ($x = periode_tertutup($old)) throw new Exception('Transaksi ini berada di periode "' . $x . '" yang sudah ditutup.');
            }
            $st = $db->prepare('SELECT COUNT(*) FROM transaksi WHERE nomor_transaksi=? AND id_transaksi<>?'); $st->execute([$no, $id]);
            if ($st->fetchColumn()) throw new Exception('Nomor transaksi ' . $no . ' sudah dipakai.');
            $total = 0; foreach ($lines as $l) $total += $l[1];
            $newid = simpan_jurnal($tgl, $no, $ket, $jenis, $kat, $lines, $id);
            log_aktivitas($id ? 'Ubah transaksi' : 'Tambah transaksi', $no . ' · ' . rp($total) . ' · ' . $ket);
            flash('success', 'Transaksi ' . $no . ' disimpan dan jurnalnya terbentuk.');
            redirect('transaksi.php?aksi=detail&id=' . $newid);
        } catch (Exception $ex) {
            flash('danger', $ex->getMessage());
            $repost = $_POST; $aksi = $id ? 'ubah' : 'baru'; $_GET['id'] = $id;
        }
    }
}

/* ======================= FORM TAMBAH / UBAH ======================= */
if ($aksi === 'baru' || $aksi === 'ubah') {
    need_admin();
    $akunAll = $db->query('SELECT * FROM akun ORDER BY kode_akun')->fetchAll();
    $kats = $db->query('SELECT * FROM kategori ORDER BY jenis,nama_kategori')->fetchAll();
    $byKode = []; foreach ($akunAll as $a) $byKode[$a['kode_akun']] = $a['id_akun'];
    $f = ['id' => 0, 'tanggal' => date('Y-m-d'), 'nomor' => nomor_baru(), 'jenis' => 'Pemasukan', 'id_kategori' => '', 'keterangan' => '', 'mode' => 'cepat',
          'nominal' => '', 'akun_kas' => isset($byKode['1001']) ? $byKode['1001'] : '', 'akun_lawan' => '', 'lines' => []];
    $kasIds = [];
    foreach (['1001', '1002'] as $kk) if (isset($byKode[$kk])) $kasIds[] = (int)$byKode[$kk];
    if ($aksi === 'ubah' && !$repost) {
        $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $st = $db->prepare('SELECT * FROM transaksi WHERE id_transaksi=?'); $st->execute([$id]);
        $t = $st->fetch();
        if (!$t) { flash('danger', 'Transaksi tidak ditemukan.'); redirect('transaksi.php'); }
        if ($x = periode_tertutup($t['tanggal'])) { flash('danger', 'Periode "' . $x . '" sudah ditutup; transaksi tidak dapat diubah.'); redirect('transaksi.php?aksi=detail&id=' . $id); }
        $st = $db->prepare('SELECT * FROM detail_transaksi WHERE id_transaksi=? ORDER BY id_detail'); $st->execute([$id]);
        $det = $st->fetchAll();
        $f['id'] = $id; $f['tanggal'] = $t['tanggal']; $f['nomor'] = $t['nomor_transaksi']; $f['jenis'] = $t['jenis'];
        $f['id_kategori'] = $t['id_kategori']; $f['keterangan'] = $t['keterangan'];
        $quick = false;
        if (count($det) === 2) {
            foreach ($det as $i => $d) {
                $o = $det[1 - $i];
                if (in_array((int)$d['id_akun'], $kasIds)) {
                    if ($t['jenis'] === 'Pemasukan' && (float)$d['debit'] > 0 && (float)$o['kredit'] > 0) { $quick = true; $f['akun_kas'] = $d['id_akun']; $f['akun_lawan'] = $o['id_akun']; $f['nominal'] = $d['debit']; }
                    if ($t['jenis'] === 'Pengeluaran' && (float)$d['kredit'] > 0 && (float)$o['debit'] > 0) { $quick = true; $f['akun_kas'] = $d['id_akun']; $f['akun_lawan'] = $o['id_akun']; $f['nominal'] = $d['kredit']; }
                }
            }
        }
        if (!$quick) { $f['mode'] = 'manual'; foreach ($det as $d) $f['lines'][] = [$d['id_akun'], (float)$d['debit'], (float)$d['kredit']]; }
    }
    if ($repost) {
        foreach (['id', 'tanggal', 'nomor', 'jenis', 'id_kategori', 'keterangan', 'mode', 'nominal', 'akun_kas', 'akun_lawan'] as $k) if (isset($repost[$k])) $f[$k] = $repost[$k];
        if (!empty($repost['l_akun'])) foreach ($repost['l_akun'] as $i => $a) $f['lines'][] = [$a, isset($repost['l_debit'][$i]) ? $repost['l_debit'][$i] : '', isset($repost['l_kredit'][$i]) ? $repost['l_kredit'][$i] : ''];
    }
    $optAkun = function ($sel, $kelompok = null, $excludeKas = false) use ($akunAll, $kasIds) {
        $h = '<option value="">— pilih akun —</option>'; $cur = '';
        foreach ($akunAll as $a) {
            if ($kelompok && !in_array($a['kelompok'], $kelompok)) continue;
            if ($excludeKas && in_array((int)$a['id_akun'], $kasIds)) continue;
            if ($a['kelompok'] !== $cur) { if ($cur !== '') $h .= '</optgroup>'; $cur = $a['kelompok']; $h .= '<optgroup label="' . e($cur) . '">'; }
            $h .= '<option value="' . $a['id_akun'] . '"' . ((string)$sel === (string)$a['id_akun'] ? ' selected' : '') . '>' . e($a['kode_akun'] . ' · ' . $a['nama_akun']) . '</option>';
        }
        return $h . ($cur !== '' ? '</optgroup>' : '');
    };
    $map = kategori_akun_map();
    layout_start($f['id'] ? 'Ubah transaksi' : 'Tambah transaksi', 'transaksi');
    page_head($f['id'] ? 'Ubah transaksi' : 'Tambah transaksi', 'Isi data transaksi. Jurnal debit–kredit dibentuk otomatis dan harus seimbang.', '<a class="btn btn-ghost" href="transaksi.php">Kembali</a>');
    ?>
    <form method="post" id="jf" novalidate>
      <?= csrf_field() ?><input type="hidden" name="do" value="simpan"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <div class="jf-layout">
        <div class="card">
          <div class="form-grid">
            <div class="field"><label for="tanggal">Tanggal</label><input type="date" id="tanggal" name="tanggal" value="<?= e($f['tanggal']) ?>" required></div>
            <div class="field"><label for="nomor">Nomor transaksi</label><input type="text" id="nomor" name="nomor" value="<?= e($f['nomor']) ?>" required></div>
            <div class="field"><span class="lbl-s">Jenis</span>
              <div class="seg"><label><input type="radio" name="jenis" value="Pemasukan" <?= $f['jenis'] === 'Pemasukan' ? 'checked' : '' ?>><span>Pemasukan</span></label><label><input type="radio" name="jenis" value="Pengeluaran" <?= $f['jenis'] === 'Pengeluaran' ? 'checked' : '' ?>><span>Pengeluaran</span></label></div></div>
            <div class="field"><label for="id_kategori">Kategori</label><select id="id_kategori" name="id_kategori"><option value="">— tanpa kategori —</option>
              <?php foreach ($kats as $k): $kode = isset($map[strtolower($k['nama_kategori'])]) ? $map[strtolower($k['nama_kategori'])] : ''; ?>
                <option value="<?= $k['id_kategori'] ?>" data-jenis="<?= e(strtolower($k['jenis'])) ?>" data-akun="<?= e($kode && isset($byKode[$kode]) ? $byKode[$kode] : '') ?>" <?= (string)$f['id_kategori'] === (string)$k['id_kategori'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
              <?php endforeach; ?></select></div>
            <div class="field full"><label for="keterangan">Keterangan</label><textarea id="keterangan" name="keterangan" placeholder="Contoh: Penjualan produk tunai"><?= e($f['keterangan']) ?></textarea></div>
            <div class="field full"><span class="lbl-s">Cara mengisi jurnal</span>
              <div class="seg"><label><input type="radio" name="mode" value="cepat" <?= $f['mode'] === 'cepat' ? 'checked' : '' ?>><span>Cepat (2 akun)</span></label><label><input type="radio" name="mode" value="manual" <?= $f['mode'] === 'manual' ? 'checked' : '' ?>><span>Manual (banyak akun)</span></label></div></div>
          </div>
          <div id="quick" class="form-grid" style="margin-top:14px">
            <div class="field"><label for="akun_kas" id="lbl_kas">Diterima di</label><select id="akun_kas" name="akun_kas"><?= $optAkun($f['akun_kas'], ['Aset']) ?></select></div>
            <div class="field"><label for="akun_lawan" id="lbl_lawan">Akun pendapatan</label><select id="akun_lawan" name="akun_lawan"><?= $optAkun($f['akun_lawan'], null, true) ?></select></div>
            <div class="field full"><label for="nominal">Nominal (Rp)</label><input type="number" id="nominal" name="nominal" min="0" step="any" inputmode="decimal" value="<?= e($f['nominal']) ?>" data-rp placeholder="5000000"></div>
          </div>
          <div id="manual" style="margin-top:14px" hidden>
            <div class="tbl-wrap"><table class="tbl jrows" id="lines"><thead><tr><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th><th></th></tr></thead><tbody>
              <?php foreach ($f['lines'] as $l): ?>
              <tr><td><select name="l_akun[]"><?= $optAkun($l[0]) ?></select></td><td><input type="number" name="l_debit[]" min="0" step="any" value="<?= $l[1] > 0 ? e($l[1]) : '' ?>"></td><td><input type="number" name="l_kredit[]" min="0" step="any" value="<?= $l[2] > 0 ? e($l[2]) : '' ?>"></td><td><button type="button" class="btn-icon del rm" aria-label="Hapus baris"><?= icon('trash') ?></button></td></tr>
              <?php endforeach; ?></tbody></table></div>
            <div class="jtot"><button type="button" class="btn btn-ghost btn-sm" id="add-row"><?= icon('plus') ?> Tambah baris</button>
              <span>Debit <b id="tot_d">Rp0</b> · Kredit <b id="tot_k">Rp0</b></span><span id="bal" class="badge-bad">—</span></div>
          </div>
          <div class="actions" style="margin-top:20px"><button class="btn btn-primary"><?= icon('check') ?> Simpan transaksi</button><a class="btn btn-ghost" href="transaksi.php">Batal</a></div>
        </div>
        <div class="card flush">
          <div class="card-h"><h2>Pratinjau jurnal</h2></div>
          <div class="tbl-wrap"><table class="tbl" id="pv" style="margin-top:12px"><thead><tr><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody></tbody></table></div>
          <p class="hint" style="padding:14px 22px 20px">Contoh: penjualan tunai Rp2.000.000 → Debit Kas Rp2.000.000 dan Kredit Penjualan Rp2.000.000. Total debit dan kredit harus sama.</p>
        </div>
      </div>
      <template id="tpl-row"><tr><td><select name="l_akun[]"><?= $optAkun('') ?></select></td><td><input type="number" name="l_debit[]" min="0" step="any"></td><td><input type="number" name="l_kredit[]" min="0" step="any"></td><td><button type="button" class="btn-icon del rm" aria-label="Hapus baris"><?= icon('trash') ?></button></td></tr></template>
    </form>
    <?php
    layout_end();
    exit;
}

/* ======================= DETAIL ======================= */
if ($aksi === 'detail') {
    $id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
    $st = $db->prepare('SELECT t.*,k.nama_kategori,u.nama AS nama_user FROM transaksi t LEFT JOIN kategori k ON k.id_kategori=t.id_kategori LEFT JOIN users u ON u.id_user=t.id_user WHERE t.id_transaksi=?');
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) { flash('danger', 'Transaksi tidak ditemukan.'); redirect('transaksi.php'); }
    $st = $db->prepare('SELECT d.*,a.kode_akun,a.nama_akun FROM detail_transaksi d JOIN akun a ON a.id_akun=d.id_akun WHERE d.id_transaksi=? ORDER BY d.id_detail'); $st->execute([$id]);
    $det = $st->fetchAll(); $td = 0; $tk = 0; foreach ($det as $d) { $td += $d['debit']; $tk += $d['kredit']; }
    $tutup = periode_tertutup($t['tanggal']);
    $act = '<a class="btn btn-ghost" href="transaksi.php">Kembali</a>';
    if (!is_owner() && !$tutup) $act .= '<a class="btn btn-primary" href="transaksi.php?aksi=ubah&id=' . $id . '">' . icon('edit') . ' Ubah</a>';
    layout_start('Detail transaksi', 'transaksi');
    page_head($t['nomor_transaksi'], e(tgl_panjang($t['tanggal'])) . ' · dicatat oleh ' . e($t['nama_user']), $act);
    ?>
    <div class="grid grid-2">
      <div class="card"><div class="card-h"><h2>Informasi</h2><?= pill($t['jenis'], $t['jenis'] === 'Pemasukan' ? 'green' : 'coral') ?></div>
        <p style="font-size:26px;font-weight:800;margin:0 0 10px"><?= e(rp($t['total'])) ?></p>
        <p class="muted" style="margin:0 0 4px">Kategori: <b style="color:var(--ink)"><?= e($t['nama_kategori'] ?: 'Tanpa kategori') ?></b></p>
        <p style="margin:0"><?= e($t['keterangan'] ?: 'Tanpa keterangan.') ?></p>
        <?php if ($tutup): ?><p style="margin-top:14px"><?= pill('Periode ' . $tutup . ' ditutup', 'amber') ?></p><?php endif; ?>
        <?php if (!is_owner() && !$tutup): ?>
          <form method="post" data-confirm="Batalkan transaksi ini? Jurnalnya akan ikut terhapus." style="margin-top:18px"><?= csrf_field() ?><input type="hidden" name="do" value="hapus"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-danger btn-sm"><?= icon('trash') ?> Batalkan transaksi</button></form>
        <?php endif; ?></div>
      <div class="card flush"><div class="card-h"><h2>Jurnal yang terbentuk</h2><?= abs($td - $tk) < 0.005 ? '<span class="badge-ok">Seimbang</span>' : '<span class="badge-bad">Tidak seimbang</span>' ?></div>
        <div class="tbl-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>Akun</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody>
        <?php foreach ($det as $d): ?><tr><td style="<?= $d['kredit'] > 0 ? 'padding-left:34px' : '' ?>"><?= e($d['kode_akun'] . ' · ' . $d['nama_akun']) ?></td><td class="num"><?= $d['debit'] > 0 ? e(rp($d['debit'])) : '-' ?></td><td class="num"><?= $d['kredit'] > 0 ? e(rp($d['kredit'])) : '-' ?></td></tr><?php endforeach; ?>
        </tbody><tfoot><tr><td>Total</td><td class="num"><?= e(rp($td)) ?></td><td class="num"><?= e(rp($tk)) ?></td></tr></tfoot></table></div></div>
    </div>
    <?php
    layout_end();
    exit;
}

/* ======================= DAFTAR ======================= */
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$dari = valid_date(isset($_GET['dari']) ? $_GET['dari'] : ''); $sampai = valid_date(isset($_GET['sampai']) ? $_GET['sampai'] : '');
$jn = isset($_GET['jenis']) && in_array($_GET['jenis'], ['Pemasukan', 'Pengeluaran']) ? $_GET['jenis'] : '';
$kt = (int)(isset($_GET['kategori']) ? $_GET['kategori'] : 0); $ak = (int)(isset($_GET['akun']) ? $_GET['akun'] : 0);
$w = []; $pa = [];
if ($q !== '') { $w[] = '(t.nomor_transaksi LIKE ? OR t.keterangan LIKE ?)'; $pa[] = "%$q%"; $pa[] = "%$q%"; }
if ($dari) { $w[] = 't.tanggal>=?'; $pa[] = $dari; }
if ($sampai) { $w[] = 't.tanggal<=?'; $pa[] = $sampai; }
if ($jn) { $w[] = 't.jenis=?'; $pa[] = $jn; }
if ($kt) { $w[] = 't.id_kategori=?'; $pa[] = $kt; }
if ($ak) { $w[] = 'EXISTS(SELECT 1 FROM detail_transaksi d WHERE d.id_transaksi=t.id_transaksi AND d.id_akun=?)'; $pa[] = $ak; }
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
$st = $db->prepare('SELECT COUNT(*),COALESCE(SUM(CASE WHEN t.jenis="Pemasukan" THEN t.total END),0),COALESCE(SUM(CASE WHEN t.jenis="Pengeluaran" THEN t.total END),0) FROM transaksi t' . $where);
$st->execute($pa); $sum = $st->fetch(PDO::FETCH_NUM);
list($page, $pages, $off) = paginate((int)$sum[0], 15, (int)(isset($_GET['hal']) ? $_GET['hal'] : 1));
$st = $db->prepare('SELECT t.*,k.nama_kategori FROM transaksi t LEFT JOIN kategori k ON k.id_kategori=t.id_kategori' . $where . ' ORDER BY t.tanggal DESC,t.id_transaksi DESC LIMIT 15 OFFSET ' . (int)$off);
$st->execute($pa); $rows = $st->fetchAll();
$kats = $db->query('SELECT * FROM kategori ORDER BY nama_kategori')->fetchAll();
$akuns = $db->query('SELECT * FROM akun ORDER BY kode_akun')->fetchAll();

layout_start('Transaksi', 'transaksi');
page_head('Transaksi', 'Seluruh pemasukan dan pengeluaran beserta jurnalnya.', is_owner() ? '' : '<a class="btn btn-amber" href="transaksi.php?aksi=baru">' . icon('plus') . ' Tambah transaksi</a>');
?>
<div class="stats">
  <div class="stat t-pine"><span>Jumlah transaksi</span><strong><?= (int)$sum[0] ?></strong><small>Sesuai filter</small></div>
  <div class="stat t-green"><span>Total pemasukan</span><strong><?= e(rp($sum[1])) ?></strong><small>Nilai transaksi pemasukan</small></div>
  <div class="stat t-coral"><span>Total pengeluaran</span><strong><?= e(rp($sum[2])) ?></strong><small>Nilai transaksi pengeluaran</small></div>
</div>
<div class="card flush">
  <form class="filters" method="get">
    <div class="field grow"><label for="q">Cari</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nomor atau keterangan"></div>
    <div class="field"><label for="f_dari">Dari</label><input type="date" id="f_dari" name="dari" value="<?= e($dari) ?>"></div>
    <div class="field"><label for="f_sampai">Sampai</label><input type="date" id="f_sampai" name="sampai" value="<?= e($sampai) ?>"></div>
    <div class="field"><label for="f_jenis">Jenis</label><select id="f_jenis" name="jenis"><option value="">Semua</option><option <?= $jn === 'Pemasukan' ? 'selected' : '' ?>>Pemasukan</option><option <?= $jn === 'Pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option></select></div>
    <div class="field"><label for="f_kat">Kategori</label><select id="f_kat" name="kategori"><option value="">Semua</option><?php foreach ($kats as $k) echo '<option value="' . $k['id_kategori'] . '"' . ($kt == $k['id_kategori'] ? ' selected' : '') . '>' . e($k['nama_kategori']) . '</option>'; ?></select></div>
    <div class="field"><label for="f_akun">Akun</label><select id="f_akun" name="akun"><option value="">Semua</option><?php foreach ($akuns as $a) echo '<option value="' . $a['id_akun'] . '"' . ($ak == $a['id_akun'] ? ' selected' : '') . '>' . e($a['kode_akun'] . ' ' . $a['nama_akun']) . '</option>'; ?></select></div>
    <button class="btn btn-primary"><?= icon('search') ?> Terapkan</button><a class="btn btn-ghost" href="transaksi.php">Reset</a>
  </form>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Tanggal</th><th>Nomor</th><th>Keterangan</th><th>Jenis</th><th class="num">Total</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $t): ?>
    <tr><td><?= e(tgl($t['tanggal'])) ?></td><td><b><?= e($t['nomor_transaksi']) ?></b></td>
      <td><?= e($t['keterangan'] ?: '—') ?><?= $t['nama_kategori'] ? '<br><small class="muted">' . e($t['nama_kategori']) . '</small>' : '' ?></td>
      <td><?= pill($t['jenis'], $t['jenis'] === 'Pemasukan' ? 'green' : 'coral') ?></td><td class="num"><b><?= e(rp($t['total'])) ?></b></td>
      <td><div class="cell-actions"><a class="btn-icon" href="transaksi.php?aksi=detail&id=<?= (int)$t['id_transaksi'] ?>" title="Lihat rincian dan jurnal"><?= icon('eye') ?></a>
      <?php if (!is_owner()): ?><a class="btn-icon" href="transaksi.php?aksi=ubah&id=<?= (int)$t['id_transaksi'] ?>" title="Ubah"><?= icon('edit') ?></a>
        <form method="post" data-confirm="Batalkan transaksi <?= e($t['nomor_transaksi']) ?>? Jurnalnya akan ikut terhapus." style="display:inline"><?= csrf_field() ?><input type="hidden" name="do" value="hapus"><input type="hidden" name="id" value="<?= (int)$t['id_transaksi'] ?>"><button class="btn-icon del" title="Batalkan"><?= icon('trash') ?></button></form><?php endif; ?></div></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="empty">Tidak ada transaksi yang cocok dengan filter.</td></tr>'; ?>
  </tbody></table></div>
  <?= pager($page, $pages) ?>
</div>
<?php layout_end(); ?>
