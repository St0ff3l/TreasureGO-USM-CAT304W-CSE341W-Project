<?php
// File: api/admin_kb_save.php
// Description: Add a new entry to the knowledge base

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication Required']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validation
if (empty($input['question']) || empty($input['answer']) || empty($input['category'])) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

try {
    if (!isset($conn) && isset($pdo)) { $conn = $pdo; }

    // Insert into DB
    $sql = "INSERT INTO KnowledgeBase 
            (KB_Question, KB_Answer, KB_Category, KB_Last_Updated, Admin_ID) 
            VALUES (?, ?, ?, NOW(), ?)";

    $stmt = $conn->prepare($sql);
    $success = $stmt->execute([
        $input['question'],
        $input['answer'],
        $input['category'],
        $_SESSION['user_id']
    ]);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database insertion failed']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>