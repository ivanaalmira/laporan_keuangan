<?php
require 'config.php'; require_login();
list($p, $dari, $sampai) = get_range('bulan_ini');
$akhir = ($p === 'semua') ? date('Y-m-d') : $sampai;
$hari = (int)round((strtotime($akhir) - strtotime($dari)) / 86400) + 1;
$pSampai = date('Y-m-d', strtotime($dari . ' -1 day'));
$pDari = date('Y-m-d', strtotime($pSampai . ' -' . ($hari - 1) . ' day'));
$now = hitung_rasio($dari, $akhir);
$prev = hitung_rasio($pDari, $pSampai);
$seri = seri_bulanan(6, $akhir);

function ulas($k, $v) {
    if ($v === null) return 'Belum dapat dihitung karena pembaginya masih nol.';
    switch ($k) {
        case 'lancar': return $v < 1 ? 'Aset lancar belum cukup menutup utang lancar. Waspadai tekanan kas.' : ($v <= 2 ? 'Rentang sehat: aset lancar mampu menutup utang jangka pendek.' : 'Sangat likuid. Pastikan kas dan persediaan tidak menganggur.');
        case 'cepat': return $v < 1 ? 'Tanpa menjual persediaan, kas dan piutang belum cukup melunasi utang lancar.' : 'Kas dan piutang sudah cukup menutup utang lancar tanpa menjual persediaan.';
        case 'kas': return $v < 20 ? 'Kas yang tersedia masih kecil dibanding utang lancar.' : ($v < 100 ? 'Kas menutup sebagian utang lancar.' : 'Kas saja sudah cukup melunasi seluruh utang lancar.');
        case 'dar': return $v < 40 ? 'Aset dibiayai terutama oleh modal sendiri. Risiko utang rendah.' : ($v <= 60 ? 'Porsi utang seimbang dengan modal sendiri.' : 'Aset banyak dibiayai utang. Risiko solvabilitas lebih tinggi.');
        case 'npm': return $v < 0 ? 'Usaha merugi pada periode ini.' : ($v < 10 ? 'Margin tipis. Tinjau harga jual dan efisiensi beban.' : 'Margin laba sehat.');
        case 'roa': return $v < 0 ? 'Aset belum menghasilkan laba.' : 'Laba yang dihasilkan tiap rupiah aset pada periode ini.';
        case 'roe': return $v < 0 ? 'Modal pemilik tergerus oleh kerugian.' : 'Laba yang dihasilkan tiap rupiah modal pemilik pada periode ini.';
    }
    return '';
}
$def = [
    ['lancar', 'Rasio lancar', 'Likuiditas', 'x', 'Aset lancar ÷ utang lancar'],
    ['cepat', 'Rasio cepat', 'Likuiditas', 'x', '(Kas + bank + piutang) ÷ utang lancar'],
    ['kas', 'Rasio kas', 'Likuiditas', '%', '(Kas + bank) ÷ utang lancar'],
    ['dar', 'Rasio utang terhadap aset', 'Solvabilitas', '%', 'Total kewajiban ÷ total aset'],
    ['npm', 'Margin laba bersih', 'Rentabilitas', '%', 'Laba bersih ÷ penjualan'],
    ['roa', 'Return on assets', 'Rentabilitas', '%', 'Laba bersih ÷ total aset'],
    ['roe', 'Return on equity', 'Rentabilitas', '%', 'Laba bersih ÷ total ekuitas'],
];
$fmt = function ($v, $u) { return $v === null ? '—' : ($u === 'x' ? number_format($v, 2, ',', '.') . ' kali' : number_format($v, 1, ',', '.') . '%'); };

layout_start('Analisis Keuangan', 'analisis');
page_head('Analisis keuangan', 'Periode: <b>' . e(label_range($p, $dari, $akhir)) . '</b> · dibandingkan dengan ' . e(label_range('custom', $pDari, $pSampai)), range_form($p, $dari, $sampai));
?>
<div class="stats">
  <div class="stat t-green"><span>Penjualan</span><strong><?= e(rp($now['raw']['penjualan'])) ?></strong><small>Sebelumnya <?= e(rp($prev['raw']['penjualan'])) ?></small></div>
  <div class="stat <?= $now['raw']['laba'] >= 0 ? 't-pine' : 't-coral' ?>"><span>Laba bersih</span><strong><?= e(rp($now['raw']['laba'])) ?></strong><small>Sebelumnya <?= e(rp($prev['raw']['laba'])) ?></small></div>
  <div class="stat t-amber"><span>Kas dan bank</span><strong><?= e(rp($now['raw']['kas'])) ?></strong><small>Sebelumnya <?= e(rp($prev['raw']['kas'])) ?></small></div>
