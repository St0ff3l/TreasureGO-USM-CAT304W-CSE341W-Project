<?php
// api/admin_get_users.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';

start_session_safe();

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

try {
    $pdo = getDBConnection();
    // Modify query to join and get the latest ban end time
    $sql = "SELECT 
                u.User_ID, 
                u.User_Username, 
                u.User_Email, 
                u.User_Role, 
                u.User_Status, 
                u.User_Created_At,
                (
                    SELECT Admin_Action_End_Date 
                    FROM Administrative_Action aa 
                    WHERE aa.Target_User_ID = u.User_ID 
                      AND aa.Admin_Action_Type = 'Ban' 
                    ORDER BY aa.Admin_Action_Start_Date DESC 
                    LIMIT 1
                ) as Ban_End_Date
            FROM User u
            ORDER BY u.User_Created_At DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $users]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>