<?php
// api/forgot_password_send.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';
require_once '../includes/sendgrid_mailer.php';

$input = getJsonInput();
$email = trim($input['email'] ?? '');

if (empty($email)) {
    jsonResponse(false, 'Email is required.');
}

try {
    $pdo = getDBConnection();

    // 1. 检查用户是否存在
    $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. 生成验证码
        $code = generateVerificationCode();
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 3. 存入数据库
        $sqlEV = "INSERT INTO Email_Verification (User_ID, EV_Email, EV_Code, EV_Purpose, EV_Expires_At) VALUES (?, ?, ?, 'reset_password', ?)";
        $stmtEV = $pdo->prepare($sqlEV);
        $stmtEV->execute([$user['User_ID'], $email, $codeHash, $expiresAt]);

        // 4. 发邮件
        $subject = "Reset Password Code - TreasureGo";
        $body = "<p>You requested a password reset.</p><p>Code: <b style='font-size: 24px;'>$code</b></p>";
        sendEmail($email, $subject, $body);
    }

    // 无论用户是否存在，都返回成功，防止枚举攻击
    jsonResponse(true, 'If an account exists, a code has been sent.', ['next_url' => "reset_password.php?email=" . urlencode($email)]);

} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
?>