<?php
// Module_Product_Ecosystem/api/Get_Audit_Products.php

header('Content-Type: application/json');

// 引入数据库配置文件
require_once __DIR__ . '/config/treasurego_db_config.php';

$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'pending';

try {
    // 构建 SQL 查询
    // 1. 修正了图片查询子句：使用 Image_URL 字段
    // 2. 增加了排序：ORDER BY Image_is_primary DESC (优先取主图)
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
                (SELECT Image_URL FROM Product_Images pi 
                 WHERE pi.Product_ID = p.Product_ID 
                 ORDER BY Image_is_primary DESC LIMIT 1) as Main_Image
            FROM Product p
            LEFT JOIN User u ON p.User_ID = u.User_ID
            LEFT JOIN Categories c ON p.Category_ID = c.Category_ID";

    // 筛选逻辑
    if ($statusFilter === 'pending') {
        $sql .= " WHERE p.Product_Review_Status = 'pending'";
    } else {
        $sql .= " WHERE p.Product_Review_Status IN ('approved', 'rejected')";
    }

    $sql .= " ORDER BY p.Product_Created_Time DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll();

    $data = [];
    foreach ($products as $row) {
        $data[] = [
            'id' => $row['Product_ID'],
            'title' => $row['Product_Title'],
            // 如果分类表联查失败，显示ID作为兜底
            'category' => $row['Category_Name'] ?? 'Unknown',
            'price' => (float)$row['Product_Price'],
            // 如果用户表联查失败，显示ID作为兜底
            'seller' => $row['Seller_Name'] ?? 'User#' . $row['User_ID'],
            'sellerAvatar' => null,
            'status' => $row['Product_Review_Status'],
            'image' => $row['Main_Image'], // 这里拿到的是 Image_URL
            'description' => $row['Product_Description'],
            'date' => $row['Product_Created_Time'],
            'condition' => $row['Product_Condition'],
            'location' => $row['Product_Location'],
            'comment' => $row['Product_Review_Comment']
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    // ⚠️ 调试模式：如果 User 表或 Categories 表字段不对，这里会报具体错误
    // 比如：Unknown column 'u.Username' in 'field list'
    error_log("SQL Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'msg' => 'SQL Error: ' . $e->getMessage()]);
}
?>