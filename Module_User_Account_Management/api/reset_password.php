<?php
// api/reset_password.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');
$newPassword = $input['new_password'] ?? '';

// 1. 基础校验 / Basic Validation
if (empty($email) || empty($code) || empty($newPassword)) {
    jsonResponse(false, 'All fields are required.');
}

if (strlen($newPassword) < 6) {
    jsonResponse(false, 'Password must be at least 6 characters.');
}

try {
    $pdo = getDBConnection();

    // 2. 查找有效的验证码记录 / Find valid verification record
    // 条件：邮箱匹配 + 用途是reset + 未使用
    $stmt = $pdo->prepare("SELECT * FROM Email_Verification 
                           WHERE EV_Email = ? AND EV_Purpose = 'reset_password' AND EV_Is_Used = 0 
                           ORDER BY EV_Created_At DESC LIMIT 1");
    $stmt->execute([$email]);
    $record = $stmt->fetch();

    if (!$record) {
        jsonResponse(false, 'Invalid or expired verification code.');
    }

    // 3. 检查过期时间 / Check Expiry
    if (strtotime($record['EV_Expires_At']) < time()) {
        jsonResponse(false, 'Verification code has expired. Please request a new one.');
    }

    // 4. 检查尝试次数（防止暴力破解） / Check Attempt Count
    if ($record['EV_Attempt_Count'] >= 5) {
        // 超过次数，作废此码
        $pdo->prepare("UPDATE Email_Verification SET EV_Is_Used = 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);
        jsonResponse(false, 'Too many failed attempts. This code is now invalid.');
    }

    // 5. 验证 Code (数据库存的是 Hash) / Verify Code
    if (password_verify($code, $record['EV_Code'])) {
        // ✅ 验证成功

        // A. 更新用户密码 / Update User Password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateUser = $pdo->prepare("UPDATE User SET User_Password_Hash = ? WHERE User_ID = ?");
        $updateUser->execute([$newHash, $record['User_ID']]);

        // B. 标记验证码已使用 / Mark code as used
        $updateEV = $pdo->prepare("UPDATE Email_Verification SET EV_Is_Used = 1 WHERE EV_ID = ?");
        $updateEV->execute([$record['EV_ID']]);

        // C. (可选) 使其他该用户的 reset code 失效，增强安全性
        // Optional: Invalidate other reset codes for this user

        jsonResponse(true, 'Password has been reset successfully. Please login.');

    } else {
        // ❌ 验证失败

        // 增加尝试次数 / Increment attempt count
        $pdo->prepare("UPDATE Email_Verification SET EV_Attempt_Count = EV_Attempt_Count + 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);
        jsonResponse(false, 'Incorrect verification code.');
    }

} catch (Exception $e) {
    error_log('Reset Password Error: ' . $e->getMessage());
    jsonResponse(false, 'System error occurred. Please try again.');
}
?>