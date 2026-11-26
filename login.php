<?php
require 'config.php';
if (user()) redirect('dashboard.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    $st = db()->prepare('SELECT * FROM users WHERE username=?'); $st->execute([$u]);
    $row = $st->fetch();
    if ($row && password_verify($p, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id_user' => (int)$row['id_user'], 'nama' => $row['nama'], 'username' => $row['username'], 'role' => $row['role']];
        log_aktivitas('Login', 'Masuk ke sistem');
        redirect('dashboard.php');
    }
    $err = 'Username atau password tidak cocok. Jika baru pertama kali memakai sistem, buka setup.php untuk mengatur password awal.';
}
$nama_p = setting('nama_perusahaan', 'Perusahaan Anda');
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Masuk · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css"></head><body>
<div class="login">
  <section class="login-art">
    <div class="brand" style="padding:0"><span class="brand-mark"><svg viewBox="0 0 32 32"><path d="M7 25V12m6 13V7m6 18v-9m6 9V10" stroke="#E9A632" stroke-width="3.4" stroke-linecap="round" fill="none"/></svg></span>
      <span class="brand-text"><strong><?= e($nama_p) ?></strong><small>Laporan Keuangan</small></span></div>
    <div>
      <h1>Setiap rupiah tercatat, setiap laporan seimbang.</h1>
      <p style="margin-top:18px">Catat transaksi sekali, lalu lihat jurnal, buku besar, laba rugi, neraca, dan arus kas tersusun otomatis.</p>
    </div>
    <div class="ledger-demo" aria-hidden="true">
      <div><span>Debit · Kas</span><span>5.000.000</span></div>
      <div><span>Kredit · Penjualan</span><span>5.000.000</span></div>
      <div class="tot"><span>Selisih</span><span>0</span></div>
    </div>
  </section>
  <section class="login-form">
    <form class="login-box" method="post" autocomplete="on">
      <h2>Masuk ke sistem</h2>
      <p class="sub" style="margin-bottom:24px">Gunakan akun owner atau admin keuangan.</p>
      <?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" required autofocus autocomplete="username"></div>
      <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
      <button class="btn btn-primary">Masuk</button>
      <div class="demo-note">Akun contoh: <b>admin</b> / admin123 · <b>owner</b> / owner123<br>Ganti password setelah masuk, lalu hapus catatan ini dari login.php.</div>
    </form>
  </section>
</div></body></html>
