<?php
session_start();
session_unset(); // Hapus semua variabel sesi
session_destroy(); // Hancurkan sesi
header('Location: admin_login.php'); // Redirect ke halaman login
exit();
?>