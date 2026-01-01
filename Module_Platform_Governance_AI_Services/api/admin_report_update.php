<?php
// Admin endpoint for updating a user report.
//
// Expected JSON payload (keys used by the admin UI):
// - Report_ID
// - status
// - reply_to_reporter
// - reply_to_reported
// - shouldBan (boolean or "true"/"false")
// - banDuration (e.g., "3d", "7d", "30d", "forever")
// - hideProduct (boolean or "true"/"false")

header('Content-Type: application/json');
require_once 'config/treasurego_db_config.php';
session_start();

// Resolve the acting admin ID from the current session.
$adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 100000001;

// Parse request body.
$inputPayload = file_get_contents("php://input");
$data = json_decode($inputPayload, true);

// Request fields (matching the frontend payload keys).
$reportId      = $data['Report_ID'] ?? null;
$status        = $data['status'] ?? null;

// Replies saved into the Report table.
$replyReporter = $data['reply_to_reporter'] ?? ''; // Message to the reporter.
$replyReported = $data['reply_to_reported'] ?? ''; // Message to the reported user.

// Normalize boolean-like fields (frontend may send true/false or strings).
$shouldBan     = isset($data['shouldBan']) && ($data['shouldBan'] === true || $data['shouldBan'] === 'true');
$hideProduct   = isset($data['hideProduct']) && ($data['hideProduct'] === true || $data['hideProduct'] === 'true');
$banDuration   = $data['banDuration'] ?? '3d';

try {
    if (!$reportId || !$status) {
        throw new Exception("Missing parameters: Report_ID or status.");
    }

    $pdo = getDatabaseConnection();
    $pdo->beginTransaction();

    $actionId = null; // Administrative_Action ID when a ban is applied.

    // Fetch report details to identify the target user and (optional) target product.
    $sqlSearch = "SELECT 
                    Reported_User_ID, 
                    Report_Type, 
                    Reported_Item_ID 
                  FROM Report 
                  WHERE Report_ID = ?";

    $stmtSearch = $pdo->prepare($sqlSearch);
    $stmtSearch->execute([$reportId]);
    $reportData = $stmtSearch->fetch(PDO::FETCH_ASSOC);

    if (!$reportData) {
        throw new Exception("Report record not found.");
    }

    $targetUserId = $reportData['Reported_User_ID'];
    $targetItemId = $reportData['Reported_Item_ID']; // Product ID (when applicable).

    // Apply account ban when requested.
    if ($shouldBan && $targetUserId) {
        // Calculate ban end date.
        $endDate = null;
        $banDuration = strtolower($banDuration);

        if ($banDuration === 'forever' || $banDuration === 'permanent') {
            $endDate = null; // Permanent ban.
        } else {
            // Supports values like "3d", "7d", "30d" or a numeric string.
            $days = intval($banDuration);
            if ($days <= 0) $days = 3; // Default to 3 days if invalid.
            $endDate = date('Y-m-d H:i:s', strtotime("+$days days"));
        }

        // Record the action in Administrative_Action.
        $sqlAction = "INSERT INTO Administrative_Action 
                      (Admin_Action_Type, Admin_Action_Reason, Admin_Action_Start_Date, Admin_Action_End_Date,
                       Admin_Action_Final_Resolution, Admin_ID, Target_User_ID, Admin_Action_Source) 
                      VALUES ('Ban', ?, NOW(), ?, 'Account banned via Report Center', ?, ?, 'report')";
        $stmtAction = $pdo->prepare($sqlAction);
        $stmtAction->execute([$replyReported, $endDate, $adminId, $targetUserId]);

        $actionId = $pdo->lastInsertId();

        // Update user status.
        $stmt = $pdo->prepare("UPDATE User SET User_Status = 'banned' WHERE User_ID = ?");
        $stmt->execute([$targetUserId]);
    }

    // Hide the reported product when the report is resolved and the option is selected.
    if ($status === 'Resolved' && $hideProduct && $targetItemId) {
        $sqlHide = "UPDATE Product 
                    SET Product_Status = 'unlisted', 
                        Product_Review_Status = 'rejected',
                        Product_Review_Comment = ? 
                    WHERE Product_ID = ?";

        $stmtHide = $pdo->prepare($sqlHide);
        $stmtHide->execute([$replyReported, $targetItemId]);
    }

    // Update the Report record with status, action link, and admin replies.
    $sqlUpdateReport = "UPDATE Report 
                        SET Report_Status = ?, 
                            Admin_Action_ID = ?, 
                            Report_Reply_To_Reporter = ?, 
                            Report_Reply_To_Reported = ?, 
                            Report_Updated_At = NOW()
                        WHERE Report_ID = ?";

    $stmtUpdate = $pdo->prepare($sqlUpdateReport);
    $stmtUpdate->execute([
        $status,
        $actionId,
        $replyReporter,
        $replyReported,
        $reportId
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Report processed successfully.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // In production, consider returning a generic error message.
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>