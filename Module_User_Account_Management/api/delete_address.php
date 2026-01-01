<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config/treasurego_db_config.php';

$data = json_decode(file_get_contents("php://input"), true);
$addr_id = isset($data['Address_ID']) ? $data['Address_ID'] : null;

if (!$addr_id) {
    echo json_encode(['status' => 'fail', 'message' => 'Missing ID']);
    exit;
}

try {
    // Can only delete own address
    $sql = "DELETE FROM Address WHERE Address_ID = :aid AND Address_User_ID = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':aid' => $addr_id, ':uid' => $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Deleted']);
    } else {
        echo json_encode(['status' => 'fail', 'message' => 'Address not found or unauthorized']);
    }
} catch (PDOException $e) {
    // If referenced by an order, usually cannot be hard deleted
    if ($e->getCode() == '23000') {
        echo json_encode(['status' => 'fail', 'message' => 'Cannot delete address used in past orders.']);
    } else {
        echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
    }
}
?>