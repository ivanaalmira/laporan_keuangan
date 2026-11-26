<?php
require 'config.php';
if (user()) log_aktivitas('Logout', 'Keluar dari sistem');
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
