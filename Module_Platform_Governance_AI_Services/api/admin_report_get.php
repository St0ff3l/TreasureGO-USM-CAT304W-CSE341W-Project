<?php
header('Content-Type: application/json');
require_once 'config/treasurego_db_config.php';

try {
    if (!$pdo) {
        throw new Exception("Database connection failed.");
    }

    // 1. 获取统计数据 (Total, Pending, Resolved, Dismissed)
    $statsQuery = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN Report_Status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN Report_Status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN Report_Status = 'Dismissed' THEN 1 ELSE 0 END) as dismissed
        FROM Report";
    $statsStmt = $pdo->query($statsQuery);
    $stats = $statsStmt->fetch();

    // 2. 获取报告详细列表
    // 补充了被举报人的 User_Email (u2.User_Email)
    $listSql = "
    SELECT 
        r.Report_ID as id,
        r.Report_Type as type,
        r.Report_Reason as reason,
        r.Report_Description as details,
        r.Report_Status as status,
        r.Report_Creation_Date as date,
        -- 新增：用户提交举报时留下的联系邮箱
        r.Report_Contact_Email as contactEmail, 
        -- 举报者账户信息 (u1)
        u1.User_ID as reporterId,
        u1.User_Username as reporter, 
        u1.User_Email as reporterAccountEmail,
        -- 被举报人账户信息 (u2)
        u2.User_ID as reportedUserId,
        u2.User_Username as reportedUserName,
        u2.User_Email as reportedUserEmail,
        -- 关联物品 ID
        r.Reported_Item_ID as reportedItemId,
        CASE 
            WHEN r.Report_Type = 'product' AND p.Product_Title IS NOT NULL THEN p.Product_Title
            ELSE u2.User_Username 
        END as targetName
    FROM Report r
    LEFT JOIN User u1 ON r.Reporting_User_ID = u1.User_ID
    LEFT JOIN User u2 ON r.Reported_User_ID = u2.User_ID
    LEFT JOIN Product p ON r.Reported_Item_ID = p.Product_ID
    ORDER BY r.Report_Creation_Date DESC";

    $listStmt = $pdo->query($listSql);
    $reports = $listStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)$stats['total'],
            'pending' => (int)$stats['pending'],
            'resolved' => (int)$stats['resolved'],
            'dismissed' => (int)$stats['dismissed']
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