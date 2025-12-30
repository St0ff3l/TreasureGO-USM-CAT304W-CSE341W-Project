<?php
// api/Process_Payment.php

// 1. 基础设置
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

$response = ['success' => false, 'msg' => 'Unknown error'];

// 2. 验证登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => 'User not logged in']);
    exit;
}
$userId = $_SESSION['user_id'];

// 3. 获取前端传来的支付信息
$input = json_decode(file_get_contents('php://input'), true);
$price = isset($input['price']) ? floatval($input['price']) : 0.00;
$planName = isset($input['plan']) ? $input['plan'] : 'Membership'; // e.g. 'VIP'
$cycle = isset($input['cycle']) ? $input['cycle'] : 'monthly';     // e.g. 'monthly'
$pinCode = isset($input['payment_pin']) ? $input['payment_pin'] : '';

// 基础校验
if ($price <= 0) {
    echo json_encode(['success' => false, 'msg' => 'Invalid price']);
    exit;
}
if (empty($pinCode)) {
    echo json_encode(['success' => false, 'msg' => 'Payment PIN is required']);
    exit;
}

try {
    $conn = getDatabaseConnection();

    // =================================================================
    // 🔥 STEP A: 支付密码验证
    // =================================================================
    $stmtUser = $conn->prepare("SELECT User_Payment_PIN_Hash, User_PIN_Retry_Count, User_PIN_Locked_Until FROM User WHERE User_ID = :uid");
    $stmtUser->execute([':uid' => $userId]);
    $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userInfo) throw new Exception("User not found");

    if ($userInfo['User_PIN_Locked_Until'] && strtotime($userInfo['User_PIN_Locked_Until']) > time()) {
        $waitMinutes = ceil((strtotime($userInfo['User_PIN_Locked_Until']) - time()) / 60);
        throw new Exception("Wallet locked. Try again in $waitMinutes minutes.");
    }

    if (!password_verify($pinCode, $userInfo['User_Payment_PIN_Hash'])) {
        $newRetry = $userInfo['User_PIN_Retry_Count'] + 1;
        $lockUntil = null;
        $errorMsg = "Incorrect PIN. Attempts remaining: " . (5 - $newRetry);

        if ($newRetry >= 5) {
            $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $newRetry = 0;
            $errorMsg = "Too many failed attempts. Wallet locked for 15 minutes.";
        }
        $conn->prepare("UPDATE User SET User_PIN_Retry_Count = :retry, User_PIN_Locked_Until = :lock WHERE User_ID = :uid")
            ->execute([':retry' => $newRetry, ':lock' => $lockUntil, ':uid' => $userId]);

        throw new Exception($errorMsg);
    }

    if ($userInfo['User_PIN_Retry_Count'] > 0) {
        $conn->prepare("UPDATE User SET User_PIN_Retry_Count = 0 WHERE User_ID = :uid")->execute([':uid' => $userId]);
    }

    // =================================================================
    // 🔥 STEP B: 核心交易事务
    // =================================================================
    $conn->beginTransaction();

    // 1. 查询余额 (Wallet_Logs)
    $sqlCheck = "SELECT Balance_After FROM Wallet_Logs WHERE User_ID = :uid ORDER BY Log_ID DESC LIMIT 1 FOR UPDATE";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute([':uid' => $userId]);
    $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $currentBalance = $result ? (float)$result['Balance_After'] : 0.00;

    if ($currentBalance < $price) {
        throw new Exception("Insufficient balance");
    }

    // 2. 扣款
    $newBalance = $currentBalance - $price;
    $negativeAmount = -1 * $price;
    $desc = "Purchase " . $planName . " (" . ucfirst($cycle) . ")";

    $sqlInsert = "INSERT INTO Wallet_Logs (User_ID, Amount, Balance_After, Description, Reference_Type, Created_AT) 
                  VALUES (:uid, :amount, :balance_after, :desc, 'membership_pay', NOW())";
    $conn->prepare($sqlInsert)->execute([
        ':uid' => $userId,
        ':amount' => $negativeAmount,
        ':balance_after' => $newBalance,
        ':desc' => $desc
    ]);

    // =========================================================
    // 🔥 STEP C: 会员开通 (适配你的数据库结构)
    // =========================================================

    // 1. 获取 Plan_ID
    $stmtGetPlan = $conn->prepare("SELECT Plan_ID, Membership_Duration_Days FROM Membership_Plans WHERE Membership_Tier = :tier AND Membership_Price = :price LIMIT 1");
    $stmtGetPlan->execute([':tier' => $planName, ':price' => $price]);
    $planRow = $stmtGetPlan->fetch(PDO::FETCH_ASSOC);

    if (!$planRow) throw new Exception("Invalid Plan or Price mismatch");

    $planId = $planRow['Plan_ID'];
    $durationDays = intval($planRow['Membership_Duration_Days']);

    // 2. 计算叠加时间
    // 逻辑：如果用户当前已有该等级(VIP/SVIP)且未过期，则在原结束时间上续费
    $sqlLastDate = "SELECT m.Memberships_End_Date 
                    FROM Memberships m
                    JOIN Membership_Plans p ON m.Plan_ID = p.Plan_ID
                    WHERE m.User_ID = :uid AND p.Membership_Tier = :tierName 
                    ORDER BY m.Memberships_End_Date DESC LIMIT 1";
    $stmtLast = $conn->prepare($sqlLastDate);
    $stmtLast->execute([':uid' => $userId, ':tierName' => $planName]);
    $lastRow = $stmtLast->fetch(PDO::FETCH_ASSOC);

    $now = new DateTime();
    $startDateObj = clone $now;

    if ($lastRow && !empty($lastRow['Memberships_End_Date'])) {
        $lastEndDateObj = new DateTime($lastRow['Memberships_End_Date']);
        if ($lastEndDateObj > $now) {
            $startDateObj = $lastEndDateObj;
        }
    }

    $endDateObj = clone $startDateObj;
    $endDateObj->modify("+$durationDays days");

    // 3. 插入 Memberships 表
    // 🔥 关键修正：你的表里 Memberships_Tier 存的是周期 (Monthly/Quarterly/Yearly)
    // 我们将前端传来的 'monthly' 转换为 'Monthly' 存进去
    $cycleEnum = ucfirst($cycle); // "monthly" -> "Monthly"

    $sqlMember = "INSERT INTO Memberships 
                  (User_ID, Plan_ID, Memberships_Start_Date, Memberships_End_Date, Memberships_Tier) 
                  VALUES 
                  (:uid, :pid, :start, :end, :tier)";

    $conn->prepare($sqlMember)->execute([
        ':uid' => $userId,
        ':pid' => $planId,
        ':start' => $startDateObj->format('Y-m-d H:i:s'),
        ':end' => $endDateObj->format('Y-m-d H:i:s'),
        ':tier' => $cycleEnum // 存入周期
    ]);

    $conn->commit();
    $response['success'] = true;
    $response['msg'] = 'Payment successful';

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    $response['msg'] = $e->getMessage();
}

echo json_encode($response);
?>