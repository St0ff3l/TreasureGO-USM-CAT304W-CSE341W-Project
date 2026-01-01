<?php
// File path: Module_Product_Ecosystem/api/Get_Seller_Product_Detail.php

require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

header('Content-Type: application/json');

try {
    // 1. Must be logged in
    $current_user_id = $_SESSION['user_id'] ?? $_SESSION['User_ID'] ?? null;
    if (!$current_user_id) {
        throw new Exception("Not logged in");
    }

    $product_id = $_GET['product_id'] ?? null;
    if (!$product_id) {
        throw new Exception("Product ID required");
    }

    $pdo = getDatabaseConnection();

    // 2. Key query logic
    // Removed the Product_Status = 'Active' restriction here
    // But added User_ID = ? restriction to ensure only the publisher can view it
    $sql = "SELECT 
                p.*,
                /* Get all images, separated by commas */
                (SELECT GROUP_CONCAT(Image_URL ORDER BY Image_is_primary DESC, Image_ID ASC) 
                 FROM Product_Images pi 
                 WHERE pi.Product_ID = p.Product_ID) as All_Images,
                /* Get main image separately */
                (SELECT Image_URL FROM Product_Images pi2 
                 WHERE pi2.Product_ID = p.Product_ID 
                 ORDER BY Image_is_primary DESC, Image_ID ASC LIMIT 1) as Main_Image
            FROM Product p
            WHERE p.Product_ID = ? AND p.User_ID = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id, $current_user_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // If not found, either ID is incorrect or this product does not belong to you
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied.']);
    } else {
        // Successfully return data
        echo json_encode(['success' => true, 'data' => [$product]]);
        // Note: Wrapped in a [] array here to allow for compatibility with your frontend processing logic
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>