<?php
require 'config.php'; require_login();
list($p, $dari, $sampai) = get_range('bulan_ini');
$lr = laba_rugi($dari, $sampai);
$ner = neraca($sampai);
$kas = saldo_kas($ner['rows']);
$seri = seri_bulanan(6, $sampai);
$terbaru = db()->query('SELECT t.*,k.nama_kategori FROM transaksi t LEFT JOIN kategori k ON k.id_kategori=t.id_kategori ORDER BY t.tanggal DESC,t.id_transaksi DESC LIMIT 7')->fetchAll();
$jt = db()->query("SELECT * FROM kewajiban WHERE status='Belum Lunas' AND jatuh_tempo IS NOT NULL AND jatuh_tempo<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY jatuh_tempo LIMIT 5")->fetchAll();
$aset_terdaftar = (float)db()->query('SELECT COALESCE(SUM(nilai_buku),0) FROM aset')->fetchColumn();
$utang_terdaftar = (float)db()->query("SELECT COALESCE(SUM(jumlah),0) FROM kewajiban WHERE status='Belum Lunas'")->fetchColumn();
$jml_trx = (int)db()->query('SELECT COUNT(*) FROM transaksi')->fetchColumn();

layout_start('Dashboard', 'dashboard');
page_head('Ringkasan keuangan', 'Periode: <b>' . e(label_range($p, $dari, $sampai)) . '</b>', range_form($p, $dari, $sampai));
if ($jml_trx === 0) echo '<div class="alert alert-info">Belum ada transaksi. ' . (is_owner() ? 'Minta admin keuangan mencatat transaksi pertama.' : '<a href="transaksi.php?aksi=baru">Catat transaksi pertama</a> untuk mengisi dashboard.') . '</div>';
if (abs($ner['selisih']) > 0.5) echo '<div class="alert alert-warn">Neraca belum seimbang (selisih ' . e(rp($ner['selisih'])) . '). Periksa jurnal yang dicatat manual.</div>';
?>
<div class="grid grid-hero">
  <div class="hero">
    <span class="lbl"><?= $lr['laba'] >= 0 ? 'Laba bersih periode ini' : 'Rugi bersih periode ini' ?></span>
    <span class="big <?= $lr['laba'] < 0 ? 'loss' : '' ?>"><?= e(rp($lr['laba'])) ?></span>
    <div class="duo"><div>Pendapatan<b><?= e(rp($lr['tot_pendapatan'])) ?></b></div><div>Beban<b><?= e(rp($lr['tot_beban'])) ?></b></div></div>
    <div style="height:130px;margin-top:10px"><canvas id="chLaba" aria-label="Grafik laba 6 bulan terakhir" role="img"></canvas></div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Pemasukan dan pengeluaran per bulan</h2></div>
    <div class="chart-box"><canvas id="chBulan" aria-label="Grafik pemasukan dan pengeluaran bulanan" role="img"></canvas></div>
  </div>
</div>
<div class="stats">
  <div class="stat t-green"><span>Total pemasukan</span><strong><?= e(rp($lr['tot_pendapatan'])) ?></strong><small>Akumulasi pendapatan</small></div>
  <div class="stat t-coral"><span>Total pengeluaran</span><strong><?= e(rp($lr['tot_beban'])) ?></strong><small>Akumulasi beban</small></div>
  <div class="stat t-amber"><span>Saldo kas dan bank</span><strong><?= e(rp($kas)) ?></strong><small>Per <?= e(tgl($p === 'semua' ? date('Y-m-d') : $sampai)) ?></small></div>
  <div class="stat t-blue"><span>Total aset</span><strong><?= e(rp($ner['tot_aset'])) ?></strong><small>Dari jurnal · aset terdaftar <?= e(rp($aset_terdaftar)) ?></small></div>
  <div class="stat t-pine"><span>Total kewajiban</span><strong><?= e(rp($ner['tot_kewajiban'])) ?></strong><small>Dari jurnal · belum lunas terdaftar <?= e(rp($utang_terdaftar)) ?></small></div>
