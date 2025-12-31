<?php
// 文件名: report_get_my_list.php
// 路径: Module_Platform_Governance_AI_Services/api/

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

ob_start();
session_set_cookie_params(0, '/');
session_start();

try {
    // --- 数据库连接部分保持不变 ---
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

    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized: Please log in.");
    }

    $current_user_id = $_SESSION['user_id'];

    // ---------------------------------------------------------
    // 🔥 修改后的 SQL 查询
    // 1. 加入了 LEFT JOIN Report_Evidence
    // 2. 使用 GROUP_CONCAT 把多张图片的路径合并成一个字符串（用逗号分隔）
    // 3. 添加 GROUP BY r.Report_ID 以支持聚合函数
    // ---------------------------------------------------------
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
                /* ✅ 新增：获取图片路径，多张图用逗号隔开 */
                GROUP_CONCAT(re.File_Path SEPARATOR ',') AS Evidence_Paths
            FROM Report r
            LEFT JOIN User u ON r.Reported_User_ID = u.User_ID
            LEFT JOIN Product p ON r.Reported_Item_ID = p.Product_ID
            LEFT JOIN Report_Evidence re ON r.Report_ID = re.Report_ID /* ✅ 连接证据表 */
            WHERE r.Reporting_User_ID = :user_id
            GROUP BY r.Report_ID  /* ✅ 必须分组，因为是一对多 */
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

        // ✅ 处理图片数据：将逗号分隔的字符串转为数组
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

            // ✅ 新增：返回图片数组给前端
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