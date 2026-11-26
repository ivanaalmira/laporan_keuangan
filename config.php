<?php
/* ============================================================
   KONFIGURASI — sesuaikan jika user/password MySQL Anda berbeda
   ============================================================ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_laporan_keuangan');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Sistem Informasi Laporan Keuangan');

date_default_timezone_set('Asia/Jakarta');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Koneksi database gagal: ' . htmlspecialchars($e->getMessage()) . ' — periksa file config.php dan pastikan MySQL di XAMPP sudah menyala.');
        }
    }
    return $pdo;
}

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/layout.php';
