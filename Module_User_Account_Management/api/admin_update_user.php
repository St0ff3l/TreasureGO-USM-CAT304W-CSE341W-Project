<?php
// api/admin_update_user.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';
require_once '../includes/utils.php';

start_session_safe();

if (!is_admin()) {
    http_response_code(403);
    jsonResponse(false, 'Admin access required');
}

$input = getJsonInput();
$userId = $input['user_id'] ?? null;
$status = $input['status'] ?? null;
$role = $input['role'] ?? null;
$duration = $input['duration'] ?? null; // 获取封禁时长

if (!$userId) {
    jsonResponse(false, 'Missing user_id');
}

try {
    $pdo = getDBConnection();
    
    if ($status) {
        $validStatuses = ['active', 'pending', 'banned'];
        if (!in_array($status, $validStatuses)) {
            jsonResponse(false, 'Invalid status');
        }

        // 如果是封禁操作，处理时长并记录到 Administrative_Action
        if ($status === 'banned') {
            $endDate = null;
            
            // 处理时长逻辑 (参考 admin_report_update.php)
            if ($duration) {
                $durationStr = strtolower((string)$duration);
                if ($durationStr === 'forever' || $durationStr === 'permanent' || $durationStr === '-1') {
                    $endDate = null; // 永久封禁
                } else {
                    $days = intval($durationStr);
                    if ($days > 0) {
                        $endDate = date('Y-m-d H:i:s', strtotime("+$days days"));
                    } else {
                        // 默认封禁3天，如果输入无效
                        $endDate = date('Y-m-d H:i:s', strtotime("+3 days"));
                    }
                }
            }

            // 插入 Administrative_Action 记录
            // 注意：这里假设当前操作者是管理员，ID 从 Session 获取
            $adminId = $_SESSION['user_id'] ?? null; 
            
            // Admin_Action_Source 是 enum('report', 'dispute')，手动封禁设为 NULL
            $sqlAction = "INSERT INTO Administrative_Action 
                          (Admin_Action_Type, Admin_Action_Reason, Admin_Action_Start_Date, Admin_Action_End_Date,
                           Admin_Action_Final_Resolution, Admin_ID, Target_User_ID, Admin_Action_Source) 
                          VALUES ('Ban', 'Manual Ban via User Management', NOW(), ?, 'Account banned manually', ?, ?, NULL)";
            $stmtAction = $pdo->prepare($sqlAction);
            $stmtAction->execute([$endDate, $adminId, $userId]);
        }

        $stmt = $pdo->prepare("UPDATE User SET User_Status = ? WHERE User_ID = ?");
        $stmt->execute([$status, $userId]);
    }

    if ($role) {
        $validRoles = ['user', 'admin'];
        if (!in_array($role, $validRoles)) {
            jsonResponse(false, 'Invalid role');
        }
        $stmt = $pdo->prepare("UPDATE User SET User_Role = ? WHERE User_ID = ?");
        $stmt->execute([$role, $userId]);
    }

    jsonResponse(true, 'User updated successfully');
} catch (Exception $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
?>