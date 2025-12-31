<?php
// Module_Product_Ecosystem/api/Toggle_Favorite.php

// --- 开启错误提示，方便调试 ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 🔥🔥🔥 关键修改：根据你的截图，config 文件夹就在 api 目录下 🔥🔥🔥
require_once __DIR__ . '/config/treasurego_db_config.php';

session_start();
header('Content-Type: application/json');

try {
    // 1. 连接数据库
    $conn = getDatabaseConnection();
    if (!$conn) throw new Exception("Database connection failed");

    // 2. 模拟登录 (如果 Session 为空，强制使用 ID=1，防止报错)

    $user_id = $_SESSION['user_id'];

    // 3. 获取商品 ID
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if ($product_id <= 0) throw new Exception("Invalid Product ID");

    // 4. 业务逻辑：检查收藏 -> 添加或删除
    $checkSql = "SELECT Favorite_ID FROM Favorites WHERE User_ID = :uid AND Product_ID = :pid";
    $stmt = $conn->prepare($checkSql);
    $stmt->execute([':uid' => $user_id, ':pid' => $product_id]);

    if ($stmt->rowCount() > 0) {
        // --- 删除 ---
        $delSql = "DELETE FROM Favorites WHERE User_ID = :uid AND Product_ID = :pid";
        $conn->prepare($delSql)->execute([':uid' => $user_id, ':pid' => $product_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed']);
    } else {
        // --- 添加 ---
        // 先查当前价格
        $priceSql = "SELECT Product_Price FROM Product WHERE Product_ID = :pid";
        $pStmt = $conn->prepare($priceSql);
        $pStmt->execute([':pid' => $product_id]);
        $prod = $pStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) throw new Exception("Product not found in DB");

        $price = $prod['Product_Price'];

        $insSql = "INSERT INTO Favorites (User_ID, Product_ID, Favorites_Save_Price, Favorites_Create_Time) VALUES (:uid, :pid, :price, NOW())";
        $conn->prepare($insSql)->execute([':uid' => $user_id, ':pid' => $product_id, ':price' => $price]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added']);
    }

} catch (Exception $e) {
    // 捕获所有 PHP 报错并以 JSON 返回
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>