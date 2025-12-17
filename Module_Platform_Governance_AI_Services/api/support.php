<?php
// ============================================
// TreasureGO AI Support API (AI 语义识别版)
// ============================================

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/treasurego_db_config.php';
require_once __DIR__ . '/config/DeepSeekService.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

try {
    // 1. 权限检查
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Auth Required']);
        exit;
    }
    $currentUserId = $_SESSION['user_id'];

    // 2. 接收数据
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);
    if (!isset($input['messages'])) { throw new Exception("Missing messages"); }

    $userMessages = $input['messages']; // 用户原本的聊天记录
    $lastUserMessage = end($userMessages)['content'];

    // ---------------------------------------------------------
    // 🧠 核心升级：注入系统提示词 (System Prompt)
    // ---------------------------------------------------------
    // 我们在这个数组的最前面，插一条“给 AI 的秘密指令”
    // 告诉 AI 必须按我们的格式输出意图
    $systemInstruction = [
        "role" => "system",
        "content" => "你是 TreasureGo 的客服 AI。
请根据用户的输入，先分析其意图，必须归类为以下之一：
[Refund_Return, Shipping_Status, Account_Issue, Complaint, Learning_Work, Creative_Writing, Life_QA, Tech_Coding, General_Inquiry]

**严格输出规则**：
你的回复必须以特殊标签 {INTENT:类别名} 开头，然后换行才是回复给用户的内容。

例如：
用户问：'钱怎么还没退回来'
你回复：'{INTENT:Refund_Return} 您好，请提供订单号...'

用户问：'我想写首诗'
你回复：'{INTENT:Creative_Writing} 好的，请问主题是...'

不要解释你的分类理由，直接输出标签和回复。"
    ];

    // 将系统指令合并到消息列表的最前面
    array_unshift($userMessages, $systemInstruction);

    // 3. 调用 AI
    $aiService = new DeepSeekService();
    // 注意：这里发送的是包含了系统指令的新数组
    $result = $aiService->sendMessage($userMessages);
    $rawAiContent = $result['choices'][0]['message']['content'] ?? "{INTENT:General_Inquiry} System Error";

    // ---------------------------------------------------------
    // ✂️ 解析 AI 返回的内容 (提取意图 + 清洗回复)
    // ---------------------------------------------------------
    $intent = 'General_Inquiry'; // 默认值
    $finalReply = $rawAiContent;

    // 使用正则提取 {INTENT:XXX}
    if (preg_match('/\{INTENT:(.*?)\}/', $rawAiContent, $matches)) {
        $intent = trim($matches[1]); // 拿到意图 (例如 Refund_Return)

        // 把标签从回复里删掉，否则用户会看到奇怪的代码
        $finalReply = trim(str_replace($matches[0], '', $rawAiContent));
    }

    // 4. 数据库写入
    $insertedLogId = null;
    if (!isset($conn) && isset($pdo)) { $conn = $pdo; }

    if (isset($conn)) {
        $sql = "INSERT INTO AIChatLog 
                (AILog_User_Query, AILog_Response, AILog_Intent_Recognized, AILog_Is_Resolved, AILog_Timestamp, User_ID) 
                VALUES (?, ?, ?, 0, NOW(), ?)";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $success = $stmt->execute([
                $lastUserMessage,
                $finalReply, // 存入干净的回复
                $intent,     // 存入 AI 识别出的意图
                $currentUserId
            ]);
            if ($success) {
                $insertedLogId = $conn->lastInsertId();
            }
        }
    }

    // 5. 修改返回结果
    // 我们要“骗”过前端，把 result 里的 content 改成处理过的 clean content
    // 否则前端界面上会显示 {INTENT:xxx}
    $result['choices'][0]['message']['content'] = $finalReply;
    $result['db_log_id'] = $insertedLogId;

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>