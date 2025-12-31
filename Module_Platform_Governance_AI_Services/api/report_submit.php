<?php
// ==============================================================================
// API: Submit Report (Product OR User)
// Path: Module_Platform_Governance_AI_Services/api/report_submit.php
// Method: POST (JSON or Multipart/Form-Data)
// Auth: Session required
// ==============================================================================

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

// 1) Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Auth Required']);
    exit;
}
$reportingUserId = (int)$_SESSION['user_id'];

// 2) Parse JSON OR FormData
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];

if (stripos($contentType, 'application/json') !== false) {
    // JSON 提交（无图片）
    $data = json_decode(file_get_contents('php://input'), true);
    if (is_array($data)) $input = $data;
} else {
    // FormData 提交（有图片）
    $input = $_POST;
}

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

// ==========================================
// 3) Validate (Updated Logic)
// ==========================================

// A. 允许 product 或 user
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

// B. 根据类型检查 ID
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

// Database Connection
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

    // ==========================================
    // 4) Context Logic (Product vs User)
    // ==========================================

    if ($type === 'product') {
        // --- 商品举报流程 ---
        // 必须查库确认商品存在，并获取卖家ID (作为被举报人)
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

        // 覆盖 reportedUserId，确保举报的是该商品的真正卖家
        $reportedUserId = (int)$ctxRow['Seller_User_ID'];
        $dbProductTitle = $ctxRow['Product_Title'] ?? null;

    } else {
        // --- 用户举报流程 ---
        // 直接使用前端传来的 reportedUserId
        // 强制将 item id 设为 null，因为这是针对人的举报
        $reportedItemId = null;

        // (可选) 这里可以加一段 SQL 检查该 user 是否存在，
        // 但为了性能通常可以直接让外键约束去处理，或者假设前端传的ID是有效的。
    }

    $contactEmail = isset($input['contactEmail']) ? trim((string)$input['contactEmail']) : null;

    // ==========================================
    // 5) Insert Report
    // ==========================================
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
        $reportedItemId // 注意：user 模式下这里是 null
    ]);

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database insertion failed']);
        exit;
    }
    $reportId = (int)$conn->lastInsertId();

    // ==========================================
    // 6) Handle Image Uploads
    // ==========================================
    $savedPaths = [];

    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $maxCount = 3;
        $maxSize = 2 * 1024 * 1024; // 2MB
        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        // 1. 修改物理存储路径
        // __DIR__ 是 /api 目录，向上退一级 (../) 就是 Module_Platform_Governance_AI_Services 目录
        $uploadDir = __DIR__ . '/../assets/images/report_images';

        if (!is_dir($uploadDir)) {
            // 递归创建目录
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
            // 文件名生成逻辑保持不变
            $filename = $reportId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            // 完整物理路径
            $absPath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($tmp, $absPath)) continue;

            // 2. 修改存入数据库的 Web 路径
            // 对应前端访问的 URL 路径
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