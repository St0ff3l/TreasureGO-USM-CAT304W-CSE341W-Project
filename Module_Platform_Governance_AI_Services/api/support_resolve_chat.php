<?php
// Marks an AI chat log entry as resolved or unresolved.
//
// Request: POST (JSON)
// - log_id: AIChatLog.AILog_ID
// - is_resolved: 0 or 1
//
// Auth: requires a logged-in user session.

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';

header("Content-Type: application/json; charset=UTF-8");

// Authentication guard.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Auth Required']);
    exit;
}

// Read request body.
$input = json_decode(file_get_contents('php://input'), true);
$logId = $input['log_id'] ?? null;
$isResolved = isset($input['is_resolved']) ? (int)$input['is_resolved'] : 0;
$userId = $_SESSION['user_id'];

// Connection handle normalization (some configs expose $pdo instead of $conn).
if (!isset($conn) && isset($pdo)) {
    $conn = $pdo;
}

if (!$logId || !isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request or DB Error']);
    exit;
}

try {
    // Update database state.
    // Only the owner of the log entry can change its resolved flag.
    $sql = "UPDATE AIChatLog SET AILog_Is_Resolved = ? WHERE AILog_ID = ? AND User_ID = ?";
    $stmt = $conn->prepare($sql);

    $success = $stmt->execute([$isResolved, $logId, $userId]);

    if ($success) {
        // rowCount() is 0 when the log id is invalid, not owned by the user,
        // or when the value is already the same.
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            // The query succeeded but did not affect any rows.
            echo json_encode(['success' => true, 'message' => 'No changes made']);
        }
    } else {
        $error = $stmt->errorInfo();
        echo json_encode(['success' => false, 'error' => $error[2]]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>