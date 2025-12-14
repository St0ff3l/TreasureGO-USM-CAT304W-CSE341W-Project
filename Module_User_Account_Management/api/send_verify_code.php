<?php
// api/send_verify_code.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';
require_once '../includes/sendgrid_mailer.php';

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$purpose = $input['purpose'] ?? 'signup'; // signup 或 reset_password

if (empty($email)) {
    jsonResponse(false, 'Email is required.');
}

try {
    $pdo = getDBConnection();

    // 1. 确认用户存在
    $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // 假如是重置密码，为了安全不提示邮箱不存在
        jsonResponse(false, 'User not found.');
    }
    $userId = $user['User_ID'];

    // 2. 限频检查 (60秒内不能重发)
    $stmtCheck = $pdo->prepare("SELECT EV_Created_At FROM Email_Verification WHERE EV_Email = ? AND EV_Purpose = ? ORDER BY EV_Created_At DESC LIMIT 1");
    $stmtCheck->execute([$email, $purpose]);
    $lastEv = $stmtCheck->fetch();

    if ($lastEv && (time() - strtotime($lastEv['EV_Created_At']) < 60)) {
        jsonResponse(false, 'Please wait 60 seconds before resending.');
    }

    // 3. 生成新码
    $code = generateVerificationCode();
    $codeHash = password_hash($code, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // 4. 存入数据库
    // (可选：将旧的未使用的标记为已使用/失效，这里简化为只插新记录)
    $sqlEV = "INSERT INTO Email_Verification (User_ID, EV_Email, EV_Code, EV_Purpose, EV_Expires_At) VALUES (?, ?, ?, ?, ?)";
    $stmtEV = $pdo->prepare($sqlEV);
    $stmtEV->execute([$userId, $email, $codeHash, $purpose, $expiresAt]);

    // 5. 发送邮件
    $subject = ($purpose === 'signup') ? "Verify Your Email" : "Reset Your Password";
    $body = "<p>Your new verification code is: <b style='font-size: 24px;'>$code</b></p><p>Expires in 10 minutes.</p>";

    if (sendEmail($email, $subject, $body)) {
        jsonResponse(true, 'Code sent successfully.');
    } else {
        jsonResponse(false, 'Failed to send email.');
    }

} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
?>