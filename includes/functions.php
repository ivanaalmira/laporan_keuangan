<?php
/* ---------- Format & util ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n) { $n = (float)$n; return ($n < 0 ? '-' : '') . 'Rp' . number_format(abs($n), 0, ',', '.'); }
function ang($n) { $n = (float)$n; if (abs($n) < 0.005) return '-'; return ($n < 0 ? '(' : '') . number_format(abs($n), 0, ',', '.') . ($n < 0 ? ')' : ''); }
function bulan_nama($m) { $b = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']; return $b[(int)$m]; }
function tgl($d) { if (!$d) return '-'; $t = strtotime($d); return date('j', $t) . ' ' . substr(bulan_nama(date('n', $t)), 0, 3) . ' ' . date('Y', $t); }
function tgl_panjang($d) { if (!$d) return '-'; $t = strtotime($d); return date('j', $t) . ' ' . bulan_nama(date('n', $t)) . ' ' . date('Y', $t); }
function valid_date($s) { $t = DateTime::createFromFormat('Y-m-d', (string)$s); return ($t && $t->format('Y-m-d') === $s) ? $s : ''; }
function redirect($u) { header('Location: ' . $u); exit; }
function pill($text, $cls = 'gray') { return '<span class="pill pill-' . e($cls) . '">' . e($text) . '</span>'; }
function pct($v, $d = 1) { return $v === null ? '—' : number_format($v, $d, ',', '.') . '%'; }

/* ---------- Flash, CSRF, auth ---------- */
function flash($t, $m) { $_SESSION['flash'][] = [$t, $m]; }
function flash_render() {
    if (empty($_SESSION['flash'])) return '';
    $h = '';
    foreach ($_SESSION['flash'] as $f) $h .= '<div class="alert alert-' . e($f[0]) . '" role="status">' . e($f[1]) . '</div>';
    unset($_SESSION['flash']);
    return $h;
}
function csrf_token() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field() { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['_csrf']) || !hash_equals(isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '', $_POST['_csrf'])) {
            http_response_code(419);
            die('Sesi formulir kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.');
        }
    }
}
function user() { return isset($_SESSION['user']) ? $_SESSION['user'] : null; }
function is_owner() { $u = user(); return $u && $u['role'] === 'owner'; }
function require_login() { if (!user()) redirect('login.php'); csrf_check(); }
function need_admin() {
    if (is_owner()) { flash('warn', 'Owner hanya dapat melihat dan memeriksa data. Pencatatan dilakukan oleh admin keuangan.'); redirect('dashboard.php'); }
}
function need_owner() {
    if (!is_owner()) { flash('warn', 'Halaman ini hanya untuk owner.'); redirect('dashboard.php'); }
}
function log_aktivitas($aksi, $detail = '') {
    try {
        $u = user();
        db()->prepare('INSERT INTO log_aktivitas(id_user,aksi,detail,ip) VALUES(?,?,?,?)')
            ->execute([$u ? $u['id_user'] : null, $aksi, $detail, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '']);
    } catch (Exception $ex) { /* tabel log belum ada: abaikan */ }
}

/* ---------- Pengaturan (key-value) ---------- */
function setting($k, $d = '') {
    static $c = null;
    if ($c === null) {
        $c = [];
        try { foreach (db()->query('SELECT kunci,nilai FROM pengaturan') as $r) $c[$r['kunci']] = $r['nilai']; } catch (Exception $ex) {}
    }
    return isset($c[$k]) ? $c[$k] : $d;
}
function set_setting($k, $v) {
    db()->prepare('INSERT INTO pengaturan(kunci,nilai) VALUES(?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)')->execute([$k, $v]);
}

