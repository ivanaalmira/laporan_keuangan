<?php
require 'config.php'; require_login();
$db = db();

/* ---------- Persetujuan laporan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = isset($_POST['do']) ? $_POST['do'] : '';
    $pid = (int)(isset($_POST['periode']) ? $_POST['periode'] : 0);
    $back = 'laporan.php?' . http_build_query(['lap' => isset($_POST['lap']) ? $_POST['lap'] : 'labarugi', 'periode' => $pid]);
    try {
        $st = $db->prepare('SELECT * FROM periode_laporan WHERE id_periode=?'); $st->execute([$pid]);
        $pr = $st->fetch();
        if (!$pr) throw new Exception('Periode tidak ditemukan.');
        $uid = user()['id_user'];
        if ($do === 'ajukan') {
            need_admin();
            $db->prepare("INSERT INTO persetujuan_laporan(id_periode,status,catatan,diajukan_oleh,tgl_diajukan) VALUES(?, 'Diajukan', NULL, ?, NOW()) ON DUPLICATE KEY UPDATE status='Diajukan',catatan=NULL,diajukan_oleh=VALUES(diajukan_oleh),tgl_diajukan=NOW(),disetujui_oleh=NULL,tgl_disetujui=NULL")->execute([$pid, $uid]);
            log_aktivitas('Ajukan laporan', $pr['nama_periode']); flash('success', 'Laporan ' . $pr['nama_periode'] . ' diajukan ke owner untuk diperiksa.');
        } elseif ($do === 'setujui') {
            need_owner();
            $db->prepare("INSERT INTO persetujuan_laporan(id_periode,status,disetujui_oleh,tgl_disetujui) VALUES(?, 'Disetujui', ?, NOW()) ON DUPLICATE KEY UPDATE status='Disetujui',catatan=NULL,disetujui_oleh=VALUES(disetujui_oleh),tgl_disetujui=NOW()")->execute([$pid, $uid]);
            log_aktivitas('Setujui laporan', $pr['nama_periode']); flash('success', 'Laporan ' . $pr['nama_periode'] . ' disetujui. Anda dapat menutup buku periode ini di menu Pengaturan.');
        } elseif ($do === 'koreksi') {
            need_owner();
            $cat = trim(isset($_POST['catatan']) ? $_POST['catatan'] : '');
            if ($cat === '') throw new Exception('Tulis catatan koreksi agar admin tahu bagian yang perlu diperbaiki.');
            $db->prepare("INSERT INTO persetujuan_laporan(id_periode,status,catatan) VALUES(?, 'Perlu Koreksi', ?) ON DUPLICATE KEY UPDATE status='Perlu Koreksi',catatan=VALUES(catatan),disetujui_oleh=NULL,tgl_disetujui=NULL")->execute([$pid, $cat]);
            log_aktivitas('Minta koreksi laporan', $pr['nama_periode'] . ' · ' . $cat); flash('warn', 'Permintaan koreksi dikirim ke admin.');
        }
    } catch (Exception $ex) { flash('danger', $ex->getMessage()); }
    redirect($back);
}

/* ---------- Helper tampilan laporan ---------- */
function rpt_head($judul, $sub) {
    return '<div class="rpt-head"><div class="co">' . e(setting('nama_perusahaan', 'Perusahaan Anda')) . '</div><div class="tt">' . e($judul) . '</div><div class="pr">' . e($sub) . '</div></div>';
}
function rr($cls, $label, $val = null) {
    return '<tr class="' . $cls . '"><td>' . e($label) . '</td><td class="num">' . ($val === null ? '' : e(rp($val))) . '</td></tr>';
}
function rpt_labarugi($d, $s) {
    $l = laba_rugi($d, $s);
    $h = '<table class="rpt">' . rr('h', 'Pendapatan');
    foreach ($l['pendapatan'] as $r) $h .= rr('i', $r['nama_akun'], $r['nilai']);
    $h .= rr('t', 'Total pendapatan', $l['tot_pendapatan']) . rr('h', 'Beban');
    foreach ($l['beban'] as $r) $h .= rr('i', $r['nama_akun'], $r['nilai']);
    $h .= rr('t', 'Total beban', $l['tot_beban']) . rr('g', $l['laba'] >= 0 ? 'Laba bersih' : 'Rugi bersih', $l['laba']);
    if (!$l['pendapatan'] && !$l['beban']) $h .= '<tr class="note"><td colspan="2">Belum ada pendapatan atau beban pada periode ini.</td></tr>';
    return $h . '<tr class="note"><td colspan="2">Laba tidak selalu sama dengan kenaikan kas: transaksi kredit, pembelian aset, pembayaran utang, dan transaksi nonkas dapat membuat keduanya berbeda.</td></tr></table>';
}
function rpt_modal($d, $s) {
    $m = perubahan_modal($d, $s);
    $h = '<table class="rpt">' . rr('t', 'Modal awal periode', $m['awal']);
    foreach ($m['plus'] as $x) $h .= rr('i', $x[0], $x[1]);
    $h .= rr('i', 'Laba (rugi) bersih periode berjalan', $m['laba']);
    foreach ($m['min'] as $x) $h .= rr('i', $x[0], -$x[1]);
    $h .= rr('g', 'Modal akhir periode', $m['akhir']);
    return $h . '<tr class="note"><td colspan="2">Modal awal sudah mencakup laba ditahan dari periode sebelumnya.</td></tr></table>';
}
function rpt_neraca($s) {
    $n = neraca($s);
    $h = '<table class="rpt">' . rr('h', 'Aset');
    foreach ($n['aset'] as $r) $h .= rr('i', $r['nama_akun'], $r['nilai']);
    $h .= rr('t', 'Total aset', $n['tot_aset']) . rr('h', 'Kewajiban');
    foreach ($n['kewajiban'] as $r) $h .= rr('i', $r['nama_akun'], $r['nilai']);
    $h .= rr('t', 'Total kewajiban', $n['tot_kewajiban']) . rr('h', 'Ekuitas');
    foreach ($n['modal'] as $r) $h .= rr('i', $r['nama_akun'], $r['nilai']);
    $h .= rr('i', 'Laba (rugi) berjalan', $n['laba_berjalan']) . rr('t', 'Total ekuitas', $n['tot_ekuitas']) . rr('g', 'Total kewajiban dan ekuitas', $n['tot_kewajiban'] + $n['tot_ekuitas']);
    $h .= '<tr class="note"><td colspan="2">' . (abs($n['selisih']) < 0.5 ? 'Neraca seimbang: aset sama dengan kewajiban ditambah ekuitas.' : 'Perhatian: terdapat selisih ' . e(rp($n['selisih'])) . '. Periksa jurnal yang dicatat manual.') . '</td></tr>';
    return $h . '</table>';
}
function rpt_aruskas($d, $s) {
    $a = arus_kas($d, $s);
    $nama = ['operasi' => 'Arus kas dari aktivitas operasi', 'investasi' => 'Arus kas dari aktivitas investasi', 'pendanaan' => 'Arus kas dari aktivitas pendanaan'];
    $h = '<table class="rpt">';
    foreach ($nama as $k => $lbl) {
        $h .= rr('h', $lbl);
        foreach ($a['items'][$k] as $x) $h .= rr('i', $x[0], $x[1]);
        if (!$a['items'][$k]) $h .= '<tr class="i"><td class="muted">Tidak ada aktivitas</td><td></td></tr>';
        $h .= rr('t', 'Kas bersih dari ' . substr($lbl, 22), $a['tot'][$k]);
    }
    $h .= rr('t', 'Kenaikan (penurunan) kas bersih', $a['net']) . rr('i', 'Kas dan bank awal periode', $a['awal']) . rr('g', 'Kas dan bank akhir periode', $a['akhir']);
    return $h . '<tr class="note"><td colspan="2">Metode langsung, dihitung dari jurnal yang melibatkan akun Kas dan Bank.</td></tr></table>';
}
$judul = ['labarugi' => 'Laporan Laba Rugi', 'modal' => 'Laporan Perubahan Modal', 'neraca' => 'Neraca (Laporan Posisi Keuangan)', 'aruskas' => 'Laporan Arus Kas'];

