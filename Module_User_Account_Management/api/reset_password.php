<?php
// api/reset_password.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');
$newPassword = $input['new_password'] ?? '';

if (empty($email) || empty($code) || empty($newPassword)) {
    jsonResponse(false, 'All fields are required.');
}

try {
    $pdo = getDBConnection();

    // 1. 验证码校验 (逻辑同 verify_code.php，但这里不仅校验，还要重置密码)
    $stmt = $pdo->prepare("SELECT * FROM Email_Verification
                           WHERE EV_Email = ? AND EV_Purpose = 'reset_password' AND EV_Is_Used = 0
                           AND EV_Expires_At > NOW()
                           ORDER BY EV_Created_At DESC LIMIT 1");
    $stmt->execute([$email]);
    $record = $stmt->fetch();

    if (!$record) {
        jsonResponse(false, 'Invalid or expired code.');
    }

    if ($record['EV_Attempt_Count'] >= 5) {
        $pdo->prepare("UPDATE Email_Verification SET EV_Is_Used = 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);
        jsonResponse(false, 'Too many attempts. Request new code.');
    }

    if (password_verify($code, $record['EV_Code'])) {
        // ✅ 验证成功，重置密码
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE User SET User_Password_Hash = ? WHERE User_ID = ?")->execute([$newHash, $record['User_ID']]);

        // 标记验证码已用
        $pdo->prepare("UPDATE Email_Verification SET EV_Is_Used = 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);

        jsonResponse(true, 'Password reset successful. Please login.');
    } else {
        $pdo->prepare("UPDATE Email_Verification SET EV_Attempt_Count = EV_Attempt_Count + 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);
        jsonResponse(false, 'Invalid code.');
    }

} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
?>