/* ---------- Rentang tanggal & filter ---------- */
function get_range($default = 'bulan_ini') {
    $p = isset($_GET['p']) ? $_GET['p'] : $default;
    $today = date('Y-m-d');
    switch ($p) {
        case 'hari_ini': $d = $s = $today; break;
        case 'tahun_ini': $d = date('Y-01-01'); $s = date('Y-12-31'); break;
        case 'semua': $d = '2000-01-01'; $s = '2099-12-31'; break;
        case 'custom':
            $d = valid_date(isset($_GET['dari']) ? $_GET['dari'] : '') ?: date('Y-m-01');
            $s = valid_date(isset($_GET['sampai']) ? $_GET['sampai'] : '') ?: date('Y-m-t');
            break;
        default: $p = 'bulan_ini'; $d = date('Y-m-01'); $s = date('Y-m-t');
    }
    if ($d > $s) { $t = $d; $d = $s; $s = $t; }
    return [$p, $d, $s];
}
function range_form($p, $d, $s, $hidden = []) {
    $opts = ['hari_ini' => 'Hari ini', 'bulan_ini' => 'Bulan ini', 'tahun_ini' => 'Tahun ini', 'semua' => 'Semua periode', 'custom' => 'Rentang tanggal'];
    $h = '<form class="range-form" method="get" data-range>';
    foreach ($hidden as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    $h .= '<select name="p" aria-label="Periode">';
    foreach ($opts as $k => $l) $h .= '<option value="' . $k . '"' . ($k === $p ? ' selected' : '') . '>' . $l . '</option>';
    $h .= '</select><span class="range-custom"' . ($p === 'custom' ? '' : ' hidden') . '><input type="date" name="dari" value="' . e($d) . '"><span>s/d</span><input type="date" name="sampai" value="' . e($s) . '"><button class="btn btn-primary btn-sm">Terapkan</button></span></form>';
    return $h;
}
function label_range($p, $d, $s) {
    if ($p === 'semua') return 'Semua periode';
    if ($d === $s) return tgl_panjang($d);
    return tgl_panjang($d) . ' s/d ' . tgl_panjang($s);
}
function paginate($total, $per, $page) { $pages = max(1, (int)ceil($total / $per)); $page = min(max(1, $page), $pages); return [$page, $pages, ($page - 1) * $per]; }
function pager($page, $pages) {
    if ($pages <= 1) return '';
    $q = $_GET;
    $mk = function ($p, $l, $dis) use ($q) {
        $q['hal'] = $p;
        return $dis ? '<span class="btn btn-ghost btn-sm disabled">' . $l . '</span>' : '<a class="btn btn-ghost btn-sm" href="?' . e(http_build_query($q)) . '">' . $l . '</a>';
    };
    return '<div class="pager">' . $mk($page - 1, 'Sebelumnya', $page <= 1) . '<span>Halaman ' . $page . ' dari ' . $pages . '</span>' . $mk($page + 1, 'Berikutnya', $page >= $pages) . '</div>';
}

/* ---------- Akuntansi: akun & jurnal ---------- */
function akun_id($kode, $wajib = true) {
    static $c = [];
    if (!isset($c[$kode])) {
        $st = db()->prepare('SELECT id_akun FROM akun WHERE kode_akun=?'); $st->execute([$kode]);
        $id = $st->fetchColumn();
        if (!$id) { if ($wajib) throw new Exception('Akun ' . $kode . ' belum ada di Daftar Akun. Jalankan setup.php terlebih dahulu.'); return 0; }
        $c[$kode] = (int)$id;
    }
    return $c[$kode];
}
function periode_tertutup($tgl) {
    $st = db()->prepare("SELECT nama_periode FROM periode_laporan WHERE status='Ditutup' AND ? BETWEEN tanggal_mulai AND tanggal_selesai LIMIT 1");
    $st->execute([$tgl]);
    return $st->fetchColumn();
}
function nomor_baru($prefix = 'TRX') {
    $st = db()->prepare('SELECT nomor_transaksi FROM transaksi WHERE nomor_transaksi LIKE ?');
    $st->execute([$prefix . '-%']);
    $max = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) if (preg_match('/^' . $prefix . '-(\d+)$/', $n, $m)) $max = max($max, (int)$m[1]);
    return $prefix . '-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}
function kategori_akun_map() {
    return ['penjualan' => '4001', 'pendapatan jasa' => '4002', 'pendapatan lain-lain' => '4003', 'gaji' => '5001', 'listrik' => '5002', 'sewa' => '5003', 'transportasi' => '5004', 'biaya operasional' => '5006'];
}
/* $lines = [[id_akun, debit, kredit], ...] — selalu divalidasi seimbang */
function simpan_jurnal($tgl, $no, $ket, $jenis, $id_kat, $lines, $id = 0) {
    $db = db(); $td = 0; $tk = 0;
    foreach ($lines as $l) { $td += $l[1]; $tk += $l[2]; }
    if ($td <= 0 || abs($td - $tk) > 0.004) throw new Exception('Jurnal tidak seimbang: total debit ' . rp($td) . ' dan kredit ' . rp($tk) . ' harus sama.');
    if ($x = periode_tertutup($tgl)) throw new Exception('Periode "' . $x . '" sudah ditutup. Buka kembali periode itu (oleh owner) untuk mengubah data.');
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    try {
        $uid = user() ? user()['id_user'] : 1;
        if ($id) {
            $db->prepare('UPDATE transaksi SET tanggal=?,nomor_transaksi=?,keterangan=?,jenis=?,total=?,id_kategori=? WHERE id_transaksi=?')
               ->execute([$tgl, $no, $ket, $jenis, $td, $id_kat, $id]);
            $db->prepare('DELETE FROM detail_transaksi WHERE id_transaksi=?')->execute([$id]);
        } else {
            $db->prepare('INSERT INTO transaksi(tanggal,nomor_transaksi,keterangan,jenis,total,id_kategori,id_user) VALUES(?,?,?,?,?,?,?)')
               ->execute([$tgl, $no, $ket, $jenis, $td, $id_kat, $uid]);
            $id = (int)$db->lastInsertId();
        }
        $ins = $db->prepare('INSERT INTO detail_transaksi(id_transaksi,id_akun,debit,kredit) VALUES(?,?,?,?)');
        foreach ($lines as $l) $ins->execute([$id, $l[0], $l[1], $l[2]]);
        if ($own) $db->commit();
    } catch (Exception $ex) {
        if ($own && $db->inTransaction()) $db->rollBack();
        throw $ex;
    }
    return $id;
}

/* ---------- Akuntansi: saldo & laporan ---------- */
function saldo_rows($dari = null, $sampai = null) {
    $w = []; $p = [];
    if ($dari) { $w[] = 't.tanggal>=?'; $p[] = $dari; }
    if ($sampai) { $w[] = 't.tanggal<=?'; $p[] = $sampai; }
    $sql = 'SELECT a.id_akun,a.kode_akun,a.nama_akun,a.kelompok,a.saldo_normal,COALESCE(x.debit,0) AS debit,COALESCE(x.kredit,0) AS kredit
            FROM akun a LEFT JOIN (SELECT d.id_akun,SUM(d.debit) AS debit,SUM(d.kredit) AS kredit FROM detail_transaksi d
            JOIN transaksi t ON t.id_transaksi=d.id_transaksi' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' GROUP BY d.id_akun) x ON x.id_akun=a.id_akun
            ORDER BY a.kode_akun';
    $st = db()->prepare($sql); $st->execute($p);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['debit'] = (float)$r['debit']; $r['kredit'] = (float)$r['kredit'];
        $r['nilai'] = in_array($r['kelompok'], ['Aset', 'Beban']) ? $r['debit'] - $r['kredit'] : $r['kredit'] - $r['debit'];
    }
    unset($r);
    return $rows;
}
function laba_rugi($dari, $sampai) {
    $pend = []; $beb = []; $tp = 0; $tb = 0;
    foreach (saldo_rows($dari, $sampai) as $r) {
        if ($r['kelompok'] === 'Pendapatan') { $tp += $r['nilai']; if ($r['debit'] || $r['kredit']) $pend[] = $r; }
        elseif ($r['kelompok'] === 'Beban') { $tb += $r['nilai']; if ($r['debit'] || $r['kredit']) $beb[] = $r; }
    }
    return ['pendapatan' => $pend, 'beban' => $beb, 'tot_pendapatan' => $tp, 'tot_beban' => $tb, 'laba' => $tp - $tb];
}
function neraca($sampai) {
    $rows = saldo_rows(null, $sampai);
    $g = ['Aset' => [], 'Kewajiban' => [], 'Modal' => []];
    $t = ['Aset' => 0, 'Kewajiban' => 0, 'Modal' => 0, 'Pendapatan' => 0, 'Beban' => 0];
    foreach ($rows as $r) {
        $t[$r['kelompok']] += $r['nilai'];
        if (isset($g[$r['kelompok']]) && ($r['debit'] || $r['kredit'])) $g[$r['kelompok']][] = $r;
    }
    $laba = $t['Pendapatan'] - $t['Beban'];
    $ekuitas = $t['Modal'] + $laba;
    return ['aset' => $g['Aset'], 'kewajiban' => $g['Kewajiban'], 'modal' => $g['Modal'], 'tot_aset' => $t['Aset'], 'tot_kewajiban' => $t['Kewajiban'],
            'tot_modal' => $t['Modal'], 'laba_berjalan' => $laba, 'tot_ekuitas' => $ekuitas, 'selisih' => $t['Aset'] - ($t['Kewajiban'] + $ekuitas), 'rows' => $rows];
}
function saldo_kas($rows) { $k = 0; foreach ($rows as $r) if (in_array($r['kode_akun'], ['1001', '1002'])) $k += $r['nilai']; return $k; }

