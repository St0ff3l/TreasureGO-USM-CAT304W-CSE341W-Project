<?php
// pages/login.php
require_once '../includes/auth.php';

// 如果已经登录，直接去个人中心
if (is_logged_in()) {
    header("Location: profile.php");
    exit();
}

// 加载纯 HTML 视图
require_once '../views/login.html';
?>