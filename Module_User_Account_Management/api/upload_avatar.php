<?php
// api/upload_avatar.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';

start_session_safe();

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['avatar'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Validate type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.']);
    exit();
}

// Validate size
if ($file['size'] > $maxSize) {
    echo json_encode(['status' => 'error', 'message' => 'File size exceeds 5MB limit.']);
    exit();
}

// Prepare upload directory
// We need to go up from api/ -> Module_User... -> Project Root -> Public_Assets
$uploadDir = '../../Public_Assets/uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$userId = get_current_user_id();
$filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $filename;

// Move file
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    // Update Database
    try {
        $pdo = getDBConnection();
        // Store path relative to project root (e.g., Public_Assets/uploads/avatars/...)
        $dbPath = 'Public_Assets/uploads/avatars/' . $filename;
        
        // Update User table
        $stmt = $pdo->prepare("UPDATE User SET User_Profile_Image = ? WHERE User_ID = ?");
        $stmt->execute([$dbPath, $userId]);
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Avatar updated successfully',
            'url' => '../../' . $dbPath // Return relative path for immediate frontend use
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
}
?>
