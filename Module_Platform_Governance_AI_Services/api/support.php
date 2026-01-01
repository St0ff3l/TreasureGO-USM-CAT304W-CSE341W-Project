<?php
// AI support endpoint used by the customer support chat UI.
//
// Flow overview:
// - Require a logged-in user.
// - Read the user's latest message from either {messages:[...]} or {message:"..."}.
// - Load KnowledgeBase entries and embed them into the system prompt.
// - Pull recent AIChatLog history to provide conversation context.
// - Call the model service and parse {TYPE:...} / {INTENT:...} tags from the reply.
// - Optionally show resolution buttons for solution replies.

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
    // Authentication guard.
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Auth Required']);
        exit;
    }
    $currentUserId = $_SESSION['user_id'];

    // Read the current user message from the request body.
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    $currentMsgContent = "";
    if (isset($input['messages']) && is_array($input['messages'])) {
        $currentMsgContent = trim(end($input['messages'])['content']);
    } elseif (isset($input['message'])) {
        $currentMsgContent = trim($input['message']);
    }

    if (empty($currentMsgContent)) { throw new Exception("Empty message"); }

    // Database connection.
    if (!isset($conn) && isset($pdo)) { $conn = $pdo; }
    if (!isset($conn)) { throw new Exception("Database connection failed"); }

    // Load KnowledgeBase entries and format them as prompt context.
    $kbStr = "";
    try {
        $conn->exec("SET NAMES utf8mb4");
        $stmtKB = $conn->query("SELECT KB_Question, KB_Answer, KB_Category FROM KnowledgeBase");
        $rows = $stmtKB->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $q = trim($row['KB_Question']);
            $a = trim($row['KB_Answer']);
            $cat = !empty($row['KB_Category']) ? trim($row['KB_Category']) : 'General_Inquiry';

            if (!empty($q) && !empty($a)) {
                $kbStr .= "### [Category: $cat]\nQ: $q\nA: $a\n\n";
            }
        }
    } catch (Exception $e) {
        $kbStr = "Error loading KB.";
    }

    // Very short / numeric inputs are treated as low-signal and answered with suggestions.
    if (mb_strlen($currentMsgContent, 'UTF-8') <= 1 || is_numeric($currentMsgContent)) {
        $recSql = "SELECT KB_Question FROM KnowledgeBase ORDER BY RAND() LIMIT 3";
        $recStmt = $conn->query($recSql);
        $questions = $recStmt->fetchAll(PDO::FETCH_COLUMN);

        $replyText = "Hello! / 您好！ / Hai!\nAre you looking for these? 👇\n\n";
        if ($questions) {
            foreach ($questions as $q) { $replyText .= "🔹 " . $q . "\n"; }
        } else {
            $replyText .= "\nPlease type a keyword (e.g., Refund).";
        }

        echo json_encode([
            'choices' => [['message' => ['content' => $replyText]]],
            'db_log_id' => null,
            'show_resolution_buttons' => false
        ]);
        exit;
    }

    // Build the system prompt that includes the KnowledgeBase content and response protocol.
    $finalMessages = [];

    $systemContent = "You are TreasureGo's AI Customer Support.

【OFFICIAL KNOWLEDGE BASE (THE TRUTH)】:
$kbStr

【EXECUTION PROTOCOL】:

