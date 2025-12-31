<?php
// api/update_profile.php
session_start();
header('Content-Type: application/json');

require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Please login first.');
}
$userId = $_SESSION['user_id'];

$input = getJsonInput();
$newUsername = trim($input['username'] ?? '');
$newEmail = trim($input['email'] ?? '');
$code = trim($input['verification_code'] ?? '');

if (empty($newUsername)) {
    jsonResponse(false, 'Username is required.');
}

try {
    $pdo = getDBConnection();

    // Get current user info
    $stmt = $pdo->prepare("SELECT User_Username, User_Email FROM User WHERE User_ID = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();

    if (!$currentUser) {
        jsonResponse(false, 'User not found.');
    }

    $updates = [];
    $params = [];

    // 2. Handle Username Update
    if ($newUsername !== $currentUser['User_Username']) {
        // Check uniqueness if required (assuming DB constraint handles it, but good to check)
        $stmtCheck = $pdo->prepare("SELECT User_ID FROM User WHERE User_Username = ? AND User_ID != ?");
        $stmtCheck->execute([$newUsername, $userId]);
        if ($stmtCheck->fetch()) {
            jsonResponse(false, 'Username already taken.');
        }
        
        $updates[] = "User_Username = ?";
        $params[] = $newUsername;
        
        // Update session username
        $_SESSION['user_username'] = $newUsername;
    }

    // 3. Handle Email Update
    if (!empty($newEmail) && $newEmail !== $currentUser['User_Email']) {
        if (empty($code)) {
            jsonResponse(false, 'Verification code required for email change.');
        }

        // Verify Code
        // Note: We check EV_Email = $newEmail because the code was sent to the NEW email
        $stmtVerify = $pdo->prepare("
            SELECT * FROM Email_Verification 
            WHERE EV_Email = ? AND EV_Purpose = 'update_email' AND EV_Is_Used = 0 
            ORDER BY EV_Created_At DESC LIMIT 1
        ");
        $stmtVerify->execute([$newEmail]);
        $evRecord = $stmtVerify->fetch();

        if (!$evRecord) {
            jsonResponse(false, 'Invalid or expired verification code.');
        }

        // Check expiry
        if (strtotime($evRecord['EV_Expires_At']) < time()) {
            jsonResponse(false, 'Verification code expired.');
        }

        // Check hash
        if (!password_verify($code, $evRecord['EV_Code'])) {
            // Increment attempt count
            $pdo->prepare("UPDATE Email_Verification SET EV_Attempt_Count = EV_Attempt_Count + 1 WHERE EV_ID = ?")->execute([$evRecord['EV_ID']]);
            jsonResponse(false, 'Invalid verification code.');
        }

        // Check if email taken (again, to be safe)
        $stmtEmailCheck = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? AND User_ID != ?");
        $stmtEmailCheck->execute([$newEmail, $userId]);
        if ($stmtEmailCheck->fetch()) {
            jsonResponse(false, 'Email already registered by another user.');
        }

        // Mark code as used
        $pdo->prepare("UPDATE Email_Verification SET EV_Is_Used = 1 WHERE EV_ID = ?")->execute([$evRecord['EV_ID']]);

        $updates[] = "User_Email = ?";
        $params[] = $newEmail;
    }

    // 4. Execute Update
    if (!empty($updates)) {
        $sql = "UPDATE User SET " . implode(', ', $updates) . " WHERE User_ID = ?";
        $params[] = $userId;
        $pdo->prepare($sql)->execute($params);
        
        jsonResponse(true, 'Profile updated successfully.');
    } else {
        jsonResponse(true, 'No changes made.');
    }

} catch (Exception $e) {
    // Handle duplicate entry error specifically if possible
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        jsonResponse(false, 'Username or Email already exists.');
    }
    jsonResponse(false, 'System error: ' . $e->getMessage());
}
?>
