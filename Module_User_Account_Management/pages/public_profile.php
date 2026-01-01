<?php
// Module_User_Account_Management/pages/public_profile.php

// 1. Include necessary files
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

// 2. Start Session (for Headerbar to show login status)
start_session_safe();

// 3. Include View
require_once __DIR__ . '/../views/public_profile.html';
?>
