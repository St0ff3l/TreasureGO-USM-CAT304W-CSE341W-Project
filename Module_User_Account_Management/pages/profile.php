<?php
// pages/profile.php
require_once '../includes/auth.php';

// 🔒 门卫拦截：必须登录才能看
require_login();

// 引入 View
require_once '../views/profile.html';
?>