<?php
// api/send_verify_code.php
session_start(); // Start Session to store temporary verification code
header('Content-Type: application/json');

require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';
require_once '../includes/sendgrid_mailer.php';

$input = getJsonInput();
$email = trim($input['email'] ?? '');
$purpose = $input['purpose'] ?? 'signup'; // signup or reset_password

if (empty($email)) {
    jsonResponse(false, 'Email is required.');
}

try {
    $pdo = getDBConnection();

    // =================================================
    // Scenario A: Signup - User does not exist yet
    // =================================================
    if ($purpose === 'signup') {
        // 1. Check if email is already registered
        $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Email already registered. Please login.');
        }

        // 2. Generate verification code
        $code = generateVerificationCode();
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $expiresAt = time() + 600; // Expires in 10 minutes

        // 3. Store in Session (Not in DB because there is no User_ID)
        $_SESSION['signup_verify'] = [
            'email' => $email,
            'code_hash' => $codeHash,
            'expires_at' => $expiresAt
        ];

        // 4. Send email
        $subject = "Verify Your Email - TreasureGo";
        $body = "<h2>Welcome to TreasureGo!</h2><p>Your verification code is: <b style='font-size: 24px;'>$code</b></p><p>This code expires in 10 minutes.</p>";

        if (sendEmail($email, $subject, $body)) {
            jsonResponse(true, 'Verification code sent.');
        } else {
            jsonResponse(false, 'Failed to send email.');
        }
    } 
    
    // =================================================
    // Scenario B: Update Email - User must be logged in
    // =================================================
    elseif ($purpose === 'update_email') {
        if (!isset($_SESSION['user_id'])) {
            jsonResponse(false, 'Please login first.');
        }
        $userId = $_SESSION['user_id'];

        // 1. Check if email is already registered by another user
        $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Email already registered by another user.');
        }

        // 2. Rate limit check (Cannot resend within 60 seconds)
        $stmtCheck = $pdo->prepare("SELECT EV_Created_At FROM Email_Verification WHERE EV_Email = ? AND EV_Purpose = ? ORDER BY EV_Created_At DESC LIMIT 1");
        $stmtCheck->execute([$email, $purpose]);
        $lastEv = $stmtCheck->fetch();

        if ($lastEv && (time() - strtotime($lastEv['EV_Created_At']) < 60)) {
            jsonResponse(false, 'Please wait 60 seconds before resending.');
        }

        // 3. Generate new code
        $code = generateVerificationCode();
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 4. Insert into database
        $sqlEV = "INSERT INTO Email_Verification (User_ID, EV_Email, EV_Code, EV_Purpose, EV_Expires_At) VALUES (?, ?, ?, ?, ?)";
        $stmtEV = $pdo->prepare($sqlEV);
        $stmtEV->execute([$userId, $email, $codeHash, $purpose, $expiresAt]);

        // 5. Send email
        $subject = "Verify Your New Email - TreasureGo";
        $body = "<p>You are updating your email address.</p><p>Your verification code is: <b style='font-size: 24px;'>$code</b></p><p>Expires in 10 minutes.</p>";

        if (sendEmail($email, $subject, $body)) {
            jsonResponse(true, 'Verification code sent to new email.');
        } else {
            jsonResponse(false, 'Failed to send email.');
        }
    }

    // =================================================
    // Scenario C: Reset Password - User must exist
    // =================================================
    else {
        // 1. Confirm user exists
        $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // For security, you could also return success to prevent email enumeration
            jsonResponse(false, 'User not found.');
        }
        $userId = $user['User_ID'];

        // 2. Rate limit check (Cannot resend within 60 seconds)
        $stmtCheck = $pdo->prepare("SELECT EV_Created_At FROM Email_Verification WHERE EV_Email = ? AND EV_Purpose = ? ORDER BY EV_Created_At DESC LIMIT 1");
        $stmtCheck->execute([$email, $purpose]);
        $lastEv = $stmtCheck->fetch();

        if ($lastEv && (time() - strtotime($lastEv['EV_Created_At']) < 60)) {
            jsonResponse(false, 'Please wait 60 seconds before resending.');
        }

        // 3. Generate new code
        $code = generateVerificationCode();
        $codeHash = password_hash($code, PASSWORD_BCRYPT);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 4. Insert into database
        $sqlEV = "INSERT INTO Email_Verification (User_ID, EV_Email, EV_Code, EV_Purpose, EV_Expires_At) VALUES (?, ?, ?, ?, ?)";
        $stmtEV = $pdo->prepare($sqlEV);
        $stmtEV->execute([$userId, $email, $codeHash, $purpose, $expiresAt]);

        // 5. Send email
        $subject = "Reset Your Password";
        $body = "<p>Your verification code is: <b style='font-size: 24px;'>$code</b></p><p>Expires in 10 minutes.</p>";

        if (sendEmail($email, $subject, $body)) {
            jsonResponse(true, 'Code sent successfully.');
        } else {
            jsonResponse(false, 'Failed to send email.');
        }
    }

} catch (Exception $e) {
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
?>
