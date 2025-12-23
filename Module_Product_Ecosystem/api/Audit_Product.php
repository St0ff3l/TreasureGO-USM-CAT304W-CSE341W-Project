<?php
// Module_Product_Ecosystem/api/Audit_Product.php

header('Content-Type: application/json');

// 引入数据库配置文件
require_once __DIR__ . '/config/treasurego_db_config.php';

// 获取 POST JSON 数据
$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? null;
$action = $input['action'] ?? null; // 前端传来的动作: 'approve' 或 'reject'
$reason = $input['reason'] ?? null; // 拒绝理由

// 简单验证参数
if (!$productId || !$action) {
    echo json_encode(['success' => false, 'msg' => 'Missing parameters']);
    exit;
}

try {
    $newStatus = '';
    $comment = null;

    if ($action === 'approve') {
        $newStatus = 'approved';
        $comment = 'Approved by Admin'; // 通过时也可以写个默认备注
    } elseif ($action === 'reject') {
        $newStatus = 'rejected';
        $comment = $reason; // 写入拒绝理由
    } else {
        throw new Exception("Invalid action type");
    }

    // 更新 Product 表
    // 只要修改状态和备注字段
    $sql = "UPDATE Product 
            SET Product_Review_Status = ?, 
                Product_Review_Comment = ? 
            WHERE Product_ID = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt->execute([$newStatus, $comment, $productId])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'msg' => 'Update failed']);
    }

} catch (Exception $e) {
    error_log("Audit_Product Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'msg' => 'Database Error: ' . $e->getMessage()]);
}
?>