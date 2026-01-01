<?php
// api/session_status.php
header('Content-Type: application/json');

// Include underlying capabilities
require_once '../includes/auth.php';
require_once '../api/config/treasurego_db_config.php';

// 1. Start Session check
start_session_safe();

$response = [
    'is_logged_in' => false,
    'user' => null
];

// 2. If logged in, get basic user display info (avatar, name)
// ... Previous code remains unchanged ...

if (is_logged_in()) {
    $user_id = get_current_user_id();

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            // Return session info even if database connection fails
            $response['is_logged_in'] = true;
            $response['user'] = [
                'user_id' => $user_id,
                'username' => $_SESSION['user_username'] ?? null,
                'role' => $_SESSION['user_role'] ?? null,
                'avatar_url' => null
            ];
            echo json_encode($response);
            exit;
        }

        $stmt = $pdo->prepare("SELECT User_Username AS User_Username, User_Role AS User_Role, User_Profile_Image AS User_Profile_Image FROM User WHERE User_ID = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            $response['is_logged_in'] = true;
            $response['user'] = [
                'user_id' => $user_id, // Add user_id to response
                'username' => $user['User_Username'],
                'role' => $user['User_Role'],
                // 👇 Modify here: Use image if available, otherwise null
                'avatar_url' => $user['User_Profile_Image'] ?? null
            ];
        } else {
            // If user row not found, fallback to session
            $response['is_logged_in'] = true;
            $response['user'] = [
                'user_id' => $user_id,
                'username' => $_SESSION['user_username'] ?? null,
                'role' => $_SESSION['user_role'] ?? null,
                'avatar_url' => null
            ];
        }
    } catch (Throwable $e) {
        // Never interrupt JSON output
        $response['is_logged_in'] = true;
        $response['user'] = [
            'user_id' => $user_id,
            'username' => $_SESSION['user_username'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'avatar_url' => null
        ];
        $response['warning'] = 'session_status_db_error';
    }
}
// ...

echo json_encode($response);

?>