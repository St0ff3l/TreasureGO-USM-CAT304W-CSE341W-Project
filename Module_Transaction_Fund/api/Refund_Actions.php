<?php
// api/Refund_Actions.php

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$action = $input['action'] ?? '';
$orderId = $input['order_id'] ?? 0;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing Order ID']);
    exit;
}

$conn = getDatabaseConnection();

try {
    if ($action === 'seller_decision') {
        $decision = $input['decision'];
        $refundType = $input['refund_type'];

        // 验证卖家身份
        $stmt = $conn->prepare("SELECT Orders_Seller_ID FROM Orders WHERE Orders_Order_ID = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['Orders_Seller_ID'] != $userId) {
            throw new Exception("You are not the seller of this order.");
        }

        // --- 卖家拒绝 ---
        if ($decision === 'reject') {
            $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'rejected', Refund_Updated_At = NOW() WHERE Order_ID = ?")
                ->execute([$orderId]);
        }
        // --- 卖家同意 ---
        else if ($decision === 'approve') {

            // 情况 B1: 仅退款 (Refund Only) -> 立即打钱
            if ($refundType === 'refund_only') {

                $stmt = $conn->prepare("SELECT Refund_Amount, Buyer_ID FROM Refund_Requests WHERE Order_ID = ?");
                $stmt->execute([$orderId]);
                $refundData = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$refundData) throw new Exception("Refund request not found.");

                $amount = $refundData['Refund_Amount'];
                $buyerId = $refundData['Buyer_ID'];

                // 🔥 开启事务
                $conn->beginTransaction();

                try {
                    // ======================================================
                    // 🔥 修改点开始：不查 User 表，改查 Wallet_Logs 获取最新余额
                    // ======================================================

                    // 1. 查找该用户最后一条流水记录，获取当前余额
                    // 使用 FOR UPDATE 锁住记录，防止并发问题
                    $balanceStmt = $conn->prepare("
                        SELECT Balance_After 
                        FROM Wallet_Logs 
                        WHERE User_ID = ? 
                        ORDER BY Log_ID DESC 
                        LIMIT 1 
                        FOR UPDATE
                    ");
                    $balanceStmt->execute([$buyerId]);
                    $lastLog = $balanceStmt->fetch(PDO::FETCH_ASSOC);

                    // 如果没查到记录，说明是新用户或没钱，余额默认为 0
                    $currentBalance = $lastLog ? $lastLog['Balance_After'] : 0;

                    // 2. 计算新余额
                    $newBalance = $currentBalance + $amount;

                    // 3. 写入新的流水记录
                    $logSql = "INSERT INTO Wallet_Logs 
                               (User_ID, Amount, Balance_After, Description, Reference_Type, Reference_ID, Created_AT) 
                               VALUES (?, ?, ?, ?, ?, ?, NOW())";

                    $desc = "Refund for Order #$orderId (Refund Only)";

                    $conn->prepare($logSql)->execute([
                        $buyerId,
                        $amount,
                        $newBalance,    // 刚刚计算出的新余额
                        $desc,
                        'Order',
                        $orderId
                    ]);

                    // ======================================================
                    // 🔥 修改点结束
                    // ======================================================

                    // 4. 更新退款和订单状态
                    $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'completed', Refund_Completed_At = NOW() WHERE Order_ID = ?")->execute([$orderId]);
                    $conn->prepare("UPDATE Orders SET Orders_Status = 'cancelled' WHERE Orders_Order_ID = ?")->execute([$orderId]);

                    $conn->commit();

                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }

            }
            // 情况 B2: 退货退款 -> 只改状态
            else {
                $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'awaiting_return', Refund_Updated_At = NOW() WHERE Order_ID = ?")
                    ->execute([$orderId]);
            }
        }
        echo json_encode(['success' => true]);
    }
    // 其他 Action 保持不变...
    else if ($action === 'submit_return_tracking') {
        $input['tracking'] ? null : throw new Exception("Tracking required");
        $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'awaiting_confirm' WHERE Order_ID = ?")->execute([$orderId]);
        echo json_encode(['success' => true]);
    }
    else if ($action === 'confirm_return_handover') {
        $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'awaiting_confirm' WHERE Order_ID = ?")->execute([$orderId]);
        echo json_encode(['success' => true]);
    }
    else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>