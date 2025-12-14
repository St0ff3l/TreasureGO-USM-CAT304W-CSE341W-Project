<?php
// api/session_status.php
header('Content-Type: application/json');

// 引入你的底层能力
require_once '../includes/auth.php';
require_once '../api/config/treasurego_db_config.php';

// 1. 开启 Session 检查
start_session_safe();

$response = [
    'is_logged_in' => false,
    'user' => null
];

// 2. 如果已登录，获取最基本的用户展示信息（头像、名字）
// ... 前面的代码不变 ...

if (is_logged_in()) {
    $user_id = get_current_user_id();

    try {
        $pdo = getDBConnection();
        // 👇 修改这里：增加查询 User_Profile_image
        $stmt = $pdo->prepare("SELECT User_Username, User_Role, User_Profile_image FROM User WHERE User_ID = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            $response['is_logged_in'] = true;
            $response['user'] = [
                'username' => $user['User_Username'],
                'role' => $user['User_Role'],
                // 👇 修改这里：如果有图就用图，没图就给 null
                'avatar_url' => $user['User_Profile_image'] ?? null
            ];
        }
    } catch (Exception $e) { /*...*/ }
}
// ...

echo json_encode($response);
?>