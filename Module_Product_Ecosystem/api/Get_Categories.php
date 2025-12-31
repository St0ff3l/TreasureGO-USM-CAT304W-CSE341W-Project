<?php
// 1. 引入你提供的数据库配置文件
require_once __DIR__ . '/config/treasurego_db_config.php';

// 设置返回内容为 JSON 格式
header('Content-Type: application/json');

try {
    // 获取数据库连接
    $pdo = getDatabaseConnection();

    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 2. 查询所有分类，按 ID 排序
    // 注意：这里我们取出了 Parent_ID 用来做父子关联
    $sql = "SELECT Category_ID, Category_Parent_ID, Category_Name FROM Categories ORDER BY Category_ID ASC";
    $stmt = $pdo->query($sql);
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 将扁平数据整理成树形结构 (Tree Structure)
    $tree = [];
    $references = [];

    // 第一遍循环：以 ID 为 Key 建立索引
    foreach ($allCategories as $key => &$cat) {
        $cat['children'] = []; // 准备一个空数组放子类
        $references[$cat['Category_ID']] = &$cat;
    }

    // 第二遍循环：把子类塞进父类的 children 数组里
    foreach ($allCategories as $key => &$cat) {
        if ($cat['Category_Parent_ID'] && isset($references[$cat['Category_Parent_ID']])) {
            // 如果有父ID，就把它加到父类的 children 里
            $references[$cat['Category_Parent_ID']]['children'][] = &$cat;
        } else {
            // 如果没有父ID (是 NULL)，它就是顶级大类
            $tree[] = &$cat;
        }
    }

    // 4. 返回 JSON 数据给前端
    echo json_encode([
        'success' => true,
        'data' => $tree
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>