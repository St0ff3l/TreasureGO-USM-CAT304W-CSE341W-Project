<?php
// Module_Product_Ecosystem/api/Audit_Product.php

// 1. Start Session to get Admin ID
session_start();

header('Content-Type: application/json');

// Include database configuration file
require_once __DIR__ . '/config/treasurego_db_config.php';

// Get POST JSON data
$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? null;
$action = $input['action'] ?? null; // 'approve' or 'reject'
$reason = $input['reason'] ?? null; // Rejection reason

// 2. Get Admin ID (Assuming session['user_id'] was set during login)
// If not logged in, set to 0 or error out. Setting default value here to prevent errors, ensure login in production.
$adminId = $_SESSION['user_id'] ?? 0;

if (!$productId || !$action) {
    echo json_encode(['success' => false, 'msg' => 'Missing parameters']);
    exit;
}

if ($adminId == 0) {
    // Optional: If Admin ID is not obtained, operation can be blocked
    // echo json_encode(['success' => false, 'msg' => 'Unauthorized']); exit;
}

try {
    // Start transaction (Ensure operations on both tables succeed simultaneously)
    $conn->beginTransaction();

    $newStatus = '';
    $newListingStatus = '';
    $reviewResult = ''; // Corresponds to the Result field in Product_Admin_Review table
    $comment = null;

    if ($action === 'approve') {
        $newStatus = 'approved';      // Product table status
        $newListingStatus = 'Active';
        $reviewResult = 'Approved';   // Review table status (Matches your table structure Enum/Varchar)
        $comment = 'Approved by Admin';
    } elseif ($action === 'reject') {
        $newStatus = 'rejected';
        $newListingStatus = 'Inactive';
        $reviewResult = 'Rejected';
        $comment = $reason;
    } else {
        throw new Exception("Invalid action type");
    }

    // --- Step 1: Update Product main table ---
    // Update status so frontend knows the product has been processed
    $sqlProduct = "UPDATE Product 
                   SET Product_Review_Status = ?,
                       Product_Status = ?,
                       Product_Review_Comment = ? 
                   WHERE Product_ID = ?";
    $stmtProduct = $conn->prepare($sqlProduct);
    if (!$stmtProduct->execute([$newStatus, $newListingStatus, $comment, $productId])) {
        throw new Exception("Failed to update Product table");
    }

    // --- Step 2: Insert into Product_Admin_Review audit log table ---
    // Note: Using NOW() to record current database time
    $sqlReview = "INSERT INTO Product_Admin_Review 
                  (Admin_Review_Result, Admin_Review_Comment, Admin_Review_Time, Product_ID, Admin_ID) 
                  VALUES (?, ?, NOW(), ?, ?)";
    $stmtReview = $conn->prepare($sqlReview);
    // Bind parameters: Result, Comment, ProductID, AdminID
    if (!$stmtReview->execute([$reviewResult, $comment, $productId, $adminId])) {
        throw new Exception("Failed to insert Audit Log");
    }

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Error occurred, rollback all operations
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Audit_Product Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'msg' => 'Database Error: ' . $e->getMessage()]);
}
?>