/* ---------- Parameter ---------- */
$lap = isset($_GET['lap']) && isset($judul[$_GET['lap']]) ? $_GET['lap'] : 'labarugi';
$pid = (int)(isset($_GET['periode']) ? $_GET['periode'] : 0); $per = null;
$periodes = $db->query('SELECT * FROM periode_laporan ORDER BY tanggal_mulai DESC')->fetchAll();
foreach ($periodes as $x) if ((int)$x['id_periode'] === $pid) $per = $x;
if ($per) { $dari = $per['tanggal_mulai']; $sampai = $per['tanggal_selesai']; $p = 'periode'; } else { list($p, $dari, $sampai) = get_range('bulan_ini'); }
$sampaiTampil = ($p === 'semua') ? date('Y-m-d') : $sampai;
$rentang = label_range($p === 'periode' ? 'custom' : $p, $dari, $sampai);
$sub = ($lap === 'neraca') ? 'Per ' . tgl_panjang($sampaiTampil) : 'Periode ' . $rentang;
if ($per) $sub .= ' (' . $per['nama_periode'] . ')';

$appr = null;
if ($per) { try { $st = $db->prepare('SELECT a.*,u1.nama AS pengaju,u2.nama AS penyetuju FROM persetujuan_laporan a LEFT JOIN users u1 ON u1.id_user=a.diajukan_oleh LEFT JOIN users u2 ON u2.id_user=a.disetujui_oleh WHERE a.id_periode=?'); $st->execute([$pid]); $appr = $st->fetch(); } catch (Exception $ex) {} }
$status = $appr ? $appr['status'] : 'Draft';

