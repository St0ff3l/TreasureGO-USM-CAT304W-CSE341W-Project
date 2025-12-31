<?php
// api/update_pin.php
session_start();
require_once '../api/config/treasurego_db_config.php'; // 确保路径正确

header('Content-Type: application/json');

// 1. 检查登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$newPin = $input['new_pin'] ?? '';
$oldPin = $input['old_pin'] ?? null;

// 2. 基础格式校验
if (!preg_match('/^\d{6}$/', $newPin)) {
    echo json_encode(['success' => false, 'message' => 'New PIN must be 6 digits']);
    exit;
}

try {
    $pdo = getDBConnection();

    // 3. 获取用户当前的 PIN Hash
    $stmt = $pdo->prepare("SELECT User_Payment_PIN_Hash FROM User WHERE User_ID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $currentHash = $user['User_Payment_PIN_Hash'];

    // 4. 验证逻辑
    if (!empty($currentHash)) {
        // === 场景 A: 修改 PIN (数据库里已有密码) ===

        // 必须提供旧 PIN
        if (empty($oldPin)) {
            echo json_encode(['success' => false, 'message' => 'Current PIN is required']);
            exit;
        }

        // 验证旧 PIN 是否正确
        if (!password_verify($oldPin, $currentHash)) {
            echo json_encode(['success' => false, 'message' => 'Incorrect Current PIN']);
            exit;
        }
    }
    // === 场景 B: 首次设置 (数据库里是 NULL) ===
    // 不需要验证 oldPin，直接通过

    // 5. 更新新 PIN
    $newHash = password_hash($newPin, PASSWORD_BCRYPT);

    $updateStmt = $pdo->prepare("
        UPDATE User 
        SET User_Payment_PIN_Hash = ?, 
            User_PIN_Retry_Count = 0, 
            User_PIN_Locked_Until = NULL 
        WHERE User_ID = ?
    ");

    if ($updateStmt->execute([$newHash, $userId])) {
        echo json_encode(['success' => true, 'message' => 'PIN updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}
?>