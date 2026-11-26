SISTEM INFORMASI LAPORAN KEUANGAN  (PHP + MySQL, tanpa framework)

CARA PASANG (XAMPP)
1. Salin folder "laporan_keuangan" ke C:\xampp\htdocs\
2. Start Apache dan MySQL di XAMPP.
3. Impor SQL database yang sudah Anda buat (db_laporan_keuangan) lewat phpMyAdmin.
4. Jika user/password MySQL berbeda, ubah config.php.
5. Buka http://localhost/laporan_keuangan/setup.php lalu klik "Jalankan setup" (cukup sekali), kemudian HAPUS setup.php.
6. Masuk di http://localhost/laporan_keuangan/  -> admin / admin123  atau  owner / owner123 (segera ganti password).

CATATAN
- Grafik dan font memakai CDN (Chart.js, Google Fonts): butuh internet saat membuka halaman.
- Neraca, laba rugi, arus kas, dan rasio dihitung dari JURNAL (detail_transaksi).
- PDF: tombol "Cetak / PDF" lalu pilih "Simpan sebagai PDF". Excel: tombol "Excel".
