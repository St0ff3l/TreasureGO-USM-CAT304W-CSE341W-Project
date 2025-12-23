<?php
// 文件名: report_get_my_list.php
// 路径: Module_Platform_Governance_AI_Services/api/

// 1. 禁止错误直接打印破坏 JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

// 开启缓冲区
ob_start();

// 2. Session 设置
session_set_cookie_params(0, '/');
session_start();

try {
    // 3. 引入数据库配置
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

    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed.");
    }

    // 4. 权限检查
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized: Please log in.");
    }

    $current_user_id = $_SESSION['user_id'];

    // 5. SQL 查询 (🔥 关键修改：添加 r.Report_Description)
    $sql = "SELECT 
                r.Report_ID,
                r.Report_Reason,
                r.Report_Description,  /* ✅ 新增：查询详细描述字段 */
                r.Report_Status,
                r.Report_Creation_Date,
                r.Reported_Item_ID,
                r.Reported_User_ID,
                u.User_Username AS Reported_Username,
                p.Product_Title AS Reported_Product_Name,
                aa.Admin_Action_Final_Resolution AS Admin_Reply
            FROM Report r
            LEFT JOIN User u ON r.Reported_User_ID = u.User_ID
            LEFT JOIN Product p ON r.Reported_Item_ID = p.Product_ID
            LEFT JOIN Administrative_Action aa ON r.Admin_Action_ID = aa.Admin_Action_ID
            WHERE r.Reporting_User_ID = :user_id
            ORDER BY r.Report_Creation_Date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $current_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reports = [];

    foreach ($rows as $row) {
        $type = 'user';
        $targetName = $row['Reported_Username'] ?? ('User #' . $row['Reported_User_ID']);

        if (!empty($row['Reported_Item_ID'])) {
            $type = 'product';
            $targetName = $row['Reported_Product_Name'] ?? ('Product #' . $row['Reported_Item_ID']);
        }

        // 6. 数组构造 (🔥 关键修改：把 details 加进去)
        $reports[] = [
            'id' => $row['Report_ID'],
            'type' => $type,
            'targetName' => $targetName,
            'reason' => $row['Report_Reason'],
            'details' => $row['Report_Description'] ?? '', /* ✅ 新增：映射数据库字段到 JSON 键 */
            'status' => ucfirst($row['Report_Status']),
            'date' => $row['Report_Creation_Date'],
            'adminReply' => $row['Admin_Reply']
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'data' => $reports]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>