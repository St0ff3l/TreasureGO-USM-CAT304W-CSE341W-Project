<?php
// api/Get_Wallet_Balance.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config/treasurego_db_config.php';
session_start();

$response = ['success' => false, 'balance' => 0.00, 'has_pin' => false, 'msg' => ''];

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} else {
    echo json_encode(['success' => false, 'msg' => 'User not logged in']);
    exit;
}

try {
    $conn = getDatabaseConnection();

    if ($conn) {
        // 🔥 修复：只查 User_Payment_PIN_Hash，不要查 User_Wallet_Balance
        $sqlUser = "SELECT User_Payment_PIN_Hash FROM User WHERE User_ID = :uid";
        $stmtUser = $conn->prepare($sqlUser);
        $stmtUser->execute([':uid' => $userId]);
        $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($userData && !empty($userData['User_Payment_PIN_Hash'])) {
            $response['has_pin'] = true;
        }

        // 🔥 修复：余额依然从 Wallet_Logs 查
        $sqlBalance = "SELECT Balance_After FROM Wallet_Logs WHERE User_ID = :uid ORDER BY Log_ID DESC LIMIT 1";
        $stmtBalance = $conn->prepare($sqlBalance);
        $stmtBalance->execute([':uid' => $userId]);
        $logData = $stmtBalance->fetch(PDO::FETCH_ASSOC);

        if ($logData) {
            $response['balance'] = (float)$logData['Balance_After'];
        } else {
            $response['balance'] = 0.00;
        }

        $response['success'] = true;
    } else {
        $response['msg'] = 'Database connection failed';
    }
} catch (Exception $e) {
    $response['msg'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>