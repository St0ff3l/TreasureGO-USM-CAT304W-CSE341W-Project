<?php
// api/admin_get_stats.php
header('Content-Type: application/json');
require_once '../api/config/treasurego_db_config.php';
require_once '../includes/auth.php';

start_session_safe();

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

try {
    $pdo = getDBConnection();

    $stats = [];

    // === 基础统计 ===
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User");
    $stats['total_users'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE User_Status = 'active'");
    $stats['active_users'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE User_Status = 'pending'");
    $stats['pending_users'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE User_Status = 'banned'");
    $stats['banned_users'] = $stmt->fetch()['count'];

    // === 邮箱验证统计 ===
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE User_Email_Verified = 1");
    $verifiedCount = $stmt->fetch()['count'];
    $stats['verified_users'] = $verifiedCount;
    $stats['verification_rate'] = $stats['total_users'] > 0 ? round(($verifiedCount / $stats['total_users']) * 100, 1) : 0;

    // === 管理员数量 ===
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE User_Role = 'admin'");
    $stats['admin_count'] = $stmt->fetch()['count'];

    // === 最近30天每日注册量 ===
    $stmt = $pdo->prepare("SELECT 
        DATE(User_Created_At) as date,
        COUNT(*) as count
        FROM User
        WHERE User_Created_At >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(User_Created_At)
        ORDER BY date ASC");
    $stmt->execute();
    $dailyRegistrations = $stmt->fetchAll();

    // 填充缺失的日期（确保连续）
    $registrationData = [];
    $startDate = new DateTime('-29 days');
    $endDate = new DateTime();
    $dateMap = [];
    foreach ($dailyRegistrations as $row) {
        $dateMap[$row['date']] = (int)$row['count'];
    }

    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dateStr = $currentDate->format('Y-m-d');
        $registrationData[] = [
            'date' => $dateStr,
            'count' => $dateMap[$dateStr] ?? 0
        ];
        $currentDate->modify('+1 day');
    }
    $stats['daily_registrations'] = $registrationData;

    // === 最近7天新用户 ===
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE User_Created_At >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['new_users_7days'] = $stmt->fetch()['count'];

    // === 最近30天新用户 ===
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE User_Created_At >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['new_users_30days'] = $stmt->fetch()['count'];

    // === 用户状态分布（用于饼图） ===
    $stmt = $pdo->query("SELECT User_Status, COUNT(*) as count FROM User GROUP BY User_Status");
    $statusDistribution = $stmt->fetchAll();
    $stats['status_distribution'] = $statusDistribution;

    // === 今日注册数 ===
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE DATE(User_Created_At) = CURDATE()");
    $stats['today_registrations'] = $stmt->fetch()['count'];

    // === 本周注册数 ===
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM User WHERE YEARWEEK(User_Created_At) = YEARWEEK(NOW())");
    $stats['this_week_registrations'] = $stmt->fetch()['count'];

    // === 尝试获取登录日志（如果User_Logins表存在） ===
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM User_Logins WHERE Login_Time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['logins_7days'] = $stmt->fetch()['count'] ?? 0;

        // 最近7天每日登录量
        $stmt = $pdo->prepare("SELECT 
            DATE(Login_Time) as date,
            COUNT(*) as count
            FROM User_Logins
            WHERE Login_Time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(Login_Time)
            ORDER BY date ASC");
        $stmt->execute();
        $dailyLogins = $stmt->fetchAll();

        $loginData = [];
        $loginStartDate = new DateTime('-6 days');
        $loginDateMap = [];
        foreach ($dailyLogins as $row) {
            $loginDateMap[$row['date']] = (int)$row['count'];
        }

        $currentLoginDate = clone $loginStartDate;
        while ($currentLoginDate <= $endDate) {
            $dateStr = $currentLoginDate->format('Y-m-d');
            $loginData[] = [
                'date' => $dateStr,
                'count' => $loginDateMap[$dateStr] ?? 0
            ];
            $currentLoginDate->modify('+1 day');
        }
        $stats['daily_logins'] = $loginData;
    } catch (Exception $e) {
        // User_Logins表不存在或字段名不同，忽略
        $stats['logins_7days'] = 0;
        $stats['daily_logins'] = [];
    }

    echo json_encode(['success' => true, 'data' => $stats]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>