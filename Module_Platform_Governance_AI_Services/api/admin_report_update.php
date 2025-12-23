<?php
header('Content-Type: application/json');
require_once 'config/treasurego_db_config.php';

// 获取前端发送的 JSON 数据
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
    exit;
}

$reportId = $input['id'];
$newStatus = $input['status']; // 'Resolved' 或 'Dismissed'
$adminNote = $input['reply'] ?? '';

try {
    // 开启事务以保证数据一致性（如果后续需要同时插入管理操作日志）
    $pdo->beginTransaction();

    // 更新报告状态
    // 注意：根据你的表结构，如果想记录处理说明，可能需要关联 Administrative_Action 表
    // 这里先执行最核心的状态更新
    $sql = "UPDATE Report SET Report_Status = ? WHERE Report_ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newStatus, $reportId]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Report updated successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>