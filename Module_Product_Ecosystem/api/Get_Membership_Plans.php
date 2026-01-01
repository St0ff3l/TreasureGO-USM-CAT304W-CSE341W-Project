<?php
// 1. Include database configuration (ensure the path is correct, adjust based on your screenshot)
require_once __DIR__ . '/config/treasurego_db_config.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }

    // 2. Query all membership plans
    $sql = "SELECT * FROM Membership_Plans";
    $stmt = $pdo->query($sql);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Prepare data structure required by the frontend
    // Format expected by frontend:
    // {
    //   'monthly': { vip: 9.9, svip: 29.9, label: '/ month' },
    //   'quarterly': { vip: 26.73, svip: 80.73, label: '/ quarter' },
    //   ...
    // }

    $response = [
        'monthly'   => ['label' => '/ month'],
        'quarterly' => ['label' => '/ quarter'],
        'yearly'    => ['label' => '/ year']
    ];

    foreach ($plans as $plan) {
        $days = $plan['Membership_Duration_Days'];
        $tier = strtolower($plan['Membership_Tier']); // 'vip' or 'svip'
        $price = floatval($plan['Membership_Price']); // Ensure it is a number

        // Map to corresponding period key based on days
        if ($days == 30) {
            $response['monthly'][$tier] = $price;
        } elseif ($days == 90) {
            $response['quarterly'][$tier] = $price;
        } elseif ($days == 365) {
            $response['yearly'][$tier] = $price;
        }
    }

    // 4. Return JSON
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>