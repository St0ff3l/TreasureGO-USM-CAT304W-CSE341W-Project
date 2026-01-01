<?php
// api/logout.php
require_once '../includes/auth.php';

start_session_safe();

// Clear and destroy Session
$_SESSION = [];
session_destroy();

// Redirect to Login Page (Note path: jump back to pages/login.php)
header("Location: ../pages/login.php");
exit();
?>