<?php
// Module_Product_Ecosystem/api/Get_User_Favorites.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 引用路径 (根据你之前的成功反馈，config 在同级目录)
require_once 'config/treasurego_db_config.php';

session_start();
header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    if (!$conn) throw new Exception("DB Connection failed");


    $user_id = $_SESSION['user_id'];

    // 🔥 SQL 修正：使用正确的 Image_URL 字段 🔥
    // 逻辑：子查询会优先找 Image_is_primary=1 的图，找不到就找最新上传的
    $sql = "
        SELECT 
            F.Favorite_ID, 
            F.Favorites_Save_Price,
            P.Product_ID, 
            P.Product_Title, 
            P.Product_Price,
            P.Product_Condition,
            (
                SELECT Image_URL 
                FROM Product_Images 
                WHERE Product_ID = P.Product_ID 
                ORDER BY Image_is_primary DESC, Image_ID ASC 
                LIMIT 1
            ) as Main_Image
        FROM Favorites F
        JOIN Product P ON F.Product_ID = P.Product_ID
        WHERE F.User_ID = :uid
        ORDER BY F.Favorites_Create_Time DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $user_id]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $favorites]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>