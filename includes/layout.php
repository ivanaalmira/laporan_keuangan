<?php
function icon($n) {
    $p = [
        'home' => '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'swap' => '<path d="M7 4L3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7"/>',
        'tag' => '<path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'book' => '<path d="M4 4h12a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4z"/><path d="M4 16a4 4 0 0 1 4-4h12"/>',
        'building' => '<path d="M4 21V5l8-2v18M12 9h8v12M8 8h1M8 12h1M8 16h1M15 13h2M15 17h2"/>',
        'coins' => '<circle cx="9" cy="9" r="6"/><path d="M15 9.2A6 6 0 1 1 9.2 15M7 9h4"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'ledger' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'report' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 17v-4M12 17v-7M16 17v-2"/>',
        'chart' => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 21a2 2 0 0 0 4 0"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'print' => '<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M7 14h10v7H7z"/>',
        'check' => '<path d="M4 12l5 5L20 6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . (isset($p[$n]) ? $p[$n] : '') . '</svg>';
}

function nav_items() {
    return [
        'Menu utama' => [
            ['dashboard', 'Dashboard', 'dashboard.php', 'home'],
            ['transaksi', 'Transaksi', 'transaksi.php', 'swap'],
            ['kategori', 'Kategori', 'kategori.php', 'tag'],
            ['akun', 'Daftar Akun', 'akun.php', 'book'],
            ['aset', 'Aset dan Kewajiban', 'aset_kewajiban.php', 'building'],
            ['modal', 'Modal', 'modal.php', 'coins'],
        ],
        'Laporan dan analisis' => [
            ['jurnal', 'Jurnal Umum', 'jurnal.php', 'list'],
            ['bukubesar', 'Buku Besar', 'buku_besar.php', 'ledger'],
            ['laporan', 'Laporan Keuangan', 'laporan.php', 'report'],
            ['analisis', 'Analisis Keuangan', 'analisis.php', 'chart'],
        ],
        'Pengaturan' => [
            ['pengguna', 'Manajemen Pengguna', 'pengguna.php', 'users', 'owner'],
            ['riwayat', 'Riwayat Aktivitas', 'riwayat.php', 'clock'],
            ['pengaturan', 'Pengaturan', 'pengaturan.php', 'cog'],
        ],
    ];
}

function layout_start($title, $active = '') {
    $u = user();
    $nama_p = setting('nama_perusahaan', 'Perusahaan Anda');
    $logo = setting('logo', '');
    $due = 0;
    try {
        $due = (int)db()->query("SELECT COUNT(*) FROM kewajiban WHERE status='Belum Lunas' AND jatuh_tempo IS NOT NULL AND jatuh_tempo<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();
    } catch (Exception $ex) {}
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' · ' . e($nama_p) . '</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="assets/css/style.css"></head><body><div class="app">';
    echo '<aside class="sidebar" id="sidebar"><div class="brand">';
    if ($logo && file_exists(__DIR__ . '/../uploads/' . $logo)) echo '<img src="uploads/' . e($logo) . '" alt="" class="brand-logo">';
    else echo '<span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M7 25V12m6 13V7m6 18v-9m6 9V10" stroke="#E9A632" stroke-width="3.4" stroke-linecap="round" fill="none"/></svg></span>';
    echo '<span class="brand-text"><strong>' . e($nama_p) . '</strong><small>Laporan Keuangan</small></span></div><nav>';
    foreach (nav_items() as $grup => $items) {
        echo '<div class="nav-group"><div class="nav-title">' . e($grup) . '</div>';
        foreach ($items as $it) {
            if (isset($it[4]) && $it[4] === 'owner' && !is_owner()) continue;
            echo '<a href="' . $it[2] . '" class="' . ($active === $it[0] ? 'active' : '') . '">' . icon($it[3]) . '<span>' . e($it[1]) . '</span></a>';
        }
        echo '</div>';
    }
    echo '</nav><a class="logout" href="logout.php">' . icon('logout') . '<span>Keluar</span></a></aside><div class="scrim" data-menu></div>';
    echo '<div class="main"><header class="topbar"><button class="btn-icon menu-btn" data-menu aria-label="Buka menu">' . icon('menu') . '</button>';
    echo '<div class="top-date">' . e(tgl_panjang(date('Y-m-d'))) . '</div><div class="top-right">';
    echo '<a class="bell" href="aset_kewajiban.php?tab=kewajiban" title="Kewajiban jatuh tempo ≤ 30 hari">' . icon('bell') . ($due ? '<b>' . $due . '</b>' : '') . '</a>';
    echo '<div class="user-chip"><span class="avatar">' . e(strtoupper(substr($u['nama'], 0, 1))) . '</span><span class="who"><strong>' . e($u['nama']) . '</strong><small>' . ($u['role'] === 'owner' ? 'Owner' : 'Admin Keuangan') . '</small></span></div>';
    echo '</div></header><main class="content" id="content">' . flash_render();
}

function layout_end($js = '', $charts = false) {
    echo '</main></div></div>';
    if ($charts) echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
    echo '<script src="assets/js/app.js"></script>';
    if ($js) echo '<script>' . $js . '</script>';
    echo '</body></html>';
}

function page_head($title, $sub = '', $actions = '') {
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1>' . ($sub ? '<p class="sub">' . $sub . '</p>' : '') . '</div><div class="actions">' . $actions . '</div></div>';
}
