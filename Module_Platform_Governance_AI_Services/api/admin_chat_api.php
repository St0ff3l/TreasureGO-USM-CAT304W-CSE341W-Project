<?php
// Chat API for the Admin Support Dashboard.
//
// Supported actions:
// - GET  ?action=conversations   Returns the latest message for each conversation thread.
// - GET  ?action=messages       Returns messages for a specific thread.
// - POST ?action=send           Sends a text message.
// - POST ?action=upload         Uploads an image and sends it as an image message.

header('Content-Type: application/json');

require_once __DIR__ . '/config/treasurego_db_config.php';
require_once __DIR__ . '/../../Module_User_Account_Management/includes/auth.php';

function json_ok($data = []) {
    // Successful JSON response.
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit();
}
function json_err($message, $code = 400) {
    // Error JSON response with HTTP status code.
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

// Session + authorization guard.
start_session_safe();
require_admin();

$admin_id = (int)get_current_user_id();

$pdo = getDatabaseConnection();
if (!$pdo) {
    json_err('Database connection failed', 500);
}

$action = $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'conversations') {
        // Conversation list: one row per (contact, product) thread with the latest message.
        // The contact is computed as the other participant relative to the current admin.
        $sql = "
            SELECT 
                u.User_ID, 
                u.User_Username, 
                u.User_Profile_image as User_Avatar_Url,
                p.Product_Title as Product_Name,
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
                GROUP BY Contact_ID, Product_ID
            ) last_msg ON u.User_ID = last_msg.Contact_ID
            JOIN Message m ON m.Message_ID = last_msg.Last_Msg_ID
            LEFT JOIN Product p ON m.Product_ID = p.Product_ID
            LEFT JOIN Product_Images pi ON p.Product_ID = pi.Product_ID AND pi.Image_is_primary = 1
            ORDER BY m.Message_Sent_At DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$admin_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_ok($rows);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'messages') {
        $contact_id = $_GET['contact_id'] ?? null;
        $product_id = $_GET['product_id'] ?? null;

        if (!$contact_id) json_err('Contact ID required');

        // Threads with Product_ID = NULL are treated as generic support chats.
        $isSupport = ($product_id === null || $product_id === '' || strtolower((string)$product_id) === 'null');

        if ($isSupport) {
            // Support chat: the user is the selected contact; the other participant is any admin.
            $sql = "
                SELECT 
                    m.Message_ID,
                    m.Message_Sender_ID as Sender_ID,
                    m.Message_Reciver_ID as Receiver_ID,
                    m.Message_Content,
                    m.Message_Type,
                    m.Message_Sent_At as Created_At,
                    m.Message_Is_Read as Is_Read,
                    m.Product_ID
                FROM Message m
                JOIN User u_sender ON u_sender.User_ID = m.Message_Sender_ID
                JOIN User u_recv ON u_recv.User_ID = m.Message_Reciver_ID
                WHERE m.Product_ID IS NULL
                  AND (
                      (m.Message_Sender_ID = ? AND u_recv.User_Role = 'admin')
                      OR
                      (m.Message_Reciver_ID = ? AND u_sender.User_Role = 'admin')
                  )
                ORDER BY m.Message_Sent_At
            ";
            $params = [$contact_id, $contact_id];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark unread messages sent by the user to an admin as read.
            $updateSql = "
                UPDATE Message m
                JOIN User u_recv ON u_recv.User_ID = m.Message_Reciver_ID
                SET m.Message_Is_Read = 1
                WHERE m.Product_ID IS NULL
                  AND m.Message_Sender_ID = ?
                  AND m.Message_Is_Read = 0
                  AND u_recv.User_Role = 'admin'
            ";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$contact_id]);

            json_ok($messages);
        }

        // Product chat: messages between this admin and the selected contact scoped to a product.
        $sql = "
            SELECT 
                Message_ID,
                Message_Sender_ID as Sender_ID,
                Message_Reciver_ID as Receiver_ID,
                Message_Content,
                Message_Type,
                Message_Sent_At as Created_At,
                Message_Is_Read as Is_Read,
                Product_ID
            FROM Message
            WHERE ((Message_Sender_ID = ? AND Message_Reciver_ID = ?) 
               OR (Message_Sender_ID = ? AND Message_Reciver_ID = ?))
              AND Product_ID = ?
            ORDER BY Message_Sent_At
        ";
        $params = [$admin_id, $contact_id, $contact_id, $admin_id, $product_id];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Mark unread messages from the contact to this admin as read for this product thread.
        $updateSql = "UPDATE Message SET Message_Is_Read = 1 
                      WHERE Message_Sender_ID = ? AND Message_Reciver_ID = ? AND Message_Is_Read = 0 AND Product_ID = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$contact_id, $admin_id, $product_id]);

        json_ok($messages);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send') {
        // JSON body: { receiver_id, product_id (optional), message }
        $input = json_decode(file_get_contents('php://input'), true);

        $receiver_id = $input['receiver_id'] ?? null;
        $product_id = $input['product_id'] ?? null;
        $message = trim($input['message'] ?? '');

        if (!$receiver_id || $message === '') json_err('receiver_id and message are required');

        // Treat empty / "null" product_id as a support thread.
        if (empty($product_id) || strtolower((string)$product_id) === 'null') {
            $product_id = null;
        }

        $sql = "INSERT INTO Message (Message_Sender_ID, Message_Reciver_ID, Product_ID, Message_Content, Message_Type, Message_Sent_At)
                VALUES (?, ?, ?, ?, 'text', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$admin_id, $receiver_id, $product_id, $message]);

        json_ok(['message' => 'sent']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
        // Multipart form-data fields: receiver_id, product_id (optional), image.
        if (!isset($_POST['receiver_id'])) json_err('receiver_id required');
        $receiver_id = $_POST['receiver_id'];
        $product_id = $_POST['product_id'] ?? null;
        if (empty($product_id) || strtolower((string)$product_id) === 'null') $product_id = null;

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_err('No image uploaded or upload error');
        }

        $file = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;

        // Basic server-side validation.
        if (!in_array($file['type'], $allowed_types, true)) {
            json_err('Invalid file type. Only JPG, PNG, GIF, WEBP allowed.');
        }
        if ($file['size'] > $max_size) {
            json_err('File too large. Max 5MB.');
        }

        // Persist uploaded images under this module's static assets.
        $upload_dir = __DIR__ . '/../assets/chat_images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('adminchat_', true) . '.' . $ext;
        $filepath = $upload_dir . $filename;

        // URL used by the frontend to render the uploaded file.
        $public_path = '/Module_Platform_Governance_AI_Services/assets/chat_images/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            json_err('Failed to save file');
        }

        $sql = "INSERT INTO Message (Message_Sender_ID, Message_Reciver_ID, Product_ID, Message_Content, Message_Type, Message_Sent_At)
                VALUES (?, ?, ?, ?, 'image', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$admin_id, $receiver_id, $product_id, $public_path]);

        json_ok(['url' => $public_path]);
    }

    json_err('Unknown action', 404);

} catch (Exception $e) {
    // Avoid returning internal details in production.
    json_err($e->getMessage(), 500);
}