1. **STEP 1: SEARCH KNOWLEDGE BASE (Priority #1)**
   - Check if the user's input matches ANY topic in the Knowledge Base above.
   - **CRITICAL**: Even if you said 'I don't know' in the past history, check the KB *AGAIN* now. If found, ANSWER IT.
   - Output: {TYPE:SOLUTION} {INTENT:Category} [Answer translated to User's Language].

2. **STEP 2: CHECK MEMORY & CHAT (Priority #2)**
   - If NOT found in KB, check if user is chatting ('Hello', 'Joke') or referencing past context ('Who am I?').
   - Output: {TYPE:CHAT} {INTENT:General_Inquiry} [Natural Reply].

3. **STEP 3: FALLBACK (Priority #3)**
   - If and ONLY if Step 1 and Step 2 fail.
   - **CHINESE**: \"{TYPE:FALLBACK} {INTENT:General_Inquiry} 抱歉，我在知识库中找不到相关信息。请点击下方链接联系人工客服寻求帮助。\"
   - **ENGLISH**: \"{TYPE:FALLBACK} {INTENT:General_Inquiry} I'm sorry, I couldn't find that information in our Knowledge Base. Please click the link below for human support.\"
   - **MALAY**: \"{TYPE:FALLBACK} {INTENT:General_Inquiry} Maaf, saya tidak menjumpai maklumat tersebut. Sila klik pautan di bawah untuk bantuan manusia.\"";

    $finalMessages[] = ["role" => "system", "content" => $systemContent];

    // Load recent chat history for context (up to 10 records).
    $historySql = "SELECT AILog_User_Query, AILog_Response 
                   FROM AIChatLog 
                   WHERE User_ID = ? 
                   ORDER BY AILog_ID DESC LIMIT 10";

    $stmtHistory = $conn->prepare($historySql);
    $stmtHistory->execute([$currentUserId]);
    $historyRows = array_reverse($stmtHistory->fetchAll(PDO::FETCH_ASSOC));

    foreach ($historyRows as $row) {
        $cleanResponse = preg_replace('/\{.*?\}/', '', $row['AILog_Response']);
        $cleanResponse = trim($cleanResponse);
        if (strpos($cleanResponse, 'System Debug') !== false) continue;

        if (!empty($row['AILog_User_Query'])) {
            $finalMessages[] = ["role" => "user", "content" => $row['AILog_User_Query']];
        }
        if (!empty($cleanResponse)) {
            $finalMessages[] = ["role" => "assistant", "content" => $cleanResponse];
        }
    }

    // Add a final instruction layer to enforce language matching and tag formatting.

    // Detect whether the current message contains Han characters (Chinese).
    $isChinese = preg_match("/\p{Han}+/u", $currentMsgContent);
    $langNote = $isChinese ? "User speaks CHINESE." : "User speaks ENGLISH/MALAY.";

    // Instruction wrapper that the model receives as the final "user" message.
    $injection = <<<EOT
User New Input: "$currentMsgContent"

[SYSTEM INSTRUCTION]:
1. $langNote Reply in this language.
2. **FORCE RE-CHECK KNOWLEDGE BASE**: Ignore previous "I'm sorry" or "Fallback" messages in history.
3. **MANDATORY FORMATTING (CRITICAL)**: 
   - If you found the answer in the Knowledge Base (e.g. Refund, Password, Shipping), START YOUR REPLY WITH: "{TYPE:SOLUTION} {INTENT:Support}".
   - If this is just a greeting or chat, START WITH: "{TYPE:CHAT} {INTENT:Chat}".
   - If you cannot find the answer, START WITH: "{TYPE:FALLBACK} {INTENT:Support}".
4. If "$currentMsgContent" is in the KB, ANSWER IT NOW using the KB content.
EOT;

    $finalMessages[] = ["role" => "user", "content" => $injection];

    // Call the model service.
    $aiService = new DeepSeekService();
    $result = $aiService->sendMessage($finalMessages);
    $rawAiContent = $result['choices'][0]['message']['content'] ?? "{TYPE:CHAT} Error";

    // Extract the TYPE/INTENT tags and remove them from the visible reply.
    $intent = 'General_Inquiry'; $msgType = 'CHAT'; $finalReply = $rawAiContent;

    if (preg_match('/\{INTENT:(.*?)\}/', $rawAiContent, $matches)) {
        $intent = trim($matches[1]); $finalReply = str_replace($matches[0], '', $finalReply);
    }
    if (preg_match('/\{TYPE:(.*?)\}/', $rawAiContent, $matches)) {
        $msgType = trim($matches[1]); $finalReply = str_replace($matches[0], '', $finalReply);
    }
    $finalReply = trim($finalReply);
    $finalReply = preg_replace('/\(🛠️.*?\)/', '', $finalReply);

    // UI hint: show resolution buttons only for solution replies.
    $showButtons = (strtoupper($msgType) === 'SOLUTION');

    // Fallback replies include a link to human support.
    if (strtoupper($msgType) === 'FALLBACK') {
        $finalReply .= "\n\n🔗 <a href='support_human_chat.html' style='color:#4F46E5; font-weight:bold; text-decoration:underline;'>Click for Human Support / 人工客服</a>";
        $showButtons = false;
    }

    // Persist the interaction in AIChatLog.
    $insertedLogId = null;
    $sqlLog = "INSERT INTO AIChatLog 
            (AILog_User_Query, AILog_Response, AILog_Intent_Recognized, AILog_Is_Resolved, AILog_Timestamp, User_ID) 
            VALUES (?, ?, ?, 0, NOW(), ?)";

    $stmtLog = $conn->prepare($sqlLog);
    if ($stmtLog) {
        $stmtLog->execute([$currentMsgContent, $rawAiContent, $intent, $currentUserId]);
        $insertedLogId = $conn->lastInsertId();
    }

    // Return the original API response shape, but replace the message content with the cleaned reply.
    $result['choices'][0]['message']['content'] = $finalReply;
    $result['db_log_id'] = $insertedLogId;
    $result['show_resolution_buttons'] = $showButtons;

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>