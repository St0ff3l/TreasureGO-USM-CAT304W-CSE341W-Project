<?php
// api/Update_Tracking.php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

$orderId = $data['order_id'] ?? null;
$tracking = $data['tracking'] ?? null;

// Validate required input parameters
if (!$orderId || !$tracking) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID or tracking number']);
    exit;
}

try {
    $conn = getDatabaseConnection();

    // Begin transaction to ensure atomicity
    $conn->beginTransaction();

    // Verify current user is the seller of this order
    $checkSql = "SELECT Orders_Order_ID FROM Orders 
                 WHERE Orders_Order_ID = :oid AND Orders_Seller_ID = :uid";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([':oid' => $orderId, ':uid' => $userId]);

    if ($checkStmt->rowCount() === 0) {
        throw new Exception("Order not found or you are not the seller.");
    }

    // Update Orders table with shipped status and timestamp
    $updateOrderSql = "UPDATE Orders 
                       SET Orders_Status = 'shipped', 
                           Orders_Shipped_At = NOW() 
                       WHERE Orders_Order_ID = :oid";
    $updateStmt = $conn->prepare($updateOrderSql);
    $updateStmt->execute([':oid' => $orderId]);

    // Insert Shipments record with tracking number
    // Shipments_Courier_Name defaults to Standard Express
    $insertShipmentSql = "INSERT INTO Shipments (
                            Order_ID, 
                            Shipments_Tracking_Number, 
                            Shipments_Courier_Name, 
                            Shipments_Type, 
                            Shipments_Status, 
                            Shipments_Shipped_Time
                          ) VALUES (
                            :oid, 
                            :tracking, 
                            'Standard Express', 
                            'forward', 
                            'shipped', 
                            NOW()
                          )";

    $insertStmt = $conn->prepare($insertShipmentSql);
    $insertStmt->execute([
        ':oid' => $orderId,
        ':tracking' => $tracking
    ]);

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Order shipped successfully']);

} catch (Exception $e) {
    // Rollback transaction if error occurs
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>