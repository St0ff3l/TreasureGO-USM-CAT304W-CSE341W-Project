<?php
// includes/auth.php

// Safely start Session
function start_session_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        // Key code: Set Cookie path to '/' (valid for the entire website)
        // Must be called before session_start()
        session_set_cookie_params(0, '/');
        session_start();
    }
}

// Check if logged in
function is_logged_in() {
    start_session_safe();
    return isset($_SESSION['user_id']);
}

// Force login required (used as a gatekeeper for Pages layer)
function require_login() {
    if (!is_logged_in()) {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $wantsJson = str_contains($accept, 'application/json') || ($xrw === 'XMLHttpRequest');

        if ($wantsJson) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit();
        }

        header("Location: ../pages/login.php");
        exit();
    }
}

// Get current user ID
function get_current_user_id() {
    start_session_safe();
    return $_SESSION['user_id'] ?? null;
}

// Check if admin
function is_admin() {
    start_session_safe();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Force admin privileges required
function require_admin() {
    if (!is_logged_in()) {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $wantsJson = str_contains($accept, 'application/json') || ($xrw === 'XMLHttpRequest');

        if ($wantsJson) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit();
        }

        header("Location: ../pages/login.php");
        exit();
    }
    if (!is_admin()) {
        http_response_code(403);
        die("Access Denied: Admin privileges required.");
    }
}
?>