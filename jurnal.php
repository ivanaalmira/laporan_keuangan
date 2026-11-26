<?php
require 'config.php'; require_login();
list($p, $dari, $sampai) = get_range('bulan_ini');
$st = db()->prepare('SELECT t.id_transaksi,t.tanggal,t.nomor_transaksi,t.keterangan,d.debit,d.kredit,a.kode_akun,a.nama_akun
    FROM transaksi t JOIN detail_transaksi d ON d.id_transaksi=t.id_transaksi JOIN akun a ON a.id_akun=d.id_akun
    WHERE t.tanggal BETWEEN ? AND ? ORDER BY t.tanggal,t.id_transaksi,d.id_detail');
$st->execute([$dari, $sampai]);
$rows = $st->fetchAll(); $td = 0; $tk = 0; foreach ($rows as $r) { $td += $r['debit']; $tk += $r['kredit']; }
layout_start('Jurnal Umum', 'jurnal');
page_head('Jurnal umum', 'Periode: <b>' . e(label_range($p, $dari, $sampai)) . '</b>', range_form($p, $dari, $sampai) . '<button class="btn btn-ghost no-print" onclick="window.print()">' . icon('print') . ' Cetak</button>');
?>
<div class="card flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Tanggal</th><th>Keterangan / akun</th><th>Ref</th><th class="num">Debit</th><th class="num">Kredit</th></tr></thead><tbody>
<?php $last = 0; foreach ($rows as $r):
    if ($last !== (int)$r['id_transaksi']) { $last = (int)$r['id_transaksi']; ?>
      <tr class="grp"><td><?= e(tgl($r['tanggal'])) ?></td><td colspan="2"><?= e($r['keterangan'] ?: 'Transaksi') ?></td><td colspan="2" class="num"><a href="transaksi.php?aksi=detail&id=<?= $last ?>"><?= e($r['nomor_transaksi']) ?></a></td></tr>
    <?php } ?>
    <tr class="sub-row"><td></td><td style="<?= $r['kredit'] > 0 ? 'padding-left:38px' : '' ?>"><?= e($r['nama_akun']) ?></td><td class="muted"><?= e($r['kode_akun']) ?></td><td class="num"><?= $r['debit'] > 0 ? e(rp($r['debit'])) : '' ?></td><td class="num"><?= $r['kredit'] > 0 ? e(rp($r['kredit'])) : '' ?></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="5" class="empty">Belum ada jurnal pada periode ini.</td></tr>'; ?>
</tbody><tfoot><tr><td colspan="3">Total <?= abs($td - $tk) < 0.005 ? '<span class="badge-ok">Seimbang</span>' : '<span class="badge-bad">Tidak seimbang</span>' ?></td><td class="num"><?= e(rp($td)) ?></td><td class="num"><?= e(rp($tk)) ?></td></tr></tfoot></table></div></div>
<?php layout_end(); ?>
