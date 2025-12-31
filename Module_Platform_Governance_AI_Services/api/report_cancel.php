<?php
// 文件名: report_cancel.php
// 路径: Module_Platform_Governance_AI_Services/api/

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';

header('Content-Type: application/json');

// 1. 登录检查
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// 2. 获取参数
$input = json_decode(file_get_contents('php://input'), true);
$reportId = $input['report_id'] ?? null;

if (!$reportId) {
    echo json_encode(['success' => false, 'message' => 'Missing Report ID']);
    exit;
}

try {
    if (!isset($conn)) $conn = $pdo; // 确保连接对象存在

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
        // 如果影响行数为0，说明ID不对，或者状态不是Pending，或者不是该用户的
        echo json_encode(['success' => false, 'message' => 'Cannot cancel: Report not found or already processed.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>