switch ($lap) {
    case 'modal': $isi = rpt_modal($dari, $sampaiTampil); break;
    case 'neraca': $isi = rpt_neraca($sampaiTampil); break;
    case 'aruskas': $isi = rpt_aruskas($dari, $sampaiTampil); break;
    default: $isi = rpt_labarugi($dari, $sampaiTampil);
}
$ttd = '<div class="sign"><div><div class="ln"></div>Disusun oleh<br><b>Admin Keuangan</b></div><div><div class="ln">' . ($status === 'Disetujui' && $appr ? '<small class="muted"><br>Disetujui ' . e(tgl($appr['tgl_disetujui'])) . '</small>' : '') . '</div>Disetujui oleh<br><b>' . e($status === 'Disetujui' && $appr && $appr['penyetuju'] ? $appr['penyetuju'] : 'Owner') . '</b></div></div>';

/* ---------- Export Excel ---------- */
if (isset($_GET['export']) && $_GET['export'] === 'xls') {
    log_aktivitas('Export Excel', $judul[$lap] . ' · ' . $sub);
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $lap . '_' . date('Ymd') . '.xls"');
    echo '<html><head><meta charset="utf-8"><style>td{padding:4px 8px;font-family:Calibri,Arial}.h td{font-weight:bold;background:#E6F2ED}.t td,.g td{font-weight:bold}.g td{background:#F1F5F3}.num{text-align:right}</style></head><body>';
    echo '<h3>' . e(setting('nama_perusahaan', 'Perusahaan Anda')) . '</h3><h4>' . e($judul[$lap]) . '</h4><p>' . e($sub) . '</p>' . $isi . '</body></html>';
    exit;
}

