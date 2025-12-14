<?php
// includes/utils.php

// 统一 JSON 响应
function jsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// 生成 6 位数字验证码
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// 获取请求 JSON 数据
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
?>