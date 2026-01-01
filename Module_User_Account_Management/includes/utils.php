<?php
// includes/utils.php

// Unified JSON response
function jsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Generate 6-digit numeric verification code
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Get request JSON data
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
?>