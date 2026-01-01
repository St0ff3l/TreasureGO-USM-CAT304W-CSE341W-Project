<?php
// Module_Product_Ecosystem/api/Get_User_Favorites.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include path (based on your previous successful feedback, config is in the same directory)
require_once __DIR__ . '/config/treasurego_db_config.php';

session_start();
header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    if (!$conn) throw new Exception("DB Connection failed");


    $user_id = $_SESSION['user_id'];

    // SQL Fix: Use the correct Image_URL field
    // Logic: Subquery prioritizes finding the image with Image_is_primary=1; if not found, it finds the latest uploaded one
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