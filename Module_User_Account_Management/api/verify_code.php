<?php
// api/verify_code.php
// ✅ 修复版：解决验证码匹配不上/过期的问题

header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');
$purpose = $input['purpose'] ?? 'signup';

if (empty($email) || empty($code)) {
    jsonResponse(false, 'Missing email or code.');
}

try {
    $pdo = getDBConnection();

    // 1. 查找该邮箱最新的一条未使用的验证码
    // 不在 SQL 里判断过期，防止 SQL 时区问题
    $sql = "SELECT * FROM Email_Verification
            WHERE EV_Email = ? AND EV_Purpose = ? AND EV_Is_Used = 0
            ORDER BY EV_Created_At DESC LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email, $purpose]);
    $record = $stmt->fetch();

    if (!$record) {
        jsonResponse(false, 'No code found. Please click Resend.');
    }

    // 2. 检查错误次数
    if ($record['EV_Attempt_Count'] >= 5) {
        $pdo->prepare("UPDATE Email_Verification SET EV_Is_Used = 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);
        jsonResponse(false, 'Too many attempts. Code invalid.');
    }

    // 3. 🔥 在 PHP 里检查过期 (解决时区 Bug) 🔥
    $expireTime = strtotime($record['EV_Expires_At']);
    if (time() > $expireTime) {
        jsonResponse(false, 'Code expired. Please Resend.');
    }

    // 4. 验证哈希
    if (password_verify((string)$code, $record['EV_Code'])) {
        // ✅ 成功
        $pdo->prepare("UPDATE Email_Verification SET EV_Is_Used = 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);

        if ($purpose === 'signup') {
            $pdo->prepare("UPDATE User SET User_Email_Verified = 1, User_Status = 'active' WHERE User_ID = ?")->execute([$record['User_ID']]);
        }
        jsonResponse(true, 'Verification successful.');
    } else {
        // ❌ 失败
        $pdo->prepare("UPDATE Email_Verification SET EV_Attempt_Count = EV_Attempt_Count + 1 WHERE EV_ID = ?")->execute([$record['EV_ID']]);
        jsonResponse(false, 'Invalid code. Check your latest email.');
    }

} catch (Exception $e) {
    jsonResponse(false, 'System Error: ' . $e->getMessage());
}
?>