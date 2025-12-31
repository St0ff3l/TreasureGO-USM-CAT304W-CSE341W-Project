<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'fail', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config/treasurego_db_config.php';

try {
    // Delete the user account
    // Note: Depending on foreign key constraints, this might fail if related data isn't handled (ON DELETE CASCADE).
    // Assuming the database is set up to handle cascades or we just want to try deleting the user.
    $sql = "DELETE FROM User WHERE User_ID = :uid";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':uid' => $_SESSION['user_id']]);

    if ($stmt->rowCount() > 0) {
        // Destroy session after deletion
        session_unset();
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Account deleted successfully']);
    } else {
        echo json_encode(['status' => 'fail', 'message' => 'User not found or already deleted']);
    }
} catch (PDOException $e) {
    // Handle foreign key constraint violations
    if ($e->getCode() == '23000') {
        echo json_encode(['status' => 'fail', 'message' => 'Cannot delete account due to active orders or listings. Please resolve them first.']);
    } else {
        echo json_encode(['status' => 'fail', 'message' => $e->getMessage()]);
    }
}
?>
