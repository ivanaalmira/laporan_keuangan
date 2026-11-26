<?php
require 'config.php'; require_login();
$db = db();
list($p, $dari, $sampai) = get_range('semua');
$idAkun = (int)(isset($_GET['akun']) ? $_GET['akun'] : 0);
$akuns = $db->query('SELECT * FROM akun ORDER BY kode_akun')->fetchAll();
$sel = null; foreach ($akuns as $a) if ((int)$a['id_akun'] === $idAkun) $sel = $a;
layout_start('Buku Besar', 'bukubesar');
$form = '<form class="range-form" method="get" data-range><select name="akun" data-autosubmit aria-label="Akun"><option value="">Ringkasan semua akun</option>';
foreach ($akuns as $a) $form .= '<option value="' . $a['id_akun'] . '"' . ($idAkun === (int)$a['id_akun'] ? ' selected' : '') . '>' . e($a['kode_akun'] . ' · ' . $a['nama_akun']) . '</option>';
$form .= '</select><select name="p" aria-label="Periode">';
foreach (['semua' => 'Semua periode', 'hari_ini' => 'Hari ini', 'bulan_ini' => 'Bulan ini', 'tahun_ini' => 'Tahun ini', 'custom' => 'Rentang tanggal'] as $k => $l) $form .= '<option value="' . $k . '"' . ($k === $p ? ' selected' : '') . '>' . $l . '</option>';
$form .= '</select><span class="range-custom"' . ($p === 'custom' ? '' : ' hidden') . '><input type="date" name="dari" value="' . e($dari) . '"><span>s/d</span><input type="date" name="sampai" value="' . e($sampai) . '"><button class="btn btn-primary btn-sm">Terapkan</button></span></form>';
page_head('Buku besar', 'Periode: <b>' . e(label_range($p, $dari, $sampai)) . '</b>', $form);

if ($sel) {
    $debitNormal = $sel['saldo_normal'] === 'Debit';
    $st = $db->prepare('SELECT COALESCE(SUM(d.debit),0),COALESCE(SUM(d.kredit),0) FROM detail_transaksi d JOIN transaksi t ON t.id_transaksi=d.id_transaksi WHERE d.id_akun=? AND t.tanggal<?');
    $st->execute([$idAkun, $dari]); $aw = $st->fetch(PDO::FETCH_NUM);
    $saldo = $debitNormal ? $aw[0] - $aw[1] : $aw[1] - $aw[0];
    $st = $db->prepare('SELECT t.id_transaksi,t.tanggal,t.nomor_transaksi,t.keterangan,d.debit,d.kredit FROM detail_transaksi d JOIN transaksi t ON t.id_transaksi=d.id_transaksi WHERE d.id_akun=? AND t.tanggal BETWEEN ? AND ? ORDER BY t.tanggal,t.id_transaksi,d.id_detail');
    $st->execute([$idAkun, $dari, $sampai]); $rows = $st->fetchAll();
    $td = 0; $tk = 0; ?>
    <div class="card flush"><div class="card-h"><h2><?= e($sel['kode_akun'] . ' · ' . $sel['nama_akun']) ?> <?= pill($sel['kelompok'], 'blue') ?></h2><span class="muted">Saldo normal <?= e($sel['saldo_normal']) ?></span></div>
    <div class="tbl-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>Tanggal</th><th>Ref</th><th>Keterangan</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo</th></tr></thead><tbody>
      <tr class="sub-row"><td colspan="5"><b>Saldo awal</b></td><td class="num"><b><?= e(rp($saldo)) ?></b></td></tr>
      <?php foreach ($rows as $r): $td += $r['debit']; $tk += $r['kredit']; $saldo += $debitNormal ? $r['debit'] - $r['kredit'] : $r['kredit'] - $r['debit']; ?>
        <tr><td><?= e(tgl($r['tanggal'])) ?></td><td><a href="transaksi.php?aksi=detail&id=<?= (int)$r['id_transaksi'] ?>"><?= e($r['nomor_transaksi']) ?></a></td><td><?= e($r['keterangan']) ?></td><td class="num"><?= $r['debit'] > 0 ? e(rp($r['debit'])) : '' ?></td><td class="num"><?= $r['kredit'] > 0 ? e(rp($r['kredit'])) : '' ?></td><td class="num"><?= e(rp($saldo)) ?></td></tr>
      <?php endforeach; if (!$rows) echo '<tr><td colspan="6" class="empty">Tidak ada mutasi pada periode ini.</td></tr>'; ?>
    </tbody><tfoot><tr><td colspan="3">Saldo akhir</td><td class="num"><?= e(rp($td)) ?></td><td class="num"><?= e(rp($tk)) ?></td><td class="num"><?= e(rp($saldo)) ?></td></tr></tfoot></table></div></div>
<?php } else {
    $rows = saldo_rows($p === 'semua' ? null : $dari, $p === 'semua' ? null : $sampai); $td = 0; $tk = 0; ?>
    <div class="card flush"><div class="card-h"><h2>Neraca saldo (ringkasan semua akun)</h2></div>
    <div class="tbl-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>Kode</th><th>Akun</th><th>Kelompok</th><th class="num">Debit</th><th class="num">Kredit</th><th class="num">Saldo</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $td += $r['debit']; $tk += $r['kredit']; $sa = $r['saldo_normal'] === 'Debit' ? $r['debit'] - $r['kredit'] : $r['kredit'] - $r['debit']; ?>
      <tr><td><b><?= e($r['kode_akun']) ?></b></td><td><a href="?akun=<?= (int)$r['id_akun'] ?>&p=<?= e($p) ?>&dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>"><?= e($r['nama_akun']) ?></a></td><td class="muted"><?= e($r['kelompok']) ?></td><td class="num"><?= e(rp($r['debit'])) ?></td><td class="num"><?= e(rp($r['kredit'])) ?></td><td class="num"><b><?= e(rp($sa)) ?></b></td></tr>
    <?php endforeach; ?></tbody><tfoot><tr><td colspan="3">Total <?= abs($td - $tk) < 0.005 ? '<span class="badge-ok">Seimbang</span>' : '<span class="badge-bad">Tidak seimbang</span>' ?></td><td class="num"><?= e(rp($td)) ?></td><td class="num"><?= e(rp($tk)) ?></td><td></td></tr></tfoot></table></div></div>
<?php }
layout_end();
