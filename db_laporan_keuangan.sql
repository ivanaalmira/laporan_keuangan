-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 26 Nov 2026 pada 09.23
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_laporan_keuangan`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `akun`
--

CREATE TABLE `akun` (
  `id_akun` int(11) NOT NULL,
  `kode_akun` varchar(20) NOT NULL,
  `nama_akun` varchar(100) NOT NULL,
  `kelompok` enum('Aset','Kewajiban','Modal','Pendapatan','Beban') NOT NULL,
  `saldo_normal` enum('Debit','Kredit') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `akun`
--

INSERT INTO `akun` (`id_akun`, `kode_akun`, `nama_akun`, `kelompok`, `saldo_normal`) VALUES
(1, '1001', 'Kas', 'Aset', 'Debit'),
(2, '1002', 'Bank', 'Aset', 'Debit'),
(3, '1003', 'Piutang Usaha', 'Aset', 'Debit'),
(4, '1004', 'Persediaan', 'Aset', 'Debit'),
(5, '2001', 'Utang Usaha', 'Kewajiban', 'Kredit'),
(6, '3001', 'Modal Pemilik', 'Modal', 'Kredit'),
(7, '4001', 'Penjualan', 'Pendapatan', 'Kredit'),
(8, '4002', 'Pendapatan Jasa', 'Pendapatan', 'Kredit'),
(9, '5001', 'Beban Gaji', 'Beban', 'Debit'),
(10, '5002', 'Beban Listrik', 'Beban', 'Debit'),
(11, '5003', 'Beban Sewa', 'Beban', 'Debit'),
(12, '5004', 'Beban Transportasi', 'Beban', 'Debit');

-- --------------------------------------------------------

--
-- Struktur dari tabel `aset`
--

CREATE TABLE `aset` (
  `id_aset` int(11) NOT NULL,
  `nama_aset` varchar(100) NOT NULL,
  `kategori_aset` varchar(100) NOT NULL,
  `tanggal_perolehan` date NOT NULL,
  `nilai_perolehan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `akumulasi_penyusutan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `nilai_buku` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `aset`
--

INSERT INTO `aset` (`id_aset`, `nama_aset`, `kategori_aset`, `tanggal_perolehan`, `nilai_perolehan`, `akumulasi_penyusutan`, `nilai_buku`) VALUES
(1, 'Kendaraan Operasional', 'Kendaraan', '2024-01-15', 150000000.00, 15000000.00, 135000000.00),
(2, 'Komputer Kantor', 'Peralatan', '2024-06-10', 15000000.00, 1500000.00, 13500000.00),
(3, 'Bangunan Kantor', 'Bangunan', '2023-05-01', 500000000.00, 25000000.00, 475000000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id_detail` int(11) NOT NULL,
  `id_transaksi` int(11) NOT NULL,
  `id_akun` int(11) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredit` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_transaksi`
--

INSERT INTO `detail_transaksi` (`id_detail`, `id_transaksi`, `id_akun`, `debit`, `kredit`) VALUES
(1, 1, 1, 5000000.00, 0.00),
(2, 1, 7, 0.00, 5000000.00),
(3, 2, 10, 500000.00, 0.00),
(4, 2, 1, 0.00, 500000.00),
(5, 3, 1, 20000.00, 0.00),
(6, 3, 8, 0.00, 20000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL,
  `deskripsi` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `jenis`, `deskripsi`) VALUES
(1, 'Penjualan', 'pemasukan', 'Pendapatan dari penjualan produk'),
(2, 'Pendapatan Jasa', 'pemasukan', 'Pendapatan dari jasa'),
(3, 'Pendapatan Lain-lain', 'pemasukan', 'Pendapatan di luar usaha utama'),
(4, 'Gaji', 'pengeluaran', 'Beban gaji karyawan'),
(5, 'Listrik', 'pengeluaran', 'Beban listrik dan air'),
(6, 'Sewa', 'pengeluaran', 'Beban sewa tempat'),
(7, 'Transportasi', 'pengeluaran', 'Beban transportasi'),
(8, 'Biaya Operasional', 'pengeluaran', 'Biaya operasional harian');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kewajiban`
--

CREATE TABLE `kewajiban` (
  `id_kewajiban` int(11) NOT NULL,
  `nama_kewajiban` varchar(100) NOT NULL,
  `jenis` enum('Jangka Pendek','Jangka Panjang') NOT NULL,
  `tanggal` date NOT NULL,
  `jatuh_tempo` date DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('Belum Lunas','Lunas') NOT NULL DEFAULT 'Belum Lunas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kewajiban`
