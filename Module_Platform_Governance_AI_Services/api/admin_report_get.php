<?php
// Admin reports API.
//
// Returns:
// - Aggregate report counts by status (stats)
// - A detailed report list, including reporter/reported user details
// - Optional evidence image paths (as an array)

// Basic response / CORS headers.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config/treasurego_db_config.php';

try {
    // Database connection is expected to be provided by the config.
    if (!isset($pdo)) {
        throw new Exception("Database connection failed.");
    }

    // Summary statistics.
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN Report_Status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN Report_Status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN Report_Status = 'Dismissed' THEN 1 ELSE 0 END) as dismissed,
            SUM(CASE WHEN Report_Status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM Report";
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Detailed report list.
    // Evidence file paths are aggregated to avoid repeating the same report row.
    $listSql = "
        SELECT 
            r.Report_ID as id,
            r.Report_Type as type,
            r.Report_Reason as reason,
            r.Report_Description as details,
            r.Report_Status as status,
            r.Report_Creation_Date as date,
            r.Report_Contact_Email as contactEmail,
            
            -- Reporter details
            u1.User_ID as reporterId,
            u1.User_Username as reporter, 
            u1.User_Email as reporterAccountEmail,
            
            -- Reported user details
            u2.User_ID as reportedUserId,
            u2.User_Username as reportedUserName,
            u2.User_Email as reportedUserEmail,
            u2.User_Profile_Image as reportedUserImage,
            
            -- Related product details (when the report targets a product)
            r.Reported_Item_ID as reportedItemId,
            CASE 
                WHEN r.Report_Type = 'product' AND p.Product_Title IS NOT NULL THEN p.Product_Title
                ELSE u2.User_Username 
            END as targetName,

            -- Primary product image for list rendering
            (SELECT Image_URL 
             FROM Product_Images pi 
             WHERE pi.Product_ID = p.Product_ID 
             LIMIT 1
            ) as productImage,

            -- Comma-separated evidence file paths (post-processed into an array)
            GROUP_CONCAT(re.File_Path) as evidence_paths

        FROM Report r
        LEFT JOIN User u1 ON r.Reporting_User_ID = u1.User_ID
        LEFT JOIN User u2 ON r.Reported_User_ID = u2.User_ID
        LEFT JOIN Product p ON r.Reported_Item_ID = p.Product_ID
        LEFT JOIN Report_Evidence re ON r.Report_ID = re.Report_ID
        
        -- Group by report so aggregated evidence stays on the same row
        GROUP BY r.Report_ID
        
        ORDER BY r.Report_Creation_Date DESC";

    $listStmt = $pdo->query($listSql);
    $rawReports = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    // Post-process evidence_paths into an evidence array.
    $reports = [];
    foreach ($rawReports as $row) {
        // Convert "path1.jpg,path2.jpg" into ["path1.jpg", "path2.jpg"].
        if (!empty($row['evidence_paths'])) {
            $row['evidence'] = explode(',', $row['evidence_paths']);
        } else {
            $row['evidence'] = [];
        }

        // Remove intermediate fields not needed by the frontend.
        unset($row['evidence_paths']);

        $reports[] = $row;
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$stats['total'],
            'pending' => (int)$stats['pending'],
            'resolved' => (int)$stats['resolved'],
            'dismissed' => (int)$stats['dismissed'],
            'cancelled' => (int)$stats['cancelled']
        ],
        'reports' => $reports
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>