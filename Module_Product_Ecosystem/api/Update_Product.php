<?php
// File path: Module_Product_Ecosystem/api/Update_Product.php

require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 1. Security Check: Login required
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login first.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

// 2. Get JSON data sent from frontend
$input = json_decode(file_get_contents('php://input'), true);

$product_id = isset($input['product_id']) ? intval($input['product_id']) : 0;
$action = isset($input['action']) ? $input['action'] : ''; // 'update_price', 'toggle_status', 'delete'
$value = isset($input['value']) ? $input['value'] : null;  // New price (if updating price)

if ($product_id <= 0 || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // 3. Key Permission Check: Verify this product belongs to the current logged-in user
    $checkSql = "SELECT User_ID, Product_Status FROM Product WHERE Product_ID = ?";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    if ($product['User_ID'] != $current_user_id) {
        echo json_encode(['success' => false, 'message' => 'Access Denied: You do not own this product.']);
        exit;
    }

    // 4. Execute logic based on action
    if ($action === 'update_price') {
        // --- Update Price ---
        if (!is_numeric($value)) throw new Exception("Invalid price format.");

        $updateSql = "UPDATE Product SET Product_Price = ? WHERE Product_ID = ?";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([$value, $product_id]);

        echo json_encode(['success' => true, 'message' => 'Price updated successfully.']);

    } elseif ($action === 'toggle_status') {
        // --- Toggle Status (List/Unlist) ---
        $newStatus = ($product['Product_Status'] === 'Active') ? 'Unlisted' : 'Active';

        $updateSql = "UPDATE Product SET Product_Status = ? WHERE Product_ID = ?";
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([$newStatus, $product_id]);

        echo json_encode(['success' => true, 'new_status' => $newStatus, 'message' => 'Status changed to ' . $newStatus]);

    } elseif ($action === 'delete') {
        // --- Delete Product (With transaction and foreign key handling) ---
        try {
            $pdo->beginTransaction();

            // 1. Delete image records first
            $delImg = "DELETE FROM Product_Images WHERE Product_ID = ?";
            $pdo->prepare($delImg)->execute([$product_id]);

            // 2. Delete review records (Critical for foreign key constraints)
            $delReview = "DELETE FROM Product_Admin_Review WHERE Product_ID = ?";
            $pdo->prepare($delReview)->execute([$product_id]);

            // 3. Finally delete the product itself
            $delProd = "DELETE FROM Product WHERE Product_ID = ?";
            $stmt = $pdo->prepare($delProd);
            $stmt->execute([$product_id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Product and related records deleted successfully.']);

        } catch (Exception $e) {
            // If error occurs, rollback all delete operations
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>