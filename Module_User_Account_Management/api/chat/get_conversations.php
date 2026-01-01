<?php
header('Content-Type: application/json');
require_once '../../api/config/treasurego_db_config.php';
require_once '../../includes/auth.php';

start_session_safe();

if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$current_user_id = $_SESSION['user_id'];

try {
    $pdo = getDBConnection();

    // Get recent contact list and their last message
    // Only return product inquiries: Exclude customer service/ticket chats where Product_ID is NULL
    $sql = "
        SELECT 
            u.User_ID, 
            u.User_Username, 
            -- ❌ Before modification: u.User_Profile_image as User_Avatar_Url,
            -- ✅ After modification: Use original field name directly to ensure consistency with frontend JS reading
            u.User_Profile_Image, 
            
            p.Product_Title as Product_Name,
            
            -- Product image logic remains unchanged
            pi.Image_URL as Product_Image_Url,
            
            m.Product_ID,
            m.Message_Content,
            m.Message_Type,
            m.Message_Sent_At as Created_At,
            m.Message_Is_Read as Is_Read,
            m.Message_Sender_ID as Sender_ID
        FROM User u
        JOIN (
            SELECT 
                CASE 
                    WHEN Message_Sender_ID = ? THEN Message_Reciver_ID 
                    ELSE Message_Sender_ID 
                END AS Contact_ID,
                Product_ID,
                MAX(Message_ID) as Last_Msg_ID
            FROM Message
            WHERE (Message_Sender_ID = ? OR Message_Reciver_ID = ?)
              AND Product_ID IS NOT NULL
            GROUP BY Contact_ID, Product_ID
        ) last_msg ON u.User_ID = last_msg.Contact_ID
        JOIN Message m ON m.Message_ID = last_msg.Last_Msg_ID
        LEFT JOIN Product p ON m.Product_ID = p.Product_ID
        LEFT JOIN Product_Images pi ON p.Product_ID = pi.Product_ID AND pi.Image_is_primary = 1
        ORDER BY m.Message_Sent_At DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $conversations]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>