<?php
// =============================================================
// File: api/admin_kb_getlist.php
// Description: Fetch all knowledge base entries for the admin panel
// =============================================================

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';

// Set response header to JSON
header("Content-Type: application/json; charset=UTF-8");

// 1. Authentication Check
// Ensure the user is logged in before accessing sensitive data
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication Required']);
    exit;
}

try {
    // 2. Database Connection Compatibility
    // Ensure $conn is available if config uses $pdo
    if (!isset($conn) && isset($pdo)) {
        $conn = $pdo;
    }

    if (!isset($conn)) {
        throw new Exception("Database connection not established.");
    }

    // 3. Fetch Data
    // Select necessary columns ordered by the most recently updated
    $sql = "SELECT KB_ID, KB_Question, KB_Answer, KB_Category, KB_Last_Updated 
            FROM KnowledgeBase 
            ORDER BY KB_Last_Updated DESC";

    $stmt = $conn->query($sql);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Return JSON Response
    echo json_encode([
        'success' => true,
        'count' => count($list), // Optional: return count for debugging
        'data' => $list
    ]);

} catch (Exception $e) {
    // Handle errors (e.g., DB connection failure)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>