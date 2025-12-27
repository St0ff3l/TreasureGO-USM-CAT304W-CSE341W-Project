<?php
// api/login_user.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';

start_session_safe();

// 1. 获取输入 (支持 JSON 或 Form Data)
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $_POST['email'] ?? '';
$password = $input['password'] ?? $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and Password are required.']);
    exit();
}

try {
    // 2. 查询数据库
    $pdo = getDBConnection();
    // 注意：字段名严格遵守你的数据库约束
    $stmt = $pdo->prepare("SELECT User_ID AS User_ID, User_Password_Hash AS User_Password_Hash, User_Username AS User_Username, User_Role AS User_Role FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 3. 验证密码
    if ($user && password_verify($password, $user['User_Password_Hash'])) {
        
        // --- 新增：封禁状态检查与自动解封逻辑 ---
        $stmtStatus = $pdo->prepare("SELECT User_Status FROM User WHERE User_ID = ?");
        $stmtStatus->execute([$user['User_ID']]);
        $currentStatus = $stmtStatus->fetchColumn();

        if ($currentStatus === 'banned') {
            // 检查是否到期，尝试自动解封
            $stmtBan = $pdo->prepare("
                SELECT Admin_Action_End_Date 
                FROM Administrative_Action 
                WHERE Target_User_ID = ? AND Admin_Action_Type = 'Ban' 
                ORDER BY Admin_Action_Start_Date DESC LIMIT 1
            ");
            $stmtBan->execute([$user['User_ID']]);
            $banInfo = $stmtBan->fetch();

            $isBanned = true;
            $banMessage = "Your account has been suspended.";

            if ($banInfo) {
                $endDate = $banInfo['Admin_Action_End_Date'];
                
                // 如果有结束日期，且当前时间已经超过结束日期 -> 自动解封
                if ($endDate && strtotime($endDate) < time()) {
                    $pdo->prepare("UPDATE User SET User_Status = 'active' WHERE User_ID = ?")->execute([$user['User_ID']]);
                    $isBanned = false; // 解封成功，允许继续登录
                } elseif ($endDate) {
                    $banMessage .= " Suspension ends on: " . $endDate;
                } else {
                    $banMessage .= " This suspension is permanent.";
                }
            } else {
                $banMessage .= " Please contact support for more details.";
            }

            if ($isBanned) {
                $banMessage .= ' If you have any questions, please contact "TreasureGO@daombledore.fun".';
                echo json_encode(['status' => 'error', 'message' => $banMessage]);
                exit();
            }
        }
        // ---------------------------------------

        // 4. 写入 Session
        $_SESSION['user_id'] = $user['User_ID'];
        $_SESSION['user_role'] = $user['User_Role'];
        $_SESSION['user_username'] = $user['User_Username'];

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            // 👇 改成跳回根目录的 index.html
            // (../../ 表示往上跳两级，回到项目根目录)
            'redirect' => '../../index.html'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
}
?>