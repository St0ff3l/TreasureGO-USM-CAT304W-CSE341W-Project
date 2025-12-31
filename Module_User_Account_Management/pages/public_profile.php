<?php
// Module_User_Account_Management/pages/public_profile.php

// 1. 引入必要文件
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

// 2. 启动 Session (为了 Headerbar 显示登录状态)
start_session_safe();

// 3. 引入视图
require_once __DIR__ . '/../views/public_profile.html';
?>
