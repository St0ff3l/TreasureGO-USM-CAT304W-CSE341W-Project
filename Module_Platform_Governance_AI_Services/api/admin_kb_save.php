<?php
// Creates a new KnowledgeBase entry.
//
// Request:
// - Method: POST (JSON)
// - Body: { "question": string, "answer": string, "category": string }
//
// Auth:
// - Requires an authenticated session (admin panel usage).

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';
header("Content-Type: application/json; charset=UTF-8");

// Session-based authentication guard.
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication Required']);
    exit;
}

// Parse JSON request body.
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields.
if (empty($input['question']) || empty($input['answer']) || empty($input['category'])) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

try {
    // Support both config styles: some configs expose $conn, others expose $pdo.
    if (!isset($conn) && isset($pdo)) { $conn = $pdo; }

    // Persist the new entry and track the admin user who created it.
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