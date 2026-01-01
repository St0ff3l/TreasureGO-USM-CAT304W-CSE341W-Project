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
$duration = $input['duration'] ?? null; // Get ban duration

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

        // If ban operation, handle duration and record to Administrative_Action
        if ($status === 'banned') {
            $endDate = null;
            
            // Handle duration logic (refer to admin_report_update.php)
            if ($duration) {
                $durationStr = strtolower((string)$duration);
                if ($durationStr === 'forever' || $durationStr === 'permanent' || $durationStr === '-1') {
                    $endDate = null; // Permanent ban
                } else {
                    $days = intval($durationStr);
                    if ($days > 0) {
                        $endDate = date('Y-m-d H:i:s', strtotime("+$days days"));
                    } else {
                        // Default ban 3 days if input invalid
                        $endDate = date('Y-m-d H:i:s', strtotime("+3 days"));
                    }
                }
            }

            // Insert Administrative_Action record
            // Note: Assume current operator is admin, ID from Session
            $adminId = $_SESSION['user_id'] ?? null; 
            
            // Admin_Action_Source is enum('report', 'dispute'), set to NULL for manual ban
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