/* ---------- Tampilan ---------- */
$q = ['lap' => $lap]; if ($per) $q['periode'] = $pid; else { $q['p'] = $p; $q['dari'] = $dari; $q['sampai'] = $sampai; }
layout_start($judul[$lap], 'laporan');
$formPer = '<form class="range-form" method="get"><input type="hidden" name="lap" value="' . e($lap) . '"><select name="periode" data-autosubmit aria-label="Periode tersimpan"><option value="0">Rentang bebas</option>';
foreach ($periodes as $x) $formPer .= '<option value="' . $x['id_periode'] . '"' . ($pid === (int)$x['id_periode'] ? ' selected' : '') . '>' . e($x['nama_periode']) . ($x['status'] === 'Ditutup' ? ' (ditutup)' : '') . '</option>';
$formPer .= '</select></form>';
page_head('Laporan keuangan', 'Disusun otomatis dari jurnal. Periode: <b>' . e($rentang) . '</b>',
    $formPer . ($per ? '' : range_form($p, $dari, $sampai, ['lap' => $lap])) .
    '<a class="btn btn-ghost no-print" href="?' . e(http_build_query($q + ['export' => 'xls'])) . '">' . icon('download') . ' Excel</a><button class="btn btn-primary no-print" onclick="window.print()">' . icon('print') . ' Cetak / PDF</button>');
echo '<div class="tabs">';
foreach ($judul as $k => $t) echo '<a class="' . ($k === $lap ? 'active' : '') . '" href="?' . e(http_build_query(array_merge($q, ['lap' => $k]))) . '">' . e(str_replace(' (Laporan Posisi Keuangan)', '', $t)) . '</a>';
echo '</div>';

if ($per) {
    $cls = ['Draft' => 'gray', 'Diajukan' => 'blue', 'Disetujui' => 'green', 'Perlu Koreksi' => 'amber'];
    echo '<div class="card no-print" style="max-width:860px;margin:0 auto 18px"><div class="card-h"><h2>Pemeriksaan dan persetujuan · ' . e($per['nama_periode']) . '</h2>' . pill($status, $cls[$status]) . '</div>';
    if ($status === 'Perlu Koreksi' && $appr['catatan']) echo '<div class="alert alert-warn"><b>Catatan owner:</b> ' . e($appr['catatan']) . '</div>';
    if ($status === 'Disetujui') echo '<p style="margin:0 0 12px">Disetujui oleh <b>' . e($appr['penyetuju']) . '</b> pada ' . e(tgl($appr['tgl_disetujui'])) . '. ' . ($per['status'] === 'Terbuka' ? 'Periode masih terbuka; owner dapat menutup buku di menu Pengaturan.' : 'Periode sudah ditutup.') . '</p>';
    if ($status === 'Diajukan') echo '<p style="margin:0 0 12px">Diajukan oleh <b>' . e($appr['pengaju']) . '</b> pada ' . e(tgl($appr['tgl_diajukan'])) . '. ' . (is_owner() ? 'Periksa keempat laporan, lalu setujui atau minta koreksi.' : 'Menunggu pemeriksaan owner.') . '</p>';
    if ($status === 'Draft') echo '<p style="margin:0 0 12px" class="muted">Laporan belum diajukan. ' . (is_owner() ? 'Menunggu admin menyelesaikan pencatatan.' : 'Pastikan semua transaksi periode ini sudah dicatat, lalu ajukan ke owner.') . '</p>';
    echo '<div class="actions">';
    $hid = csrf_field() . '<input type="hidden" name="periode" value="' . $pid . '"><input type="hidden" name="lap" value="' . e($lap) . '">';
    if (!is_owner() && in_array($status, ['Draft', 'Perlu Koreksi'])) echo '<form method="post">' . $hid . '<input type="hidden" name="do" value="ajukan"><button class="btn btn-primary">' . ($status === 'Draft' ? 'Ajukan ke owner' : 'Ajukan ulang ke owner') . '</button></form>';
    if (is_owner() && $status === 'Diajukan') {
        echo '<form method="post" data-confirm="Setujui laporan periode ini?">' . $hid . '<input type="hidden" name="do" value="setujui"><button class="btn btn-primary">' . icon('check') . ' Setujui laporan</button></form>';
        echo '<form method="post" class="actions">' . $hid . '<input type="hidden" name="do" value="koreksi"><input type="text" name="catatan" placeholder="Catatan koreksi untuk admin" style="width:280px" aria-label="Catatan koreksi"><button class="btn btn-ghost">Minta koreksi</button></form>';
    }
    echo '</div></div>';
}
?>
<div class="paper"><?= rpt_head($judul[$lap], $sub) . $isi . $ttd ?></div>
<?php layout_end(); ?>
