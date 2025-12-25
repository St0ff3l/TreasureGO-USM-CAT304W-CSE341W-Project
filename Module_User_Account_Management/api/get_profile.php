<?php
// api/get_profile.php
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

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
    $stmt = $pdo->prepare("SELECT User_ID, User_Username, User_Email, User_Role, User_Created_At, User_Profile_Image AS User_Profile_image, User_Average_Rating FROM User WHERE User_ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // 3. 计算统计数据 (分开处理，防止一个失败影响其他)
        
        // A. Published Count
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE User_ID = ?");
            $stmt->execute([$user_id]);
            $user['posted_count'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $user['posted_count'] = 0;
        }

        // B. Sold Count
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Product WHERE User_ID = ? AND Product_Status = 'Sold'");
            $stmt->execute([$user_id]);
            $user['sold_count'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            $user['sold_count'] = 0;
        }

        // C. Membership Tier
        try {
            $stmt = $pdo->prepare("SELECT mp.Membership_Tier FROM Memberships m JOIN Membership_Plans mp ON m.Membership_Plan_ID = mp.Membership_Plan_ID WHERE m.User_ID = ? AND m.Membership_Status = 'Active' AND m.Membership_End_Date > NOW() ORDER BY m.Membership_End_Date DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $membership = $stmt->fetchColumn();
            $user['Memberships_tier'] = $membership ? $membership : 'Free';
        } catch (Exception $e) {
            $user['Memberships_tier'] = 'Free';
        }
        
        // 4. 返回 JSON
        echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>