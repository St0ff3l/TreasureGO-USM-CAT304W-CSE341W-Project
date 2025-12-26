<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

$response = ['success' => false, 'msg' => 'Unknown error'];

// 1. 获取当前用户
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => 'User not logged in']);
    exit;
}
$userId = $_SESSION['user_id'];

// 2. 获取前端传来的支付信息
$input = json_decode(file_get_contents('php://input'), true);
$price = isset($input['price']) ? floatval($input['price']) : 0.00;
$planName = isset($input['plan']) ? $input['plan'] : 'Membership';
$cycle = isset($input['cycle']) ? $input['cycle'] : 'monthly';

if ($price <= 0) {
    echo json_encode(['success' => false, 'msg' => 'Invalid price']);
    exit;
}

try {
    $conn = getDatabaseConnection();

    // === 开启事务 (Transaction) ===
    $conn->beginTransaction();

    // 3. 再次查询最新余额 (后端校验)
    $sqlCheck = "SELECT Balance_After FROM Wallet_Logs WHERE User_ID = :uid ORDER BY Log_ID DESC LIMIT 1 FOR UPDATE";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bindParam(':uid', $userId, PDO::PARAM_INT);
    $stmtCheck->execute();
    $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    $currentBalance = $result ? (float)$result['Balance_After'] : 0.00;

    // 4. 判断余额是否足够
    if ($currentBalance < $price) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'msg' => 'Insufficient balance (Server Check)']);
        exit;
    }

    // 5. 计算新余额
    $newBalance = $currentBalance - $price;

    // 6. 插入扣款记录 (Wallet_Logs)
    $sqlInsert = "INSERT INTO Wallet_Logs 
                  (User_ID, Amount, Balance_After, Description, Reference_Type, Created_AT) 
                  VALUES 
                  (:uid, :amount, :balance_after, :desc, 'membership_pay', NOW())";

    $negativeAmount = -1 * $price;
    $description = "Purchase " . $planName . " (" . ucfirst($cycle) . ")";

    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bindParam(':uid', $userId, PDO::PARAM_INT);
    $stmtInsert->bindParam(':amount', $negativeAmount);
    $stmtInsert->bindParam(':balance_after', $newBalance);
    $stmtInsert->bindParam(':desc', $description);
    $stmtInsert->execute();

    // =========================================================
    // 7. 核心修改逻辑
    // =========================================================

    // A. 动态获取 Plan_ID 和 Duration (保持不变)
    $sqlGetPlan = "SELECT Plan_ID, Membership_Duration_Days 
                   FROM Membership_Plans 
                   WHERE Membership_Tier = :tier 
                   AND Membership_Price = :price 
                   LIMIT 1";

    $stmtGetPlan = $conn->prepare($sqlGetPlan);
    $stmtGetPlan->bindParam(':tier', $planName);
    $stmtGetPlan->bindParam(':price', $price);
    $stmtGetPlan->execute();
    $planRow = $stmtGetPlan->fetch(PDO::FETCH_ASSOC);

    if (!$planRow) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'msg' => 'Invalid Plan or Price mismatch']);
        exit;
    }

    $planId = $planRow['Plan_ID'];
    $durationDays = intval($planRow['Membership_Duration_Days']);

    // --- B. 计算叠加时间 (已修改：按等级叠加) ---

    // 🔥 修改点：不再查 Plan_ID，而是查 Membership_Tier
    // 这样 30天的VIP 和 90天的VIP 会被视为同一种会员，时间可以叠加
    $sqlLastDate = "SELECT m.Memberships_End_Date 
                    FROM Memberships m
                    JOIN Membership_Plans p ON m.Plan_ID = p.Plan_ID
                    WHERE m.User_ID = :uid 
                    AND p.Membership_Tier = :tierName 
                    ORDER BY m.Memberships_End_Date DESC 
                    LIMIT 1";

    $stmtLast = $conn->prepare($sqlLastDate);
    $stmtLast->bindParam(':uid', $userId, PDO::PARAM_INT);
    $stmtLast->bindParam(':tierName', $planName); // 传入 'VIP'
    $stmtLast->execute();
    $lastRow = $stmtLast->fetch(PDO::FETCH_ASSOC);

    $now = new DateTime();
    $startDateObj = clone $now;

    // 2. 续费逻辑
    if ($lastRow && !empty($lastRow['Memberships_End_Date'])) {
        $lastEndDateObj = new DateTime($lastRow['Memberships_End_Date']);

        // 如果旧的结束时间比现在还晚，说明还没过期，直接续在后面
        if ($lastEndDateObj > $now) {
            $startDateObj = $lastEndDateObj;
        }
    }

    // 3. 计算新的结束时间 (使用数据库天数)
    $endDateObj = clone $startDateObj;
    $endDateObj->modify("+$durationDays days");

    // 格式化 Tier
    $tierEnum = ucfirst($cycle);

    // 转换为字符串用于 SQL
    $startDateStr = $startDateObj->format('Y-m-d H:i:s');
    $endDateStr = $endDateObj->format('Y-m-d H:i:s');

    // C. 插入 Memberships
    $sqlMember = "INSERT INTO Memberships 
                  (User_ID, Plan_ID, Memberships_Start_Date, Memberships_End_Date, Memberships_Tier) 
                  VALUES 
                  (:uid, :pid, :start_date, :end_date, :tier)";

    $stmtMember = $conn->prepare($sqlMember);
    $stmtMember->bindParam(':uid', $userId, PDO::PARAM_INT);
    $stmtMember->bindParam(':pid', $planId, PDO::PARAM_INT);
    $stmtMember->bindParam(':start_date', $startDateStr);
    $stmtMember->bindParam(':end_date', $endDateStr);
    $stmtMember->bindParam(':tier', $tierEnum);
    $stmtMember->execute();

    // =========================================================

    // === 提交事务 ===
    $conn->commit();

    $response['success'] = true;
    $response['msg'] = 'Payment successful';

} catch (Exception $e) {
    if (isset($conn)) { $conn->rollBack(); }
    $response['msg'] = 'Database Error: ' . $e->getMessage();
}

echo json_encode($response);
?>