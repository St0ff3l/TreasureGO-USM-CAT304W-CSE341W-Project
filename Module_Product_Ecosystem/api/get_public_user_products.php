<?php
// File path: Module_Product_Ecosystem/api/get_public_user_products.php

// 1. Include database configuration
// Ensure the path correctly points to your configuration file
require_once __DIR__ . '/config/treasurego_db_config.php';

// 2. Set response headers: JSON format + Allow CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // 3. Get user_id passed from frontend
    // Example: get_public_user_products.php?user_id=100000005
    $target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    // If no ID is passed or ID is invalid, return an empty array
    if ($target_user_id <= 0) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 4. Build SQL query
    // Use AS aliases to adapt to frontend JS (Name, Price, Status, Image_Url)
    $sql = "SELECT 
                p.Product_ID, 
                p.Product_Title as Name,            /* Convert to Name for frontend */
                p.Product_Price as Price,           /* Convert to Price for frontend */
                p.Product_Status as Status,         /* Convert to Status for frontend */
                p.Product_Created_Time as Created_At,
                p.Product_Condition,
                p.Product_Location,
                p.Delivery_Method,
                
                /* --- Image Retrieval Logic --- */
                /* Assuming you have a Product_Images table. If not, delete this subquery and use null or a default image */
                (SELECT Image_URL 
                 FROM Product_Images pi 
                 WHERE pi.Product_ID = p.Product_ID 
                 ORDER BY pi.Image_is_primary DESC, pi.Image_ID ASC 
                 LIMIT 1) as Image_Url

            FROM Product p
            WHERE p.User_ID = ? 
            
            /* --- Key Filtering Logic --- */
            /* 1. If product is 'Active' (for sale), it must be 'approved' to be displayed */
            /* 2. If product is 'Sold', display directly (usually sold items do not need review status check again) */
            AND (
                (p.Product_Status = 'Active' AND p.Product_Review_Status = 'approved')
                OR 
                (p.Product_Status = 'Sold')
            )
            
            ORDER BY p.Product_Created_Time DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$target_user_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Return JSON result
    echo json_encode([
        'status' => 'success',
        'data' => $products
    ]);

} catch (Exception $e) {
    // Error handling
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>