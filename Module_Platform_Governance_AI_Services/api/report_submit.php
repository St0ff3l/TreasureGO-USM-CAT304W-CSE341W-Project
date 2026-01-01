<?php
// Submits a report against a product or a user.
//
// Method: POST
// Content-Type:
// - application/json (text-only report)
// - multipart/form-data (report with up to 3 evidence images)
//
// Auth: requires a logged-in session

session_start();
require_once __DIR__ . '/config/treasurego_db_config.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Authentication guard.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Auth Required']);
    exit;
}
$reportingUserId = (int)$_SESSION['user_id'];

// Read request payload from either JSON or multipart/form-data.
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];

if (stripos($contentType, 'application/json') !== false) {
    // JSON submission (no images)
    $data = json_decode(file_get_contents('php://input'), true);
    if (is_array($data)) $input = $data;
} else {
    // FormData submission (with images)
    $input = $_POST;
}

// Request fields.
$type = isset($input['type']) ? strtolower(trim((string)$input['type'])) : 'product';
$reportReason = isset($input['reportReason']) ? trim((string)$input['reportReason']) : '';
$details = isset($input['details']) ? trim((string)$input['details']) : '';

$reportedUserId = null;
if (isset($input['reportedUserId']) && $input['reportedUserId'] !== null && $input['reportedUserId'] !== '') {
    $reportedUserId = (int)$input['reportedUserId'];
}

$reportedItemId = null;
if (isset($input['reportedItemId']) && $input['reportedItemId'] !== null && $input['reportedItemId'] !== '') {
    $reportedItemId = (int)$input['reportedItemId'];
}

// Validate request.
$allowedTypes = ['product', 'user'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid report type. Supported: product, user.']);
    exit;
}

if ($reportReason === '' || mb_strlen($reportReason, 'UTF-8') > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid report reason']);
    exit;
}

if ($details === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Details are required']);
    exit;
}

// Validate required IDs based on report type.
if ($type === 'product' && (!$reportedItemId || $reportedItemId <= 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID (reportedItemId) is required for product reports']);
    exit;
}

if ($type === 'user' && (!$reportedUserId || $reportedUserId <= 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID (reportedUserId) is required for user reports']);
    exit;
}

// Resolve database connection handle.
if (!isset($conn) && isset($pdo)) {
    $conn = $pdo;
}

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $dbProductTitle = null;

    // Context resolution:
    // - Product report: verify the product exists and infer the seller as the reported user.
    // - User report: report the specified user and ignore any reported item ID.

    if ($type === 'product') {
        // Product report path.
        // Verify the product exists and read its seller ID.
        $ctxSql = "SELECT p.User_ID AS Seller_User_ID, p.Product_Title
                   FROM Product p
                   WHERE p.Product_ID = ?
                   LIMIT 1";
        $ctxStmt = $conn->prepare($ctxSql);
        $ctxStmt->execute([$reportedItemId]);
        $ctxRow = $ctxStmt->fetch(PDO::FETCH_ASSOC);

        if (!$ctxRow) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }

        // Override reportedUserId so the report targets the actual seller for this product.
        $reportedUserId = (int)$ctxRow['Seller_User_ID'];
        $dbProductTitle = $ctxRow['Product_Title'] ?? null;

    } else {
        // User report path.
        // Treat this as a user-only report; no product context is stored.
        $reportedItemId = null;

        // An optional existence check for the reported user could be added here.
    }

    $contactEmail = isset($input['contactEmail']) ? trim((string)$input['contactEmail']) : null;

    // Insert the report record.
    $sql = "INSERT INTO Report (
                Report_Type,
                Report_Reason,
                Report_Description,
                Report_Status,
                Report_Creation_Date,
                Admin_Action_ID,
                Reporting_User_ID,
                Report_Contact_Email,
                Reported_User_ID,
                Reported_Item_ID
            ) VALUES (?, ?, ?, 'Pending', NOW(), NULL, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $ok = $stmt->execute([
        $type,
        $reportReason,
        $details,
        $reportingUserId,
        $contactEmail,
        $reportedUserId,
        $reportedItemId // In user mode this is null.
    ]);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database insertion failed']);
        exit;
    }
    $reportId = (int)$conn->lastInsertId();

    // Save up to 3 evidence images and store their public paths.
    $savedPaths = [];

    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $maxCount = 3;
        $maxSize = 2 * 1024 * 1024; // 2MB
        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        // Store uploaded files under this module's assets directory.
        $uploadDir = __DIR__ . '/../assets/images/report_images';

        if (!is_dir($uploadDir)) {
            // Create the directory if it does not exist.
            @mkdir($uploadDir, 0775, true);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $count = min(count($_FILES['images']['name']), $maxCount);

        for ($i = 0; $i < $count; $i++) {
            $err  = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            $tmp  = $_FILES['images']['tmp_name'][$i] ?? null;
            $size = (int)($_FILES['images']['size'][$i] ?? 0);

            if ($err === UPLOAD_ERR_NO_FILE) continue;
            if ($err !== UPLOAD_ERR_OK || !$tmp) continue;
            if ($size <= 0 || $size > $maxSize) continue;

            $mime = $finfo->file($tmp);
            if (!isset($allowedMime[$mime])) continue;

            $ext = $allowedMime[$mime];
            // Filename generation is based on the report ID + timestamp + random suffix.
            $filename = $reportId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            // Absolute filesystem path.
            $absPath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($tmp, $absPath)) continue;

            // Public URL path stored in the database.
            $webPath = '/Module_Platform_Governance_AI_Services/assets/images/report_images/' . $filename;
            $savedPaths[] = $webPath;

            $evStmt = $conn->prepare("INSERT INTO Report_Evidence (Report_ID, File_Path) VALUES (?, ?)");
            $evStmt->execute([$reportId, $webPath]);
        }
    }

    echo json_encode([
        'success' => true,
        'report_id' => $reportId,
        'status' => 'Pending',
        'report_type' => $type,
        'reported_user_id' => $reportedUserId,
        'product_title' => $dbProductTitle,
        'evidence' => $savedPaths
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
?>