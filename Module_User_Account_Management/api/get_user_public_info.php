<?php
header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';

start_session_safe();

// 允许未登录用户获取公开信息（例如商品详情页展示卖家信息），但为了安全，这里仅返回公开字段
// 如果业务要求必须登录才能看卖家信息，可以取消注释下面这行
// if (!is_logged_in()) { ... }

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit();
}

try {
    $pdo = getDBConnection();
    // 只查询公开信息：用户名、头像、注册时间、评分
    $stmt = $pdo->prepare("SELECT User_ID, User_Username, User_Profile_image as User_Avatar_Url, User_Created_At, User_Average_Rating FROM User WHERE User_ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user) {
        // 计算统计数据 (分开处理，防止一个失败影响其他)
        
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
         
         echo json_encode(['status' => 'success', 'data' => $user]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
