<?php

// 1. Core Fix: Turn off error echoing to prevent Warning from breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
// api/forgot_password_send.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';
require_once '../includes/sendgrid_mailer.php';

// Get JSON Input
$input = getJsonInput();
$email = trim($input['email'] ?? '');

if (empty($email)) {
    jsonResponse(false, 'Email is required.');
}

try {
    $pdo = getDBConnection();

    // 1. Check if user exists
    $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Generate 6-digit code
        $code = (string)rand(100000, 999999);

        // 3. Securely hash the code for storage
        // Note: Database stores Hash to prevent code exposure if DB leaked
        $codeHash = password_hash($code, PASSWORD_BCRYPT);

        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 4. Insert into Email_Verification table
        // Key: Explicitly set EV_Purpose to 'reset_password'
        $sqlEV = "INSERT INTO Email_Verification (User_ID, EV_Email, EV_Code, EV_Purpose, EV_Expires_At) 
                  VALUES (?, ?, ?, 'reset_password', ?)";
        $stmtEV = $pdo->prepare($sqlEV);
        $stmtEV->execute([$user['User_ID'], $email, $codeHash, $expiresAt]);

        // 5. Send Real Email
        $subject = "Reset Password Code - TreasureGo";
        $body = "<p>You requested a password reset for your TreasureGo account.</p>
                 <p>Your verification code is: <b style='font-size: 24px; color: #4F46E5;'>$code</b></p>
                 <p>This code expires in 10 minutes.</p>";

        $emailSent = sendEmail($email, $subject, $body);

        $isSent = sendEmail($email, $subject, $body);

        if ($emailSent) {
            // Success: Only return redirect URL
            jsonResponse(true, 'Verification code sent to your email.', [
                'next_url' => "reset_password.php?email=" . urlencode($email)
            ]);
        } else {
            jsonResponse(false, 'Failed to send email. Please try again later.');
        }
    } else {
        // User not found: To prevent enumeration attacks, usually return success or vague message
        // But for development debugging, use explicit message here, change to "If account exists..." before production
        jsonResponse(false, 'Email not found.');
    }

} catch (Exception $e) {
    // Log error, don't expose system error to user
    error_log($e->getMessage());
    jsonResponse(false, 'An internal error occurred.');
}
?>