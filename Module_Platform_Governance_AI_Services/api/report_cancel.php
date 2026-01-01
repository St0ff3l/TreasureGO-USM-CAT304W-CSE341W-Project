<?php
// Cancels a report created by the current user.
//
// The report can only be cancelled if:
// - It belongs to the current logged-in user, and
// - Its current status is 'Pending'.

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';

header('Content-Type: application/json');

// Authentication guard.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Read JSON request body.
$input = json_decode(file_get_contents('php://input'), true);
$reportId = $input['report_id'] ?? null;

if (!$reportId) {
    echo json_encode(['success' => false, 'message' => 'Missing Report ID']);
    exit;
}

try {
    if (!isset($conn)) $conn = $pdo; // Ensure a connection handle is available.

    $sql = "UPDATE Report 
            SET Report_Status = 'Cancelled' 
            WHERE Report_ID = :id 
            AND Reporting_User_ID = :uid 
            AND Report_Status = 'Pending'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id' => $reportId,
        ':uid' => $_SESSION['user_id']
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Report cancelled successfully.']);
    } else {
        // No affected rows means: wrong ID, not owned by this user, or not pending.
        echo json_encode(['success' => false, 'message' => 'Cannot cancel: Report not found or already processed.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>