</div>
<div class="grid grid-main">
  <div class="card flush">
    <div class="card-h"><h2>Transaksi terbaru</h2><a class="btn btn-ghost btn-sm" href="transaksi.php">Lihat semua</a></div>
    <div class="tbl-wrap"><table class="tbl" style="margin-top:12px"><thead><tr><th>Tanggal</th><th>Keterangan</th><th>Jenis</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($terbaru as $t): ?>
      <tr><td><?= e(tgl($t['tanggal'])) ?></td><td><a href="transaksi.php?aksi=detail&id=<?= (int)$t['id_transaksi'] ?>"><?= e($t['keterangan'] ?: $t['nomor_transaksi']) ?></a><br><small class="muted"><?= e($t['nomor_transaksi']) ?><?= $t['nama_kategori'] ? ' · ' . e($t['nama_kategori']) : '' ?></small></td>
      <td><?= pill($t['jenis'], $t['jenis'] === 'Pemasukan' ? 'green' : 'coral') ?></td><td class="num"><?= e(rp($t['total'])) ?></td></tr>
    <?php endforeach; if (!$terbaru) echo '<tr><td colspan="4" class="empty">Belum ada transaksi.</td></tr>'; ?>
    </tbody></table></div>
  </div>
  <div class="card">
    <div class="card-h"><h2>Kewajiban jatuh tempo</h2><a class="btn btn-ghost btn-sm" href="aset_kewajiban.php?tab=kewajiban">Kelola</a></div>
    <?php foreach ($jt as $k): $lewat = $k['jatuh_tempo'] < date('Y-m-d'); ?>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #EDF2EF">
        <div><b><?= e($k['nama_kewajiban']) ?></b><br><small class="muted"><?= $lewat ? 'Lewat jatuh tempo ' : 'Jatuh tempo ' ?><?= e(tgl($k['jatuh_tempo'])) ?></small></div>
        <div style="text-align:right"><b><?= e(rp($k['jumlah'])) ?></b><br><?= pill($lewat ? 'Terlambat' : 'Segera', $lewat ? 'coral' : 'amber') ?></div>
      </div>
    <?php endforeach; if (!$jt) echo '<div class="empty">Tidak ada utang yang jatuh tempo dalam 30 hari.</div>'; ?>
  </div>
</div>
<?php
$js = 'var S=' . json_encode($seri) . ';
Chart.defaults.font.family="Plus Jakarta Sans,system-ui,sans-serif";
var fmt=function(v){return "Rp"+Math.round(v).toLocaleString("id-ID")};
var short=function(v){var a=Math.abs(v);return a>=1e9?(v/1e9)+" M":a>=1e6?(v/1e6)+" jt":a>=1e3?(v/1e3)+" rb":v};
new Chart(document.getElementById("chBulan"),{type:"bar",data:{labels:S.l,datasets:[{label:"Pemasukan",data:S.p,backgroundColor:"#1E8A5A",borderRadius:6},{label:"Pengeluaran",data:S.b,backgroundColor:"#D4503F",borderRadius:6}]},options:{maintainAspectRatio:false,plugins:{tooltip:{callbacks:{label:function(c){return c.dataset.label+": "+fmt(c.raw)}}},legend:{position:"bottom"}},scales:{y:{ticks:{callback:short},grid:{color:"#EDF2EF"}},x:{grid:{display:false}}}}});
new Chart(document.getElementById("chLaba"),{type:"line",data:{labels:S.l,datasets:[{label:"Laba",data:S.laba,borderColor:"#F2C14E",backgroundColor:"rgba(242,193,78,.18)",fill:true,tension:.35,pointRadius:3,pointBackgroundColor:"#F2C14E"}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return "Laba: "+fmt(c.raw)}}}},scales:{y:{display:false},x:{ticks:{color:"#A9CCC3"},grid:{display:false}}}}});';
layout_end($js, true);
