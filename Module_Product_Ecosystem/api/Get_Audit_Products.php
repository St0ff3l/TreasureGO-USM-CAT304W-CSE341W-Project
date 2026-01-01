<?php
// Module_Product_Ecosystem/api/Get_Audit_Products.php

header('Content-Type: application/json');
require_once __DIR__ . '/config/treasurego_db_config.php';

$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';

try {
    $sql = "SELECT 
                p.Product_ID, 
                p.Product_Title, 
                p.Product_Description, 
                p.Product_Price, 
                p.Product_Created_Time, 
                p.Product_Review_Status,
                p.Product_Condition,
                p.Product_Location,
                p.Product_Review_Comment,
                u.User_Username AS Seller_Name,
                c.Category_Name,
                
                -- ✅ Key: Get all images for the product, concatenated by comma
                (SELECT GROUP_CONCAT(Image_URL SEPARATOR ',') 
                 FROM Product_Images pi 
                 WHERE pi.Product_ID = p.Product_ID) as All_Images,
                 
                par.Admin_Review_Result,
                par.Admin_Review_Comment as Log_Comment,
                par.Admin_Review_Time as Log_Time,
                adminUser.User_Username as Admin_Name

            FROM Product p
            LEFT JOIN User u ON p.User_ID = u.User_ID
            LEFT JOIN Categories c ON p.Category_ID = c.Category_ID
            LEFT JOIN Product_Admin_Review par ON par.Product_Review_ID = (
                SELECT Product_Review_ID FROM Product_Admin_Review 
                WHERE Product_ID = p.Product_ID 
                ORDER BY Admin_Review_Time DESC LIMIT 1
            )
            LEFT JOIN User adminUser ON par.Admin_ID = adminUser.User_ID";

    if ($statusFilter === 'pending') {
        $sql .= " WHERE p.Product_Review_Status = 'pending' ORDER BY p.Product_Created_Time DESC";
    } else {
        $sql .= " WHERE p.Product_Review_Status IN ('approved', 'rejected') ORDER BY par.Admin_Review_Time DESC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll();

    $data = [];
    foreach ($products as $row) {
        // ✅ Key: Pass the string fetched from the database directly to the image field
        $imageString = $row['All_Images'] ?? '';

        $displayDate = ($statusFilter === 'pending') ? $row['Product_Created_Time'] : ($row['Log_Time'] ?? $row['Product_Created_Time']);
        $displayComment = ($statusFilter === 'pending') ? null : ($row['Log_Comment'] ?? $row['Product_Review_Comment']);

        $data[] = [
            'id' => $row['Product_ID'],
            'title' => $row['Product_Title'],
            'category' => $row['Category_Name'] ?? 'Unknown',
            'price' => (float)$row['Product_Price'],
            'seller' => $row['Seller_Name'] ?? 'User#' . $row['User_ID'],
            'admin_auditor' => $row['Admin_Name'] ?? null,
            'status' => $row['Product_Review_Status'],
            'image' => $imageString, // Here it is "url1,url2"
            'description' => $row['Product_Description'],
            'date' => $displayDate,
            'condition' => $row['Product_Condition'],
            'location' => $row['Product_Location'],
            'comment' => $displayComment
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    error_log("SQL Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'msg' => 'SQL Error: ' . $e->getMessage()]);
}
?>