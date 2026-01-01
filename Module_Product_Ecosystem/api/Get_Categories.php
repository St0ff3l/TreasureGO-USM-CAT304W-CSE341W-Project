<?php
// 1. Include the provided database configuration file
require_once __DIR__ . '/config/treasurego_db_config.php';

// Set response content type to JSON
header('Content-Type: application/json');

try {
    // Get database connection
    $pdo = getDatabaseConnection();

    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 2. Query all categories, sorted by ID
    // Note: We select Parent_ID here to establish parent-child relationships
    $sql = "SELECT Category_ID, Category_Parent_ID, Category_Name FROM Categories ORDER BY Category_ID ASC";
    $stmt = $pdo->query($sql);
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Organize flat data into a Tree Structure
    $tree = [];
    $references = [];

    // First pass: Build an index using ID as the Key
    foreach ($allCategories as $key => &$cat) {
        $cat['children'] = []; // Prepare an empty array for subcategories
        $references[$cat['Category_ID']] = &$cat;
    }

    // Second pass: Add subcategories into the parent's children array
    foreach ($allCategories as $key => &$cat) {
        if ($cat['Category_Parent_ID'] && isset($references[$cat['Category_Parent_ID']])) {
            // If there is a parent ID, add it to the parent's children array
            $references[$cat['Category_Parent_ID']]['children'][] = &$cat;
        } else {
            // If there is no parent ID (it is NULL), it is a top-level category
            $tree[] = &$cat;
        }
    }

    // 4. Return JSON data to the frontend
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