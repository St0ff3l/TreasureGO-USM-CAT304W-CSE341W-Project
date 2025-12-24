<?php
// api/get_profile.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';

start_session_safe();

// 1. 严格校验 Session
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = get_current_user_id();

try {
    $pdo = getDBConnection();
    // 2. 查询数据 (不查密码!)
    $stmt = $pdo->prepare("SELECT User_ID, User_Username, User_Email, User_Role, User_Created_At, User_Profile_Image AS User_Profile_image FROM User WHERE User_ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // 3. 返回 JSON
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>