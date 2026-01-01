<?php
// api/signup_user.php
// Enforce email verification before saving to database

session_start(); // Start Session to read verification code
error_reporting(E_ALL);
ini_set('display_errors', 0);

function fatal_handler() {
    $error = error_get_last();
    if ($error !== NULL && $error['type'] === E_ERROR) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'PHP Fatal Error: ' . $error['message']]);
        exit;
    }
}
register_shutdown_function("fatal_handler");

header('Content-Type: application/json');

require_once '../api/config/treasurego_db_config.php';
require_once '../includes/utils.php';

try {
    $input = getJsonInput();
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $code = trim($input['code'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($code)) {
        jsonResponse(false, 'All fields (including verification code) are required.');
    }

    // 1. Verify code in Session
    if (!isset($_SESSION['signup_verify'])) {
        jsonResponse(false, 'Please click "Send Code" first.');
    }

    $verifyData = $_SESSION['signup_verify'];

    // Check if email matches
    if ($verifyData['email'] !== $email) {
        jsonResponse(false, 'Email mismatch. Please resend code.');
    }

    // Check expiration
    if (time() > $verifyData['expires_at']) {
        jsonResponse(false, 'Verification code expired. Please resend.');
    }

    // Check verification code
    if (!password_verify($code, $verifyData['code_hash'])) {
        jsonResponse(false, 'Invalid verification code.');
    }

    // =================================================
    // Verification passed, start saving to database
    // =================================================
    $pdo = getDBConnection();

    // 2. Check again if email is already registered (prevent concurrent registration)
    $stmt = $pdo->prepare("SELECT User_ID FROM User WHERE User_Email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Email already registered.');
    }

    // 3. Create user (set directly to active and verified)
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // Note: Set directly to 'active' and User_Email_Verified = 1
    $sql = "INSERT INTO User (User_Username, User_Email, User_Password_Hash, User_Role, User_Status, User_Email_Verified, User_Created_At)
            VALUES (?, ?, ?, 'user', 'active', 1, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $email, $passwordHash]);
    
    // 4. Clear Session
    unset($_SESSION['signup_verify']);

    jsonResponse(true, 'Signup successful! You can now login.');

} catch (Exception $e) {
    jsonResponse(false, 'System error: ' . $e->getMessage());
}
?>
