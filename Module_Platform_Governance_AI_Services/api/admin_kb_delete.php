<?php
// Deletes a KnowledgeBase entry by ID.
//
// Request:
// - Method: POST (JSON)
// - Body: { "id": <KB_ID> }
//
// Response:
// - { "success": true } on success
// - { "success": false, "error": "..." } on failure

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';

// CORS + JSON response headers.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// CORS preflight.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Session-based authentication guard.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication Required']);
    exit;
}

// Parse JSON request body.
$input = json_decode(file_get_contents('php://input'), true);

// Deletion requires a KnowledgeBase primary key.
if (empty($input['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID is missing']);
    exit;
}

try {
    // Use the DB connection provided by the module config.
    if (!isset($conn) && isset($pdo)) { $conn = $pdo; }

    $sql = "DELETE FROM KnowledgeBase WHERE KB_ID = ?";
    $stmt = $conn->prepare($sql);
    $success = $stmt->execute([$input['id']]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Delete operation failed in DB']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>