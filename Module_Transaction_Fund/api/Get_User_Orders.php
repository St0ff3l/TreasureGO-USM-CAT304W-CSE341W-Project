<?php
// api/Get_User_Orders.php

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => 'Please login first']);
    exit;
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'buying' => [], 'selling' => []];

try {
    $conn = getDatabaseConnection();

    // ======================================================
    // 1. 查询“我买的” (Buying)
    // ======================================================
    $sqlBuy = "SELECT 
                    o.Orders_Order_ID, 
                    o.Orders_Total_Amount, 
                    o.Orders_Status, 
                    MAX(o.Orders_Created_AT) AS Orders_Created_AT,
                    o.Orders_Seller_ID,
                    o.Orders_Buyer_ID,
                    MAX(o.Address_ID) AS Address_ID,
                    
                    /* 基本订单信息 */
                    MAX(s.Shipments_Shipped_Time) AS Orders_Shipped_At,
                    MAX(s.Shipments_Tracking_Number) AS Tracking_Number,
                    MAX(u.User_Username) AS Seller_Username,

                    /* 🔥 退款信息 (关联 Refund_Requests) */
                    MAX(rr.Refund_Status) AS Refund_Status,
                    MAX(rr.Refund_Type) AS Refund_Type,
                    MAX(rr.Refund_Amount) AS Refund_Amount,

                    /* 🔥 卖家退货地址 (关联 Address 表) */
                    MAX(sa.Address_Detail) AS Seller_Return_Address,

                    p.Product_ID, 
                    p.Product_Title,
                    p.Product_Description,
                    p.Product_Condition,
                    
                    /* 🔥🔥 修改点 1：动态判断配送方式 (Address_ID 为空即为面交) 🔥🔥 */
                    (CASE WHEN MAX(o.Address_ID) IS NULL THEN 'meetup' ELSE 'shipping' END) AS Delivery_Method,
                    
                    /* 🔥🔥 修改点 2：添加 Product_Location 供面交显示 🔥🔥 */
                    p.Product_Location,
                    
                    c.Category_Name,

                    (SELECT Image_URL FROM Product_Images WHERE Product_ID = p.Product_ID AND Image_is_primary = 1 LIMIT 1) AS Main_Image,
                    GROUP_CONCAT(DISTINCT pi.Image_URL SEPARATOR ',') AS All_Images

               FROM Orders o
               JOIN Product p ON o.Product_ID = p.Product_ID
               LEFT JOIN Categories c ON p.Category_ID = c.Category_ID
               LEFT JOIN Product_Images pi ON p.Product_ID = pi.Product_ID
               
               LEFT JOIN User u ON o.Orders_Seller_ID = u.User_ID

               LEFT JOIN Shipments s ON o.Orders_Order_ID = s.Order_ID AND s.Shipments_Type = 'forward'
               
               /* 关联退款表 */
               LEFT JOIN Refund_Requests rr ON o.Orders_Order_ID = rr.Order_ID

               /* 🔥🔥 修复点：使用正确的字段名 Address_User_ID 🔥🔥 */
               LEFT JOIN Address sa ON o.Orders_Seller_ID = sa.Address_User_ID AND sa.Address_Is_Default = 1
               
               WHERE o.Orders_Buyer_ID = :uid
               GROUP BY o.Orders_Order_ID
               ORDER BY Orders_Created_AT DESC";

    $stmtBuy = $conn->prepare($sqlBuy);
    $stmtBuy->execute([':uid' => $userId]);
    $response['buying'] = $stmtBuy->fetchAll(PDO::FETCH_ASSOC);

    // ======================================================
    // 2. 查询“我卖的” (Selling)
    // ======================================================
    $sqlSell = "SELECT 
                    o.Orders_Order_ID, 
                    o.Orders_Total_Amount, 
                    o.Orders_Status, 
                    MAX(o.Orders_Created_AT) AS Orders_Created_AT,
                    o.Orders_Seller_ID,
                    o.Orders_Buyer_ID,
                    MAX(o.Address_ID) AS Address_ID,

                    MAX(s.Shipments_Shipped_Time) AS Orders_Shipped_At,
                    MAX(s.Shipments_Tracking_Number) AS Tracking_Number,
                    MAX(u.User_Username) AS Buyer_Username,

                    /* 🔥 退款信息 */
                    MAX(rr.Refund_Status) AS Refund_Status,
                    MAX(rr.Refund_Type) AS Refund_Type,
                    MAX(rr.Refund_Amount) AS Refund_Amount,

                    /* 🔥 卖家(我)的默认地址 */
                    MAX(sa.Address_Detail) AS Seller_Return_Address,

                    p.Product_ID, 
                    p.Product_Title,
                    p.Product_Description,
                    p.Product_Condition,
                    
                    /* 🔥🔥 修改点 1：动态判断配送方式 🔥🔥 */
                    (CASE WHEN MAX(o.Address_ID) IS NULL THEN 'meetup' ELSE 'shipping' END) AS Delivery_Method,

                    /* 🔥🔥 修改点 2：添加 Product_Location 🔥🔥 */
                    p.Product_Location,

                    c.Category_Name,

                    (SELECT Image_URL FROM Product_Images WHERE Product_ID = p.Product_ID AND Image_is_primary = 1 LIMIT 1) AS Main_Image,
                    GROUP_CONCAT(DISTINCT pi.Image_URL SEPARATOR ',') AS All_Images

               FROM Orders o
               JOIN Product p ON o.Product_ID = p.Product_ID
               LEFT JOIN Categories c ON p.Category_ID = c.Category_ID
               LEFT JOIN Product_Images pi ON p.Product_ID = pi.Product_ID

               LEFT JOIN User u ON o.Orders_Buyer_ID = u.User_ID

               LEFT JOIN Shipments s ON o.Orders_Order_ID = s.Order_ID AND s.Shipments_Type = 'forward'

               LEFT JOIN Refund_Requests rr ON o.Orders_Order_ID = rr.Order_ID

               /* 🔥🔥 修复点：使用正确的字段名 Address_User_ID 🔥🔥 */
               LEFT JOIN Address sa ON o.Orders_Seller_ID = sa.Address_User_ID AND sa.Address_Is_Default = 1

               WHERE o.Orders_Seller_ID = :uid
               GROUP BY o.Orders_Order_ID
               ORDER BY Orders_Created_AT DESC";

    $stmtSell = $conn->prepare($sqlSell);
    $stmtSell->execute([':uid' => $userId]);
    $response['selling'] = $stmtSell->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;

} catch (Exception $e) {
    $response['msg'] = $e->getMessage();
}

echo json_encode($response);
?>