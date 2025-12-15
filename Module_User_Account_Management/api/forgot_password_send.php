<?php

// 1. 🔇 核心修复：关掉错误回显，防止 Warning 破坏 JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
// api/forgot_password_send.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';
require_once '../includes/sendgrid_mailer.php';

// 获取 JSON 输入 / Get JSON Input
$input = getJsonInput();
$email = trim($input['email'] ?? '');

if (empty($email)) {
    jsonResponse(false, 'Email is required.');
}

try {
    $pdo = getDBConnection();

    // 1. 检查用户是否存在 / Check if user exists
    $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. 生成 6 位验证码 / Generate 6-digit code
        $code = (string)rand(100000, 999999);

        // 3. 安全哈希存储验证码 / Securely hash the code for storage
        // 注意：数据库存储的是 Hash，防止数据库泄露导致验证码暴露
        $codeHash = password_hash($code, PASSWORD_BCRYPT);

        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 4. 写入 Email_Verification 表 / Insert into DB
        // 关键：明确 EV_Purpose 为 'reset_password'
        $sqlEV = "INSERT INTO Email_Verification (User_ID, EV_Email, EV_Code, EV_Purpose, EV_Expires_At) 
                  VALUES (?, ?, ?, 'reset_password', ?)";
        $stmtEV = $pdo->prepare($sqlEV);
        $stmtEV->execute([$user['User_ID'], $email, $codeHash, $expiresAt]);

        // 5. 发送真实邮件 / Send Real Email
        $subject = "Reset Password Code - TreasureGo";
        $body = "<p>You requested a password reset for your TreasureGo account.</p>
                 <p>Your verification code is: <b style='font-size: 24px; color: #4F46E5;'>$code</b></p>
                 <p>This code expires in 10 minutes.</p>";

        $emailSent = sendEmail($email, $subject, $body);

        $isSent = sendEmail($email, $subject, $body);

        if ($emailSent) {
            // 成功：只返回跳转 URL / Success: Only return redirect URL
            jsonResponse(true, 'Verification code sent to your email.', [
                'next_url' => "reset_password.php?email=" . urlencode($email)
            ]);
        } else {
            jsonResponse(false, 'Failed to send email. Please try again later.');
        }
    } else {
        // 用户不存在：为了防止枚举攻击，通常也返回成功，或者模糊提示
        // 但为了开发调试方便，这里暂用明确提示，上线前可改为 "If account exists..."
        jsonResponse(false, 'Email not found.');
    }

} catch (Exception $e) {
    // 记录日志，不要把系统错误直接暴露给用户 / Log error, don't expose system error to user
    error_log($e->getMessage());
    jsonResponse(false, 'An internal error occurred.');
}
?>