<?php
// File path: Module_User_Account_Management/api/Get_User_Reviews.php

// 1. Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // 2. Start Session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 3. --- Specify unique, correct config file path ---
    // Go up two levels from current file (Get_User_Reviews.php) to Product module to find config
    $configPath = __DIR__ . '/../../Module_Product_Ecosystem/api/config/treasurego_db_config.php';

    if (!file_exists($configPath)) {
        throw new Exception("Database config file not found. Please confirm file exists at: " . $configPath);
    }

    require_once $configPath;

    // 4. Check if function exists again (for debugging)
    if (!function_exists('getDatabaseConnection')) {
        // If still not found, loaded file content is incorrect, possibly empty file
        throw new Exception("Config file loaded, but getDatabaseConnection() function not found. Please check if file content is complete.");
    }

    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed (PDO returned empty).");
    }

    // 5. Check Login
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        exit;
    }

    $current_user_id = $_SESSION['user_id'];

    // 6. Get user rating data (from User table)
    $sqlUser = "SELECT User_Average_Rating, User_Review_Count FROM User WHERE User_ID = ?";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([$current_user_id]);
    $userStats = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userStats) {
        $userStats = ['User_Average_Rating' => '0.0', 'User_Review_Count' => 0];
    }

    // 7. Get review list (join query from Review table)
    $sqlReviews = "
        SELECT 
            r.Reviews_ID,
            r.Reviews_Rating,
            r.Reviews_Comment,
            r.Reviews_Created_At,
            
            p.Product_ID,
            p.Product_Title,
            (SELECT Image_URL FROM Product_Images pi WHERE pi.Product_ID = p.Product_ID LIMIT 1) as Main_Image,
            
            u.User_Username as Reviewer_Name,
            u.User_Profile_Image as Reviewer_Avatar,
            
            -- Determine reviewer identity
            CASE 
                WHEN o.Orders_Buyer_ID = r.User_ID THEN 'Buyer'
                WHEN o.Orders_Seller_ID = r.User_ID THEN 'Seller'
                ELSE 'Unknown'
            END as Reviewer_Role

        FROM Review r
        LEFT JOIN Orders o ON r.Order_ID = o.Orders_Order_ID
        LEFT JOIN Product p ON o.Product_ID = p.Product_ID
        LEFT JOIN User u ON r.User_ID = u.User_ID
        
        WHERE r.Target_User_ID = ? 
        ORDER BY r.Reviews_Created_At DESC
    ";

    $stmtReviews = $pdo->prepare($sqlReviews);
    $stmtReviews->execute([$current_user_id]);
    $reviewsList = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

    // 8. Return final data
    echo json_encode([
        'success' => true,
        'user_stats' => $userStats,
        'data' => $reviewsList
    ]);

} catch (Throwable $e) {
    // Catch all errors and return JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'API Error: ' . $e->getMessage()
    ]);
}
?>