</div>
<div class="ratio-grid">
<?php foreach ($def as $d): $k = $d[0]; $v = $now[$k]; $pv = $prev[$k];
    $delta = ($v !== null && $pv !== null) ? $v - $pv : null;
    $lowerBetter = ($k === 'dar');
    $good = $delta === null ? null : ($lowerBetter ? $delta <= 0 : $delta >= 0);
    $w = $v === null ? 0 : max(0, min(100, $d[3] === 'x' ? $v * 50 : abs($v)));
?>
  <div class="ratio"><h3><?= e($d[1]) ?> · <?= e($d[2]) ?></h3>
    <div class="v"><?= e($fmt($v, $d[3])) ?></div>
    <div class="d <?= $good === null ? 'muted' : ($good ? 'pos' : 'neg') ?>"><?= $delta === null ? 'Belum ada pembanding' : ($delta >= 0 ? '▲ ' : '▼ ') . e($d[3] === 'x' ? number_format(abs($delta), 2, ',', '.') . ' kali' : number_format(abs($delta), 1, ',', '.') . ' poin') . ' dari periode lalu' ?></div>
    <div class="bar"><i style="width:<?= $w ?>%"></i></div>
    <p><b>Rumus:</b> <?= e($d[4]) ?>.<br><?= e(ulas($k, $v)) ?></p></div>
<?php endforeach; ?>
</div>
<div class="grid grid-2">
  <div class="card"><div class="card-h"><h2>Rasio persen: periode ini vs sebelumnya</h2></div><div class="chart-box"><canvas id="chR" role="img" aria-label="Perbandingan rasio"></canvas></div></div>
  <div class="card"><div class="card-h"><h2>Laba bersih 6 bulan terakhir</h2></div><div class="chart-box"><canvas id="chL" role="img" aria-label="Laba bulanan"></canvas></div></div>
</div>
<p class="hint">Interpretasi bersifat umum. Batas sehat berbeda antar jenis usaha, jadi bandingkan juga dengan rata-rata industri Anda.</p>
<?php
$lab = ['Rasio kas', 'Utang/aset', 'Margin laba', 'ROA', 'ROE'];
$cur = [$now['kas'], $now['dar'], $now['npm'], $now['roa'], $now['roe']]; $pre = [$prev['kas'], $prev['dar'], $prev['npm'], $prev['roa'], $prev['roe']];
$js = 'Chart.defaults.font.family="Plus Jakarta Sans,system-ui,sans-serif";
var S=' . json_encode($seri) . ';
new Chart(document.getElementById("chR"),{type:"bar",data:{labels:' . json_encode($lab) . ',datasets:[{label:"Periode ini",data:' . json_encode($cur) . ',backgroundColor:"#165250",borderRadius:6},{label:"Periode lalu",data:' . json_encode($pre) . ',backgroundColor:"#E9A632",borderRadius:6}]},options:{maintainAspectRatio:false,plugins:{legend:{position:"bottom"},tooltip:{callbacks:{label:function(c){return c.dataset.label+": "+(c.raw===null?"—":c.raw.toFixed(1)+"%")}}}},scales:{y:{ticks:{callback:function(v){return v+"%"}},grid:{color:"#EDF2EF"}},x:{grid:{display:false}}}}});
new Chart(document.getElementById("chL"),{type:"line",data:{labels:S.l,datasets:[{label:"Laba bersih",data:S.laba,borderColor:"#1E8A5A",backgroundColor:"rgba(30,138,90,.12)",fill:true,tension:.35,pointRadius:4}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return "Rp"+Math.round(c.raw).toLocaleString("id-ID")}}}},scales:{y:{grid:{color:"#EDF2EF"},ticks:{callback:function(v){var a=Math.abs(v);return a>=1e6?(v/1e6)+" jt":a>=1e3?(v/1e3)+" rb":v}}},x:{grid:{display:false}}}}});';
layout_end($js, true);
