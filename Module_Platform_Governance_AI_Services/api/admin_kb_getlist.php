<?php
// Lists KnowledgeBase entries for the admin panel.
//
// Response:
// - success: boolean
// - count: number of returned rows
// - data: array of KnowledgeBase records

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';

header("Content-Type: application/json; charset=UTF-8");

// Session-based authorization guard.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication Required']);
    exit;
}

try {
    // Support both config styles: some configs expose $conn, others expose $pdo.
    if (!isset($conn) && isset($pdo)) {
        $conn = $pdo;
    }

    if (!isset($conn)) {
        throw new Exception("Database connection not established.");
    }

    // Fetch entries ordered by the most recently updated.
    $sql = "SELECT KB_ID, KB_Question, KB_Answer, KB_Category, KB_Last_Updated 
            FROM KnowledgeBase 
            ORDER BY KB_Last_Updated DESC";

    $stmt = $conn->query($sql);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($list),
        'data' => $list
    ]);

} catch (Exception $e) {
    // Return a 500 for unexpected failures.
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>