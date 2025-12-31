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
             // 1. Fetch all valid memberships (active or future)
             $stmt = $pdo->prepare("
                 SELECT 
                     mp.Membership_Tier,
                     mp.Membership_Price,
                     mp.Membership_Description,
                     m.Memberships_Start_Date,
                     m.Memberships_End_Date
                 FROM Memberships m 
                 JOIN Membership_Plans mp ON m.Plan_ID = mp.Plan_ID 
                 WHERE m.User_ID = ? 
                   AND m.Memberships_End_Date > NOW() 
                 ORDER BY mp.Membership_Price DESC, m.Memberships_Start_Date ASC
             ");
             $stmt->execute([$user_id]);
             $allMemberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
             $currentDate = date('Y-m-d H:i:s');
             $highestActiveTier = null;
             $highestPrice = -1;
             $selectedDescription = '';
 
             // 2. Identify Highest Active Tier (must be active NOW)
             foreach ($allMemberships as $m) {
                 if ($m['Memberships_Start_Date'] <= $currentDate && $m['Memberships_End_Date'] > $currentDate) {
                     if ($m['Membership_Price'] > $highestPrice) {
                         $highestPrice = $m['Membership_Price'];
                         $highestActiveTier = $m['Membership_Tier'];
                         $selectedDescription = $m['Membership_Description'];
                     }
                 }
             }
 
             if ($highestActiveTier) {
                 // 3. Filter records for this specific tier
                 $tierRecords = array_filter($allMemberships, function($m) use ($highestActiveTier) {
                     return $m['Membership_Tier'] === $highestActiveTier;
                 });
                 
                 // Sort by Start Date (crucial for merging)
                 usort($tierRecords, function($a, $b) {
                     return strcmp($a['Memberships_Start_Date'], $b['Memberships_Start_Date']);
                 });
 
                 // 4. Merge overlapping/continuous intervals
                 $mergedIntervals = [];
                 foreach ($tierRecords as $rec) {
                     if (empty($mergedIntervals)) {
                         $mergedIntervals[] = [
                             'start' => $rec['Memberships_Start_Date'],
                             'end' => $rec['Memberships_End_Date']
                         ];
                     } else {
                         $lastIndex = count($mergedIntervals) - 1;
                         $last = &$mergedIntervals[$lastIndex];
                         
                         // Check for overlap or adjacency
                         if ($rec['Memberships_Start_Date'] <= $last['end']) {
                             if ($rec['Memberships_End_Date'] > $last['end']) {
                                 $last['end'] = $rec['Memberships_End_Date'];
                             }
                         } else {
                             $mergedIntervals[] = [
                                 'start' => $rec['Memberships_Start_Date'],
                                 'end' => $rec['Memberships_End_Date']
                             ];
                         }
                     }
                 }
 
                 // 5. Find the interval that covers NOW
                 $finalStart = null;
                 $finalEnd = null;
                 foreach ($mergedIntervals as $interval) {
                     if ($interval['start'] <= $currentDate && $interval['end'] > $currentDate) {
                         $finalStart = $interval['start'];
                         $finalEnd = $interval['end'];
                         break;
                     }
                 }
 
                 if ($finalStart && $finalEnd) {
                     $user['Memberships_tier'] = $highestActiveTier;
                     $user['Memberships_Start_Date'] = $finalStart;
                     $user['Memberships_End_Date'] = $finalEnd;
                     $user['Membership_Description'] = $selectedDescription;
                 } else {
                     $user['Memberships_tier'] = 'Free';
                 }
 
             } else {
                 $user['Memberships_tier'] = 'Free';
                 $user['Memberships_Start_Date'] = null;
                 $user['Memberships_End_Date'] = null;
                 $user['Membership_Description'] = 'Standard free account.';
             }
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
