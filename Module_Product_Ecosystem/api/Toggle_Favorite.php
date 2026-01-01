<?php
// Module_Product_Ecosystem/api/Toggle_Favorite.php

// --- Enable error display for debugging ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/config/treasurego_db_config.php';

session_start();
header('Content-Type: application/json');

try {
    // 1. Connect to database
    $conn = getDatabaseConnection();
    if (!$conn) throw new Exception("Database connection failed");

    // 2. Get User ID from Session
    $user_id = $_SESSION['user_id'];

    // 3. Get Product ID
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if ($product_id <= 0) throw new Exception("Invalid Product ID");

    // 4. Business logic: Check favorites -> Add or Remove
    $checkSql = "SELECT Favorite_ID FROM Favorites WHERE User_ID = :uid AND Product_ID = :pid";
    $stmt = $conn->prepare($checkSql);
    $stmt->execute([':uid' => $user_id, ':pid' => $product_id]);

    if ($stmt->rowCount() > 0) {
        // --- Remove ---
        $delSql = "DELETE FROM Favorites WHERE User_ID = :uid AND Product_ID = :pid";
        $conn->prepare($delSql)->execute([':uid' => $user_id, ':pid' => $product_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed']);
    } else {
        // --- Add ---
        // First check current price
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
    // Catch all exceptions and return as JSON
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>