--

INSERT INTO `kewajiban` (`id_kewajiban`, `nama_kewajiban`, `jenis`, `tanggal`, `jatuh_tempo`, `jumlah`, `status`) VALUES
(1, 'Utang Bank BRI', 'Jangka Panjang', '2025-01-10', '2027-01-10', 100000000.00, 'Belum Lunas'),
(2, 'Utang Supplier A', 'Jangka Pendek', '2025-11-01', '2025-12-01', 5000000.00, 'Belum Lunas');

-- --------------------------------------------------------

--
-- Struktur dari tabel `modal`
--

CREATE TABLE `modal` (
  `id_modal` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis` enum('Setoran','Penarikan','Dividen') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `modal`
--

INSERT INTO `modal` (`id_modal`, `tanggal`, `jenis`, `keterangan`, `jumlah`) VALUES
(1, '2025-01-01', 'Setoran', 'Modal awal pendirian', 200000000.00),
(2, '2025-06-01', 'Setoran', 'Tambahan modal owner', 50000000.00),
(3, '2025-10-01', 'Penarikan', 'Prive owner', 10000000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `periode_laporan`
--

CREATE TABLE `periode_laporan` (
  `id_periode` int(11) NOT NULL,
  `nama_periode` varchar(100) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `status` enum('Terbuka','Ditutup') NOT NULL DEFAULT 'Terbuka'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `periode_laporan`
--

INSERT INTO `periode_laporan` (`id_periode`, `nama_periode`, `tanggal_mulai`, `tanggal_selesai`, `status`) VALUES
(1, 'November 2026', '2026-11-01', '2026-11-30', 'Terbuka'),
(2, 'Oktober 2026', '2026-10-01', '2026-10-31', 'Ditutup');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `nomor_transaksi` varchar(50) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `jenis` enum('Pemasukan','Pengeluaran') NOT NULL,
  `total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `id_kategori` int(11) DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id_transaksi`, `tanggal`, `nomor_transaksi`, `keterangan`, `jenis`, `total`, `id_kategori`, `id_user`, `created_at`) VALUES
(1, '2026-11-26', 'TRX-001', 'Penjualan produk tunai', 'Pemasukan', 5000000.00, 1, 2, '2026-11-26 07:49:35'),
(2, '2026-11-26', 'TRX-002', 'Pembayaran listrik', 'Pengeluaran', 500000.00, 5, 2, '2026-11-26 07:49:41'),
(3, '2026-11-26', 'TRX-003', 'jasa edit video tiktok', 'Pemasukan', 20000.00, 2, 2, '2026-11-26 15:12:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('owner','admin') NOT NULL DEFAULT 'admin',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id_user`, `nama`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Owner Perusahaan', 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'owner', '2026-11-26 07:47:50'),
(2, 'Admin Keuangan', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-11-26 07:47:50');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `akun`
--
ALTER TABLE `akun`
  ADD PRIMARY KEY (`id_akun`),
  ADD UNIQUE KEY `kode_akun` (`kode_akun`);

--
-- Indeks untuk tabel `aset`
--
ALTER TABLE `aset`
  ADD PRIMARY KEY (`id_aset`);

--
-- Indeks untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `fk_detail_transaksi` (`id_transaksi`),
  ADD KEY `fk_detail_akun` (`id_akun`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indeks untuk tabel `kewajiban`
--
ALTER TABLE `kewajiban`
  ADD PRIMARY KEY (`id_kewajiban`);

--
-- Indeks untuk tabel `modal`
--
ALTER TABLE `modal`
  ADD PRIMARY KEY (`id_modal`);

--
-- Indeks untuk tabel `periode_laporan`
--
ALTER TABLE `periode_laporan`
  ADD PRIMARY KEY (`id_periode`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD UNIQUE KEY `nomor_transaksi` (`nomor_transaksi`),
  ADD KEY `fk_transaksi_kategori` (`id_kategori`),
  ADD KEY `fk_transaksi_user` (`id_user`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `akun`
--
ALTER TABLE `akun`
  MODIFY `id_akun` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `aset`
--
ALTER TABLE `aset`
  MODIFY `id_aset` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `kewajiban`
--
ALTER TABLE `kewajiban`
  MODIFY `id_kewajiban` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `modal`
--
ALTER TABLE `modal`
  MODIFY `id_modal` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `periode_laporan`
--
ALTER TABLE `periode_laporan`
  MODIFY `id_periode` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD CONSTRAINT `fk_detail_akun` FOREIGN KEY (`id_akun`) REFERENCES `akun` (`id_akun`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detail_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
