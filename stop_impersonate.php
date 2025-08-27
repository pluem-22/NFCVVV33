<?php
// stop_impersonate.php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db_config.php';

if (!isset($_SESSION['impersonator_admin_id'], $_SESSION['impersonation_log_id'])) {
    header('Location: index.php'); exit;
}

// ปิด log
$log_id = intval($_SESSION['impersonation_log_id']);
$st=$conn->prepare("UPDATE admin_impersonation_logs SET stopped_at = NOW() WHERE id=?");
$st->bind_param("i",$log_id); $st->execute(); $st->close();

// คืนตัวตนเป็นแอดมิน
$admin_id = intval($_SESSION['impersonator_admin_id']);
unset($_SESSION['impersonator_admin_id'], $_SESSION['impersonation_log_id']);

$s=$conn->prepare("SELECT id, username, role, name FROM users WHERE id=? LIMIT 1");
$s->bind_param("i",$admin_id); $s->execute(); $s->bind_result($id,$uname,$role,$name);
if ($s->fetch()) {
    $_SESSION['user_id']=$id; $_SESSION['username']=$uname; $_SESSION['role']=$role; $_SESSION['name']=$name;
}
$s->close();

header('Location: index.php');
