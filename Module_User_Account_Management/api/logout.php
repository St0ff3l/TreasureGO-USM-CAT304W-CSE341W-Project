<?php
// api/logout.php
require_once '../includes/auth.php';

start_session_safe();

// 清空并销毁 Session
$_SESSION = [];
session_destroy();

// Redirect to Login Page (注意路径：跳回 pages/login.php)
header("Location: ../pages/login.php");
exit();
?>