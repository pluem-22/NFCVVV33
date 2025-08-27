<?php
// logout.php
session_start();

// ลบข้อมูล session ทั้งหมด
session_unset();
session_destroy();

// ลบ cookie session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Redirect ไปหน้า login
header("Location: login.php");
exit();
?>