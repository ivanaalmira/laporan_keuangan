<?php
/* Jalankan SEKALI setelah database diimpor: http://localhost/laporan_keuangan/setup.php
   Fungsi: membuat tabel tambahan, melengkapi daftar akun, dan mengatur password awal. */
require 'config.php';
$db = db();
$pesan = []; $sudah = false;
try { $sudah = (bool)$db->query("SELECT 1 FROM pengaturan WHERE kunci='setup_selesai'")->fetchColumn(); } catch (Exception $ex) {}
$jalan = ($_SERVER['REQUEST_METHOD'] === 'POST') && (!$sudah || isset($_POST['paksa']));
if ($jalan) {
    csrf_check();
    $db->exec("CREATE TABLE IF NOT EXISTS pengaturan (kunci VARCHAR(50) PRIMARY KEY, nilai TEXT NULL) ENGINE=InnoDB");
    $db->exec("CREATE TABLE IF NOT EXISTS log_aktivitas (id_log INT AUTO_INCREMENT PRIMARY KEY, id_user INT NULL, aksi VARCHAR(100) NOT NULL, detail TEXT NULL, ip VARCHAR(45) NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, KEY(id_user), KEY(created_at)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE IF NOT EXISTS persetujuan_laporan (id_periode INT PRIMARY KEY, status VARCHAR(20) NOT NULL DEFAULT 'Draft', catatan TEXT NULL, diajukan_oleh INT NULL, tgl_diajukan DATETIME NULL, disetujui_oleh INT NULL, tgl_disetujui DATETIME NULL, CONSTRAINT fk_persetujuan_periode FOREIGN KEY (id_periode) REFERENCES periode_laporan(id_periode) ON DELETE CASCADE) ENGINE=InnoDB");
    $pesan[] = 'Tabel tambahan siap: pengaturan, log_aktivitas, persetujuan_laporan.';

    $akun = [['1005', 'Aset Tetap', 'Aset', 'Debit'], ['1006', 'Akumulasi Penyusutan', 'Aset', 'Kredit'], ['2002', 'Utang Jangka Panjang', 'Kewajiban', 'Kredit'],
             ['3002', 'Prive / Dividen', 'Modal', 'Debit'], ['4003', 'Pendapatan Lain-lain', 'Pendapatan', 'Kredit'], ['5005', 'Beban Penyusutan', 'Beban', 'Debit'], ['5006', 'Beban Operasional', 'Beban', 'Debit']];
    $ins = $db->prepare('INSERT IGNORE INTO akun(kode_akun,nama_akun,kelompok,saldo_normal) VALUES(?,?,?,?)');
    foreach ($akun as $a) $ins->execute($a);
    $pesan[] = 'Daftar akun dilengkapi (1005, 1006, 2002, 3002, 4003, 5005, 5006) bila belum ada.';

    $pw = ['owner' => ['Owner Perusahaan', 'owner123', 'owner'], 'admin' => ['Admin Keuangan', 'admin123', 'admin']];
    foreach ($pw as $un => $d) {
        $hash = password_hash($d[1], PASSWORD_DEFAULT);
        $st = $db->prepare('SELECT id_user FROM users WHERE username=?'); $st->execute([$un]);
        if ($st->fetchColumn()) $db->prepare('UPDATE users SET password=? WHERE username=?')->execute([$hash, $un]);
        else $db->prepare('INSERT INTO users(nama,username,password,role) VALUES(?,?,?,?)')->execute([$d[0], $un, $hash, $d[2]]);
    }
    $pesan[] = 'Password awal diatur: admin / admin123 dan owner / owner123.';
    $db->prepare("INSERT INTO pengaturan(kunci,nilai) VALUES('setup_selesai','1') ON DUPLICATE KEY UPDATE nilai='1'")->execute();
    $db->prepare("INSERT IGNORE INTO pengaturan(kunci,nilai) VALUES('nama_perusahaan','Perusahaan Anda')")->execute();
    $sudah = true;
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head>
<body style="display:grid;place-items:center;min-height:100vh;padding:20px"><div class="card" style="max-width:560px;width:100%">
<h1 style="font-size:24px;font-weight:800;margin-bottom:8px">Setup awal sistem</h1>
<?php if ($pesan): ?>
  <div class="alert alert-success"><?php foreach ($pesan as $m) echo '<div>✓ ' . e($m) . '</div>'; ?></div>
  <p><b>Selesai.</b> Hapus file <code>setup.php</code> dari folder website, lalu <a href="login.php">masuk ke sistem</a>.</p>
<?php elseif ($sudah): ?>
  <div class="alert alert-info">Setup sudah pernah dijalankan. Hapus file <code>setup.php</code> demi keamanan.</div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="paksa" value="1"><button class="btn btn-ghost">Jalankan ulang (reset password admin &amp; owner)</button></form>
<?php else: ?>
  <p class="sub" style="margin-bottom:16px">Langkah ini akan:</p>
  <ul style="line-height:1.9"><li>membuat tabel <code>pengaturan</code>, <code>log_aktivitas</code>, dan <code>persetujuan_laporan</code>;</li>
  <li>menambah akun pelengkap (Aset Tetap, Utang Jangka Panjang, Prive, dll.) tanpa mengubah akun lama;</li>
  <li>mengatur password awal <b>admin123</b> dan <b>owner123</b> (hash di SQL contoh hanya placeholder).</li></ul>
  <form method="post" style="margin-top:20px"><?= csrf_field() ?><button class="btn btn-primary">Jalankan setup</button></form>
<?php endif; ?></div></body></html>
