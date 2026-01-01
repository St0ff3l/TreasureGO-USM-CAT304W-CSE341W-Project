<?php
// api/Refund_Actions.php

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$action = $input['action'] ?? '';

// Get PDO connection object
$conn = getDatabaseConnection();

try {
    // Scenario 0: Get seller address list
    if ($action === 'get_seller_addresses') {
        $stmt = $conn->prepare("
            SELECT Address_ID, Address_Receiver_Name, Address_Phone_Number, Address_Detail, Address_Is_Default 
            FROM Address 
            WHERE Address_User_ID = ? 
            ORDER BY Address_Is_Default DESC, Address_Created_At DESC
        ");

        $stmt->execute([$userId]);

        $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'addresses' => $addresses]);
        exit;
    }

    // Operations below require Order ID
    $orderId = $input['order_id'] ?? 0;
    if (!$orderId) {
        throw new Exception("Missing Order ID");
    }

    // Scenario 1: Seller handles refund request (Approve/Reject)
    if ($action === 'seller_decision') {
        $decision = $input['decision'];
        $refundType = $input['refund_type'] ?? '';

        $rejectReasonCode = $input['reject_reason_code'] ?? null;
        $rejectReasonText = $input['reject_reason_text'] ?? null;
        $returnAddressSnapshot = $input['return_address'] ?? null;

        // Verify seller identity
        $stmt = $conn->prepare("SELECT Orders_Seller_ID FROM Orders WHERE Orders_Order_ID = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['Orders_Seller_ID'] != $userId) {
            throw new Exception("You are not the seller of this order.");
        }

        // Seller rejects the refund request
        if ($decision === 'reject') {
            $stmtAttempt = $conn->prepare("SELECT COALESCE(Request_Attempt, 1) AS Request_Attempt FROM Refund_Requests WHERE Order_ID = ?");
            $stmtAttempt->execute([$orderId]);
            $row = $stmtAttempt->fetch(PDO::FETCH_ASSOC);
            $attempt = $row ? intval($row['Request_Attempt']) : 1;

            $newStatus = ($attempt >= 2) ? 'dispute_in_progress' : 'rejected';

            $sql = "UPDATE Refund_Requests
                    SET Refund_Status = ?,
                        Refund_Updated_At = NOW(),
                        Seller_Reject_Reason_Code = ?,
                        Seller_Reject_Reason_Text = ?
                    WHERE Order_ID = ?";
            $conn->prepare($sql)->execute([$newStatus, $rejectReasonCode, $rejectReasonText, $orderId]);

            echo json_encode(['success' => true, 'refund_status' => $newStatus]);
            exit;
        }
        // Seller approves the refund request
        else if ($decision === 'approve') {
            // Case B1: Refund only
            if ($refundType === 'refund_only') {
                $stmt = $conn->prepare("SELECT Refund_Amount, Buyer_ID FROM Refund_Requests WHERE Order_ID = ?");
                $stmt->execute([$orderId]);
                $refundData = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$refundData) throw new Exception("Refund request not found.");
                $amount = $refundData['Refund_Amount'];
                $buyerId = $refundData['Buyer_ID'];

                $conn->beginTransaction();
                try {
                    // Get current balance
                    $balanceStmt = $conn->prepare("SELECT Balance_After FROM Wallet_Logs WHERE User_ID = ? ORDER BY Log_ID DESC LIMIT 1 FOR UPDATE");
                    $balanceStmt->execute([$buyerId]);
                    $lastLog = $balanceStmt->fetch(PDO::FETCH_ASSOC);
                    $currentBalance = $lastLog ? $lastLog['Balance_After'] : 0;

                    $newBalance = $currentBalance + $amount;

                    // Write wallet log
                    $logSql = "INSERT INTO Wallet_Logs (User_ID, Amount, Balance_After, Description, Reference_Type, Reference_ID, Created_AT) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    $conn->prepare($logSql)->execute([$buyerId, $amount, $newBalance, "Refund for Order #$orderId (Refund Only)", 'Order', $orderId]);

                    // Update refund status
                    $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'completed', Refund_Completed_At = NOW() WHERE Order_ID = ?")->execute([$orderId]);
                    $conn->prepare("UPDATE Orders SET Orders_Status = 'cancelled' WHERE Orders_Order_ID = ?")->execute([$orderId]);

                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollBack();
                    throw $e;
                }
            }
            // Case B2: Return and refund
            else {
                if (!$returnAddressSnapshot) {
                    throw new Exception("Return address is required for approval.");
                }

                $newStatus = 'awaiting_return';
                $updateSql = "UPDATE Refund_Requests 
                              SET Refund_Status = ?, 
                                  Refund_Updated_At = NOW(),
                                  Return_Address_Detail = ? 
                              WHERE Order_ID = ?";
                $conn->prepare($updateSql)->execute([$newStatus, $returnAddressSnapshot, $orderId]);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Scenario: Seller refuses received return
    else if ($action === 'seller_refuse_return_received') {
        $reasonCode = $input['reason_code'] ?? null;
        $reasonText = $input['reason_text'] ?? null;

        $stmt = $conn->prepare("SELECT Orders_Seller_ID FROM Orders WHERE Orders_Order_ID = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['Orders_Seller_ID'] != $userId) {
            throw new Exception("You are not the seller of this order.");
        }

        $sql = "UPDATE Refund_Requests
                SET Refund_Status = 'dispute_in_progress',
                    Refund_Updated_At = NOW(),
                    Seller_Refuse_Receive_Reason_Code = ?,
                    Seller_Refuse_Receive_Reason_Text = ?
                WHERE Order_ID = ?";
        $conn->prepare($sql)->execute([$reasonCode, $reasonText, $orderId]);

        echo json_encode(['success' => true, 'refund_status' => 'dispute_in_progress']);
        exit;
    }

    // Scenario 2: Seller confirms received return
    else if ($action === 'seller_confirm_return_received') {
        $stmt = $conn->prepare("SELECT Orders_Seller_ID FROM Orders WHERE Orders_Order_ID = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || $order['Orders_Seller_ID'] != $userId) {
            throw new Exception("You are not the seller of this order.");
        }

        $stmt = $conn->prepare("SELECT Refund_Amount, Buyer_ID FROM Refund_Requests WHERE Order_ID = ?");
        $stmt->execute([$orderId]);
        $refundData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$refundData) throw new Exception("Refund request not found.");
        $amount = $refundData['Refund_Amount'];
        $buyerId = $refundData['Buyer_ID'];

        $conn->beginTransaction();
        try {
            $balanceStmt = $conn->prepare("SELECT Balance_After FROM Wallet_Logs WHERE User_ID = ? ORDER BY Log_ID DESC LIMIT 1 FOR UPDATE");
            $balanceStmt->execute([$buyerId]);
            $lastLog = $balanceStmt->fetch(PDO::FETCH_ASSOC);
            $currentBalance = $lastLog ? $lastLog['Balance_After'] : 0;

            $newBalance = $currentBalance + $amount;

            $logSql = "INSERT INTO Wallet_Logs (User_ID, Amount, Balance_After, Description, Reference_Type, Reference_ID, Created_AT) VALUES (?, ?, ?, ?, 'Order', ?, NOW())";
            $conn->prepare($logSql)->execute([$buyerId, $amount, $newBalance, "Refund for Order #$orderId (Return Received)", $orderId]);

            $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'completed', Refund_Completed_At = NOW() WHERE Order_ID = ?")->execute([$orderId]);
            $conn->prepare("UPDATE Orders SET Orders_Status = 'cancelled' WHERE Orders_Order_ID = ?")->execute([$orderId]);

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    // Scenario 3: Buyer submits return tracking number
    else if ($action === 'submit_return_tracking') {
        $tracking = $input['tracking'] ?? '';
        if (!$tracking) throw new Exception("Tracking required");

        // Verify user is the buyer
        $checkStmt = $conn->prepare("SELECT Orders_Buyer_ID FROM Orders WHERE Orders_Order_ID = ?");
        $checkStmt->execute([$orderId]);
        $orderInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$orderInfo || $orderInfo['Orders_Buyer_ID'] != $userId) {
            throw new Exception("Unauthorized: You are not the buyer of this order.");
        }

        $stmt = $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'awaiting_confirm', Return_Tracking_Number = ? WHERE Order_ID = ?");
        $stmt->execute([$tracking, $orderId]);

        echo json_encode(['success' => true]);
    }

    // Scenario 4: Buyer confirms in-person return handover
    else if ($action === 'confirm_return_handover') {
        // Verify user is the buyer
        $checkStmt = $conn->prepare("SELECT Orders_Buyer_ID FROM Orders WHERE Orders_Order_ID = ?");
        $checkStmt->execute([$orderId]);
        $orderInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$orderInfo || $orderInfo['Orders_Buyer_ID'] != $userId) {
            throw new Exception("Unauthorized: You are not the buyer of this order.");
        }

        $conn->prepare("UPDATE Refund_Requests SET Refund_Status = 'awaiting_confirm' WHERE Order_ID = ?")->execute([$orderId]);
        echo json_encode(['success' => true]);
    }
    else {
        throw new Exception("Invalid action: " . htmlspecialchars($action));
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>