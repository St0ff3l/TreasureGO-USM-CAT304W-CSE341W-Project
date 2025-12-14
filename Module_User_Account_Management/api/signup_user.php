<?php
// api/signup_user.php
// ✅ 防崩溃调试版：能捕捉 Fatal Error 并显示给前端

// 1. 设置错误处理，将所有 PHP 报错转化为 JSON 返回
error_reporting(E_ALL);
ini_set('display_errors', 0); // 关掉默认输出，防止破坏 JSON

function fatal_handler() {
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'PHP Fatal Error: ' . $error['message'] . ' on line ' . $error['line']]);
        exit;
    }
}
register_shutdown_function("fatal_handler");

header('Content-Type: application/json');

require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';
require_once '../includes/sendgrid_mailer.php';

try {
    $input = getJsonInput();
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        jsonResponse(false, 'All fields are required.');
    }

    $pdo = getDBConnection();

    // 1. 检查邮箱
    $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Email already registered.');
    }

    // 2. 创建用户
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $sql = "INSERT INTO User (User_Username, User_Email, User_Password_Hash, User_Role, User_Status, User_Email_Verified, User_Created_At)
            VALUES (?, ?, ?, 'user', 'pending', 0, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $passwordHash]);
    $userId = $pdo->lastInsertId();

    // 3. 生成验证码
    $code = generateVerificationCode();
    $codeHash = password_hash($code, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $sqlEV = "INSERT INTO Email_Verification (User_ID, EV_Email, EV_Code, EV_Purpose, EV_Expires_At) VALUES (?, ?, ?, 'signup', ?)";
    $stmtEV = $pdo->prepare($sqlEV);
    $stmtEV->execute([$userId, $email, $codeHash, $expiresAt]);

    // 4. 发送邮件
    // ⚠️ 这里是最容易崩溃的地方
    if (!function_exists('curl_init')) {
        throw new Exception("PHP cURL extension is NOT enabled. Please enable it in php.ini");
    }

    $subject = "Verify Your Email - TreasureGo";
    $body = "<h2>Welcome to TreasureGo!</h2><p>Your verification code is: <b style='font-size: 24px;'>$code</b></p>";

    $emailSent = sendEmail($email, $subject, $body);

    if ($emailSent) {
        jsonResponse(true, 'Signup successful!', ['next_url' => "verify_email.php?email=" . urlencode($email) . "&purpose=signup"]);
    } else {
        // 如果邮件失败，但没有崩溃，会走这里
        jsonResponse(true, 'Account created, but email failed. Check logs.', ['next_url' => "verify_email.php?email=" . urlencode($email) . "&purpose=signup"]);
    }

} catch (Exception $e) {
    jsonResponse(false, 'System error: ' . $e->getMessage());
}
?>