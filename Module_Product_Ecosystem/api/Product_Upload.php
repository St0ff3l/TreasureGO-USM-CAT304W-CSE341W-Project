<?php
// Product_Upload.php - 商品上传处理 (PDO版)

// 【新增 1】开启 Session，必须放在文件最第一行
session_start();

// 引入数据库配置
require_once 'config/treasurego_db_config.php';

header('Content-Type: application/json');

try {
    // 1. 获取数据库连接
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("无法连接到远程数据库");
    }

    // 2. 仅允许 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => '仅允许 POST 请求']);
        exit();
    }

    // 【新增 2】核心安全检查：判断用户是否登录
    // 假设你的登录页面在登录成功后设置了 $_SESSION['user_id']
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401); // 401 代表未授权
        echo json_encode(['success' => false, 'message' => '请先登录后再发布商品']);
        exit();
    }

    // 【修改 3】直接从 Session 获取 User_ID，不再从前端 POST 获取
    $user_id = $_SESSION['user_id'];

    // 3. 获取并清理其他表单数据 (User_ID 已经去掉了)
    $product_title = trim($_POST['product_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $condition = trim($_POST['condition'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // 接收地址
    $location = trim($_POST['address'] ?? 'Online');

    // 接收分类ID
    $category_id = intval($_POST['category_id'] ?? 100000005);

    // 其他默认字段
    $review_status = 'Pending';
    $product_status = 'Active';

    // 4. 数据验证
    if (empty($product_title)) throw new Exception("商品名称不能为空");
    if ($price <= 0) throw new Exception("价格必须大于 0");
    if (empty($condition)) throw new Exception("请选择商品条件");
    if (empty($description)) throw new Exception("商品描述不能为空");
    if (empty($location)) throw new Exception("请填写交易地址");

    // ... (中间所有图片处理代码保持完全不变，此处省略以节省空间) ...
    // ... 5. 处理图片文件 ...
    // ... Copy之前的图片处理代码即可 ...

    // 为了完整性，我保留了图片上传逻辑的占位，你不需要改动你原来的图片代码
    // 这里只要确保 image_paths 变量逻辑还在即可
    // ---------------------------------------------------------
    // 假设这里是你原来的图片处理代码
    $image_paths = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        // ... (保持你原来的代码不变) ...
        $upload_dir = '../../Public_Assets/images/products/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        // ... (简化的上传逻辑，请保留你原文件中的完整逻辑) ...
        // 这一块我没动，直接用你原来的逻辑就行
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_count = count($_FILES['images']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['images']['tmp_name'][$i];
                $file_name = $_FILES['images']['name'][$i];
                $new_filename = 'prod_' . time() . '_' . uniqid() . '.' . pathinfo($file_name, PATHINFO_EXTENSION);
                if(move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                    $image_paths[] = 'Public_Assets/images/products/' . $new_filename;
                }
            }
        }
    }
    // ---------------------------------------------------------

    // 6. 开启事务
    $pdo->beginTransaction();

    // 7. 插入商品
    $sql_product = "INSERT INTO Product (
        Product_Title,
        Product_Description,
        Product_Price,
        Product_Condition,
        Product_Status,
        Product_Created_Time,
        Product_Location,
        Product_Review_Status,
        User_ID,
        Category_ID
    ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql_product);
    $stmt->execute([
        $product_title,
        $description,
        $price,
        $condition,
        $product_status,
        $location,
        $review_status,
        $user_id,       // 这里使用的是从 Session 获取的安全 ID
        $category_id
    ]);

    $product_id = $pdo->lastInsertId();

    // 8. 插入图片
    if (!empty($image_paths)) {
        $sql_image = "INSERT INTO Product_Images (
            Product_ID,
            Image_URL,
            Image_is_primary,
            Image_Upload_Time
        ) VALUES (?, ?, ?, NOW())";

        $stmt_img = $pdo->prepare($sql_image);

        foreach ($image_paths as $index => $path) {
            $is_primary = ($index === 0) ? 1 : 0;
            $stmt_img->execute([$product_id, $path, $is_primary]);
        }
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '商品发布成功！',
        'product_id' => $product_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 如果是 401 错误，保持 401，否则 400
    if (http_response_code() !== 401) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>