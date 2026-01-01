<?php
// api/update_pin.php
session_start();
require_once '../api/config/treasurego_db_config.php'; // 确保路径正确

header('Content-Type: application/json');

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$newPin = $input['new_pin'] ?? '';
$oldPin = $input['old_pin'] ?? null;

// 2. Basic Format Validation
if (!preg_match('/^\d{6}$/', $newPin)) {
    echo json_encode(['success' => false, 'message' => 'New PIN must be 6 digits']);
    exit;
}

try {
    $pdo = getDBConnection();

    // 3. Get User Current PIN Hash
    $stmt = $pdo->prepare("SELECT User_Payment_PIN_Hash FROM User WHERE User_ID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $currentHash = $user['User_Payment_PIN_Hash'];

    // 4. Validation Logic
    if (!empty($currentHash)) {
        // === Scenario A: Change PIN (Password exists in DB) ===

        // Must provide old PIN
        if (empty($oldPin)) {
            echo json_encode(['success' => false, 'message' => 'Current PIN is required']);
            exit;
        }

        // Verify old PIN
        if (!password_verify($oldPin, $currentHash)) {
            echo json_encode(['success' => false, 'message' => 'Incorrect Current PIN']);
            exit;
        }
    }
    // === Scenario B: First Time Setup (NULL in DB) ===
    // No need to verify oldPin, pass directly

    // 5. Update New PIN
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