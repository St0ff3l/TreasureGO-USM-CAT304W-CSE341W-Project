<?php
// api/login_user.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';

start_session_safe();

// 1. Get Input (Support JSON or Form Data)
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $_POST['email'] ?? '';
$password = $input['password'] ?? $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and Password are required.']);
    exit();
}

try {
    // 2. Query Database
    $pdo = getDBConnection();
    // Note: Field names strictly follow your database constraints
    $stmt = $pdo->prepare("SELECT User_ID AS User_ID, User_Password_Hash AS User_Password_Hash, User_Username AS User_Username, User_Role AS User_Role FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 3. Verify Password
    if ($user && password_verify($password, $user['User_Password_Hash'])) {
        
        // --- New: Ban Status Check and Auto-Unban Logic ---
        $stmtStatus = $pdo->prepare("SELECT User_Status FROM User WHERE User_ID = ?");
        $stmtStatus->execute([$user['User_ID']]);
        $currentStatus = $stmtStatus->fetchColumn();

        if ($currentStatus === 'banned') {
            // Check if expired, try auto-unban
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
                
                // If there is an end date, and current time has passed end date -> Auto unban
                if ($endDate && strtotime($endDate) < time()) {
                    $pdo->prepare("UPDATE User SET User_Status = 'active' WHERE User_ID = ?")->execute([$user['User_ID']]);
                    $isBanned = false; // Unban successful, allow login
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

        // 4. Write Session
        $_SESSION['user_id'] = $user['User_ID'];
        $_SESSION['user_role'] = $user['User_Role'];
        $_SESSION['user_username'] = $user['User_Username'];

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            // 👇 Change to jump back to root index.html
            // (../../ means go up two levels, back to project root)
            'redirect' => '../../index.html'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System error: ' . $e->getMessage()]);
}
?>