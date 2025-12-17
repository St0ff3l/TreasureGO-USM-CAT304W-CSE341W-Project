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

if (!$userId || !$status) {
    jsonResponse(false, 'Missing user_id or status');
}

$validStatuses = ['active', 'pending', 'banned'];
if (!in_array($status, $validStatuses)) {
    jsonResponse(false, 'Invalid status. Must be: active, pending, or banned');
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE User SET User_Status = ? WHERE User_ID = ?");
    $stmt->execute([$status, $userId]);

    jsonResponse(true, 'User status updated successfully');
} catch (Exception $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}
?>