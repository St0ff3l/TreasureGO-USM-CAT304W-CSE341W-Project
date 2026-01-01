<?php
// pages/login.php
require_once '../includes/auth.php';

// If already logged in, redirect to profile
if (is_logged_in()) {
    header("Location: profile.php");
    exit();
}

// Load pure HTML view
require_once '../views/login.html';
?>