function perubahan_modal($dari, $sampai) {
    $awal = neraca(date('Y-m-d', strtotime($dari . ' -1 day')));
    $items_plus = []; $items_min = []; $plus = 0; $min = 0;
    foreach (saldo_rows($dari, $sampai) as $r) {
        if ($r['kelompok'] !== 'Modal') continue;
        if ($r['kredit'] > 0) { $items_plus[] = ['Penambahan — ' . $r['nama_akun'], $r['kredit']]; $plus += $r['kredit']; }
        if ($r['debit'] > 0) { $items_min[] = ['Pengurangan — ' . $r['nama_akun'], $r['debit']]; $min += $r['debit']; }
    }
    $laba = laba_rugi($dari, $sampai)['laba'];
    return ['awal' => $awal['tot_ekuitas'], 'plus' => $items_plus, 'min' => $items_min, 'tot_plus' => $plus, 'tot_min' => $min, 'laba' => $laba, 'akhir' => $awal['tot_ekuitas'] + $plus + $laba - $min];
}

function klas_kas($r) {
    if (in_array($r['kelompok'], ['Pendapatan', 'Beban'])) return 'operasi';
    if (in_array($r['kode_akun'], ['1003', '1004', '2001'])) return 'operasi';
    if ($r['kelompok'] === 'Aset') return 'investasi';
    return 'pendanaan';
}
function arus_kas($dari, $sampai) {
    $kas = "('1001','1002')";
    $st = db()->prepare("SELECT a.kode_akun,a.nama_akun,a.kelompok,SUM(d.kredit-d.debit) AS efek
        FROM detail_transaksi d JOIN akun a ON a.id_akun=d.id_akun JOIN transaksi t ON t.id_transaksi=d.id_transaksi
        WHERE t.tanggal BETWEEN ? AND ? AND a.kode_akun NOT IN $kas
        AND d.id_transaksi IN (SELECT d2.id_transaksi FROM detail_transaksi d2 JOIN akun a2 ON a2.id_akun=d2.id_akun WHERE a2.kode_akun IN $kas)
        GROUP BY a.id_akun,a.kode_akun,a.nama_akun,a.kelompok ORDER BY a.kode_akun");
    $st->execute([$dari, $sampai]);
    $g = ['operasi' => [], 'investasi' => [], 'pendanaan' => []]; $t = ['operasi' => 0, 'investasi' => 0, 'pendanaan' => 0];
    foreach ($st->fetchAll() as $r) {
        $v = (float)$r['efek']; if (abs($v) < 0.005) continue;
        $k = klas_kas($r);
        $g[$k][] = [($v >= 0 ? 'Penerimaan — ' : 'Pembayaran — ') . $r['nama_akun'], $v];
        $t[$k] += $v;
    }
    $st = db()->prepare("SELECT COALESCE(SUM(d.debit-d.kredit),0) FROM detail_transaksi d JOIN akun a ON a.id_akun=d.id_akun JOIN transaksi t ON t.id_transaksi=d.id_transaksi WHERE a.kode_akun IN $kas AND t.tanggal<?");
    $st->execute([$dari]);
    $awal = (float)$st->fetchColumn();
    $net = $t['operasi'] + $t['investasi'] + $t['pendanaan'];
    return ['items' => $g, 'tot' => $t, 'net' => $net, 'awal' => $awal, 'akhir' => $awal + $net];
}

function seri_bulanan($n, $akhir) {
    if ($akhir > date('Y-m-d')) $akhir = date('Y-m-d');
    $end = new DateTime(date('Y-m-01', strtotime($akhir)));
    $m = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $d = clone $end; $d->modify('-' . $i . ' month');
        $m[$d->format('Y-m')] = ['l' => substr(bulan_nama($d->format('n')), 0, 3) . ' ' . $d->format('y'), 'p' => 0, 'b' => 0];
    }
    $keys = array_keys($m);
    $st = db()->prepare("SELECT DATE_FORMAT(t.tanggal,'%Y-%m') AS ym,a.kelompok,SUM(d.kredit-d.debit) AS v
        FROM detail_transaksi d JOIN akun a ON a.id_akun=d.id_akun JOIN transaksi t ON t.id_transaksi=d.id_transaksi
        WHERE a.kelompok IN ('Pendapatan','Beban') AND t.tanggal BETWEEN ? AND ? GROUP BY ym,a.kelompok");
    $st->execute([$keys[0] . '-01', date('Y-m-t', strtotime($akhir))]);
    foreach ($st->fetchAll() as $r) {
        if (!isset($m[$r['ym']])) continue;
        if ($r['kelompok'] === 'Pendapatan') $m[$r['ym']]['p'] += (float)$r['v']; else $m[$r['ym']]['b'] += -(float)$r['v'];
    }
    $o = ['l' => [], 'p' => [], 'b' => [], 'laba' => []];
    foreach ($m as $x) { $o['l'][] = $x['l']; $o['p'][] = $x['p']; $o['b'][] = $x['b']; $o['laba'][] = $x['p'] - $x['b']; }
    return $o;
}

function hitung_rasio($dari, $sampai) {
    $n = neraca($sampai); $lr = laba_rugi($dari, $sampai);
    $kas = 0; $piutang = 0; $lancar = 0; $ul = 0;
    foreach ($n['rows'] as $r) {
        if ($r['kelompok'] === 'Aset' && (int)$r['kode_akun'] < 1005) $lancar += $r['nilai'];
        if (in_array($r['kode_akun'], ['1001', '1002'])) $kas += $r['nilai'];
        if ($r['kode_akun'] === '1003') $piutang += $r['nilai'];
        if ($r['kode_akun'] === '2001') $ul += $r['nilai'];
    }
    $penjualan = 0;
    foreach ($lr['pendapatan'] as $r) if ($r['kode_akun'] !== '4003') $penjualan += $r['nilai'];
    $div = function ($a, $b) { return abs($b) < 0.005 ? null : $a / $b; };
    $x = function ($v) { return $v === null ? null : $v * 100; };
    return [
        'lancar' => $div($lancar, $ul), 'cepat' => $div($kas + $piutang, $ul), 'kas' => $x($div($kas, $ul)),
        'dar' => $x($div($n['tot_kewajiban'], $n['tot_aset'])), 'npm' => $x($div($lr['laba'], $penjualan)),
        'roa' => $x($div($lr['laba'], $n['tot_aset'])), 'roe' => $x($div($lr['laba'], $n['tot_ekuitas'])),
        'raw' => ['kas' => $kas, 'ul' => $ul, 'lancar' => $lancar, 'laba' => $lr['laba'], 'penjualan' => $penjualan, 'aset' => $n['tot_aset'], 'utang' => $n['tot_kewajiban']],
    ];
}
