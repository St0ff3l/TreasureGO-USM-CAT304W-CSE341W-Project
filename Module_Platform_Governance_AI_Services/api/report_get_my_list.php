<?php
// report_get_my_list.php
// Module_Platform_Governance_AI_Services/api/

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

ob_start();
session_set_cookie_params(0, '/');
session_start();

try {
    // Load the database configuration (local module path first, then fallback).
    $config_path = __DIR__ . '/config/treasurego_db_config.php';
    if (file_exists($config_path)) {
        require_once $config_path;
    } else {
        $fallback = __DIR__ . '/../../config/treasurego_db_config.php';
        if (file_exists($fallback)) {
            require_once $fallback;
        } else {
            throw new Exception("System Error: Config file not found.");
        }
    }

    // Ensure a valid database connection.
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed.");
    }

    // Require a logged-in user.
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized: Please log in.");
    }

    $current_user_id = $_SESSION['user_id'];

    // Query reports created by the current user.
    // Evidence images are aggregated into a comma-separated list and expanded into an array in PHP.
    $sql = "SELECT 
                r.Report_ID,
                r.Report_Reason,
                r.Report_Description,
                r.Report_Status,
                r.Report_Creation_Date,
                r.Report_Reply_To_Reporter,
                r.Report_Updated_At,
                r.Reported_Item_ID,
                r.Reported_User_ID,
                u.User_Username AS Reported_Username,
                p.Product_Title AS Reported_Product_Name,
                /* Evidence image paths (comma-separated) */
                GROUP_CONCAT(re.File_Path SEPARATOR ',') AS Evidence_Paths
            FROM Report r
            LEFT JOIN User u ON r.Reported_User_ID = u.User_ID
            LEFT JOIN Product p ON r.Reported_Item_ID = p.Product_ID
            LEFT JOIN Report_Evidence re ON r.Report_ID = re.Report_ID /* Evidence join */
            WHERE r.Reporting_User_ID = :user_id
            GROUP BY r.Report_ID  /* Group by report to support evidence aggregation */
            ORDER BY r.Report_Creation_Date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reports = [];

    foreach ($rows as $row) {
        // Infer the report target type (user vs product) based on whether a product ID is present.
        $type = 'user';
        $targetName = $row['Reported_Username'] ?? ('User #' . $row['Reported_User_ID']);

        if (!empty($row['Reported_Item_ID'])) {
            $type = 'product';
            $targetName = $row['Reported_Product_Name'] ?? ('Product #' . $row['Reported_Item_ID']);
        }

        // Convert a comma-separated evidence path list into an array.
        $evidenceImages = [];
        if (!empty($row['Evidence_Paths'])) {
            $evidenceImages = explode(',', $row['Evidence_Paths']);
        }

        $reports[] = [
            'id' => $row['Report_ID'],
            'type' => $type,
            'targetName' => $targetName,
            'reason' => $row['Report_Reason'],
            'details' => $row['Report_Description'] ?? '',
            'status' => ucfirst($row['Report_Status']),
            'date' => $row['Report_Creation_Date'],
            'adminReply' => $row['Report_Reply_To_Reporter'] ?? '',
            'updatedAt' => $row['Report_Updated_At'] ?? '',

            // Evidence image URLs/paths for the frontend.
            'evidence' => $evidenceImages
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => $reports]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>